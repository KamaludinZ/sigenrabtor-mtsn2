<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat status kegiatan (entri RiwayatStatus::entri(): aksi, status tujuan, siapa, kapan, catatan; PRD N7) — hanya
 * ditambah. Nama & peran pelaku disalin saat itu agar riwayat tetap terbaca bila akunnya berubah atau dihapus
 * (FK user_id menyusul bersama akun di tabel users). sidik = sidik per bagian data saat dikembalikan/dibuka kuncinya
 * (PerbaikanKegiatan), json agar urutan bagian tetap. Keputusan admin (setujui, kembalikan) di sini menjadi sumber
 * catatan pemeriksaan (PRD pemeriksaan_dokumen).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_status_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->string('aksi', 30);
            $table->enum('status', ['draf', 'diperiksa', 'dikembalikan', 'disetujui', 'final']);
            $table->foreignId('user_id')->nullable()->index();
            $table->string('oleh');
            $table->string('peran', 20);
            $table->text('catatan')->nullable();
            $table->json('sidik')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();

            $table->index(['activity_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_status_kegiatan');
    }
};
