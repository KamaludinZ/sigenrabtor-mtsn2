<?php

use App\Enums\StatusKegiatan;
use App\Support\Dokumen\DokumenKegiatan;
use App\Support\Dokumen\RiwayatDokumen;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat dokumen (PRD §7 Riwayat Dokumen, F28; kontrak App\Contracts\RiwayatDokumenRepository): satu baris per berkas
 * yang pernah dicetak, diunduh, atau diarsipkan — hanya tambah, bernomor versi per `kunci_versi` (RiwayatDokumen::kunciVersi
 * saat dicatat: kegiatan × jenis × sumber dana × format; disimpan agar tetap sama walau kegiatannya kemudian dihapus).
 * Cuplikan kegiatan (nama, tahun, leading, status saat itu) & ringkas isi ikut tersimpan.
 * - id teks ≤ 64 (uuid; contoh tiruan berid tetap seperti `rd-2026-1`);
 * - `activity_id` & `document_id` (baris `documents` kegiatan × jenis × sumber) dikosongkan bila rujukannya dihapus;
 *   `tahun_anggaran` → tahun_anggaran, `kode_leading` → leadings (kosong = tanpa leading), `sumber_dana` → sumber_dana;
 * - CHECK `riwayat_dokumen_isian_check`: jenis DokumenKegiatan::JENIS (jenis DokumenKegiatan::PER_SUMBER wajib bersumber
 *   dana), format & asal = kunci RiwayatDokumen::FORMAT/ASAL (arsip hanya PDF), status StatusKegiatan, nama berkas tidak
 *   kosong & berakhiran .{format}, ukuran > 0, nomor dokumen tidak kosong bila ada, versi ≥ 1, nama kegiatan/pembuat tidak
 *   kosong, ringkas sah (`riwayat_ringkas_sah`: objek berkunci total_rab/bagian_lengkap/jumlah_bagian, nilai null atau
 *   bilangan bulat ≥ 0, bagian lengkap ≤ jumlah bagian);
 * - unik (kunci_versi, versi);
 * - trigger `riwayat_dokumen_hanya_tambah`: baris tidak bisa diubah/dihapus kecuali rujukan kegiatan/dokumen/akun
 *   dikosongkan (aksi FK, pg_trigger_depth() > 1, atau pengosongan langsung).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_dokumen', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('kode_leading', 10)->nullable();
            $table->string('nama_kegiatan', 255);
            $table->string('kunci_versi', 320);
            $table->string('jenis', 10);
            $table->string('sumber_dana', 20)->nullable();
            $table->string('format', 5);
            $table->string('asal', 10);
            $table->string('nama_berkas', 255);
            $table->unsignedBigInteger('ukuran')->nullable();
            $table->string('status', 20);
            $table->boolean('bertanda_draf')->default(false);
            $table->string('nomor_dokumen', 100)->nullable();
            $table->unsignedInteger('versi');
            $table->jsonb('ringkas')->default('{}');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dibuat_oleh', 255);
            $table->timestampTz('dibuat_pada', 6)->useCurrent();
            $table->unique(['kunci_versi', 'versi']);
            $table->index(['tahun_anggaran', 'dibuat_pada']);
            $table->index('activity_id');
            $table->index('kode_leading');
            $table->index('user_id');
            $table->foreign('sumber_dana')->references('kode')->on('sumber_dana')->cascadeOnUpdate()->restrictOnDelete();
        });

        $daftar = fn (array $nilai) => implode(', ', array_map(fn (string $n) => "'".str_replace("'", "''", $n)."'", $nilai));
        $jenis = $daftar(array_keys(DokumenKegiatan::JENIS));
        $perSumber = $daftar(DokumenKegiatan::PER_SUMBER);
        $format = $daftar(array_keys(RiwayatDokumen::FORMAT));
        $asal = $daftar(array_keys(RiwayatDokumen::ASAL));
        $status = $daftar(array_map(fn (StatusKegiatan $s) => $s->value, StatusKegiatan::cases()));

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION riwayat_ringkas_sah(ringkas jsonb) RETURNS boolean LANGUAGE sql IMMUTABLE AS \$\$
                SELECT jsonb_typeof(ringkas) = 'object'
                    AND NOT EXISTS (SELECT 1 FROM jsonb_object_keys(ringkas) k WHERE k NOT IN ('total_rab', 'bagian_lengkap', 'jumlah_bagian'))
                    AND NOT EXISTS (
                        SELECT 1 FROM jsonb_each(ringkas) e
                        WHERE jsonb_typeof(e.value) <> 'null'
                            AND (jsonb_typeof(e.value) <> 'number' OR (e.value)::numeric < 0 OR (e.value)::numeric <> trunc((e.value)::numeric)))
                    AND (jsonb_typeof(ringkas->'bagian_lengkap') IS DISTINCT FROM 'number' OR jsonb_typeof(ringkas->'jumlah_bagian') IS DISTINCT FROM 'number'
                        OR (ringkas->>'bagian_lengkap')::numeric <= (ringkas->>'jumlah_bagian')::numeric)
            \$\$;

            ALTER TABLE riwayat_dokumen
                ADD CONSTRAINT riwayat_dokumen_tahun_foreign FOREIGN KEY (tahun_anggaran) REFERENCES tahun_anggaran (tahun)
                    ON UPDATE CASCADE DEFERRABLE INITIALLY DEFERRED,
                ADD CONSTRAINT riwayat_dokumen_leading_foreign FOREIGN KEY (kode_leading) REFERENCES leadings (kode)
                    ON UPDATE CASCADE DEFERRABLE INITIALLY DEFERRED,
                ADD CONSTRAINT riwayat_dokumen_isian_check CHECK (
                    jenis IN ({$jenis})
                    AND (jenis NOT IN ({$perSumber}) OR sumber_dana IS NOT NULL)
                    AND format IN ({$format})
                    AND asal IN ({$asal})
                    AND (asal <> 'arsip' OR format = 'pdf')
                    AND status IN ({$status})
                    AND btrim(nama_berkas) <> '' AND lower(nama_berkas) LIKE '%.' || format
                    AND (ukuran IS NULL OR ukuran > 0)
                    AND (nomor_dokumen IS NULL OR btrim(nomor_dokumen) <> '')
                    AND versi >= 1
                    AND btrim(nama_kegiatan) <> '' AND btrim(dibuat_oleh) <> '' AND btrim(kunci_versi) <> ''
                    AND riwayat_ringkas_sah(ringkas));

            CREATE OR REPLACE FUNCTION riwayat_dokumen_hanya_tambah() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF pg_trigger_depth() > 1 THEN
                    RETURN COALESCE(NEW, OLD);
                END IF;
                IF TG_OP = 'DELETE'
                    OR (NEW.id, NEW.tahun_anggaran, NEW.kode_leading, NEW.nama_kegiatan, NEW.kunci_versi, NEW.jenis, NEW.sumber_dana, NEW.format, NEW.asal,
                        NEW.nama_berkas, NEW.ukuran, NEW.status, NEW.bertanda_draf, NEW.nomor_dokumen, NEW.versi, NEW.ringkas, NEW.dibuat_oleh, NEW.dibuat_pada)
                    IS DISTINCT FROM (OLD.id, OLD.tahun_anggaran, OLD.kode_leading, OLD.nama_kegiatan, OLD.kunci_versi, OLD.jenis, OLD.sumber_dana, OLD.format, OLD.asal,
                        OLD.nama_berkas, OLD.ukuran, OLD.status, OLD.bertanda_draf, OLD.nomor_dokumen, OLD.versi, OLD.ringkas, OLD.dibuat_oleh, OLD.dibuat_pada)
                    OR (NEW.activity_id IS DISTINCT FROM OLD.activity_id AND NEW.activity_id IS NOT NULL)
                    OR (NEW.document_id IS DISTINCT FROM OLD.document_id AND NEW.document_id IS NOT NULL)
                    OR (NEW.user_id IS DISTINCT FROM OLD.user_id AND NEW.user_id IS NOT NULL) THEN
                    RAISE EXCEPTION 'riwayat_dokumen hanya bisa ditambah' USING ERRCODE = 'restrict_violation';
                END IF;
                RETURN NEW;
            END
            \$\$;
            CREATE TRIGGER riwayat_dokumen_hanya_tambah BEFORE UPDATE OR DELETE ON riwayat_dokumen
                FOR EACH ROW EXECUTE FUNCTION riwayat_dokumen_hanya_tambah();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_dokumen');
        DB::statement('DROP FUNCTION IF EXISTS riwayat_dokumen_hanya_tambah()');
        DB::statement('DROP FUNCTION IF EXISTS riwayat_ringkas_sah(jsonb)');
    }
};
