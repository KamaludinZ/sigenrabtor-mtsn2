<?php

namespace App\Support\Kegiatan\Duplikat;

use App\Contracts\TahunAnggaranRepository;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Kegiatan\Impor\PemeriksaImporKegiatan;
use App\Support\Kegiatan\JadwalPelaksanaan;
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
     * Kegiatan tahun $tahun dalam cakupan akun, sebagai baris pilihan: identitas, kode RKTM, sumber dana & jumlah rincian
     * RAB, bulan, ringkasan isian, dan `sudah_ada` (id kegiatan tahun aktif bernama sama — tidak bisa diduplikat lagi).
     *
     * @param  list<array<string, mixed>>  $kegiatan  Semua kegiatan (semua tahun).
     * @param  array{role: string, leadings: list<string>}  $pengguna
     * @return list<array<string, mixed>>
     */
    public static function sumber(array $kegiatan, int $tahun, array $pengguna): array
    {
        $tahunAktif = StubData::tahunAktif()['tahun'];
        $aktif = [];
        foreach ($kegiatan as $k) {
            if (TahunKegiatan::dari($k) === $tahunAktif) {
                $aktif[PemeriksaImporKegiatan::kunciNama((string) ($k['nama_kegiatan'] ?? ''))] ??= (int) $k['id'];
            }
        }

        $baris = [];
        foreach ($kegiatan as $k) {
            if (TahunKegiatan::dari($k) !== $tahun || ($pengguna['role'] !== 'admin' && ! in_array($k['leading'] ?? null, $pengguna['leadings'], true))) {
                continue;
            }
            $anggaran = AnggaranKegiatan::untuk($k);
            $nama = trim((string) ($k['nama_kegiatan'] ?? '')) ?: 'Kegiatan tanpa nama';
            $pelaksanaan = DataPelaksanaan::dari($k);
            $baris[] = [
                'id' => (int) $k['id'],
                'nama' => $nama,
                'leading' => $k['leading'] ?? null,
                'nama_leading' => MasterKode::leading($k['leading'] ?? null)['nama_unit'] ?? null,
                'kode_rktm' => $k['kode_rktm'] ?? null,
                'sumber' => array_map(fn ($a) => ['sumber' => $a->sumber, 'total' => $a->total(), 'item' => $a->jumlahItem()], $anggaran),
                'total' => array_sum(array_map(fn ($a) => $a->total(), $anggaran)),
                'bulan' => JadwalPelaksanaan::teksBulan($pelaksanaan->bulan, $tahun),
                'tujuan' => trim((string) ($k['tujuan'] ?? '')) ?: null,
                'tempat' => $pelaksanaan->tempat ?: null,
                'peserta' => $pelaksanaan->pesertaJumlah ? $pelaksanaan->pesertaJumlah.' orang'.($pelaksanaan->pesertaJenis ? " ({$pelaksanaan->pesertaJenis})" : '') : ($pelaksanaan->pesertaJenis ?: null),
                'lama_hari' => $pelaksanaan->lamaHari,
                'status' => (string) ($k['status'] ?? 'draf'),
                'sudah_ada' => $aktif[PemeriksaImporKegiatan::kunciNama($nama)] ?? null,
            ];
        }
        usort($baris, fn (array $a, array $b) => [$a['leading'] ?? '~', $a['nama']] <=> [$b['leading'] ?? '~', $b['nama']]);

        return $baris;
    }
}
