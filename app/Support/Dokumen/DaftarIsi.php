<?php

namespace App\Support\Dokumen;

use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\MasterKode;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;

/**
 * Halaman "Daftar Kegiatan" (daftar isi) buku per leading × sumber dana (PRD Fase 5 & 7, tabel daftar_isi_templates):
 * konfigurasi judul, kolom, urutan, dan baris jumlah; isinya kegiatan leading itu yang dibiayai sumber dana itu.
 * Nomor halaman sebenarnya baru diketahui saat buku digabung, jadi pratinjau menampilkan "…".
 */
final class DaftarIsi
{
    /** Kolom tabel: kunci → [label, wajib]. Urutan tampil mengikuti urutan ini. */
    public const KOLOM = [
        'no' => ['No', true],
        'kode_rktm' => ['Kode RKTM', false],
        'nama_kegiatan' => ['Nama Kegiatan', true],
        'bulan' => ['Bulan Pelaksanaan', false],
        'total' => ['Jumlah Anggaran', false],
        'halaman' => ['Halaman', true],
    ];

    public const URUTAN = [
        'kode_rktm' => 'Kode RKTM',
        'nama' => 'Nama kegiatan',
        'bulan' => 'Bulan pelaksanaan',
    ];

    /** Penanda yang bisa dipakai di subjudul. */
    public const PENANDA = ['{leading}', '{sumber}', '{tahun}'];

    public const BAWAAN = [
        'judul' => 'DAFTAR KEGIATAN',
        'subjudul' => '{leading} · Sumber Dana {sumber} · Tahun Anggaran {tahun}',
        'kolom' => ['no', 'kode_rktm', 'nama_kegiatan', 'total', 'halaman'],
        'urutan' => 'kode_rktm',
        'tampilkan_jumlah' => true,
        'catatan' => '',
    ];

    /**
     * Konfigurasi yang aman dipakai: teks dipangkas & dibatasi, kolom dikenal (wajib selalu ada, urutan baku), urutan
     * dikenal; baris jumlah hanya bila kolom Jumlah Anggaran tampil.
     *
     * @param  array<mixed>  $konfigurasi
     * @return array{judul: string, subjudul: string, kolom: list<string>, urutan: string, tampilkan_jumlah: bool, catatan: string}
     */
    public static function rapikan(array $konfigurasi): array
    {
        $teks = fn (mixed $nilai, int $maks, string $baku) => is_string($nilai) ? mb_substr(trim((string) preg_replace('/\s+/u', ' ', $nilai)), 0, $maks) : $baku;
        $dipilih = array_filter((array) ($konfigurasi['kolom'] ?? self::BAWAAN['kolom']), 'is_string');
        $kolom = array_values(array_filter(array_keys(self::KOLOM), fn (string $k) => self::KOLOM[$k][1] || in_array($k, $dipilih, true)));

        return [
            'judul' => $teks($konfigurasi['judul'] ?? null, 100, self::BAWAAN['judul']) ?: self::BAWAAN['judul'],
            'subjudul' => $teks($konfigurasi['subjudul'] ?? null, 200, self::BAWAAN['subjudul']),
            'kolom' => $kolom,
            'urutan' => array_key_exists($konfigurasi['urutan'] ?? null, self::URUTAN) ? $konfigurasi['urutan'] : self::BAWAAN['urutan'],
            'tampilkan_jumlah' => in_array('total', $kolom, true) && (bool) ($konfigurasi['tampilkan_jumlah'] ?? true),
            'catatan' => is_string($konfigurasi['catatan'] ?? null) ? mb_substr(trim($konfigurasi['catatan']), 0, 500) : '',
        ];
    }

    /** Subjudul dengan penanda {leading}, {sumber}, {tahun} terisi. */
    public static function subjudul(string $subjudul, string $leading, string $sumber): string
    {
        $unit = MasterKode::leading($leading)['nama_unit'] ?? $leading;

        return strtr($subjudul, ['{leading}' => $unit, '{sumber}' => $sumber, '{tahun}' => (string) StubData::tahunAktif()['tahun']]);
    }

    /**
     * Kegiatan satu leading yang dibiayai satu sumber dana, berurutan sesuai konfigurasi.
     *
     * @param  list<array<string, mixed>>  $semuaKegiatan
     * @return list<array{id: int, no: int, kode_rktm: string|null, nama_kegiatan: string, bulan: string|null, total: int}>
     */
    public static function baris(array $semuaKegiatan, string $leading, string $sumber, string $urutan): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $baris = [];

        foreach ($semuaKegiatan as $kegiatan) {
            $anggaran = AnggaranKegiatan::perSumber($kegiatan)[$sumber] ?? null;

            if (($kegiatan['leading'] ?? null) !== $leading || $anggaran === null) {
                continue;
            }

            $bulan = array_values(array_filter((array) ($kegiatan['bulan_pelaksanaan'] ?? []), 'is_int'));
            $baris[] = [
                'id' => (int) $kegiatan['id'],
                'kode_rktm' => $kegiatan['kode_rktm'] ?? null,
                'nama_kegiatan' => trim((string) ($kegiatan['nama_kegiatan'] ?? '')) ?: 'Kegiatan tanpa nama',
                'bulan' => JadwalPelaksanaan::teksBulan($bulan, $tahun),
                'bulan_awal' => $bulan === [] ? 13 : min($bulan),
                'total' => $anggaran->total(),
            ];
        }

        // Kode RKTM bertingkat dibandingkan per bagian (6.17.2 sebelum 6.17.12); kegiatan tanpa kode di akhir.
        $perKode = fn (array $a, array $b) => $a['kode_rktm'] === null || $b['kode_rktm'] === null
            ? ($a['kode_rktm'] === null) <=> ($b['kode_rktm'] === null)
            : version_compare($a['kode_rktm'], $b['kode_rktm']);

        usort($baris, fn (array $a, array $b) => match ($urutan) {
            'nama' => 0,
            'bulan' => $a['bulan_awal'] <=> $b['bulan_awal'],
            default => $perKode($a, $b),
        } ?: strnatcasecmp($a['nama_kegiatan'], $b['nama_kegiatan']));

        return array_map(fn (array $b, int $i) => ['no' => $i + 1] + array_diff_key($b, ['bulan_awal' => true]), $baris, array_keys($baris));
    }
}
