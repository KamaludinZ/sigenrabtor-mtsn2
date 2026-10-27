<?php

namespace App\Support\Kegiatan;

/**
 * Keadaan tombol "Buat dengan AI" satu bagian BagianAi (komponen <x-ai.tombol-buat>): siap, menunggu (di klien lewat
 * wire:loading), ada saran, gagal (Coba lagi), nonaktif beserta alasannya, atau tautan (jadwal diusulkan di Matrik &
 * Jadwal). Urutan aturan: terkunci → nama kegiatan kosong → saran sudah ada → batas harian habis → gagal → siap.
 */
final class TombolAi
{
    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  list<array<string, string>>  $saran  Saran yang sudah diterima untuk bagian ini.
     * @return array{status: string, label: string, keterangan: string|null, jumlah: int}
     */
    public static function keadaan(string $bagian, array $kegiatan, bool $bisaUbah, int $sisa, array $saran = [], ?string $galat = null): array
    {
        $hasil = fn (string $status, string $label, ?string $keterangan = null) => ['status' => $status, 'label' => $label, 'keterangan' => $keterangan, 'jumlah' => count($saran)];

        return match (true) {
            BagianAi::BAGIAN[$bagian]['kolom'] === null => $hasil('tautan', 'Usulkan di Matrik & Jadwal', 'Usulan jadwal AI disusun dan dipakai di tab Matrik & Jadwal.'),
            ! $bisaUbah => $hasil('nonaktif', 'Buat dengan AI', 'Kegiatan terkunci — kalimatnya tidak bisa diubah.'),
            blank(trim((string) ($kegiatan['nama_kegiatan'] ?? ''))) => $hasil('nonaktif', 'Buat dengan AI', 'Isi nama kegiatan dulu di Data Kegiatan.'),
            $saran !== [] => $hasil('ada', 'Lihat saran ('.count($saran).')'),
            $sisa <= 0 => $hasil('nonaktif', 'Buat dengan AI', BatasAi::pesanHabis()),
            $galat !== null => $hasil('gagal', 'Coba lagi', $galat),
            default => $hasil('siap', 'Buat dengan AI', "Sisa {$sisa} permintaan AI hari ini."),
        };
    }
}
