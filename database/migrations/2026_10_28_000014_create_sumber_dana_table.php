<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sumber dana (PRD F5): tabel rujukan `sumber_dana` (kode = nilai App\Enums\SumberDana, nama, keterangan, urutan baku
 * tampil) yang diisi DIPA & Komite di migrasi ini, menggantikan CHECK enum di anggaran_kegiatan dengan foreign key
 * (ON UPDATE CASCADE; sumber dana yang masih dipakai RAB tidak bisa dihapus) — tabel lain yang menyebut sumber dana
 * (data pagu, template per sumber, buku, arsip final) merujuk tabel yang sama. Isinya harus sama dengan enum
 * (SkemaSumberDanaTest).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sumber_dana', function (Blueprint $table) {
            $table->string('kode', 10)->primary();
            $table->string('nama', 50);
            $table->string('keterangan');
            $table->unsignedSmallInteger('urutan')->unique();
        });

        DB::table('sumber_dana')->insert([
            ['kode' => 'DIPA', 'nama' => 'DIPA', 'keterangan' => 'Daftar Isian Pelaksanaan Anggaran (APBN).', 'urutan' => 1],
            ['kode' => 'Komite', 'nama' => 'Komite', 'keterangan' => 'Dana komite madrasah.', 'urutan' => 2],
        ]);

        DB::statement('ALTER TABLE anggaran_kegiatan DROP CONSTRAINT anggaran_kegiatan_sumber_dana_check');
        Schema::table('anggaran_kegiatan', function (Blueprint $table) {
            $table->foreign('sumber_dana')->references('kode')->on('sumber_dana')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('anggaran_kegiatan', function (Blueprint $table) {
            $table->dropForeign(['sumber_dana']);
        });
        DB::statement("ALTER TABLE anggaran_kegiatan ADD CONSTRAINT anggaran_kegiatan_sumber_dana_check CHECK (sumber_dana::text = ANY (ARRAY['DIPA'::character varying, 'Komite'::character varying]::text[]))");
        Schema::dropIfExists('sumber_dana');
    }
};
