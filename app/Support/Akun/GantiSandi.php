<?php

namespace App\Support\Akun;

use App\Contracts\PenggunaRepository;
use App\Support\Pengaturan\RiwayatPengaturan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Ganti sandi akun sendiri (PRD §8 "Kelola Profil & Sandi", N3 sandi terenkripsi) — satu-satunya jalur pengguna
 * mengganti sandinya (tersimpan hash bcrypt lewat `PenggunaRepository::gantiSandi`, sandi asli tidak pernah ditulis):
 * - `SYARAT` = `PendaftaranAkun::SYARAT_SANDI` + `beda` (berbeda dari sandi saat ini); `syarat($baru, $lama, $nama,
 *   $surel)` (kembaran klien Alpine `formSandi`, resources/js/sandi.js);
 * - `galat($isian, $akun)` per kunci `sandi_lama` (wajib & cocok bila akun sudah punya sandi — akun contoh tanpa sandi
 *   boleh mengosongkannya), `sandi_baru` (syarat pendaftaran + berbeda dari sandi tersimpan), `sandi_ulang`;
 * - sandi saat ini yang salah dibatasi `MAKS_SALAH` per `JEDA_DETIK` per akun (RateLimiter) → galat `sandi_lama`;
 * - `simpan($pengguna, $isian)` → {diganti_pada} + riwayat `ganti_sandi_akun` (tanpa sandi); `status($id)` = {ada,
 *   diganti_pada} untuk tab Sandi.
 */
final class GantiSandi
{
    public const SYARAT = PendaftaranAkun::SYARAT_SANDI + ['beda' => 'Berbeda dari sandi saat ini'];

    public const MAKS_SALAH = 5;

    public const JEDA_DETIK = 300;

    /** @return array{panjang: bool, campur: bool, pribadi: bool, beda: bool} */
    public static function syarat(string $baru, string $lama, string $nama, string $surel): array
    {
        return PendaftaranAkun::syaratSandi($baru, $nama, $surel) + ['beda' => $baru !== '' && $baru !== $lama];
    }

    /** @return array{ada: bool, diganti_pada: string|null} */
    public static function status(int $id): array
    {
        $sandi = app(PenggunaRepository::class)->sandi($id);

        return ['ada' => $sandi !== null, 'diganti_pada' => $sandi['diganti_pada'] ?? null];
    }

    /**
     * @param  array<string, mixed>  $isian  {sandi_lama, sandi_baru, sandi_ulang}
     * @param  array<string, mixed>  $akun  Akun PenggunaRepository.
     * @return array<string, string>
     */
    public static function galat(array $isian, array $akun): array
    {
        [$lama, $baru, $ulang] = array_map(fn (string $k) => (string) ($isian[$k] ?? ''), ['sandi_lama', 'sandi_baru', 'sandi_ulang']);
        $tersimpan = app(PenggunaRepository::class)->sandi((int) $akun['id']);
        $kunci = self::kunci((int) $akun['id']);
        $syarat = PendaftaranAkun::syaratSandi($baru, (string) $akun['name'], (string) $akun['email']);
        $kurang = array_keys(array_filter($syarat, fn (bool $ok) => ! $ok));

        $galatLama = match (true) {
            $tersimpan === null => null,
            RateLimiter::tooManyAttempts($kunci, self::MAKS_SALAH) => 'Terlalu banyak percobaan sandi salah — coba lagi dalam '.max(1, RateLimiter::availableIn($kunci)).' detik.',
            $lama === '' => 'Isi sandi Anda saat ini.',
            ! Hash::check($lama, $tersimpan['hash']) => 'Sandi saat ini salah.',
            default => null,
        };
        if ($galatLama === 'Sandi saat ini salah.') {
            RateLimiter::hit($kunci, self::JEDA_DETIK);
        }

        return array_filter([
            'sandi_lama' => $galatLama,
            'sandi_baru' => match (true) {
                $baru === '' => 'Buat sandi baru.',
                mb_strlen($baru) > PendaftaranAkun::MAKS_SANDI => 'Terlalu panjang (maksimal '.PendaftaranAkun::MAKS_SANDI.' karakter).',
                $kurang !== [] => PendaftaranAkun::PESAN_SANDI[$kurang[0]],
                ($lama !== '' && $baru === $lama) || ($tersimpan !== null && Hash::check($baru, $tersimpan['hash'])) => 'Sandi baru harus berbeda dari sandi saat ini.',
                default => null,
            },
            'sandi_ulang' => match (true) {
                $ulang === '' => 'Ulangi sandi baru.',
                $ulang !== $baru => 'Sandi ulang tidak sama dengan sandi baru.',
                default => null,
            },
        ]);
    }

    /**
     * @param  array{id: int|string}  $pengguna
     * @param  array<string, mixed>  $isian
     * @return array{diganti_pada: string|null}
     *
     * @throws ValidationException
     * @throws RuntimeException Akun tidak tercatat / gagal disimpan.
     */
    public static function simpan(array $pengguna, array $isian): array
    {
        $repo = app(PenggunaRepository::class);
        $akun = $repo->temukan((int) $pengguna['id']) ?? throw new RuntimeException('Akun Anda belum tercatat — hubungi admin.');
        if (($galat = self::galat($isian, $akun)) !== []) {
            throw ValidationException::withMessages($galat);
        }
        $repo->gantiSandi((int) $akun['id'], Hash::make((string) $isian['sandi_baru']));
        RateLimiter::clear(self::kunci((int) $akun['id']));
        RiwayatPengaturan::catat('ganti_sandi_akun', "{$akun['email']} — sandi diganti");

        return ['diganti_pada' => $repo->sandi((int) $akun['id'])['diganti_pada'] ?? null];
    }

    private static function kunci(int $id): string
    {
        return 'ganti-sandi:'.$id;
    }
}
