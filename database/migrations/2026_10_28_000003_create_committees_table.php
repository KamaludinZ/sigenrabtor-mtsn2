<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pelaksana & panitia kegiatan (PRD §6 committees): disimpan utuh bersama Data Kegiatan, urutan = urutan cetak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan');
            $table->string('nama', 150);
            $table->string('jabatan', 150)->nullable();
            $table->string('peran', 100);

            $table->unique(['activity_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committees');
    }
};
