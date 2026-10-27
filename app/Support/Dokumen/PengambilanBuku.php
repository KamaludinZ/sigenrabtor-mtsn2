<?php

namespace App\Support\Dokumen;

/**
 * Pengambilan kegiatan untuk menyusun buku (PRD §18 & antrean cetak massal): tiap kegiatan diambil satu per satu,
 * KAK/TOR & RAB sumber dana buku disusun, lalu dicatat siap atau masih DRAF beserta alasannya. Tahap frontend
 * menjalankannya bertahap dari klien (seperti impor bertahap); backend menggantinya dengan antrean.
 */
final class PengambilanBuku
{
    /** Kegiatan yang diambil per langkah. */
    public const PER_LANGKAH = 1;

    /**
     * @param  array<string, mixed>  $kegiatan
     * @return array{id: int, nama: string, halaman: int|null, hasil: string, pesan: string}
     */
    public static function ambil(array $kegiatan, string $sumber, ?int $halaman): array
    {
        $status = StatusDraf::gabung(SusunanKak::dari($kegiatan, $sumber)->statusDraf, SusunanRab::dari($kegiatan, $sumber)->statusDraf);

        return [
            'id' => (int) $kegiatan['id'],
            'nama' => trim((string) ($kegiatan['nama_kegiatan'] ?? '')) ?: 'Kegiatan tanpa nama',
            'halaman' => $halaman,
            'hasil' => $status->draf ? 'draf' : 'siap',
            'pesan' => $status->draf ? $status->alasan[0]['teks'] : 'KAK/TOR & RAB siap dicetak.',
        ];
    }
}
