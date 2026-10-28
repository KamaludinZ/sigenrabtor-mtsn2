<?php

use App\Support\Terbilang;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Format angka rupiah & terbilang di database (PRD aturan 2 & 10, §17 "Terbilang & Format Indonesia") — kembaran
 * App\Support\Terbilang dan App\Support\Format untuk laporan/view dan pemeriksaan data (kasus bersama
 * tests/fixtures/terbilang.json):
 * - `terbilang_kata(bigint)` = Terbilang::kata ("satu juta seribu satu", "nol", "minus …"; di atas
 *   Terbilang::MAKSIMUM → galat), `terbilang_rupiah(bigint)` = Terbilang::rupiah (tiap kata berhuruf awal kapital);
 * - `format_angka(bigint)` = Format::angka ("10.000.000"), `format_rupiah` = Format::rupiah ("Rp10.000.000",
 *   negatif "-Rp600.000"), `format_rupiah_dokumen` = Format::rupiahDokumen ("Rp10.000.000,-");
 * - anggaran_kegiatan: salinan `terbilang` wajib sama dengan terbilang total RAB-nya (CHECK
 *   `anggaran_kegiatan_terbilang_sesuai`; total 0 = kosong). Salinan yang berbeda dihitung ulang lebih dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        $maks = Terbilang::MAKSIMUM;
        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION terbilang_eja(n bigint) RETURNS text LANGUAGE plpgsql IMMUTABLE STRICT AS \$\$
            DECLARE
                satuan text[] := ARRAY['satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
                depan text;
                sisa bigint;
            BEGIN
                IF n = 0 THEN RETURN ''; END IF;
                IF n < 12 THEN RETURN satuan[n]; END IF;
                IF n < 20 THEN RETURN satuan[n - 10] || ' belas'; END IF;
                IF n >= 1000000000000 THEN depan := terbilang_eja(n / 1000000000000) || ' triliun'; sisa := n % 1000000000000;
                ELSIF n >= 1000000000 THEN depan := terbilang_eja(n / 1000000000) || ' miliar'; sisa := n % 1000000000;
                ELSIF n >= 1000000 THEN depan := terbilang_eja(n / 1000000) || ' juta'; sisa := n % 1000000;
                ELSIF n < 100 THEN depan := satuan[n / 10] || ' puluh'; sisa := n % 10;
                ELSIF n < 200 THEN depan := 'seratus'; sisa := n - 100;
                ELSIF n < 1000 THEN depan := satuan[n / 100] || ' ratus'; sisa := n % 100;
                ELSIF n < 2000 THEN depan := 'seribu'; sisa := n - 1000;
                ELSE depan := terbilang_eja(n / 1000) || ' ribu'; sisa := n % 1000;
                END IF;
                RETURN CASE WHEN sisa = 0 THEN depan ELSE depan || ' ' || terbilang_eja(sisa) END;
            END
            \$\$;

            CREATE OR REPLACE FUNCTION terbilang_kata(n bigint) RETURNS text LANGUAGE plpgsql IMMUTABLE STRICT AS \$\$
            BEGIN
                IF abs(n) > {$maks} THEN
                    RAISE EXCEPTION 'Nilai terlalu besar untuk diterbilangkan (maksimal 999 triliun).' USING ERRCODE = 'numeric_value_out_of_range';
                END IF;
                IF n = 0 THEN RETURN 'nol'; END IF;
                RETURN CASE WHEN n < 0 THEN 'minus ' || terbilang_eja(-n) ELSE terbilang_eja(n) END;
            END
            \$\$;

            CREATE OR REPLACE FUNCTION terbilang_rupiah(n bigint) RETURNS text LANGUAGE sql IMMUTABLE STRICT AS \$\$
                SELECT initcap(terbilang_kata(n) || ' rupiah')
            \$\$;

            CREATE OR REPLACE FUNCTION format_angka(n bigint) RETURNS text LANGUAGE sql IMMUTABLE STRICT AS \$\$
                SELECT CASE WHEN n < 0 THEN '-' ELSE '' END || regexp_replace(abs(n)::text, '(\\d)(?=(\\d{3})+\$)', '\\1.', 'g')
            \$\$;

            CREATE OR REPLACE FUNCTION format_rupiah(n bigint) RETURNS text LANGUAGE sql IMMUTABLE STRICT AS \$\$
                SELECT CASE WHEN n < 0 THEN '-' ELSE '' END || 'Rp' || format_angka(abs(n))
            \$\$;

            CREATE OR REPLACE FUNCTION format_rupiah_dokumen(n bigint) RETURNS text LANGUAGE sql IMMUTABLE STRICT AS \$\$
                SELECT format_rupiah(n) || ',-'
            \$\$;

            UPDATE anggaran_kegiatan SET terbilang = CASE WHEN total_rab = 0 THEN NULL ELSE terbilang_rupiah(total_rab) END
                WHERE terbilang IS DISTINCT FROM CASE WHEN total_rab = 0 THEN NULL ELSE terbilang_rupiah(total_rab) END;
            ALTER TABLE anggaran_kegiatan ADD CONSTRAINT anggaran_kegiatan_terbilang_sesuai
                CHECK (terbilang IS NOT DISTINCT FROM CASE WHEN total_rab = 0 THEN NULL ELSE terbilang_rupiah(total_rab) END);
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE anggaran_kegiatan DROP CONSTRAINT anggaran_kegiatan_terbilang_sesuai;
            DROP FUNCTION IF EXISTS format_rupiah_dokumen(bigint);
            DROP FUNCTION IF EXISTS format_rupiah(bigint);
            DROP FUNCTION IF EXISTS format_angka(bigint);
            DROP FUNCTION IF EXISTS terbilang_rupiah(bigint);
            DROP FUNCTION IF EXISTS terbilang_kata(bigint);
            DROP FUNCTION IF EXISTS terbilang_eja(bigint);
            SQL);
    }
};
