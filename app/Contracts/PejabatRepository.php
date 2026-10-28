<?php

namespace App\Contracts;

/**
 * Pejabat penanda tangan per tahun anggaran (PRD tabel pejabat_penanda_tangan, F8): peran `kepala` (Kepala Madrasah),
 * `komite` (Ketua Komite, "Mengetahui"), dan `leading` per kode leading. Tahun lain menyimpan pejabatnya sendiri.
 */
interface PejabatRepository
{
    /**
     * Pejabat tahun $tahun; yang belum diisi memakai jabatan bawaan dengan nama & NIP null.
     *
     * @return array{kepala: array{jabatan: string, nama: string|null, nip: string|null}, komite: array{jabatan: string, nama: string|null, nip: string|null}, leading: array<string, array{jabatan: string, nama: string|null, nip: string|null}>}
     */
    public function untukTahun(int $tahun): array;

    /**
     * Simpan satu pejabat. $peran `kepala`/`komite`/`leading` ($leading = kode leading bila `leading`); NIP tanpa pemisah.
     *
     * @param  array{jabatan: string, nama: string|null, nip: string|null}  $pejabat
     */
    public function simpan(int $tahun, string $peran, ?string $leading, array $pejabat): void;

    /**
     * Pejabat leading $kode yang tersimpan untuk tahun $tahun — juga untuk leading yang kini nonaktif (untukTahun hanya
     * memuat leading aktif); null bila belum pernah disimpan.
     *
     * @return array{jabatan: string, nama: string|null, nip: string|null}|null
     */
    public function leadingTersimpan(int $tahun, string $kode): ?array;

    /**
     * Pengaturan blok "Mengetahui" (Ketua Komite) di lembar pengesahan tahun $tahun: tampil atau tidak per sumber dana
     * (kunci SumberDana). Bawaan PRD §12: tampil di dokumen DIPA maupun Komite.
     *
     * @return array<string, bool>
     */
    public function aturanMengetahui(int $tahun): array;

    /**
     * @param  array<string, bool>  $aturan  Sumber dana → tampil.
     */
    public function simpanAturanMengetahui(int $tahun, array $aturan): void;
}
