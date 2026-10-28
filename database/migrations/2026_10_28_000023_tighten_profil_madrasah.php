<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Profil madrasah (PRD §6 profil_madrasah, satu baris) — aturan isian disamakan dengan formulir Profil & Pejabat
 * (ProfilMadrasah::rules): nama/nama singkat/kota wajib, telepon ≤ 40 karakter berisi angka, spasi, dan + ( ) - . /,
 * visi ≤ 1000 karakter.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE profil_madrasah DROP CONSTRAINT profil_madrasah_isian_check');
        DB::statement("ALTER TABLE profil_madrasah ADD CONSTRAINT profil_madrasah_isian_check CHECK (btrim(nama) <> '' AND btrim(nama_singkat) <> '' AND btrim(kota) <> ''
            AND (telepon IS NULL OR (char_length(telepon) <= 40 AND telepon ~ '^[0-9+()./[:space:]-]+$'))
            AND (visi IS NULL OR char_length(visi) <= 1000))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE profil_madrasah DROP CONSTRAINT profil_madrasah_isian_check');
        DB::statement("ALTER TABLE profil_madrasah ADD CONSTRAINT profil_madrasah_isian_check CHECK (btrim(nama) <> '' AND btrim(nama_singkat) <> '' AND btrim(kota) <> '' AND (telepon IS NULL OR telepon ~ '^[0-9 ()+./-]+$'))");
    }
};
