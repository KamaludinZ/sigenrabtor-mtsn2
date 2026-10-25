<?php

namespace App\Contracts;

/**
 * Penyimpanan data kegiatan. Tahap frontend memakai implementasi tiruan (file JSON);
 * backend menggantinya dengan Eloquent tanpa mengubah komponen yang memakainya.
 */
interface KegiatanRepository
{
    /** Kolom teks yang riwayat isiannya boleh dibaca lewat riwayatIsian(). */
    public const KOLOM_RIWAYAT = ['tempat', 'peserta_jenis'];

    /**
     * Simpan data kegiatan; $id null berarti kegiatan baru. Mengembalikan id kegiatan.
     *
     * @param  array<string, mixed>  $data
     */
    public function simpan(array $data, ?int $id = null): int;

    /**
     * @return array<string, mixed>|null Data kegiatan + kunci id, status, diperbarui_pada.
     */
    public function temukan(int $id): ?array;

    /**
     * Ringkasan kegiatan terbaru lebih dulu.
     *
     * @return list<array{id: int, nama_kegiatan: string, status: string, diperbarui_pada: string}>
     */
    public function terbaru(int $batas = 10): array;

    /**
     * Seluruh kegiatan (data lengkap), terakhir diperbarui lebih dulu — untuk halaman Daftar Kegiatan dan cek nama ganda.
     *
     * @return list<array<string, mixed>>
     */
    public function semua(): array;

    /**
     * Nilai berbeda yang pernah diisi pada satu kolom (lihat KOLOM_RIWAYAT), dari kegiatan yang terakhir
     * diperbarui lebih dulu. Kosong dilewati; duplikat beda huruf besar/kecil dihitung satu.
     *
     * @return list<string>
     *
     * @throws \InvalidArgumentException Kolom di luar KOLOM_RIWAYAT.
     */
    public function riwayatIsian(string $kolom, int $batas = 20): array;
}
