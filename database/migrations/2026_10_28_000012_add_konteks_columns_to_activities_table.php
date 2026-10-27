<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konteks kegiatan (PRD §9 "Konteks utama": catatan bebas leading sebagai bahan AI). Teksnya di activities.konteks;
 * migrasi ini menambah `konteks_diperbarui_pada` — kapan catatan terakhir berubah, diisi repositori setiap isi konteks
 * berubah (simpan otomatis maupun Simpan Draf), terpisah dari versi Data Kegiatan — dan batas panjang 3.000 karakter
 * (sama dengan ValidasiKegiatan) di database. Konteks lama mendapat waktu perubahan kegiatannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->timestampTz('konteks_diperbarui_pada', 6)->nullable();
        });

        DB::statement("UPDATE activities SET konteks_diperbarui_pada = updated_at WHERE konteks IS NOT NULL AND konteks <> ''");
        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_konteks_panjang_check CHECK (char_length(konteks) <= 3000)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE activities DROP CONSTRAINT activities_konteks_panjang_check');

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn('konteks_diperbarui_pada');
        });
    }
};
