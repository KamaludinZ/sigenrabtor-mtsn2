<?php

use App\Support\Kegiatan\TeksKhusus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Teks baku KAK/TOR yang disesuaikan per kegiatan (PRD F4 "Template Master Ber-variabel"; isian `teks_khusus` UbahDataKegiatan,
 * aturan TeksKhusus): kolom activities.teks_khusus jsonb — NULL = semua memakai teks baku, selain itu objek berkunci
 * TeksKhusus::BIDANG (daftar = larik teks 1–MAKS_BUTIR butir, paragraf = teks) dengan panjang per teks/butir & penanda
 * hanya `{nama_kegiatan}` yang sama dengan TeksKhusus::galat — dijaga fungsi `teks_khusus_sah(jsonb)` yang dibangkitkan
 * dari konstanta yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        $syarat = [];
        foreach (TeksKhusus::BIDANG as $kunci => [, , $jenis, $min, $maks]) {
            $panjang = "char_length(%s) BETWEEN {$min} AND {$maks} AND NOT (%s ~ '\\{(?!nama_kegiatan\\})[^{}]*\\}')";
            $syarat[] = $jenis === 'daftar'
                ? "(NOT t ? '{$kunci}' OR (jsonb_typeof(t->'{$kunci}') = 'array' AND jsonb_array_length(t->'{$kunci}') BETWEEN 1 AND ".TeksKhusus::MAKS_BUTIR."
                    AND NOT EXISTS (SELECT 1 FROM jsonb_array_elements(t->'{$kunci}') b WHERE jsonb_typeof(b) <> 'string' OR NOT (".sprintf($panjang, "(b #>> '{}')", "(b #>> '{}')").'))))'
                : "(NOT t ? '{$kunci}' OR (jsonb_typeof(t->'{$kunci}') = 'string' AND ".sprintf($panjang, "(t->>'{$kunci}')", "(t->>'{$kunci}')").'))';
        }
        $kunci = implode(', ', array_map(fn (string $k) => "'{$k}'", array_keys(TeksKhusus::BIDANG)));
        $syarat = implode("\n                AND ", $syarat);

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION teks_khusus_sah(t jsonb) RETURNS boolean LANGUAGE sql IMMUTABLE AS \$\$
                SELECT t IS NULL OR (jsonb_typeof(t) = 'object' AND t <> '{}'::jsonb
                AND NOT EXISTS (SELECT 1 FROM jsonb_object_keys(t) k WHERE k NOT IN ({$kunci}))
                AND {$syarat})
            \$\$;
            ALTER TABLE activities ADD COLUMN teks_khusus jsonb NULL;
            ALTER TABLE activities ADD CONSTRAINT activities_teks_khusus_check CHECK (teks_khusus_sah(teks_khusus));
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE activities DROP CONSTRAINT IF EXISTS activities_teks_khusus_check;
            ALTER TABLE activities DROP COLUMN IF EXISTS teks_khusus;
            DROP FUNCTION IF EXISTS teks_khusus_sah(jsonb);
            SQL);
    }
};
