<?php

namespace App\Support\Pengaturan;

use App\Contracts\PejabatRepository;
use App\Support\MasterKode;
use App\Support\Stub\StubData;

/**
 * Penanda tangan dokumen satu kegiatan (PRD F8 "Penanda tangan terikat"): pejabat yang berlaku pada tahun aktif dari
 * PejabatRepository. Kolom "Pembuat" = pejabat leading kegiatan; selama nama pejabat leading belum diisi admin, memakai
 * nama akun leading yang memegang leading itu (sedang masuk). NIP selalu dalam format cetak (Nip::format).
 */
final class PenandaTangan
{
    /**
     * @param  array<string, mixed>  $kegiatan
     * @return array{jabatan: string, nama: string|null, nip: string|null}|null Null bila leading kegiatan belum dipilih/tidak dikenal.
     */
    public static function pembuat(array $kegiatan): ?array
    {
        $kode = $kegiatan['leading'] ?? null;
        $leading = MasterKode::cari(MasterKode::leadings(), $kode);
        if ($leading === null) {
            return null;
        }

        $pejabat = StubData::pejabatLeading()[$kode] ?? ['jabatan' => $leading['jabatan_penanda_tangan'], 'nama' => null, 'nip' => null];
        if ($pejabat['nama']) {
            return ['jabatan' => $pejabat['jabatan'], 'nama' => $pejabat['nama'], 'nip' => $pejabat['nip'] ? Nip::format($pejabat['nip']) : null];
        }

        $pengguna = StubData::currentUser();
        $akunLeading = $pengguna['role'] === 'leading' && in_array($kode, $pengguna['leadings'], true);

        return ['jabatan' => $pejabat['jabatan'], 'nama' => $akunLeading ? $pengguna['name'] : null, 'nip' => null];
    }

    /**
     * Penanda tangan blok "Mengetahui" (Ketua Komite) dokumen sumber dana $sumber menurut pengaturan tahun aktif
     * (PejabatRepository::aturanMengetahui; bawaan DIPA & Komite); null bila blok tidak ditampilkan. Tanpa sumber dana:
     * tampil.
     *
     * @return array{jabatan: string, nama: string|null, nip: string|null}|null
     */
    public static function mengetahui(?string $sumber): ?array
    {
        $aturan = app(PejabatRepository::class)->aturanMengetahui(StubData::tahunAktif()['tahun']);

        return $sumber === null || ($aturan[$sumber] ?? true) ? self::pimpinan()['komite'] : null;
    }

    /**
     * Kepala Madrasah & Ketua Komite tahun aktif dengan NIP berformat cetak.
     *
     * @return array{kepala: array{jabatan: string, nama: string|null, nip: string|null}, komite: array{jabatan: string, nama: string|null, nip: string|null}}
     */
    public static function pimpinan(): array
    {
        return array_map(fn (array $p) => ['nip' => $p['nip'] ? Nip::format($p['nip']) : null] + $p, StubData::pejabat());
    }
}
