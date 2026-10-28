<?php

namespace App\Support\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;

/**
 * Kontrol tahun anggaran aktif yang sentral (PRD F6 & aturan 3 "Satu tahun untuk semua"): apa saja yang mengikuti
 * tahun aktif beserta kesiapannya, pilihan tahun lain, dan satu-satunya jalur mengganti tahun aktif (dipakai halaman
 * Pengaturan › Tahun Anggaran maupun kontrol tahun di bilah atas).
 */
final class KontrolTahunAktif
{
    /**
     * Bagian yang mengikuti tahun aktif → [judul, keterangan ({tahun} diisi), butir KesiapanPengaturan yang menentukan
     * kesiapannya].
     */
    public const DIPAKAI = [
        'dokumen' => ['Cover & judul dokumen', 'Cover, judul KAK/TOR & RAB, dan sebutan sumber dana “… Tahun {tahun}”.', []],
        'dasar_hukum' => ['Dasar hukum & latar belakang', 'Butir dasar hukum, SK RKTM/RKAM, dan paragraf baku TA {tahun}.', ['dasar_hukum', 'sk_rktm']],
        'jadwal' => ['Jadwal, matrik & tanggal', 'Bulan pelaksanaan, matrik tahapan, jadwal, dan tanggal dokumen di tahun {tahun}.', []],
        'pejabat' => ['Penanda tangan', 'Kepala Madrasah, Ketua Komite, dan pejabat leading TA {tahun}.', ['pejabat']],
        'master' => ['Master kode', 'Pilihan SK, IKSK, RKTM, EDM, dan 5 Budaya Mutu TA {tahun}.', ['master']],
        'data' => ['Kegiatan, pagu & monitoring', 'Data Kegiatan, Data Pagu, Dashboard, Pemeriksaan, dan Monitoring TA {tahun}.', []],
    ];

    /**
     * Daftar DIPAKAI untuk tahun aktif dengan status siap (null = selalu mengikuti, tanpa isian).
     *
     * @return list<array{kunci: string, judul: string, keterangan: string, siap: bool|null, catatan: string|null}>
     */
    public static function dipakai(): array
    {
        $kesiapan = KesiapanPengaturan::tahunAktif();
        $butir = collect($kesiapan->butir)->keyBy('kunci');

        return array_map(function (string $kunci) use ($kesiapan, $butir) {
            [$judul, $keterangan, $syarat] = self::DIPAKAI[$kunci];
            $belum = array_values(array_filter($syarat, fn (string $s) => ! ($butir[$s]['siap'] ?? true)));

            return [
                'kunci' => $kunci,
                'judul' => $judul,
                'keterangan' => str_replace('{tahun}', (string) $kesiapan->tahun, $keterangan),
                'siap' => $syarat === [] ? null : $belum === [],
                'catatan' => $belum === [] ? null : implode(' ', array_map(fn (string $s) => $butir[$s]['keterangan'], $belum)),
            ];
        }, array_keys(self::DIPAKAI));
    }

    /**
     * Semua tahun anggaran (terbaru dulu) dengan status master kode & jumlah kegiatannya.
     *
     * @return list<array{tahun: int, is_active: bool, is_locked: bool, master_tersedia: bool, jumlah_kegiatan: int, diaktifkan_oleh: string|null, diaktifkan_pada: string|null}>
     */
    public static function pilihan(): array
    {
        $kegiatan = app(KegiatanRepository::class)->semua();

        return array_map(fn (array $t) => [
            'tahun' => $t['tahun'],
            'is_active' => $t['is_active'],
            'is_locked' => $t['is_locked'],
            'master_tersedia' => MasterKode::tersedia($t['tahun']),
            'jumlah_kegiatan' => count(array_filter($kegiatan, fn (array $k) => TahunKegiatan::dari($k) === $t['tahun'])),
            'diaktifkan_oleh' => $t['diaktifkan_oleh'] ?? null,
            'diaktifkan_pada' => $t['diaktifkan_pada'] ?? null,
        ], app(TahunAnggaranRepository::class)->semua());
    }

    /**
     * Aktifkan $tahun (konfirmasi dilakukan pemanggil): simpan, catat riwayat, dan siapkan notifikasi untuk halaman
     * berikutnya. $kunciSebelumnya sekaligus mengunci tahun yang sebelumnya aktif (bila belum terkunci) — "tahun lalu
     * dikunci" (PRD F6) dalam satu langkah, tercatat di keterangan riwayat yang sama. Mengembalikan false bila tahun itu
     * tidak ada atau sudah aktif.
     *
     * @param  array{name: string}  $pengguna
     */
    public static function aktifkan(int $tahun, array $pengguna, bool $kunciSebelumnya = false): bool
    {
        $repo = app(TahunAnggaranRepository::class);
        $daftar = collect($repo->semua());
        $dipilih = $daftar->firstWhere('tahun', $tahun);
        if ($dipilih === null || $dipilih['is_active']) {
            return false;
        }

        $sebelumnya = $daftar->firstWhere('is_active', true);
        $repo->aktifkan($tahun, $pengguna['name']);
        $dikunci = $kunciSebelumnya && $sebelumnya !== null && ! $sebelumnya['is_locked'];
        if ($dikunci) {
            $repo->kunci($sebelumnya['tahun'], true);
        }
        RiwayatPengaturan::catat('ganti_tahun', "Tahun Anggaran {$tahun} diaktifkan"
            .($sebelumnya ? " (sebelumnya {$sebelumnya['tahun']})" : '')
            .($dikunci ? "; {$sebelumnya['tahun']} dikunci" : '').'.');
        session()->flash('notify', ['message' => "Tahun Anggaran {$tahun} aktif. Seluruh dokumen kini memakai tahun {$tahun}."
            .($dikunci ? " Tahun {$sebelumnya['tahun']} dikunci — datanya hanya bisa dilihat." : ''), 'type' => 'success']);

        return true;
    }
}
