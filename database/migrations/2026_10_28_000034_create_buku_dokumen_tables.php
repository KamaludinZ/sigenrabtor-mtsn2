<?php

use App\Support\Dokumen\ProsesPdfBuku;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buku dokumen per leading × sumber dana di database (PRD §18, F19; §6 `buku_dokumen`):
 * - view `buku_kegiatan`: isi buku — satu baris per kegiatan × sumber dana yang dipilih (anggaran_kegiatan.dipilih) per
 *   tahun anggaran & leading, dengan nama unit & status aktif leading, nama & urutan sumber dana, status kegiatan, total
 *   RAB (salinan anggaran_kegiatan), dan `urutan` di buku = kode RKTM bertingkat per bagian (6.17.2 sebelum 6.17.12;
 *   kode bukan angka bertitik/kosong di akhir) lalu nama kegiatan — sama dengan DaftarIsi::baris urutan `kode`;
 * - tabel `buku_dokumen`: riwayat penyusunan buku (bentuk BukuDokumenRepository): tahun, leading, sumber dana (FK ke
 *   tabel rujukannya; leading/sumber yang dipakai tidak bisa dihapus), jumlah kegiatan = panjang cuplikan `kegiatan`
 *   (jsonb daftar {id, nama, halaman, hasil}), jumlah halaman, ada DRAF, status pembuatan PDF `pdf` (jsonb: status
 *   antre/proses/selesai/gagal, tahap ProsesPdfBuku::TAHAP, persen 0–100), berkas PDF hanya bersama status selesai,
 *   pembuat (FK users + nama saat itu), waktu dibuat bermikrodetik.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW buku_kegiatan AS
            SELECT
                k.tahun_anggaran,
                k.kode_leading,
                l.nama_unit,
                l.is_active AS leading_aktif,
                a.sumber_dana,
                sd.nama AS nama_sumber,
                sd.urutan AS urutan_sumber,
                k.id AS activity_id,
                COALESCE(NULLIF(btrim(k.nama_kegiatan), ''), 'Kegiatan tanpa nama') AS nama_kegiatan,
                k.kode_rktm,
                k.status,
                a.total_rab,
                row_number() OVER (
                    PARTITION BY k.tahun_anggaran, k.kode_leading, a.sumber_dana
                    ORDER BY CASE WHEN k.kode_rktm ~ '^[0-9]+(\.[0-9]+)*$' THEN string_to_array(k.kode_rktm, '.')::int[] END NULLS LAST,
                        lower(k.nama_kegiatan), k.id
                ) AS urutan
            FROM activities k
            JOIN anggaran_kegiatan a ON a.activity_id = k.id AND a.dipilih
            JOIN leadings l ON l.kode = k.kode_leading
            JOIN sumber_dana sd ON sd.kode = a.sumber_dana
            SQL);

        Schema::create('buku_dokumen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('kode_leading', 10);
            $table->string('sumber_dana');
            $table->unsignedInteger('jumlah_kegiatan');
            $table->unsignedInteger('halaman')->default(0);
            $table->boolean('ada_draf')->default(false);
            $table->jsonb('kegiatan')->default('[]');
            $table->jsonb('pdf')->nullable();
            $table->string('file_pdf_path')->nullable()->unique();
            $table->unsignedBigInteger('ukuran')->nullable();
            $table->foreignId('dibuat_oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('dibuat_oleh', 150);
            $table->timestampTz('dibuat_pada', 6)->useCurrent();

            $table->index(['tahun_anggaran', 'dibuat_pada']);
            $table->index(['tahun_anggaran', 'kode_leading', 'sumber_dana']);
            $table->foreign('tahun_anggaran')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate();
            $table->foreign('kode_leading')->references('kode')->on('leadings')->cascadeOnUpdate();
            $table->foreign('sumber_dana')->references('kode')->on('sumber_dana')->cascadeOnUpdate();
        });

        $tahap = implode(', ', array_map(fn (string $t) => "'{$t}'", array_keys(ProsesPdfBuku::TAHAP)));
        DB::statement("ALTER TABLE buku_dokumen ADD CONSTRAINT buku_dokumen_isian_check CHECK (
            jsonb_typeof(kegiatan) = 'array' AND jsonb_array_length(kegiatan) = jumlah_kegiatan
            AND btrim(dibuat_oleh) <> ''
            AND (pdf IS NULL OR (jsonb_typeof(pdf) = 'object'
                AND pdf ->> 'status' IN ('antre', 'proses', 'selesai', 'gagal')
                AND (pdf ->> 'tahap' IS NULL OR pdf ->> 'tahap' IN ({$tahap}))
                AND (pdf ->> 'persen' IS NULL OR (pdf ->> 'persen')::numeric BETWEEN 0 AND 100)))
            AND (file_pdf_path IS NULL OR (lower(file_pdf_path) LIKE '%.pdf' AND pdf ->> 'status' = 'selesai'))
            AND (ukuran IS NULL OR (ukuran > 0 AND file_pdf_path IS NOT NULL)))");
    }

    public function down(): void
    {
        Schema::dropIfExists('buku_dokumen');
        DB::statement('DROP VIEW IF EXISTS buku_kegiatan');
    }
};
