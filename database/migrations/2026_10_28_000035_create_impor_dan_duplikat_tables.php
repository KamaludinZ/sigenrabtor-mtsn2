<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kegiatan & draf hasil impor dan duplikat (PRD §19 F22; ImporBertahap: Unggah → Periksa → Proses → Laporan):
 * - `impor_kegiatan`: satu baris per impor (jenis excel = Impor dari Excel, perkin = cascade Perkin) — tahun anggaran,
 *   berkas & sheet, pengimpor (FK users + nama saat itu), status berjalan/selesai/dibatalkan (waktu selesai hanya bila
 *   tidak berjalan), dan rekap jumlah baris (siap + dilewati + gagal = baris; dibuat ≤ siap);
 * - `draf_impor_kegiatan`: baris pratinjau impor (nomor baris berkas unik per impor) berstatus siap/dilewati/gagal dengan
 *   data draf kegiatan (jsonb objek, bentuk KegiatanRepository), pesan & peringatan (jsonb daftar); begitu disimpan
 *   menunjuk kegiatan yang dibuat (`kegiatan_id`, hanya untuk baris siap, bersama `diproses_pada`); ikut terhapus
 *   bersama impornya;
 * - `activities.impor_id`: impor yang membuat kegiatan (dihapus → kosong; hanya untuk asal impor_excel/impor_perkin);
 * - `duplikat_kegiatan`: jejak duplikat dari tahun lalu — kegiatan sumber (dihapus → kosong, tahunnya tetap tercatat),
 *   draf hasil (satu jejak per hasil, ikut terhapus), tahun tujuan ≠ tahun sumber, catatan perubahan (jsonb daftar
 *   {jenis, teks} DuplikatKegiatan::rencana), pembuat & waktu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impor_kegiatan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->enum('jenis', ['excel', 'perkin']);
            $table->string('nama_berkas');
            $table->string('lembar', 100)->nullable();
            $table->enum('status', ['berjalan', 'selesai', 'dibatalkan'])->default('berjalan');
            $table->unsignedInteger('jumlah_baris')->default(0);
            $table->unsignedInteger('jumlah_siap')->default(0);
            $table->unsignedInteger('jumlah_dilewati')->default(0);
            $table->unsignedInteger('jumlah_gagal')->default(0);
            $table->unsignedInteger('jumlah_dibuat')->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pengguna', 150);
            $table->timestampTz('dibuat_pada', 6)->useCurrent();
            $table->timestampTz('selesai_pada', 6)->nullable();
            $table->index(['tahun_anggaran', 'dibuat_pada']);
            $table->foreign('tahun_anggaran')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate();
        });
        DB::statement("ALTER TABLE impor_kegiatan ADD CONSTRAINT impor_kegiatan_isian_check CHECK (
            btrim(nama_berkas) <> '' AND btrim(pengguna) <> '' AND (lembar IS NULL OR btrim(lembar) <> '')
            AND jumlah_siap + jumlah_dilewati + jumlah_gagal = jumlah_baris AND jumlah_dibuat <= jumlah_siap
            AND (status = 'berjalan') = (selesai_pada IS NULL))");

        Schema::create('draf_impor_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('impor_id')->constrained('impor_kegiatan')->cascadeOnDelete();
            $table->unsignedInteger('nomor_baris');
            $table->enum('status', ['siap', 'dilewati', 'gagal']);
            $table->jsonb('data')->default('{}');
            $table->jsonb('pesan')->default('[]');
            $table->jsonb('peringatan')->default('[]');
            $table->foreignId('kegiatan_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->timestampTz('diproses_pada', 6)->nullable();
            $table->unique(['impor_id', 'nomor_baris']);
        });
        DB::statement("ALTER TABLE draf_impor_kegiatan ADD CONSTRAINT draf_impor_kegiatan_isian_check CHECK (
            nomor_baris >= 1 AND jsonb_typeof(data) = 'object' AND jsonb_typeof(pesan) = 'array' AND jsonb_typeof(peringatan) = 'array'
            AND (kegiatan_id IS NULL OR (status = 'siap' AND diproses_pada IS NOT NULL))
            AND (status <> 'gagal' OR jsonb_array_length(pesan) > 0))");

        Schema::table('activities', function (Blueprint $table) {
            $table->foreignUuid('impor_id')->nullable()->after('duplikat_dari_id')->constrained('impor_kegiatan')->nullOnDelete();
        });
        DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_impor_check CHECK (impor_id IS NULL OR asal IN ('impor_excel', 'impor_perkin'))");

        Schema::create('duplikat_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sumber_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->unsignedSmallInteger('sumber_tahun');
            $table->foreignId('hasil_id')->unique()->constrained('activities')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->jsonb('catatan')->default('[]');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pengguna', 150);
            $table->timestampTz('dibuat_pada', 6)->useCurrent();
            $table->index(['tahun_anggaran', 'dibuat_pada']);
            $table->foreign('tahun_anggaran')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate();
        });
        DB::statement("ALTER TABLE duplikat_kegiatan ADD CONSTRAINT duplikat_kegiatan_isian_check CHECK (
            sumber_tahun <> tahun_anggaran AND (sumber_id IS NULL OR sumber_id <> hasil_id)
            AND jsonb_typeof(catatan) = 'array' AND btrim(pengguna) <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('duplikat_kegiatan');
        DB::statement('ALTER TABLE activities DROP CONSTRAINT activities_impor_check');
        Schema::table('activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('impor_id');
        });
        Schema::dropIfExists('draf_impor_kegiatan');
        Schema::dropIfExists('impor_kegiatan');
    }
};
