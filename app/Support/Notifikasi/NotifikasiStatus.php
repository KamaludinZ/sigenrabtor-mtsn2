<?php

namespace App\Support\Notifikasi;

use App\Support\Kegiatan\TahunKegiatan;

/**
 * Notifikasi status dokumen (PRD F17–F18) yang disusun dari riwayat_status kegiatan tahun aktif: admin diberi tahu
 * aksi leading (pengajuan, penarikan, tandai final), akun leading diberi tahu keputusan admin atas kegiatan leading-nya
 * atau buatannya (disetujui, dikembalikan beserta catatan, kunci dibuka, final). Aksi oleh peran yang sama tidak
 * dikirim. Belum dibaca = terjadi sesudah penanda baca akun (NotifikasiRepository).
 */
final class NotifikasiStatus
{
    public const BATAS = 30;

    /** Aksi → [peran penerima, judul, ikon, nada]. */
    public const JENIS = [
        'ajukan' => ['admin', 'Pengajuan baru untuk diperiksa', 'check-badge', 'info'],
        'tarik' => ['admin', 'Pengajuan ditarik', 'history', 'netral'],
        'setujui' => ['leading', 'Disetujui — cetak & unggah arsip final', 'check-circle', 'baik'],
        'kembalikan' => ['leading', 'Dikembalikan untuk perbaikan', 'alert', 'peringatan'],
        'buka_kunci' => ['leading', 'Kunci dibuka untuk perbaikan', 'lock', 'peringatan'],
        'finalkan' => ['*', 'Ditandai final', 'lock', 'baik'],
    ];

    /**
     * @param  array{id: int, role: string, leadings: list<string>}  $pengguna
     * @param  list<array<string, mixed>>  $kegiatan  Semua kegiatan (disaring ke tahun $tahun dan cakupan akun).
     * @return list<array{id: string, aksi: string, judul: string, ikon: string, nada: string, kegiatan_id: int, kegiatan: string, oleh: string, pada: string, catatan: string|null, baru: bool}>
     */
    public static function untuk(array $pengguna, array $kegiatan, int $tahun, ?string $dibacaSampai): array
    {
        $batasBaca = $dibacaSampai ? strtotime($dibacaSampai) : null;
        $hasil = [];

        foreach ($kegiatan as $k) {
            if (TahunKegiatan::dari($k) !== $tahun || ! self::dalamCakupan($pengguna, $k)) {
                continue;
            }

            foreach ((array) ($k['riwayat_status'] ?? []) as $r) {
                $jenis = self::JENIS[$r['aksi'] ?? ''] ?? null;
                $peranPelaku = $r['peran'] ?? null;
                if ($jenis === null || $peranPelaku === $pengguna['role'] || ($jenis[0] !== '*' && $jenis[0] !== $pengguna['role'])) {
                    continue;
                }

                $pada = (string) ($r['pada'] ?? '');
                $hasil[] = [
                    'id' => md5($k['id'].'|'.$r['aksi'].'|'.$pada),
                    'aksi' => (string) $r['aksi'],
                    'judul' => $jenis[1],
                    'ikon' => $jenis[2],
                    'nada' => $jenis[3],
                    'kegiatan_id' => (int) $k['id'],
                    'kegiatan' => trim((string) ($k['nama_kegiatan'] ?? '')) ?: 'Kegiatan tanpa nama',
                    'oleh' => (string) ($r['oleh'] ?? ''),
                    'pada' => $pada,
                    'catatan' => filled($r['catatan'] ?? null) ? (string) $r['catatan'] : null,
                    'baru' => $batasBaca === null || strtotime($pada) > $batasBaca,
                ];
            }
        }

        usort($hasil, fn (array $a, array $b) => strtotime($b['pada']) <=> strtotime($a['pada']));

        return array_slice($hasil, 0, self::BATAS);
    }

    /** @param  list<array{baru: bool}>  $notifikasi */
    public static function jumlahBaru(array $notifikasi): int
    {
        return count(array_filter($notifikasi, fn (array $n) => $n['baru']));
    }

    /**
     * Admin: semua kegiatan; akun leading: kegiatan leading-nya atau buatannya.
     *
     * @param  array{id: int, role: string, leadings: list<string>}  $pengguna
     * @param  array<string, mixed>  $kegiatan
     */
    private static function dalamCakupan(array $pengguna, array $kegiatan): bool
    {
        return $pengguna['role'] === 'admin'
            || in_array($kegiatan['leading'] ?? null, $pengguna['leadings'], true)
            || (int) ($kegiatan['user_id'] ?? 0) === (int) $pengguna['id'];
    }
}
