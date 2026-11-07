<?php

namespace App\Support\Master\Impor;

use App\Support\Impor\LembarExcel;
use App\Support\Master\FormatKode;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Tata letak sheet cascade Perjanjian Kinerja (Perkin) gaya madrasah — kolom kode tidak berjudul "Kode SK"/"Kode IKSK"
 * (mis. KEGIATAN, SASARAN/INDIKATOR, Indikator Kinerja, kode sub kegiatan tanpa judul), judul dua baris, baris nomor
 * kolom, dan baris kelompok "BIDANG …". Kolom dikenali dari isinya:
 * - kode SK / IKSK / RKTM = kolom dengan kecocokan pola terbanyak (FormatKode, PemeriksaRktm) di bawah judul;
 *   uraian SK & IKSK = kolom tepat di kanannya (judul gabungan "Indikator Kinerja" C:D);
 * - rencana aksi, unit/tim penerima mandat (terdekat di kiri rencana aksi), anggaran DIPA & Komite dari teks judulnya;
 * - bulan 1–12 dari baris judul berisi angka 1..12 (atau nama bulan) berurutan.
 * `baris` = baris sebelum kode SK pertama (data mulai sesudahnya). Dipakai KolomMaster::judul sebagai cadangan bila judul
 * baku tidak ditemukan.
 */
final class TataLetakPerkin
{
    /** Baris teratas yang diperiksa. */
    private const BATAS_BARIS = 600;

    private const BATAS_KOLOM = 60;

    private const NAMA_BULAN = ['jan', 'feb', 'mar', 'apr', 'mei', 'jun', 'jul', 'agu', 'sep', 'okt', 'nov', 'des'];

    /**
     * @return array{baris: int, kolom: array<string, string>, bulan: array<int, string>}|null Kolom: kode_sk, uraian_sk,
     *                                                                                         kode_iksk, uraian_iksk, kode_rktm, unit, uraian (rencana aksi), pagu_dipa, pagu_komite (yang ditemukan saja).
     */
    public static function kenali(Worksheet $lembar): ?array
    {
        $akhirBaris = min(self::BATAS_BARIS, $lembar->getHighestDataRow());
        $akhirKolom = min(self::BATAS_KOLOM, Coordinate::columnIndexFromString($lembar->getHighestDataColumn()));

        $sel = [];
        for ($b = 1; $b <= $akhirBaris; $b++) {
            for ($k = 1; $k <= $akhirKolom; $k++) {
                $nilai = self::nilai($lembar, Coordinate::stringFromColumnIndex($k).$b);
                if ($nilai !== null && $nilai !== '') {
                    $sel[$b][$k] = is_string($nilai) ? trim((string) preg_replace('/\s+/u', ' ', $nilai)) : $nilai;
                }
            }
        }

        $hitung = ['sk' => [], 'iksk' => [], 'rktm' => []];
        $barisSkPertama = null;
        foreach ($sel as $b => $isi) {
            foreach ($isi as $k => $v) {
                if (! is_string($v)) {
                    continue;
                }
                if (preg_match('/^SK\b/i', $v) && FormatKode::sk($v) !== null) {
                    $hitung['sk'][$k] = ($hitung['sk'][$k] ?? 0) + 1;
                } elseif (preg_match('/^IKSK\b/i', $v) && FormatKode::iksk($v) !== null) {
                    $hitung['iksk'][$k] = ($hitung['iksk'][$k] ?? 0) + 1;
                } elseif (PemeriksaRktm::normalkanKode($v) !== null && preg_match('/^\d+\.\d+\.\d+$/', $v)) {
                    $hitung['rktm'][$k] = ($hitung['rktm'][$k] ?? 0) + 1;
                }
            }
        }
        if ($hitung['sk'] === [] && $hitung['iksk'] === []) {
            return null;
        }

        $terbanyak = function (array $n): ?int {
            if ($n === []) {
                return null;
            }
            arsort($n);

            return (int) array_key_first($n);
        };
        $kolomSk = $terbanyak($hitung['sk']);
        $kolomIksk = $terbanyak($hitung['iksk']);
        $kolomRktm = $terbanyak($hitung['rktm']);

        foreach ($sel as $b => $isi) {
            $kodeDi = $kolomSk ?? $kolomIksk;
            if (isset($isi[$kodeDi]) && is_string($isi[$kodeDi]) && preg_match('/^(IK)?SK\b/i', $isi[$kodeDi])) {
                $barisSkPertama = $b;
                break;
            }
        }
        $awalData = $barisSkPertama ?? 1;

        $huruf = fn (int $k) => Coordinate::stringFromColumnIndex($k);
        $kolom = [];
        if ($kolomSk !== null) {
            $kolom['kode_sk'] = $huruf($kolomSk);
            $kolom['uraian_sk'] = $huruf($kolomSk + 1);
        }
        if ($kolomIksk !== null) {
            $kolom['kode_iksk'] = $huruf($kolomIksk);
            $kolom['uraian_iksk'] = $huruf($kolomIksk + 1);
        }
        if ($kolomRktm !== null) {
            $kolom['kode_rktm'] = $huruf($kolomRktm);
        }

        // Judul teks (baris di atas data): rencana aksi, unit, anggaran.
        $judul = [];
        foreach ($sel as $b => $isi) {
            if ($b >= $awalData) {
                break;
            }
            foreach ($isi as $k => $v) {
                if (is_string($v)) {
                    $judul[$k][] = LembarExcel::normalkanJudul($v);
                }
            }
        }
        $cariJudul = function (array $kata) use ($judul): array {
            $hasil = [];
            foreach ($judul as $k => $daftar) {
                foreach ($daftar as $teks) {
                    foreach ($kata as $pola) {
                        if (preg_match('/\b'.$pola.'\b/', $teks)) {
                            $hasil[] = $k;
                            break 2;
                        }
                    }
                }
            }

            return $hasil;
        };

        $rencana = $cariJudul(['rencana aksi', 'rencana kegiatan']);
        if ($rencana !== []) {
            $kolom['uraian'] = $huruf($rencana[0]);
            $unit = array_filter($cariJudul(['unit', 'penerima mandat', 'penanggung jawab', 'leading']), fn (int $k) => $k < $rencana[0]);
            if ($unit !== []) {
                $kolom['unit'] = $huruf(max($unit));
            }
        }
        $dipa = $cariJudul(['dipa', 'bos']);
        $komite = $cariJudul(['komite']);
        if ($dipa !== []) {
            $kolom['pagu_dipa'] = $huruf($dipa[0]);
        }
        if ($komite !== []) {
            $kolom['pagu_komite'] = $huruf($komite[0]);
        }

        return ['baris' => $awalData - 1, 'kolom' => $kolom, 'bulan' => self::bulan($sel, $awalData)];
    }

    /** Nilai sel; rumus (mis. judul bulan "=V6+1") memakai hasil hitung tersimpan, lalu dihitung bila perlu. */
    public static function nilai(Worksheet $lembar, string $alamat): mixed
    {
        $sel = $lembar->getCell($alamat);
        $nilai = $sel->getValue();
        if (is_string($nilai) && str_starts_with($nilai, '=')) {
            $nilai = $sel->getOldCalculatedValue();
            if ($nilai === null) {
                try {
                    $nilai = $sel->getCalculatedValue();
                } catch (\Throwable) {
                    $nilai = null;
                }
            }
        }

        return $nilai;
    }

    /**
     * Kolom bulan 1..12 dari baris judul: angka 1..12 berurutan di kolom bersebelahan atau nama bulan (Jan, Februari, …).
     *
     * @param  array<int, array<int, mixed>>  $sel
     * @return array<int, string> Bulan → huruf kolom.
     */
    public static function bulan(array $sel, int $awalData): array
    {
        foreach ($sel as $b => $isi) {
            if ($b >= $awalData) {
                break;
            }
            $bulan = self::bulanDariBaris($isi);
            if (count($bulan) === 12) {
                return $bulan;
            }
        }

        return [];
    }

    /**
     * @param  array<int, mixed>  $isi  Kolom (indeks) → nilai satu baris.
     * @return array<int, string>
     */
    public static function bulanDariBaris(array $isi): array
    {
        $bulan = [];
        foreach ($isi as $k => $v) {
            $n = self::nomorBulan($v);
            if ($n !== null && ! isset($bulan[$n])) {
                $bulan[$n] = Coordinate::stringFromColumnIndex($k);
            }
        }
        ksort($bulan);

        return count($bulan) === 12 ? $bulan : [];
    }

    /** 1..12, "Jan", "Januari", "Bulan 3" → nomor bulan; null bila bukan judul bulan. */
    public static function nomorBulan(mixed $nilai): ?int
    {
        if (is_int($nilai) || is_float($nilai)) {
            return (float) $nilai === floor((float) $nilai) && $nilai >= 1 && $nilai <= 12 ? (int) $nilai : null;
        }
        $teks = LembarExcel::normalkanJudul((string) $nilai);
        if (preg_match('/^(bulan |bln )?([0-9]{1,2})$/', $teks, $cocok)) {
            $n = (int) $cocok[2];

            return $n >= 1 && $n <= 12 ? $n : null;
        }
        foreach (self::NAMA_BULAN as $i => $awal) {
            if ($teks !== '' && str_starts_with($teks, $awal) && mb_strlen($teks) <= 9) {
                return $i + 1;
            }
        }

        return null;
    }
}
