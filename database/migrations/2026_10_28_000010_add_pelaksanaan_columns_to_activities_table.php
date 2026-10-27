<?php

use App\Support\Kegiatan\DataPelaksanaan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data pelaksanaan kegiatan (PRD F4: tempat, lama, peserta, bulan, jadwal mulai–selesai). Isiannya sudah di activities;
 * migrasi ini menambah `tanggal_selesai` — salinan DataPelaksanaan::selesai() (mulai + lama, hari kerja melewati akhir
 * pekan) yang diperbarui repositori saat jadwal disimpan — agar rentang tanggal Monitoring & daftar bisa dicari di
 * database, beserta batasan: selesai tidak sebelum mulai (dan hanya ada bila mulai ada), bulan pelaksanaan berupa angka
 * 1–12, serta indeks jadwal per tahun dan indeks GIN untuk "bulan pelaksanaan memuat …".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->date('tanggal_selesai')->nullable();
            $table->index(['tahun_anggaran', 'tanggal_mulai', 'tanggal_selesai']);
        });

        foreach (DB::table('activities')->select(['id', 'tanggal_mulai', 'lama_hari', 'hari_kerja'])->orderBy('id')->cursor() as $k) {
            DB::table('activities')->where('id', $k->id)->update(['tanggal_selesai' => DataPelaksanaan::dari((array) $k)->selesai()?->toDateString()]);
        }

        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_tanggal_selesai_check CHECK (tanggal_selesai IS NULL OR (tanggal_mulai IS NOT NULL AND tanggal_selesai >= tanggal_mulai))');
        DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_bulan_pelaksanaan_isi_check CHECK (NOT jsonb_path_exists(bulan_pelaksanaan, '\$[*] ? (@.type() != \"number\" || @ < 1 || @ > 12 || @.floor() != @)'))");
        DB::statement('CREATE INDEX activities_bulan_pelaksanaan_gin ON activities USING gin (bulan_pelaksanaan jsonb_path_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX activities_bulan_pelaksanaan_gin');
        DB::statement('ALTER TABLE activities DROP CONSTRAINT activities_bulan_pelaksanaan_isi_check');
        DB::statement('ALTER TABLE activities DROP CONSTRAINT activities_tanggal_selesai_check');

        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['tahun_anggaran', 'tanggal_mulai', 'tanggal_selesai']);
            $table->dropColumn('tanggal_selesai');
        });
    }
};
