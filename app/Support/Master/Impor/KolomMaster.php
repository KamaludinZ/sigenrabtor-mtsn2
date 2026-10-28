<?php

namespace App\Support\Master\Impor;

use App\Support\Impor\LembarExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Kolom berkas master kode per jenis (PRD §14) — satu sumber aturan kolom wajib & format teks untuk semua pembaca
 * (Pemeriksa*): label kolom, wajib/opsional, panjang maksimal isian (sejalan dengan kolom tabel master), dan judul yang
 * dikenali (konstanta JUDUL pembacanya).
 * - `judul($lembar, $jenis)`: baris judul tabel; kolom wajib yang tidak ada → GagalPratinjauMaster yang menyebut kolom
 *   mana yang belum ada (dan judul yang dikenali) bila ada baris judul yang hampir lengkap, selain itu daftar semua kolom
 *   wajib.
 * - `galatPanjang($jenis, $isian)`: isian yang melebihi batas panjang → pesan per kolom (baris menjadi gagal).
 * - `opsionalHilang($judul, $jenis)`: catatan tingkat jenis untuk kolom opsional yang tidak ada (memakai bawaan).
 */
final class KolomMaster
{
    /** Jenis → kolom: kunci → [label, wajib, panjang maksimal (null = tanpa batas teks)]. */
    public const KOLOM = [
        'sk' => ['kode' => ['Kode SK', true, null], 'uraian' => ['Uraian SK', true, 1000]],
        'iksk' => ['kode' => ['Kode IKSK', true, null], 'kode_sk' => ['Kode SK', true, null], 'uraian' => ['Uraian IKSK', true, 1000]],
        'rktm' => ['kode' => ['Kode', true, null], 'snp' => ['SNP', true, 300], 'kegiatan' => ['Kegiatan', true, 500], 'sub_kegiatan' => ['Sub Kegiatan', true, 1000]],
        'edm' => ['kode' => ['Kode', true, null], 'uraian' => ['Uraian', true, 1000], 'komponen' => ['Komponen', false, 100]],
        'budaya' => ['nomor' => ['No', false, null], 'nama' => ['Budaya Mutu', true, 150]],
    ];

    /** Kolom opsional yang tidak ada → catatan bawaan yang dipakai. */
    private const BAWAAN_OPSIONAL = [
        'edm.komponen' => 'nama komponen memakai master tahun itu',
        'budaya.nomor' => 'nomor mengikuti urutan baris',
    ];

    /** Jenis → judul kolom yang dikenali (konstanta JUDUL pembacanya). */
    public static function sinonim(string $jenis): array
    {
        return match ($jenis) {
            'sk' => PemeriksaSk::JUDUL,
            'iksk' => PemeriksaIksk::JUDUL,
            'rktm' => PemeriksaRktm::JUDUL,
            'edm' => PemeriksaEdm::JUDUL,
            'budaya' => PemeriksaBudayaMutu::JUDUL,
        };
    }

    /** @return list<string> */
    public static function wajib(string $jenis): array
    {
        return array_keys(array_filter(self::KOLOM[$jenis], fn (array $k) => $k[1]));
    }

    /**
     * @return array{baris: int, kolom: array<string, string>}
     *
     * @throws GagalPratinjauMaster
     */
    public static function judul(Worksheet $lembar, string $jenis): array
    {
        $sinonim = self::sinonim($jenis);
        $wajib = self::wajib($jenis);
        $judul = LembarExcel::cariJudul($lembar, $sinonim, $wajib);
        if ($judul !== null) {
            return $judul;
        }

        $label = fn (string $kunci) => '"'.self::KOLOM[$jenis][$kunci][0].'"';
        $terdekat = LembarExcel::judulTerdekat($lembar, $sinonim, $wajib);
        if ($terdekat === null) {
            throw new GagalPratinjauMaster('Judul kolom '.self::gabung(array_map($label, $wajib)).' tidak ditemukan di sheet '.$lembar->getTitle().'.');
        }

        $hilang = array_values(array_diff($wajib, array_keys($terdekat['kolom'])));
        $dikenali = implode('; ', array_map(fn (string $k) => self::KOLOM[$jenis][$k][0].': '.implode(', ', array_map(fn (string $j) => "\"{$j}\"", array_slice($sinonim[$k], 0, 3))), $hilang));

        throw new GagalPratinjauMaster('Kolom wajib '.self::gabung(array_map($label, $hilang)).' tidak ditemukan di baris judul sheet '.$lembar->getTitle()." (baris {$terdekat['baris']}) — judul yang dikenali {$dikenali}.");
    }

    /**
     * Pesan isian yang melebihi batas panjang kolomnya.
     *
     * @param  array<string, string>  $isian  Kunci kolom → teks.
     * @return list<string>
     */
    public static function galatPanjang(string $jenis, array $isian): array
    {
        $pesan = [];
        foreach ($isian as $kunci => $teks) {
            $maks = self::KOLOM[$jenis][$kunci][2] ?? null;
            if ($maks !== null && ($n = mb_strlen($teks)) > $maks) {
                $pesan[] = self::KOLOM[$jenis][$kunci][0]." terlalu panjang ({$n} karakter, maksimal {$maks}).";
            }
        }

        return $pesan;
    }

    /**
     * Catatan untuk kolom opsional yang tidak ada di baris judul.
     *
     * @param  array{kolom: array<string, string>}  $judul
     * @return list<string>
     */
    public static function opsionalHilang(array $judul, string $jenis): array
    {
        $pesan = [];
        foreach (self::KOLOM[$jenis] as $kunci => [$label, $wajib]) {
            if (! $wajib && ! isset($judul['kolom'][$kunci])) {
                $pesan[] = "Kolom \"{$label}\" tidak ada — ".(self::BAWAAN_OPSIONAL["{$jenis}.{$kunci}"] ?? 'dikosongkan').'.';
            }
        }

        return $pesan;
    }

    /** ["A"] → "A"; ["A","B"] → "A" dan "B"; ["A","B","C"] → "A", "B", dan "C". */
    private static function gabung(array $bagian): string
    {
        return count($bagian) <= 2 ? implode(' dan ', $bagian) : implode(', ', array_slice($bagian, 0, -1)).', dan '.end($bagian);
    }
}
