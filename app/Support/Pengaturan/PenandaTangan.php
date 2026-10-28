<?php

namespace App\Support\Pengaturan;

/**
 * Penanda tangan dokumen satu kegiatan (PRD F8 "Penanda tangan terikat"): pejabat yang berlaku pada tahun $tahun (bawaan
 * tahun aktif) lewat PejabatBerlaku. Kolom "Pembuat" = pejabat leading kegiatan; selama nama pejabat leading belum diisi
 * admin, memakai nama akun leading pembuat kegiatan (atau satu-satunya akun leading aktif yang tertaut ke leading itu).
 * NIP selalu dalam format cetak (Nip::format). Untuk beberapa pertanyaan pada satu dokumen, pakai satu objek
 * PejabatBerlaku::tahun() agar pejabat dibaca sekali.
 */
final class PenandaTangan
{
    /**
     * @param  array<string, mixed>  $kegiatan
     * @return array{jabatan: string, nama: string|null, nip: string|null}|null Null bila leading kegiatan belum dipilih/tidak dikenal.
     */
    public static function pembuat(array $kegiatan, ?int $tahun = null): ?array
    {
        return PejabatBerlaku::tahun($tahun)->pembuat($kegiatan);
    }

    /**
     * Penanda tangan blok "Mengetahui" (Ketua Komite) dokumen sumber dana $sumber menurut pengaturan tahun itu
     * (PejabatRepository::aturanMengetahui; bawaan DIPA & Komite); null bila blok tidak ditampilkan. Tanpa sumber dana:
     * tampil.
     *
     * @return array{jabatan: string, nama: string|null, nip: string|null}|null
     */
    public static function mengetahui(?string $sumber, ?int $tahun = null): ?array
    {
        return PejabatBerlaku::tahun($tahun)->mengetahui($sumber);
    }

    /**
     * Kepala Madrasah & Ketua Komite tahun itu dengan NIP berformat cetak.
     *
     * @return array{kepala: array{jabatan: string, nama: string|null, nip: string|null}, komite: array{jabatan: string, nama: string|null, nip: string|null}}
     */
    public static function pimpinan(?int $tahun = null): array
    {
        return PejabatBerlaku::tahun($tahun)->pimpinan();
    }
}
