<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kegiatan (PRD §6 activities) — kolom mengikuti isian Data Kegiatan (KegiatanForm::dataUntukDisimpan()) dan kode &
 * klasifikasi. Kolom rujukan ke tabel yang belum ada (tahun_anggaran, leadings, kode_rktm/sk/iksk/edm, budaya_mutu,
 * data_pagu) sudah disiapkan & diindeks; foreign key-nya ditambahkan oleh migrasi yang membuat tabel-tabel itu.
 * Waktu bermikrodetik (timestamptz(6)) karena updated_at dipakai sebagai versi untuk mengenali perubahan di tab lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            // Tahun pemilik dicatat saat dibuat dan tidak berubah (PRD aturan 3); pembuat = operator di rekap Monitoring.
            $table->foreignId('tahun_anggaran_id');
            $table->foreignId('leading_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Identitas & isi KAK/TOR.
            $table->string('nama_kegiatan');
            $table->string('nama_kegiatan_cover')->nullable();
            $table->boolean('cover_manual')->default(false);
            $table->text('latar_belakang')->nullable();
            $table->text('tujuan')->nullable();
            $table->text('sasaran')->nullable();
            $table->text('uraian_pelaksanaan')->nullable();
            $table->text('konteks')->nullable();

            // Pelaksanaan (DataPelaksanaan): tanggal selesai diturunkan dari tanggal mulai + lama hari.
            $table->date('tanggal_dokumen')->nullable();
            $table->date('tanggal_laporan')->nullable();
            $table->boolean('laporan_otomatis')->default(true);
            $table->date('tanggal_mulai')->nullable();
            $table->unsignedSmallInteger('lama_hari')->nullable();
            $table->boolean('hari_kerja')->default(false);
            $table->jsonb('bulan_pelaksanaan')->default('[]');
            $table->string('tempat')->nullable();
            $table->unsignedInteger('peserta_jumlah')->nullable();
            $table->string('peserta_jenis', 150)->nullable();

            // Kode & klasifikasi dari master tahun kegiatan; kode yang dikosongkan dicatat di kode_hilang (KodeHilang).
            $table->foreignId('kode_rktm_id')->nullable()->index();
            $table->foreignId('kode_sk_id')->nullable()->index();
            $table->foreignId('kode_iksk_id')->nullable()->index();
            $table->foreignId('kode_edm_id')->nullable()->index();
            $table->foreignId('budaya_mutu_id')->nullable()->index();
            $table->jsonb('kode_hilang')->nullable();

            // Asal kegiatan: isian manual, impor Excel/cascade Perkin, atau salinan kegiatan tahun lalu.
            $table->enum('asal', ['manual', 'impor_excel', 'impor_perkin'])->default('manual');
            $table->foreignId('duplikat_dari_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->uuid('data_pagu_id')->nullable()->index();

            // Status dokumen (sama dengan App\Enums\StatusKegiatan) dan jejak kunci final.
            $table->enum('status', ['draf', 'diperiksa', 'dikembalikan', 'disetujui', 'final'])->default('draf');
            $table->timestampTz('dikunci_pada', 6)->nullable();
            $table->foreignId('dikunci_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestampsTz(6);

            $table->index(['tahun_anggaran_id', 'leading_id', 'status']);
        });

        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_lama_hari_check CHECK (lama_hari BETWEEN 1 AND 366)');
        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_peserta_jumlah_check CHECK (peserta_jumlah > 0)');
        DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_bulan_pelaksanaan_check CHECK (jsonb_typeof(bulan_pelaksanaan) = 'array')");
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
