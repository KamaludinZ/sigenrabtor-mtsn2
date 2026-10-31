<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identitas instalasi (App\Support\Basisdata\PengamanDeploy): satu baris id yang juga dicatat di volume storage
 * (storage/app/private/deploy/instalasi.json). Saat redeploy keduanya dicocokkan sehingga volume storage yang baru/kosong
 * di atas database lama (atau sebaliknya) terdeteksi sebelum migrasi, bukan diam-diam menampilkan data contoh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instalasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestampTz('dibuat_pada', 6)->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instalasi');
    }
};
