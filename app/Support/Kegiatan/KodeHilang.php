<?php

namespace App\Support\Kegiatan;

use App\Support\MasterKode;

/**
 * Penanda kode master yang hilang pada kegiatan: kode tersimpan yang tidak ada di master tahun kegiatan itu (mis. master
 * diganti setelah kode dipilih), atau kode lama yang dikosongkan saat kegiatan diduplikat dari tahun lalu / diimpor dari
 * cascade Perkin dan belum diganti. Catatan kode yang dikosongkan disimpan di kunci `kode_hilang` kegiatan
 * ({kolom: {kode, asal, tahun}}) dan gugur begitu kolomnya diisi lagi (sisa()).
 */
final class KodeHilang
{
    /** Kolom kode master → [label, kunci pencarian di master, jangkar bagian di halaman Kode & Klasifikasi]. */
    public const KOLOM = [
        'kode_rktm' => ['Kode RKTM', 'kode', 'kode-rktm'],
        'kode_sk' => ['Kode SK', 'kode', 'kode-perkin'],
        'kode_iksk' => ['IKSK', 'kode', 'kode-perkin'],
        'kode_edm' => ['Kode EDM', 'kode', 'kode-edm'],
        'budaya_mutu' => ['5 Budaya Mutu', 'nomor', 'kode-budaya'],
    ];

    public const ASAL = ['master', 'duplikat', 'impor'];

    /**
     * Baris master satu kolom kode untuk tahun $tahun (null = tahun aktif).
     *
     * @return list<array<string, mixed>>
     */
    public static function master(string $kolom, ?int $tahun = null): array
    {
        return match ($kolom) {
            'kode_rktm' => MasterKode::kodeRktm($tahun),
            'kode_sk' => MasterKode::kodeSk($tahun),
            'kode_iksk' => MasterKode::kodeIksk(null, $tahun),
            'kode_edm' => MasterKode::kodeEdm($tahun),
            'budaya_mutu' => MasterKode::budayaMutu($tahun),
        };
    }

    /**
     * Kode terisi pada $kegiatan yang tidak ada di master tahun $tahun.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return list<array{kolom: string, label: string, kode: string}>
     */
    public static function tidakAdaDi(array $kegiatan, int $tahun): array
    {
        $hasil = [];
        foreach (self::KOLOM as $kolom => [$label, $kunci]) {
            $nilai = $kegiatan[$kolom] ?? null;
            if (filled($nilai) && MasterKode::cari(self::master($kolom, $tahun), $nilai, $kunci) === null) {
                $hasil[] = ['kolom' => $kolom, 'label' => $label, 'kode' => (string) $nilai];
            }
        }

        return $hasil;
    }

    /**
     * Catatan satu kode yang dikosongkan saat disalin/diimpor (nilai kunci `kode_hilang`).
     *
     * @return array{kode: string, asal: string, tahun: int|null}
     */
    public static function catatan(string $kode, string $asal, ?int $tahun = null): array
    {
        return ['kode' => $kode, 'asal' => $asal, 'tahun' => $tahun];
    }

    /**
     * Penanda kode yang hilang pada satu kegiatan, urut kolom KOLOM: kode tersimpan yang tidak ada di master tahun
     * kegiatan (`master`), lalu kode yang dikosongkan saat diduplikat/diimpor dan kolomnya masih kosong.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return list<array{kolom: string, label: string, kode: string, asal: string, tahun_asal: int|null, jangkar: string, sebab: string, teks: string}>
     */
    public static function dari(array $kegiatan): array
    {
        $tahun = TahunKegiatan::dari($kegiatan);
        $tidakAda = array_column(self::tidakAdaDi($kegiatan, $tahun), null, 'kolom');
        $catatan = self::sisa((array) ($kegiatan['kode_hilang'] ?? []), $kegiatan);
        $hasil = [];

        foreach (self::KOLOM as $kolom => [$label, , $jangkar]) {
            $c = isset($tidakAda[$kolom]) ? self::catatan($tidakAda[$kolom]['kode'], 'master', $tahun) : ($catatan[$kolom] ?? null);
            if ($c === null) {
                continue;
            }
            $sebab = match ($c['asal']) {
                'duplikat' => 'dari TA '.($c['tahun'] ?? '…').' ',
                'impor' => 'dari cascade Perkin ',
                default => '',
            }."tidak ada di master TA {$tahun}";
            $hasil[] = [
                'kolom' => $kolom, 'label' => $label, 'kode' => $c['kode'], 'asal' => $c['asal'], 'tahun_asal' => $c['tahun'], 'jangkar' => $jangkar,
                'sebab' => $sebab, 'teks' => "{$label} {$c['kode']} {$sebab} — pilih kode pengganti.",
            ];
        }

        return $hasil;
    }

    /**
     * Catatan `kode_hilang` yang masih berlaku untuk $data: kolomnya masih kosong; yang sudah diisi lagi gugur.
     *
     * @param  array<string, mixed>  $catatan
     * @param  array<string, mixed>  $data
     * @return array<string, array{kode: string, asal: string, tahun: int|null}>
     */
    public static function sisa(array $catatan, array $data): array
    {
        $hasil = [];
        foreach ($catatan as $kolom => $c) {
            if (isset(self::KOLOM[$kolom]) && is_array($c) && filled($c['kode'] ?? null) && in_array($c['asal'] ?? null, self::ASAL, true) && blank($data[$kolom] ?? null)) {
                $hasil[$kolom] = self::catatan((string) $c['kode'], $c['asal'], isset($c['tahun']) ? (int) $c['tahun'] : null);
            }
        }

        return $hasil;
    }
}
