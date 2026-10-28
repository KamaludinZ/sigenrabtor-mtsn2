<?php

use App\Enums\StatusKegiatan;
use App\Support\Dokumen\DokumenKegiatan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skema dokumen & konfigurasi tahun aktif (PRD §6 `documents`/`number_sequences`, §17 "Satu Tahun untuk Semua"):
 * - tahun_anggaran: tepat satu tahun aktif selama tabel berisi (trigger tertunda `tahun_anggaran_tepat_satu_aktif`,
 *   melengkapi indeks parsial "paling banyak satu"; diperiksa saat commit sehingga ganti tahun aktif dua langkah tetap
 *   sah; tahun terkunci boleh diaktifkan untuk dilihat — KelolaTahunAnggaran hanya melarang mengunci tahun aktif), dan
 *   fungsi `tahun_aktif()` = tahun yang sedang aktif;
 * - documents: satu baris per kegiatan × jenis (DokumenKegiatan::JENIS) × sumber dana (NULLS NOT DISTINCT). Tahun dokumen
 *   selalu tahun kegiatannya (FK komposit ke activities (id, tahun_anggaran)), template & cover yang dipakai harus dari
 *   tahun yang sama (FK komposit; dihapus → kosong), sumber dana dijaga sepasang dengan baris anggaran_kegiatan kegiatan
 *   itu (FK komposit), RAB & set lengkap wajib bersumber dana; konten jsonb objek, status = StatusKegiatan, nomor
 *   dokumen unik per tahun, berkas .docx/.pdf hasil cetak hanya bersama waktu cetak; ikut terhapus bersama kegiatan;
 * - number_sequences: penghitung nomor per jenis × tahun, dinaikkan atomik lewat `nomor_dokumen_berikutnya(jenis, tahun)`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION tahun_anggaran_tepat_satu_aktif() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF EXISTS (SELECT 1 FROM tahun_anggaran) AND NOT EXISTS (SELECT 1 FROM tahun_anggaran WHERE is_active) THEN
                    RAISE EXCEPTION 'Harus ada satu tahun anggaran aktif.' USING ERRCODE = 'check_violation';
                END IF;
                RETURN NULL;
            END
            $$;
            CREATE CONSTRAINT TRIGGER tahun_anggaran_tepat_satu_aktif AFTER INSERT OR UPDATE OF is_active OR DELETE ON tahun_anggaran
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION tahun_anggaran_tepat_satu_aktif();
            CREATE OR REPLACE FUNCTION tahun_aktif() RETURNS smallint LANGUAGE sql STABLE AS $$
                SELECT tahun FROM tahun_anggaran WHERE is_active
            $$;
            SQL);

        // Pasangan (id, tahun) untuk FK komposit "tahun dokumen = tahun kegiatan/template".
        Schema::table('activities', fn (Blueprint $table) => $table->unique(['id', 'tahun_anggaran'], 'activities_id_tahun_unique'));
        Schema::table('document_templates', fn (Blueprint $table) => $table->unique(['id', 'tahun_anggaran'], 'document_templates_id_tahun_unique'));
        Schema::table('cover_templates', fn (Blueprint $table) => $table->unique(['id', 'tahun_anggaran'], 'cover_templates_id_tahun_unique'));

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('activity_id');
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->unsignedBigInteger('anggaran_kegiatan_id')->nullable();
            $table->string('sumber_dana')->nullable();
            $table->string('jenis', 10);
            $table->uuid('template_id')->nullable();
            $table->uuid('cover_template_id')->nullable();
            $table->string('nomor_dokumen', DokumenKegiatan::MAKS_NOMOR)->nullable();
            $table->jsonb('konten')->default('{}');
            $table->string('status', 20)->default(StatusKegiatan::Draf->value);
            $table->boolean('bertanda_draf')->default(true);
            $table->string('file_docx_path')->nullable()->unique();
            $table->string('file_pdf_path')->nullable()->unique();
            $table->foreignId('dicetak_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('dicetak_pada', 6)->nullable();
            $table->timestampsTz(6);

            $table->unique(['tahun_anggaran', 'nomor_dokumen']);
            $table->index(['tahun_anggaran', 'jenis', 'status']);
            $table->foreign(['activity_id', 'tahun_anggaran'], 'documents_kegiatan_foreign')
                ->references(['id', 'tahun_anggaran'])->on('activities')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign(['anggaran_kegiatan_id', 'activity_id', 'sumber_dana'], 'documents_anggaran_foreign')
                ->references(['id', 'activity_id', 'sumber_dana'])->on('anggaran_kegiatan')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('sumber_dana')->references('kode')->on('sumber_dana')->cascadeOnUpdate()->restrictOnDelete();
        });

        $jenis = implode(', ', array_map(fn (string $j) => "'{$j}'", array_keys(DokumenKegiatan::JENIS)));
        $perSumber = implode(', ', array_map(fn (string $j) => "'{$j}'", DokumenKegiatan::PER_SUMBER));
        $status = implode(', ', array_map(fn (StatusKegiatan $s) => "'{$s->value}'", StatusKegiatan::cases()));
        DB::unprepared(<<<SQL
            ALTER TABLE documents
                ADD CONSTRAINT documents_template_foreign FOREIGN KEY (template_id, tahun_anggaran)
                    REFERENCES document_templates (id, tahun_anggaran) ON UPDATE CASCADE ON DELETE SET NULL (template_id),
                ADD CONSTRAINT documents_cover_foreign FOREIGN KEY (cover_template_id, tahun_anggaran)
                    REFERENCES cover_templates (id, tahun_anggaran) ON UPDATE CASCADE ON DELETE SET NULL (cover_template_id),
                ADD CONSTRAINT documents_isian_check CHECK (
                    jenis IN ({$jenis}) AND status IN ({$status})
                    AND (anggaran_kegiatan_id IS NULL) = (sumber_dana IS NULL)
                    AND (jenis NOT IN ({$perSumber}) OR sumber_dana IS NOT NULL)
                    AND jsonb_typeof(konten) = 'object'
                    AND (nomor_dokumen IS NULL OR btrim(nomor_dokumen) <> '')
                    AND (file_docx_path IS NULL OR lower(file_docx_path) LIKE '%.docx')
                    AND (file_pdf_path IS NULL OR lower(file_pdf_path) LIKE '%.pdf')
                    AND ((file_docx_path IS NULL AND file_pdf_path IS NULL) OR dicetak_pada IS NOT NULL));
            CREATE UNIQUE INDEX documents_satu_per_jenis ON documents (activity_id, jenis, sumber_dana) NULLS NOT DISTINCT;
            SQL);

        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 10);
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('nomor_terakhir')->default(0);
            $table->timestampsTz(6);
            $table->unique(['jenis', 'tahun']);
            $table->foreign('tahun')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate();
        });
        DB::unprepared(<<<SQL
            ALTER TABLE number_sequences ADD CONSTRAINT number_sequences_jenis_check CHECK (jenis IN ({$jenis}));
            CREATE OR REPLACE FUNCTION nomor_dokumen_berikutnya(p_jenis text, p_tahun integer) RETURNS integer LANGUAGE sql AS \$\$
                INSERT INTO number_sequences (jenis, tahun, nomor_terakhir, created_at, updated_at)
                VALUES (p_jenis, p_tahun, 1, now(), now())
                ON CONFLICT (jenis, tahun) DO UPDATE SET nomor_terakhir = number_sequences.nomor_terakhir + 1, updated_at = now()
                RETURNING nomor_terakhir
            \$\$;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS nomor_dokumen_berikutnya(text, integer)');
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('documents');
        Schema::table('cover_templates', fn (Blueprint $table) => $table->dropUnique('cover_templates_id_tahun_unique'));
        Schema::table('document_templates', fn (Blueprint $table) => $table->dropUnique('document_templates_id_tahun_unique'));
        Schema::table('activities', fn (Blueprint $table) => $table->dropUnique('activities_id_tahun_unique'));
        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS tahun_aktif();
            DROP TRIGGER IF EXISTS tahun_anggaran_tepat_satu_aktif ON tahun_anggaran;
            DROP FUNCTION IF EXISTS tahun_anggaran_tepat_satu_aktif();
            SQL);
    }
};
