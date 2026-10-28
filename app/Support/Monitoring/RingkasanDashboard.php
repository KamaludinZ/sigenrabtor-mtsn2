<?php

namespace App\Support\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\MasterKode;
use App\Support\Stub\StubData;

/**
 * Data ringkasan Dashboard utama (PRD F26, §11 "Dashboard Ringkas") — satu sumber untuk halaman Dashboard dan endpoint
 * `dashboard.data`: kegiatan tahun aktif dalam cakupan akun (admin semua leading, akun leading hanya leading-nya —
 * `cakupan()`), lalu RingkasanAnggaran: jumlah kegiatan, per status, total & per sumber dana (pagu, total RAB, selisih,
 * persen pemakaian, porsi, kelebihan, kondisi RAB), kombinasi sumber dana, dan per leading (admin: semua leading
 * termasuk yang belum punya kegiatan).
 */
final class RingkasanDashboard
{
    /**
     * Leading yang terlihat akun: null = semua (admin).
     *
     * @param  array{role: string, leadings?: list<string>}  $pengguna
     * @return list<string>|null
     */
    public static function cakupan(array $pengguna): ?array
    {
        return $pengguna['role'] === 'admin' ? null : ($pengguna['leadings'] ?? []);
    }

    /** @param  array{role: string, leadings?: list<string>}  $pengguna */
    public static function ringkasan(array $pengguna, ?int $tahun = null): RingkasanAnggaran
    {
        return RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['tahun' => $tahun ?? StubData::tahunAktif()['tahun'], 'leadings' => self::cakupan($pengguna)]);
    }

    /** "Semua leading" atau "1.6 · Tim Penjamin Mutu, …". */
    public static function namaCakupan(?array $cakupan): string
    {
        return $cakupan === null
            ? 'Semua leading'
            : implode(', ', array_map(fn (string $kode) => $kode.' · '.(MasterKode::leading($kode)['nama_unit'] ?? $kode), $cakupan));
    }

    /**
     * Baris per leading dalam cakupan (admin: semua, termasuk tanpa kegiatan).
     *
     * @param  list<string>|null  $cakupan
     * @return list<array<string, mixed>>
     */
    public static function perLeading(RingkasanAnggaran $ringkasan, ?array $cakupan): array
    {
        return array_values(array_filter($ringkasan->perLeading(), fn (array $b) => $cakupan === null || in_array($b['kode'], $cakupan, true)));
    }

    /**
     * Bentuk JSON endpoint `dashboard.data`.
     *
     * @param  array{role: string, leadings?: list<string>}  $pengguna
     * @return array<string, mixed>
     */
    public static function untuk(array $pengguna): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $cakupan = self::cakupan($pengguna);
        $ringkasan = self::ringkasan($pengguna, $tahun);

        return [
            'tahun' => $tahun,
            'cakupan' => ['semua' => $cakupan === null, 'leading' => $cakupan, 'nama' => self::namaCakupan($cakupan)],
            'jumlah_kegiatan' => $ringkasan->jumlahKegiatan(),
            'per_status' => array_map(
                fn (string $status, int $jumlah) => ['status' => $status, 'label' => StatusKegiatan::from($status)->label(), 'jumlah' => $jumlah],
                array_keys($ringkasan->perStatus()), $ringkasan->perStatus(),
            ),
            'total' => RingkasanAnggaran::sel($ringkasan->total()),
            'per_sumber' => $ringkasan->perbandinganSumber(),
            'kombinasi_sumber' => $ringkasan->kombinasiSumber(),
            'per_leading' => array_map(fn (array $b) => [
                'kode' => $b['kode'], 'nama_unit' => $b['nama_unit'], 'jumlah' => $b['jumlah'], 'per_status' => $b['perStatus'],
                'pagu' => $b['pagu'], 'total' => $b['total'], 'rab' => $b['rab'], 'melebihi' => $b['melebihi'], 'tanpa_pagu' => $b['tanpa_pagu'],
            ], self::perLeading($ringkasan, $cakupan)),
        ];
    }
}
