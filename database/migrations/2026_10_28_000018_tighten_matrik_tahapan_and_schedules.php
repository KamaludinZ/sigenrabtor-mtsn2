<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aturan matrik tahapan & jadwal di database (PRD F10, aturan 3 & 8) — kembaran MatrikTahapan/JadwalKegiatan dan aturan
 * isian halaman Matrik & Jadwal:
 * - `bulan` daftar nomor bulan 1–12 tanpa ganda & urut naik (fungsi `daftar_bulan_sah`, sama dengan
 *   MatrikTahapan::rapikanBulan); bulan jadwal juga harus bersambung (mulai s.d. sampai).
 * - uraian butir matrik wajib, tanpa spasi di tepi, ≤ MatrikTahapan::MAKS_URAIAN (150); uraian jadwal tanpa spasi di
 *   tepi, ≤ JadwalKegiatan::MAKS_URAIAN (200), dan wajib bila bulannya terisi (baris kosong boleh).
 * - urutan mulai 1; jadwal paling banyak JadwalKegiatan::MAKS_BARIS (12) baris.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION daftar_bulan_sah(b jsonb, bersambung boolean DEFAULT false) RETURNS boolean
            LANGUAGE plpgsql IMMUTABLE AS $$
            DECLARE
                e jsonb;
                nilai integer;
                sebelum integer := 0;
            BEGIN
                IF b IS NULL OR jsonb_typeof(b) <> 'array' THEN
                    RETURN false;
                END IF;
                FOR e IN SELECT * FROM jsonb_array_elements(b) LOOP
                    IF jsonb_typeof(e) <> 'number' OR (e #>> '{}') !~ '^[0-9]{1,2}$' THEN
                        RETURN false;
                    END IF;
                    nilai := (e #>> '{}')::integer;
                    IF nilai < 1 OR nilai > 12 OR nilai <= sebelum OR (bersambung AND sebelum > 0 AND nilai <> sebelum + 1) THEN
                        RETURN false;
                    END IF;
                    sebelum := nilai;
                END LOOP;
                RETURN true;
            END $$
            SQL);

        DB::statement('ALTER TABLE matrik_tahapan DROP CONSTRAINT matrik_tahapan_bulan_check');
        DB::statement('ALTER TABLE matrik_tahapan ADD CONSTRAINT matrik_tahapan_bulan_check CHECK (daftar_bulan_sah(bulan))');
        DB::statement("ALTER TABLE matrik_tahapan ADD CONSTRAINT matrik_tahapan_uraian_check CHECK (uraian <> '' AND uraian !~ '^\\s|\\s$' AND char_length(uraian) <= 150)");
        DB::statement('ALTER TABLE matrik_tahapan ADD CONSTRAINT matrik_tahapan_urutan_check CHECK (urutan >= 1)');

        DB::statement('ALTER TABLE schedules DROP CONSTRAINT schedules_bulan_check');
        DB::statement('ALTER TABLE schedules ADD CONSTRAINT schedules_bulan_check CHECK (daftar_bulan_sah(bulan, true))');
        DB::statement("ALTER TABLE schedules ADD CONSTRAINT schedules_uraian_check CHECK (uraian !~ '^\\s|\\s$' AND char_length(uraian) <= 200 AND (uraian <> '' OR bulan = '[]'::jsonb))");
        DB::statement('ALTER TABLE schedules ADD CONSTRAINT schedules_urutan_check CHECK (urutan BETWEEN 1 AND 12)');
    }

    public function down(): void
    {
        foreach (['matrik_tahapan', 'schedules'] as $tabel) {
            foreach (['bulan', 'uraian', 'urutan'] as $kolom) {
                DB::statement("ALTER TABLE {$tabel} DROP CONSTRAINT {$tabel}_{$kolom}_check");
            }
            DB::statement("ALTER TABLE {$tabel} ADD CONSTRAINT {$tabel}_bulan_check CHECK (jsonb_typeof(bulan) = 'array')");
        }
        DB::statement('DROP FUNCTION daftar_bulan_sah(jsonb, boolean)');
    }
};
