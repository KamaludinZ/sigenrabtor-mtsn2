<?php

namespace App\Support\Akun;

use App\Contracts\PenggunaRepository;
use App\Support\Pengaturan\RiwayatPengaturan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Atur ulang sandi akun oleh admin ("Lupa sandi? Hubungi admin", PRD §8) — satu-satunya jalur admin mengganti sandi akun
 * lain: hak `kelola_akun` (HakAkses), bukan akun sendiri (galat `akun` — ganti lewat Akun saya), akun tidak ada →
 * RuntimeException `PESAN_TIDAK_ADA`. `aturUlang()` membuat sandi sementara `sandiSementara()` (`PANJANG` karakter dari
 * `HURUF` tanpa huruf mirip, selalu memuat huruf & angka — lolos syarat PendaftaranAkun bagi akun mana pun), menyimpan
 * hash-nya (`PenggunaRepository::gantiSandi`; cookie "ingat saya" lama akun itu ikut gugur), dan mencatat riwayat
 * `atur_ulang_sandi` tanpa sandinya. Sandi sementara hanya dikembalikan sekali untuk disampaikan admin ke pemilik akun.
 */
final class AturUlangSandi
{
    public const PANJANG = 12;

    public const HURUF = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const PESAN_TIDAK_ADA = 'Akun tidak ditemukan.';

    /**
     * @param  array{id?: int|null, role: string}  $pengguna
     * @return array{sandi: string, akun: array{id: int, nama: string, surel: string}}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws RuntimeException
     */
    public static function aturUlang(int $akunId, array $pengguna): array
    {
        HakAkses::pastikan($pengguna, 'kelola_akun');
        if ((int) ($pengguna['id'] ?? 0) === $akunId) {
            throw ValidationException::withMessages(['akun' => 'Sandi akun Anda sendiri diganti lewat Akun saya › Sandi.']);
        }
        $repo = app(PenggunaRepository::class);
        $akun = $repo->temukan($akunId) ?? throw new RuntimeException(self::PESAN_TIDAK_ADA);
        $sandi = self::sandiSementara((string) $akun['name'], (string) $akun['email']);
        $repo->gantiSandi($akunId, Hash::make($sandi));
        RiwayatPengaturan::catat('atur_ulang_sandi', "Sandi akun {$akun['name']} ({$akun['email']}) diatur ulang — sandi sementara diberikan admin.");

        return ['sandi' => $sandi, 'akun' => ['id' => $akunId, 'nama' => (string) $akun['name'], 'surel' => (string) $akun['email']]];
    }

    /** Sandi acak yang memenuhi semua syarat sandi untuk nama & surel itu. */
    public static function sandiSementara(string $nama = '', string $surel = ''): string
    {
        do {
            $sandi = '';
            for ($i = 0; $i < self::PANJANG; $i++) {
                $sandi .= self::HURUF[random_int(0, strlen(self::HURUF) - 1)];
            }
        } while (in_array(false, PendaftaranAkun::syaratSandi($sandi, $nama, $surel), true));

        return $sandi;
    }
}
