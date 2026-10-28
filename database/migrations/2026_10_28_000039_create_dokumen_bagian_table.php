<?php

use App\Support\Dokumen\HasilKakTor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil susunan KAK/TOR per dokumen (PRD §7B.2; baris dari App\Support\Dokumen\HasilKakTor):
 * - documents.sidik_isi (md5 seluruh hasil, HasilKakTor::sidik) & disusun_pada — berpasangan: dokumen yang belum pernah
 *   disusun tidak punya keduanya;
 * - dokumen_bagian: satu baris per bagian 1–HasilKakTor::JUMLAH_BAGIAN (unik per dokumen) — judul, blok isi jsonb (larik
 *   blok berjenis HasilKakTor::JENIS_BLOK dengan kunci isinya, fungsi `isi_bagian_kak_sah`), status lengkap/kurang/periksa
 *   yang sejalan dengan daftar `kurang`/`periksa`, sidik md5; hanya untuk dokumen yang memuat KAK/TOR (FK komposit
 *   (document_id, jenis_dokumen) → documents (id, jenis), jenis di HasilKakTor::JENIS); ikut terhapus bersama dokumen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $jenisBlok = [];
        foreach (HasilKakTor::JENIS_BLOK as $jenis => $kunci) {
            $jenisBlok[] = $kunci === null
                ? "b->>'jenis' = '{$jenis}'"
                : "(b->>'jenis' = '{$jenis}' AND ".(in_array($kunci, ['teks', 'pesan'], true)
                    ? "jsonb_typeof(b->'{$kunci}') = 'string' AND btrim(b->>'{$kunci}') <> ''"
                    : "jsonb_typeof(b->'{$kunci}') = 'array'").')';
        }
        $jenisBlok = implode("\n                    OR ", $jenisBlok);

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION isi_bagian_kak_sah(isi jsonb) RETURNS boolean LANGUAGE sql IMMUTABLE AS \$\$
                SELECT jsonb_typeof(isi) = 'array' AND jsonb_array_length(isi) > 0
                AND NOT EXISTS (SELECT 1 FROM jsonb_array_elements(isi) b WHERE jsonb_typeof(b) <> 'object' OR NOT (
                    {$jenisBlok}))
            \$\$;
            CREATE OR REPLACE FUNCTION daftar_teks_sah(d jsonb) RETURNS boolean LANGUAGE sql IMMUTABLE AS \$\$
                SELECT jsonb_typeof(d) = 'array'
                AND NOT EXISTS (SELECT 1 FROM jsonb_array_elements(d) t WHERE jsonb_typeof(t) <> 'string' OR btrim(t #>> '{}') = '')
            \$\$;
            ALTER TABLE documents ADD COLUMN sidik_isi char(32) NULL, ADD COLUMN disusun_pada timestamptz(6) NULL,
                ADD CONSTRAINT documents_sidik_isi_check CHECK ((sidik_isi IS NULL) = (disusun_pada IS NULL) AND (sidik_isi IS NULL OR sidik_isi ~ '^[0-9a-f]{32}$')),
                ADD CONSTRAINT documents_id_jenis_unique UNIQUE (id, jenis);
            SQL);

        Schema::create('dokumen_bagian', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->string('jenis_dokumen', 10);
            $table->unsignedSmallInteger('nomor');
            $table->string('judul', HasilKakTor::MAKS_JUDUL);
            $table->jsonb('isi');
            $table->string('status', 10);
            $table->jsonb('kurang')->default('[]');
            $table->jsonb('periksa')->default('[]');
            $table->char('sidik', 32);
            $table->timestampsTz(6);

            $table->unique(['document_id', 'nomor']);
            $table->foreign(['document_id', 'jenis_dokumen'], 'dokumen_bagian_dokumen_foreign')
                ->references(['id', 'jenis'])->on('documents')->cascadeOnUpdate()->cascadeOnDelete();
        });

        $jenis = implode(', ', array_map(fn (string $j) => "'{$j}'", HasilKakTor::JENIS));
        $status = implode(', ', array_map(fn (string $s) => "'{$s}'", HasilKakTor::STATUS));
        $jumlah = HasilKakTor::JUMLAH_BAGIAN;
        DB::unprepared(<<<SQL
            ALTER TABLE dokumen_bagian ADD CONSTRAINT dokumen_bagian_isian_check CHECK (
                jenis_dokumen IN ({$jenis})
                AND nomor BETWEEN 1 AND {$jumlah}
                AND btrim(judul) <> ''
                AND isi_bagian_kak_sah(isi)
                AND status IN ({$status})
                AND daftar_teks_sah(kurang) AND daftar_teks_sah(periksa)
                AND (status <> 'lengkap' OR (kurang = '[]'::jsonb AND periksa = '[]'::jsonb))
                AND (status <> 'kurang' OR kurang <> '[]'::jsonb)
                AND (status <> 'periksa' OR (kurang = '[]'::jsonb AND periksa <> '[]'::jsonb))
                AND sidik ~ '^[0-9a-f]{32}$');
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_bagian');
        DB::unprepared(<<<'SQL'
            ALTER TABLE documents DROP CONSTRAINT IF EXISTS documents_id_jenis_unique, DROP CONSTRAINT IF EXISTS documents_sidik_isi_check,
                DROP COLUMN IF EXISTS sidik_isi, DROP COLUMN IF EXISTS disusun_pada;
            DROP FUNCTION IF EXISTS isi_bagian_kak_sah(jsonb);
            DROP FUNCTION IF EXISTS daftar_teks_sah(jsonb);
            SQL);
    }
};
