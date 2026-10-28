<?php

namespace App\Support\Pemeriksaan;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Stub\StubData;
use Carbon\CarbonImmutable;

/**
 * Daftar kegiatan untuk admin (PRD Fase 6 §16, F17) — satu aturan untuk halaman Pemeriksaan (`Pemeriksaan\Daftar`) dan
 * endpoint JSON admin `pemeriksaan.data`: kegiatan tahun aktif (TahunKegiatan) dalam baris AntreanPemeriksaan, saringan
 * `status` (tab AntreanPemeriksaan::TAB, bawaan `diperiksa`), `cari` (nama kegiatan atau pembuat, tanpa beda huruf) dan
 * `leading` (kode leading yang dikenal, termasuk nonaktif); urutan per tab dari AntreanPemeriksaan::untukTab; halaman
 * `hal` berisi PER_HALAMAN baris.
 */
final class DaftarPemeriksaan
{
    public const PER_HALAMAN = 50;

    /**
     * Nilai saringan yang rapi (nilai asing kembali ke bawaan).
     *
     * @param  array<string, mixed>  $nilai
     * @return array{status: string, cari: string, leading: string, hal: int}
     */
    public static function saringan(array $nilai): array
    {
        $status = (string) ($nilai['status'] ?? '');
        $leading = (string) ($nilai['leading'] ?? '');

        return [
            'status' => array_key_exists($status, AntreanPemeriksaan::TAB) ? $status : 'diperiksa',
            'cari' => mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) ($nilai['cari'] ?? ''))), 0, 100),
            'leading' => MasterKode::leading($leading) !== null ? $leading : '',
            'hal' => max(1, (int) filter_var($nilai['hal'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]])),
        ];
    }

    /**
     * Baris semua kegiatan tahun $tahun (null = tahun aktif).
     *
     * @return list<array<string, mixed>>
     */
    public static function semua(?int $tahun = null): array
    {
        $tahun ??= StubData::tahunAktif()['tahun'];

        return AntreanPemeriksaan::baris(array_values(array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => TahunKegiatan::dari($k) === $tahun)));
    }

    /**
     * Baris tab `status` sesudah cari & saringan leading, terurut seperti antreannya.
     *
     * @param  list<array<string, mixed>>  $baris  Hasil semua().
     * @param  array{status: string, cari: string, leading: string}  $saringan
     * @return list<array<string, mixed>>
     */
    public static function saring(array $baris, array $saringan): array
    {
        $cari = mb_strtolower(trim($saringan['cari']));

        return array_values(array_filter(
            AntreanPemeriksaan::untukTab($baris, $saringan['status']),
            fn (array $b) => ($cari === '' || str_contains(mb_strtolower($b['nama'].' '.($b['pembuat'] ?? '')), $cari))
                && ($saringan['leading'] === '' || $b['leading'] === $saringan['leading']),
        ));
    }

    /**
     * Data endpoint: tab berjumlah, baris halaman yang diminta (bentuk JSON), dan total baris tersaring.
     *
     * @param  array<string, mixed>  $nilai  Parameter URL (status, cari, leading, hal).
     * @return array<string, mixed>
     */
    public static function untuk(array $nilai): array
    {
        $saringan = self::saringan($nilai);
        $semua = self::semua();
        $jumlah = AntreanPemeriksaan::jumlah($semua);
        $tersaring = self::saring($semua, $saringan);
        $halaman = max(1, (int) ceil(count($tersaring) / self::PER_HALAMAN));
        $saringan['hal'] = min($saringan['hal'], $halaman);

        return [
            'tahun' => StubData::tahunAktif()['tahun'],
            'saringan' => $saringan,
            'tab' => array_map(fn (string $kunci, string $label) => ['kunci' => $kunci, 'label' => $label, 'keterangan' => AntreanPemeriksaan::KETERANGAN[$kunci], 'jumlah' => $jumlah[$kunci]], array_keys(AntreanPemeriksaan::TAB), AntreanPemeriksaan::TAB),
            'jumlah' => count($tersaring),
            'halaman' => ['ke' => $saringan['hal'], 'dari' => $halaman, 'per' => self::PER_HALAMAN],
            'baris' => array_map(self::json(...), array_slice($tersaring, ($saringan['hal'] - 1) * self::PER_HALAMAN, self::PER_HALAMAN)),
        ];
    }

    /**
     * @param  array<string, mixed>  $b  Baris AntreanPemeriksaan.
     * @return array<string, mixed>
     */
    private static function json(array $b): array
    {
        $diajukan = $b['diajukan']['pada'] ?? null;

        return [
            'id' => $b['id'],
            'nama' => $b['nama'],
            'leading' => $b['leading'],
            'nama_leading' => $b['nama_leading'],
            'pembuat' => $b['pembuat'],
            'status' => ['kunci' => $b['status']->value, 'label' => $b['status']->label()],
            'diajukan' => $b['diajukan'],
            'menunggu_hari' => $b['status']->value === 'diperiksa' && $diajukan !== null ? (int) CarbonImmutable::parse($diajukan)->startOfDay()->diffInDays(CarbonImmutable::now()->startOfDay()) : null,
            'terakhir' => $b['terakhir'],
            'anggaran' => array_map(fn (array $a) => ['sumber' => $a['sumber'], 'total' => $a['total'], 'status_pagu' => $a['pagu']->status, 'selisih' => $a['pagu']->selisih, 'keterangan_pagu' => $a['pagu']->teks()], $b['anggaran']),
            'kurang' => $b['kurang'],
            'tautan' => route('pemeriksaan.periksa', ['kegiatan' => $b['id']]),
        ];
    }
}
