<?php

use App\Support\Dokumen\SuntingDokumen;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak sunting teks langsung di pratinjau (PRD F5 "Edit Teks Langsung", N7 riwayat perubahan;
 * App\Support\Dokumen\RiwayatSunting): satu baris per penyimpanan teks yang benar-benar berubah — kegiatan, bidang
 * SuntingDokumen::BIDANG beserta nomor bagian KAK/TOR & asalnya (isian kegiatan | teks khusus), teks lama & baru (null =
 * kosong / teks baku; keduanya tidak sama), penyunting (akun dihapus → kosong, nama saat itu tetap), waktu bermikrodetik.
 * - CHECK `riwayat_sunting_isian_check`: pasangan (bidang, nomor_bagian, asal) = SuntingDokumen::BIDANG, teks tidak
 *   kosong tanpa spasi tepi, panjang ≤ SuntingDokumen::maksTeks per bidang, nama penyunting tidak kosong;
 * - trigger `riwayat_sunting_hanya_tambah`: baris tidak bisa diubah/dihapus kecuali rujukan akun dikosongkan atau ikut
 *   terhapus bersama kegiatannya (aksi FK, pg_trigger_depth() > 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_sunting_dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->string('bidang', 30);
            $table->unsignedSmallInteger('nomor_bagian');
            $table->string('asal', 12);
            $table->text('teks_lama')->nullable();
            $table->text('teks_baru')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pengguna', 255);
            $table->timestampTz('dibuat_pada', 6)->useCurrent();
            $table->index(['activity_id', 'dibuat_pada']);
            $table->index(['activity_id', 'bidang']);
        });

        $pasangan = implode(', ', array_map(
            fn (string $kunci, array $b) => "('{$kunci}', {$b[0]}, '{$b[1]}')",
            array_keys(SuntingDokumen::BIDANG),
            SuntingDokumen::BIDANG,
        ));
        $maks = implode(' ', array_map(fn (string $kunci) => "WHEN '{$kunci}' THEN ".SuntingDokumen::maksTeks($kunci), array_keys(SuntingDokumen::BIDANG)));
        $teksSah = fn (string $kolom) => "({$kolom} IS NULL OR (btrim({$kolom}) <> '' AND {$kolom} = btrim({$kolom}) AND char_length({$kolom}) <= CASE bidang {$maks} END))";

        DB::unprepared(<<<SQL
            ALTER TABLE riwayat_sunting_dokumen ADD CONSTRAINT riwayat_sunting_isian_check CHECK (
                (bidang, nomor_bagian, asal) IN ({$pasangan})
                AND teks_lama IS DISTINCT FROM teks_baru
                AND {$teksSah('teks_lama')} AND {$teksSah('teks_baru')}
                AND btrim(pengguna) <> '');

            CREATE OR REPLACE FUNCTION riwayat_sunting_hanya_tambah() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF pg_trigger_depth() > 1 THEN
                    RETURN COALESCE(NEW, OLD);
                END IF;
                IF TG_OP = 'DELETE' OR (NEW.activity_id, NEW.bidang, NEW.nomor_bagian, NEW.asal, NEW.teks_lama, NEW.teks_baru, NEW.pengguna, NEW.dibuat_pada)
                    IS DISTINCT FROM (OLD.activity_id, OLD.bidang, OLD.nomor_bagian, OLD.asal, OLD.teks_lama, OLD.teks_baru, OLD.pengguna, OLD.dibuat_pada)
                    OR (NEW.user_id IS DISTINCT FROM OLD.user_id AND NEW.user_id IS NOT NULL) THEN
                    RAISE EXCEPTION 'riwayat_sunting_dokumen hanya bisa ditambah' USING ERRCODE = 'restrict_violation';
                END IF;
                RETURN NEW;
            END
            \$\$;
            CREATE TRIGGER riwayat_sunting_hanya_tambah BEFORE UPDATE OR DELETE ON riwayat_sunting_dokumen
                FOR EACH ROW EXECUTE FUNCTION riwayat_sunting_hanya_tambah();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_sunting_dokumen');
        DB::statement('DROP FUNCTION IF EXISTS riwayat_sunting_hanya_tambah()');
    }
};
