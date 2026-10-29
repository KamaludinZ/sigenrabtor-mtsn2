<?php

namespace App\Support\Akun;

use App\Contracts\PenggunaRepository;
use App\Support\Pengaturan\AturanAkun;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Pengaturan\RiwayatPengaturan;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Ubah profil akun sendiri (PRD §8 "Kelola Profil & Sandi": ubah nama & jabatan) — satu-satunya jalur pengguna mengubah
 * profilnya (surel, peran, NIP, dan tautan leading tetap diatur admin lewat KelolaAkun):
 * - `ISIAN` nama & jabatan; `rapikan()` (satu baris berspasi tunggal, `AturanAkun::rapikan`), `galat()` (nama wajib,
 *   berhuruf, ≤ `AturanAkun::MAKS_NAMA`; jabatan ≤ `MAKS_JABATAN`);
 * - `versi($akun)` = sidik nama & jabatan tersimpan — `simpan()` menolak bila berubah di tempat lain (mis. admin
 *   mengubahnya) → `PengaturanBerubah` `PESAN_BENTROK`;
 * - `simpan($pengguna, $isian, $versi)` → {akun, ringkasan|null, versi}: tanpa perubahan tidak ditulis; selain itu
 *   `PenggunaRepository::simpan` (hanya name & jabatan) + riwayat `ubah_profil_akun` ("Nama: lama → baru").
 */
final class UbahProfil
{
    public const ISIAN = ['nama' => 'Nama', 'jabatan' => 'Jabatan'];

    public const PESAN_BENTROK = 'Profil Anda baru saja diubah di tempat lain (mis. oleh admin) — periksa data terbaru, lalu simpan lagi.';

    /** @param  array<string, mixed>  $isian
     * @return array{nama: string, jabatan: string} */
    public static function rapikan(array $isian): array
    {
        return [
            'nama' => AturanAkun::rapikan((string) ($isian['nama'] ?? '')),
            'jabatan' => AturanAkun::rapikan((string) ($isian['jabatan'] ?? '')),
        ];
    }

    /** @param  array{nama: string, jabatan: string}  $isian
     * @return array<string, string> */
    public static function galat(array $isian): array
    {
        return array_filter([
            'nama' => match (true) {
                $isian['nama'] === '' => 'Nama lengkap wajib diisi.',
                mb_strlen($isian['nama']) > AturanAkun::MAKS_NAMA => 'Terlalu panjang (maksimal '.AturanAkun::MAKS_NAMA.' karakter).',
                preg_match('/\p{L}/u', $isian['nama']) !== 1 => 'Nama harus berisi huruf.',
                default => null,
            },
            'jabatan' => mb_strlen($isian['jabatan']) > AturanAkun::MAKS_JABATAN ? 'Terlalu panjang (maksimal '.AturanAkun::MAKS_JABATAN.' karakter).' : null,
        ]);
    }

    /** @param  array<string, mixed>  $akun */
    public static function versi(array $akun): string
    {
        return md5(json_encode([(string) ($akun['name'] ?? ''), (string) ($akun['jabatan'] ?? '')]));
    }

    /**
     * @param  array{id: int|string}  $pengguna
     * @param  array<string, mixed>  $isian
     * @return array{akun: array<string, mixed>, ringkasan: string|null, versi: string}
     *
     * @throws ValidationException
     * @throws PengaturanBerubah
     * @throws RuntimeException Akun tidak tercatat / gagal disimpan.
     */
    public static function simpan(array $pengguna, array $isian, ?string $versi = null): array
    {
        $repo = app(PenggunaRepository::class);
        $akun = $repo->temukan((int) $pengguna['id']) ?? throw new RuntimeException('Akun Anda belum tercatat — hubungi admin.');
        $isian = self::rapikan($isian);
        if (($galat = self::galat($isian)) !== []) {
            throw ValidationException::withMessages($galat);
        }
        if ($versi !== null && $versi !== self::versi($akun)) {
            throw new PengaturanBerubah(self::PESAN_BENTROK);
        }
        $lama = ['nama' => (string) $akun['name'], 'jabatan' => (string) ($akun['jabatan'] ?? '')];
        $ringkasan = RiwayatPengaturan::perubahan($lama, $isian, self::ISIAN);
        if ($ringkasan === null) {
            return ['akun' => $akun, 'ringkasan' => null, 'versi' => self::versi($akun)];
        }
        $repo->simpan(['name' => $isian['nama'], 'jabatan' => $isian['jabatan'] !== '' ? $isian['jabatan'] : null], (int) $akun['id']);
        $baru = $repo->temukan((int) $akun['id']) ?? $akun;
        RiwayatPengaturan::catat('ubah_profil_akun', "{$baru['email']} — {$ringkasan}");

        return ['akun' => $baru, 'ringkasan' => $ringkasan, 'versi' => self::versi($baru)];
    }
}
