<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kode & klasifikasi kegiatan (PRD F7): indeks (tahun_anggaran, kode) untuk tiap kolom kode master — pemakaian kode
 * oleh kegiatan tahun itu (kolom "Dipakai" Master Kode, JenisKode::pemakaian) dan kode yang tidak ada lagi di master
 * (KodeHilang) dicari per tahun & kode — serta aturan IKSK selalu bersama SK induknya (kode SK wajib dipilih lebih
 * dulu, ValidasiKegiatan). Kode tetap tanpa FK ke master: kode yang hilang dari master tetap tersimpan & terbaca.
 */
return new class extends Migration
{
    /** Kolom kode kegiatan yang dicari per tahun. */
    private const KOLOM = ['kode_rktm', 'kode_sk', 'kode_iksk', 'kode_edm', 'budaya_mutu'];

    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            foreach (self::KOLOM as $kolom) {
                $table->index(['tahun_anggaran', $kolom]);
            }
        });

        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_kode_iksk_sk_check CHECK (kode_iksk IS NULL OR kode_sk IS NOT NULL)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE activities DROP CONSTRAINT activities_kode_iksk_sk_check');

        Schema::table('activities', function (Blueprint $table) {
            foreach (self::KOLOM as $kolom) {
                $table->dropIndex(['tahun_anggaran', $kolom]);
            }
        });
    }
};
