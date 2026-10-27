<?php

namespace App\Support\Kegiatan\Duplikat;

use App\Contracts\TahunAnggaranRepository;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;

/**
 * Duplikat kegiatan tahun lalu menjadi draf tahun aktif (PRD §19, F22): sumber = kegiatan tahun anggaran lain dalam
 * cakupan akun (admin semua, akun leading leading-nya), dengan daftar hal yang diperbarui otomatis saat disalin.
 */
final class DuplikatKegiatan
{
    /** Hal yang berubah saat kegiatan diduplikat → [judul, keterangan] ({tahun} = tahun aktif). */
    public const PERUBAHAN = [
        'tahun' => ['Tahun anggaran', 'Menjadi TA {tahun}; cover, judul, dasar hukum, dan tanggal mengikuti tahun itu.'],
        'status' => ['Status', 'Dimulai sebagai Draf — riwayat pemeriksaan, arsip final, dan kunci tidak ikut.'],
        'pejabat' => ['Penanda tangan', 'Memakai pejabat TA {tahun} (Kepala, Ketua Komite, pejabat leading).'],
        'kode' => ['Kode master', 'Dicocokkan ke master TA {tahun}; kode yang tidak ada lagi dikosongkan untuk dipilih ulang.'],
        'jadwal' => ['Tanggal & jadwal', 'Tanggal mulai, laporan, dan dokumen digeser ke tahun {tahun}; bulan jadwal & matrik tetap.'],
        'anggaran' => ['Pagu & RAB', 'Rincian RAB disalin; pagu diambil dari Data Pagu TA {tahun} bila namanya sama.'],
    ];

    /**
     * Tahun anggaran yang bisa menjadi sumber duplikat (selain tahun aktif), terbaru dulu.
     *
     * @return list<int>
     */
    public static function tahunSumber(): array
    {
        $aktif = StubData::tahunAktif()['tahun'];

        return array_values(array_filter(array_column(app(TahunAnggaranRepository::class)->semua(), 'tahun'), fn (int $t) => $t !== $aktif));
    }

    /**
     * Kegiatan tahun $tahun dalam cakupan akun, sebagai baris pilihan.
     *
     * @param  list<array<string, mixed>>  $kegiatan
     * @param  array{role: string, leadings: list<string>}  $pengguna
     * @return list<array{id: int, nama: string, leading: string|null, nama_leading: string|null, sumber: list<string>, total: int, status: string}>
     */
    public static function sumber(array $kegiatan, int $tahun, array $pengguna): array
    {
        $baris = [];
        foreach ($kegiatan as $k) {
            if (TahunKegiatan::dari($k) !== $tahun || ($pengguna['role'] !== 'admin' && ! in_array($k['leading'] ?? null, $pengguna['leadings'], true))) {
                continue;
            }
            $anggaran = AnggaranKegiatan::untuk($k);
            $baris[] = [
                'id' => (int) $k['id'],
                'nama' => trim((string) ($k['nama_kegiatan'] ?? '')) ?: 'Kegiatan tanpa nama',
                'leading' => $k['leading'] ?? null,
                'nama_leading' => MasterKode::leading($k['leading'] ?? null)['nama_unit'] ?? null,
                'sumber' => array_map(fn ($a) => $a->sumber, $anggaran),
                'total' => array_sum(array_map(fn ($a) => $a->total(), $anggaran)),
                'status' => (string) ($k['status'] ?? 'draf'),
            ];
        }
        usort($baris, fn (array $a, array $b) => [$a['leading'] ?? '~', $a['nama']] <=> [$b['leading'] ?? '~', $b['nama']]);

        return $baris;
    }
}
