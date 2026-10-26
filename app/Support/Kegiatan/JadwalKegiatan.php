<?php

namespace App\Support\Kegiatan;

/**
 * Jadwal kegiatan KAK/TOR (PRD F10, bagian 7): tabel No, Uraian Kegiatan, Waktu Pelaksanaan — 6 baris baku yang bisa
 * diedit. Waktu disimpan sebagai nomor bulan; tahunnya selalu tahun anggaran aktif saat ditampilkan/dicetak
 * (PRD aturan 3 "Satu tahun untuk semua"), jadi tidak ada lagi "Maret 2025" tertinggal di dokumen 2026.
 */
final readonly class JadwalKegiatan
{
    public const JUMLAH_BAKU = 6;

    /** Batas baris jadwal yang bisa ditambahkan leading (satu baris per bulan sudah mencakup setahun). */
    public const MAKS_BARIS = 12;

    /**
     * @param  list<array{uraian: string, bulan: list<int>}>  $baris
     */
    public function __construct(
        public array $baris,
    ) {}

    /**
     * Jadwal tersimpan di data kegiatan (kunci "jadwal"); belum pernah diisi → 6 baris baku kosong.
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function dari(array $kegiatan): self
    {
        $tersimpan = is_array($kegiatan['jadwal'] ?? null) ? array_values(array_filter($kegiatan['jadwal'], 'is_array')) : null;

        $baris = $tersimpan === null
            ? array_fill(0, self::JUMLAH_BAKU, ['uraian' => '', 'bulan' => []])
            : array_map(fn (array $b) => ['uraian' => trim((string) ($b['uraian'] ?? '')), 'bulan' => MatrikTahapan::rapikanBulan($b['bulan'] ?? [])], $tersimpan);

        return new self($baris);
    }

    /** Waktu pelaksanaan satu baris, mis. "Juli 2026" atau "Juli – Agustus 2026"; null bila bulannya belum diisi. */
    public function waktu(int $index, int $tahun): ?string
    {
        return JadwalPelaksanaan::teksBulan($this->baris[$index]['bulan'] ?? [], $tahun);
    }

    /** Baris yang uraian dan waktunya sudah terisi. */
    public function jumlahTerisi(): int
    {
        return count(array_filter($this->baris, fn (array $b) => $b['uraian'] !== '' && $b['bulan'] !== []));
    }

    /** Lengkap bila ada baris dan semuanya terisi (syarat cek kelengkapan saat cetak, PRD aturan 8). */
    public function lengkap(): bool
    {
        return $this->baris !== [] && $this->jumlahTerisi() === count($this->baris);
    }
}
