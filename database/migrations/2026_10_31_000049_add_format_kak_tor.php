<?php

use App\Support\Dokumen\FormatKakTor;
use App\Support\Dokumen\SuntingDokumen;
use App\Support\Kegiatan\TeksKhusus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Format susunan KAK/TOR (App\Support\Dokumen\FormatKakTor: format 9 bagian & format 5 bab TOR 2027):
 * - activities.format_dokumen: format pilihan kegiatan itu (NULL = ikut tahun anggaran), CHECK = FormatKakTor::FORMAT;
 * - pengaturan_format.format_kak: format pilihan admin per tahun anggaran (NULL = bawaan tahun), CHECK yang sama;
 * - teks baku yang bisa disesuaikan bertambah (TeksKhusus::BIDANG: penanggung jawab, narasumber, output, outcome) —
 *   fungsi `teks_khusus_sah()` (migrasi 000038) dan CHECK bidang jejak/draf sunting pratinjau (000041, 000042) dibangun
 *   ulang dari konstanta saat ini. Batalkan: kolom dibuang dan aturan kembali tanpa bidang baru.
 */
return new class extends Migration
{
    /** Bidang teks khusus & sunting yang ditambahkan migrasi ini. */
    private const BIDANG_BARU = ['penanggung_jawab', 'narasumber', 'output', 'outcome'];

    public function up(): void
    {
        $format = implode(', ', array_map(fn (string $f) => "'{$f}'", array_keys(FormatKakTor::FORMAT)));
        DB::unprepared(<<<SQL
            ALTER TABLE activities ADD COLUMN format_dokumen varchar(30) NULL;
            ALTER TABLE activities ADD CONSTRAINT activities_format_dokumen_check CHECK (format_dokumen IS NULL OR format_dokumen IN ({$format}));
            ALTER TABLE pengaturan_format ADD COLUMN format_kak varchar(30) NULL;
            ALTER TABLE pengaturan_format ADD CONSTRAINT pengaturan_format_kak_check CHECK (format_kak IS NULL OR format_kak IN ({$format}));
            SQL);

        $this->aturanBidang(array_keys(TeksKhusus::BIDANG), array_keys(SuntingDokumen::BIDANG));
    }

    public function down(): void
    {
        $this->aturanBidang(array_diff(array_keys(TeksKhusus::BIDANG), self::BIDANG_BARU), array_diff(array_keys(SuntingDokumen::BIDANG), self::BIDANG_BARU));

        DB::unprepared(<<<'SQL'
            ALTER TABLE pengaturan_format DROP CONSTRAINT IF EXISTS pengaturan_format_kak_check;
            ALTER TABLE pengaturan_format DROP COLUMN IF EXISTS format_kak;
            ALTER TABLE activities DROP CONSTRAINT IF EXISTS activities_format_dokumen_check;
            ALTER TABLE activities DROP COLUMN IF EXISTS format_dokumen;
            SQL);
    }

    /**
     * Bangun ulang fungsi teks_khusus_sah & CHECK bidang sunting untuk daftar bidang itu (bentuk sama dengan 000038/41/42).
     *
     * @param  list<string>  $teksKhusus  Kunci TeksKhusus::BIDANG.
     * @param  list<string>  $sunting  Kunci SuntingDokumen::BIDANG.
     */
    private function aturanBidang(array $teksKhusus, array $sunting): void
    {
        $syarat = [];
        foreach ($teksKhusus as $kunci) {
            [, , $jenis, $min, $maks] = TeksKhusus::BIDANG[$kunci];
            $panjang = "char_length(%s) BETWEEN {$min} AND {$maks} AND NOT (%s ~ '\\{(?!nama_kegiatan\\})[^{}]*\\}')";
            $syarat[] = $jenis === 'daftar'
                ? "(NOT t ? '{$kunci}' OR (jsonb_typeof(t->'{$kunci}') = 'array' AND jsonb_array_length(t->'{$kunci}') BETWEEN 1 AND ".TeksKhusus::MAKS_BUTIR."
                    AND NOT EXISTS (SELECT 1 FROM jsonb_array_elements(t->'{$kunci}') b WHERE jsonb_typeof(b) <> 'string' OR NOT (".sprintf($panjang, "(b #>> '{}')", "(b #>> '{}')").'))))'
                : "(NOT t ? '{$kunci}' OR (jsonb_typeof(t->'{$kunci}') = 'string' AND ".sprintf($panjang, "(t->>'{$kunci}')", "(t->>'{$kunci}')").'))';
        }
        $kunciTeks = implode(', ', array_map(fn (string $k) => "'{$k}'", $teksKhusus));
        $syarat = implode("\n                AND ", $syarat);

        $pasangan = implode(', ', array_map(fn (string $k) => "('{$k}', ".SuntingDokumen::BIDANG[$k][0].", '".SuntingDokumen::BIDANG[$k][1]."')", $sunting));
        $maksTeks = implode(' ', array_map(fn (string $k) => "WHEN '{$k}' THEN ".SuntingDokumen::maksTeks($k), $sunting));
        $maksDraf = implode(' ', array_map(fn (string $k) => "WHEN '{$k}' THEN ".SuntingDokumen::maksDraf($k), $sunting));
        $bidang = implode(', ', array_map(fn (string $k) => "'{$k}'", $sunting));
        $teksSah = fn (string $kolom) => "({$kolom} IS NULL OR (btrim({$kolom}) <> '' AND {$kolom} = btrim({$kolom}) AND char_length({$kolom}) <= CASE bidang {$maksTeks} END))";

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION teks_khusus_sah(t jsonb) RETURNS boolean LANGUAGE sql IMMUTABLE AS \$\$
                SELECT t IS NULL OR (jsonb_typeof(t) = 'object' AND t <> '{}'::jsonb
                AND NOT EXISTS (SELECT 1 FROM jsonb_object_keys(t) k WHERE k NOT IN ({$kunciTeks}))
                AND {$syarat})
            \$\$;
            ALTER TABLE riwayat_sunting_dokumen DROP CONSTRAINT IF EXISTS riwayat_sunting_isian_check;
            ALTER TABLE riwayat_sunting_dokumen ADD CONSTRAINT riwayat_sunting_isian_check CHECK (
                (bidang, nomor_bagian, asal) IN ({$pasangan})
                AND teks_lama IS DISTINCT FROM teks_baru
                AND {$teksSah('teks_lama')} AND {$teksSah('teks_baru')}
                AND btrim(pengguna) <> '');
            ALTER TABLE draf_sunting_dokumen DROP CONSTRAINT IF EXISTS draf_sunting_isian_check;
            ALTER TABLE draf_sunting_dokumen ADD CONSTRAINT draf_sunting_isian_check CHECK (
                bidang IN ({$bidang})
                AND char_length(teks) <= CASE bidang {$maksDraf} END
                AND (teks_awal IS NULL OR (btrim(teks_awal) <> '' AND teks_awal = btrim(teks_awal)))
                AND diperbarui_pada >= dibuat_pada);
            SQL);
    }
};
