<?php

namespace App\Support\Akun;

use App\Contracts\PenggunaRepository;
use App\Support\MasterKode;
use App\Support\Pengaturan\Nip;

/**
 * Data akun yang sedang masuk untuk halaman Masuk & Akun (PRD §8: akun dibuat/diaktifkan admin, peran admin/leading,
 * kelola profil & sandi) — dibaca dari PenggunaRepository (tiruan selama autentikasi belum nyata):
 * - `untuk($pengguna)` = profil {id, nama, surel, jabatan, nip + nip_format, peran + label, leading tertaut (kode, nama unit,
 *   aktif), aktif, terakhir_masuk, dibuat_pada} + `riwayat_masuk` terbaru (BATAS_RIWAYAT) — null bila akun tidak tercatat;
 * - `persona()` = akun contoh yang bisa dipakai masuk di mode demo (PERSONA: id → peran demo) beserta surelnya;
 * - `cariSurel($surel)` = akun bersurel itu (tanpa beda huruf besar).
 */
final class DataAkun
{
    public const BATAS_RIWAYAT = 10;

    /** Akun contoh yang dipakai mode demo (StubData::currentUser) → peran. */
    public const PERSONA = [1 => 'leading', 2 => 'admin'];

    public const LABEL_PERAN = PeranAkun::LABEL;

    /**
     * @param  array{id?: int|null}  $pengguna
     * @return array<string, mixed>|null
     */
    public static function untuk(array $pengguna): ?array
    {
        $repo = app(PenggunaRepository::class);
        $akun = isset($pengguna['id']) ? $repo->temukan((int) $pengguna['id']) : null;
        if ($akun === null) {
            return null;
        }
        $peran = (string) $akun['role'];

        return [
            'id' => (int) $akun['id'],
            'nama' => (string) $akun['name'],
            'surel' => (string) $akun['email'],
            'jabatan' => $akun['jabatan'] ?? null,
            'nip' => $akun['nip'] ?? null,
            'nip_format' => filled($akun['nip'] ?? null) ? Nip::format((string) $akun['nip']) : null,
            'peran' => $peran,
            'label_peran' => self::LABEL_PERAN[$peran] ?? $peran,
            'leading' => array_map(fn (string $kode) => [
                'kode' => $kode,
                'nama_unit' => MasterKode::leading($kode)['nama_unit'] ?? '',
                'aktif' => (bool) (MasterKode::leading($kode)['is_active'] ?? false),
            ], $akun['leadings'] ?? []),
            'aktif' => (bool) ($akun['is_active'] ?? true),
            'terakhir_masuk' => $akun['terakhir_masuk'] ?? null,
            'dibuat_pada' => $akun['dibuat_pada'] ?? null,
            'riwayat_masuk' => $repo->riwayatMasuk((int) $akun['id'], self::BATAS_RIWAYAT),
        ];
    }

    /** @return list<array{id: int, peran: string, nama: string, surel: string, jabatan: string|null}> */
    public static function persona(): array
    {
        $repo = app(PenggunaRepository::class);
        $hasil = [];
        foreach (self::PERSONA as $id => $peran) {
            if (($akun = $repo->temukan($id)) !== null) {
                $hasil[] = ['id' => $id, 'peran' => $peran, 'nama' => (string) $akun['name'], 'surel' => (string) $akun['email'], 'jabatan' => $akun['jabatan'] ?? null];
            }
        }

        return $hasil;
    }

    /** @return array<string, mixed>|null */
    public static function cariSurel(string $surel): ?array
    {
        $surel = mb_strtolower(trim($surel));

        return collect(app(PenggunaRepository::class)->semua())->first(fn (array $a) => mb_strtolower((string) $a['email']) === $surel);
    }
}
