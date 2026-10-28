<?php

use App\Support\Kegiatan\ArsipFinal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * arsip_final (PRD §6, F18): PDF bertanda tangan per kegiatan × sumber dana — satu baris per baris anggaran_kegiatan
 * (`anggaran_kegiatan_id` unik; activity_id & sumber_dana salinannya, dijaga sama lewat FK komposit ke
 * anggaran_kegiatan (id, activity_id, sumber_dana)); ikut terhapus bersama kegiatan/anggarannya. Berkas di disk local
 * (`file_path` .pdf, unik), nama asli .pdf, ukuran 1 bait–ArsipFinal::MAKS_KB, pengunggah (FK users, akun dihapus →
 * kosong; nama saat itu tetap) dan waktu unggah bermikrodetik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anggaran_kegiatan', function (Blueprint $table) {
            $table->unique(['id', 'activity_id', 'sumber_dana'], 'anggaran_kegiatan_id_kegiatan_sumber_unique');
        });

        Schema::create('arsip_final', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anggaran_kegiatan_id')->unique();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->string('sumber_dana');
            $table->string('file_path')->unique();
            $table->string('nama_berkas');
            $table->unsignedInteger('ukuran');
            $table->foreignId('diunggah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_pengunggah');
            $table->timestampTz('diunggah_pada', 6)->useCurrent();

            $table->foreign(['anggaran_kegiatan_id', 'activity_id', 'sumber_dana'], 'arsip_final_anggaran_foreign')
                ->references(['id', 'activity_id', 'sumber_dana'])->on('anggaran_kegiatan')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('sumber_dana')->references('kode')->on('sumber_dana')->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('ALTER TABLE arsip_final ADD CONSTRAINT arsip_final_isian_check CHECK (
            ukuran BETWEEN 1 AND '.(ArsipFinal::MAKS_KB * 1024)."
            AND lower(nama_berkas) LIKE '%.pdf' AND lower(file_path) LIKE '%.pdf' AND btrim(nama_pengunggah) <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('arsip_final');
        Schema::table('anggaran_kegiatan', function (Blueprint $table) {
            $table->dropUnique('anggaran_kegiatan_id_kegiatan_sumber_unique');
        });
    }
};
