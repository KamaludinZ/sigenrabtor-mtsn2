<?php

use App\Support\Kegiatan\RiwayatStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * catatan_pemeriksaan: draf catatan admin per kegiatan sebelum keputusan dikirim (satu baris per kegiatan, ikut terhapus
 * bersama kegiatan; akun dihapus → user_id kosong, nama penulis tetap). Isi sama dengan catatan riwayat yang dirapikan
 * RiwayatStatus::rapikanCatatan: tidak kosong, tanpa spasi tepi, ≤ RiwayatStatus::MAKS_CATATAN karakter. Catatan
 * keputusan yang sudah dikirim tetap di riwayat_status_kegiatan (view pemeriksaan_dokumen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catatan_pemeriksaan', function (Blueprint $table) {
            $table->foreignId('activity_id')->primary()->constrained('activities')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('oleh', 255);
            $table->text('catatan');
            $table->timestampTz('diperbarui_pada', 6)->useCurrent();
        });
        DB::statement("ALTER TABLE catatan_pemeriksaan ADD CONSTRAINT catatan_pemeriksaan_isian_check CHECK (btrim(oleh) <> ''
            AND btrim(catatan) <> '' AND catatan = btrim(catatan) AND char_length(catatan) <= ".RiwayatStatus::MAKS_CATATAN.')');
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan_pemeriksaan');
    }
};
