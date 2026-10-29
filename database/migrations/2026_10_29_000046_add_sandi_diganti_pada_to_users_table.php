<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Waktu sandi akun terakhir diganti pengguna sendiri (PRD §8 "Kelola Profil & Sandi"; PenggunaRepository::sandi/gantiSandi,
 * ditampilkan di tab Sandi Akun saya). Kosong = belum pernah diganti sejak akun dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestampTz('sandi_diganti_pada', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sandi_diganti_pada');
        });
    }
};
