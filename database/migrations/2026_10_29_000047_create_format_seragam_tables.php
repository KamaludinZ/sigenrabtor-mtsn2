<?php

use App\Support\Dokumen\DokumenKegiatan;
use App\Support\Dokumen\FormatSeragam;
use App\Support\Dokumen\PenomoranDokumen;
use App\Support\Dokumen\PilihanTemplate;
use App\Support\Kegiatan\KonteksAi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skema Format Seragam (PRD §9; bentuk PilihanTemplateRepository & PenomoranDokumenRepository):
 * - pilihan_template: satu baris per tahun × leading (kosong = pilihan per jenis kegiatan) × jenis kegiatan
 *   (KonteksAi::jenisKegiatan) → master KAK/TOR & cover tahun yang sama (FK komposit (id, tahun) ke document_templates /
 *   cover_templates; template dihapus → kolomnya kosong = kembali ke bawaan). Master harus jenis keluarga KAK/TOR
 *   (PilihanTemplate::JENIS_KAK; trigger `pilihan_template_kak_sah`), baris khusus leading maks. PilihanTemplate::MAKS_LEADING
 *   per tahun (constraint trigger tertunda `pilihan_template_maks_leading`);
 * - aturan_penomoran: tahun × jenis dokumen (DokumenKegiatan::JENIS) → pola (`pola_nomor_sah()` = FormatSeragam::galatPola
 *   untuk pola yang sudah dirapikan: tanpa spasi, ≤ MAKS_POLA, memuat {urut}, hanya FormatSeragam::TOKEN), digit
 *   (PenomoranDokumen::DIGIT), aktif; nomor terakhir tetap di number_sequences (kini CHECK tidak negatif);
 * - pengaturan_format: waktu terakhir pilihan template & aturan penomoran tahun itu disimpan (versi pengenal perubahan).
 */
return new class extends Migration
{
    public function up(): void
    {
        $jenisKegiatan = $this->daftar(array_keys(KonteksAi::jenisKegiatan()));
        $jenisKak = $this->daftar(PilihanTemplate::JENIS_KAK);
        $jenisDokumen = $this->daftar(array_keys(DokumenKegiatan::JENIS));
        $token = $this->daftar(array_keys(FormatSeragam::TOKEN));
        $digit = implode(', ', PenomoranDokumen::DIGIT);
        $maksPola = FormatSeragam::MAKS_POLA;
        $maksLeading = PilihanTemplate::MAKS_LEADING;

        Schema::create('pilihan_template', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('kode_leading', 10)->nullable();
            $table->string('jenis_kegiatan', 20);
            $table->uuid('kak_template_id')->nullable();
            $table->uuid('cover_template_id')->nullable();
            $table->timestampsTz(6);
            $table->foreign('tahun_anggaran')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate();
            $table->foreign('kode_leading')->references('kode')->on('leadings')->cascadeOnUpdate();
        });
        DB::unprepared(<<<SQL
            ALTER TABLE pilihan_template ADD CONSTRAINT pilihan_template_jenis_check CHECK (jenis_kegiatan IN ({$jenisKegiatan}));
            ALTER TABLE pilihan_template ADD CONSTRAINT pilihan_template_unik UNIQUE NULLS NOT DISTINCT (tahun_anggaran, kode_leading, jenis_kegiatan);
            ALTER TABLE pilihan_template ADD CONSTRAINT pilihan_template_kak_fk FOREIGN KEY (kak_template_id, tahun_anggaran)
                REFERENCES document_templates (id, tahun_anggaran) ON UPDATE CASCADE ON DELETE SET NULL (kak_template_id);
            ALTER TABLE pilihan_template ADD CONSTRAINT pilihan_template_cover_fk FOREIGN KEY (cover_template_id, tahun_anggaran)
                REFERENCES cover_templates (id, tahun_anggaran) ON UPDATE CASCADE ON DELETE SET NULL (cover_template_id);

            CREATE OR REPLACE FUNCTION pilihan_template_kak_sah() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF NEW.kak_template_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM document_templates WHERE id = NEW.kak_template_id AND jenis IN ({$jenisKak})
                ) THEN
                    RAISE EXCEPTION 'Pilihan template hanya boleh memakai master KAK/TOR.' USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END
            \$\$;
            CREATE TRIGGER pilihan_template_kak_sah BEFORE INSERT OR UPDATE OF kak_template_id ON pilihan_template
                FOR EACH ROW EXECUTE FUNCTION pilihan_template_kak_sah();

            CREATE OR REPLACE FUNCTION pilihan_template_maks_leading() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF (SELECT count(*) FROM pilihan_template WHERE tahun_anggaran = NEW.tahun_anggaran AND kode_leading IS NOT NULL) > {$maksLeading} THEN
                    RAISE EXCEPTION 'Paling banyak {$maksLeading} template khusus leading per tahun anggaran.' USING ERRCODE = 'check_violation';
                END IF;
                RETURN NULL;
            END
            \$\$;
            CREATE CONSTRAINT TRIGGER pilihan_template_maks_leading AFTER INSERT OR UPDATE OF kode_leading, tahun_anggaran ON pilihan_template
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION pilihan_template_maks_leading();

            CREATE OR REPLACE FUNCTION pola_nomor_sah(p_pola text) RETURNS boolean LANGUAGE sql IMMUTABLE AS \$\$
                SELECT COALESCE(
                    p_pola <> '' AND p_pola !~ '\\s' AND char_length(p_pola) <= {$maksPola} AND position('{urut}' in p_pola) > 0
                    AND NOT EXISTS (SELECT 1 FROM regexp_matches(p_pola, '\\{[a-z_]+\\}', 'g') AS m(penanda) WHERE m.penanda[1] NOT IN ({$token})),
                    false)
            \$\$;
            SQL);

        Schema::create('aturan_penomoran', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('jenis', 10);
            $table->string('pola', FormatSeragam::MAKS_POLA);
            $table->unsignedSmallInteger('digit');
            $table->boolean('aktif')->default(true);
            $table->timestampsTz(6);
            $table->primary(['tahun_anggaran', 'jenis']);
            $table->foreign('tahun_anggaran')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate();
        });
        DB::unprepared(<<<SQL
            ALTER TABLE aturan_penomoran ADD CONSTRAINT aturan_penomoran_isian_check
                CHECK (jenis IN ({$jenisDokumen}) AND digit IN ({$digit}) AND pola_nomor_sah(pola));
            ALTER TABLE number_sequences ADD CONSTRAINT number_sequences_nomor_check CHECK (nomor_terakhir >= 0);
            SQL);

        Schema::create('pengaturan_format', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun_anggaran')->primary();
            $table->timestampTz('pilihan_diperbarui_pada', 6)->nullable();
            $table->timestampTz('penomoran_diperbarui_pada', 6)->nullable();
            $table->timestampsTz(6);
            $table->foreign('tahun_anggaran')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_format');
        DB::statement('ALTER TABLE number_sequences DROP CONSTRAINT IF EXISTS number_sequences_nomor_check');
        Schema::dropIfExists('aturan_penomoran');
        Schema::dropIfExists('pilihan_template');
        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS pola_nomor_sah(text);
            DROP FUNCTION IF EXISTS pilihan_template_maks_leading();
            DROP FUNCTION IF EXISTS pilihan_template_kak_sah();
            SQL);
    }

    /** @param  list<string>  $nilai */
    private function daftar(array $nilai): string
    {
        return implode(', ', array_map(fn (string $n) => DB::getPdo()->quote($n), $nilai));
    }
};
