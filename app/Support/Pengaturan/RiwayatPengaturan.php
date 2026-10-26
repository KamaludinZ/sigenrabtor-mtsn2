<?php

namespace App\Support\Pengaturan;

use App\Contracts\RiwayatPengaturanRepository;

/**
 * Riwayat perubahan pengaturan admin (PRD N7, tabel activity_logs): daftar aksi yang dicatat beserta bagian pengaturannya,
 * dan perangkum perubahan konfigurasi ("Format: A → B; …") agar riwayat menjelaskan apa yang berubah, bukan hanya kapan.
 */
final class RiwayatPengaturan
{
    /** Aksi: kunci → [label, bagian BagianPengaturan]. */
    public const AKSI = [
        'ganti_tahun' => ['Ganti tahun aktif', 'tahun'],
        'unggah_cover' => ['Unggah cover', 'template'],
        'ubah_tata_letak_cover' => ['Ubah tata letak cover', 'template'],
        'hapus_cover' => ['Hapus cover', 'template'],
        'unggah_master' => ['Unggah master KAK/TOR', 'template'],
        'hapus_master' => ['Hapus master KAK/TOR', 'template'],
        'ubah_daftar_isi' => ['Ubah daftar isi', 'template'],
        'hapus_daftar_isi_khusus' => ['Kembalikan daftar isi ke bawaan', 'template'],
        'ubah_nomor_halaman' => ['Ubah nomor halaman', 'template'],
        'ubah_profil' => ['Ubah profil madrasah', 'madrasah'],
        'unggah_logo' => ['Unggah logo madrasah', 'madrasah'],
        'hapus_logo' => ['Hapus logo madrasah', 'madrasah'],
    ];

    /** Catat satu perubahan atas nama pengguna yang sedang masuk. */
    public static function catat(string $aksi, string $keterangan): void
    {
        app(RiwayatPengaturanRepository::class)->catat($aksi, $keterangan);
    }

    /**
     * Ringkasan perubahan dua konfigurasi: "Label: lama → baru; …" (null bila tidak ada yang berubah).
     *
     * @param  array<string, mixed>  $lama
     * @param  array<string, mixed>  $baru
     * @param  array<string, string>  $label  Kunci → label yang dirangkum (urutan = urutan ringkasan).
     */
    public static function perubahan(array $lama, array $baru, array $label): ?string
    {
        $teks = fn (mixed $nilai) => match (true) {
            is_bool($nilai) => $nilai ? 'ya' : 'tidak',
            is_array($nilai) => $nilai === [] ? '—' : implode(', ', $nilai),
            $nilai === null || $nilai === '' => '—',
            default => (string) $nilai,
        };
        $bagian = [];

        foreach ($label as $kunci => $judul) {
            if (($lama[$kunci] ?? null) !== ($baru[$kunci] ?? null)) {
                $bagian[] = "{$judul}: {$teks($lama[$kunci] ?? null)} → {$teks($baru[$kunci] ?? null)}";
            }
        }

        return $bagian === [] ? null : implode('; ', $bagian);
    }
}
