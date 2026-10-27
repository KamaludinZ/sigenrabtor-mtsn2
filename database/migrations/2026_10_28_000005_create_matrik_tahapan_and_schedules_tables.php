<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Matrik tahapan & jadwal kegiatan (PRD §6 matrik_tahapan & schedules, F10) — bentuk MatrikTahapan/JadwalKegiatan:
 * satu baris per butir tahapan / baris jadwal, bulan sebagai daftar nomor bulan (tahunnya selalu tahun anggaran aktif,
 * PRD aturan 3), urutan = urutan cetak. activities.matrik_jadwal_disimpan_pada membedakan "belum pernah disimpan"
 * (butir & 6 baris baku) dari "disimpan kosong" (jadwal kosong tersimpan apa adanya).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matrik_tahapan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->enum('tahapan', ['persiapan', 'pelaksanaan', 'evaluasi_pelaporan']);
            $table->unsignedSmallInteger('urutan');
            $table->string('uraian')->default('');
            $table->jsonb('bulan')->default('[]');

            $table->unique(['activity_id', 'tahapan', 'urutan']);
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan');
            $table->string('uraian')->default('');
            $table->jsonb('bulan')->default('[]');

            $table->unique(['activity_id', 'urutan']);
        });

        foreach (['matrik_tahapan', 'schedules'] as $tabel) {
            DB::statement("ALTER TABLE {$tabel} ADD CONSTRAINT {$tabel}_bulan_check CHECK (jsonb_typeof(bulan) = 'array')");
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->timestampTz('matrik_jadwal_disimpan_pada', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('matrik_jadwal_disimpan_pada');
        });
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('matrik_tahapan');
    }
};
