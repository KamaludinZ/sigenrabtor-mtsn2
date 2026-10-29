<?php

namespace App\Support\Akun;

use App\Contracts\PenggunaRepository;

/**
 * State autentikasi tiruan (PRD §8 "Daftar & Masuk"; padanan Auth Laravel sampai autentikasi nyata dipasang) — satu-satunya
 * penulis & pembaca sesi akun:
 * - sesi `KUNCI_AKUN` (id akun yang masuk) + `KUNCI_PERAN` (peran) + `KUNCI_WAKTU`; `masuk($akun)` (dipakai halaman
 *   Masuk & sakelar demo `/demo/peran/{role}` lewat `masukPersona()`), `keluar()`;
 * - `sudahMasuk()` = sesi berisi akun atau peran demo; `wajibMasuk()` = config `sigenrabtor.akun.wajib_masuk`
 *   (middleware WajibMasuk; tes PHPUnit & E2E mematikannya sehingga halaman tetap bisa dibuka tanpa masuk);
 * - `pengguna()` = bentuk `StubData::currentUser()` {id, name, email, role, jabatan, leadings, masuk_pada}: akun di sesi
 *   (nama, peran, jabatan, leading dari PenggunaRepository) atau, tanpa akun di sesi, persona peran demo
 *   (`DataAkun::PERSONA`; bawaan leading) — peran persona tetap peran demo, admin tanpa tautan leading;
 * - `nonaktif()` = akun di sesi kini nonaktif/terhapus (WajibMasuk mengeluarkannya).
 */
final class SesiAkun
{
    public const KUNCI_AKUN = 'stub_akun';

    public const KUNCI_PERAN = 'stub_role';

    public const KUNCI_WAKTU = 'stub_masuk_pada';

    /** @param  array{id: int|string, role: string}  $akun */
    public static function masuk(array $akun): void
    {
        session()->put([
            self::KUNCI_AKUN => (int) $akun['id'],
            self::KUNCI_PERAN => (string) $akun['role'],
            self::KUNCI_WAKTU => now()->format('Y-m-d\TH:i:sP'),
        ]);
        session()->regenerate();
    }

    /** Masuk sebagai persona peran demo (sakelar "lihat sebagai"). */
    public static function masukPersona(string $peran): int
    {
        $id = (int) array_search($peran, DataAkun::PERSONA, true);
        session()->put([self::KUNCI_AKUN => $id, self::KUNCI_PERAN => $peran, self::KUNCI_WAKTU => now()->format('Y-m-d\TH:i:sP')]);

        return $id;
    }

    public static function keluar(): void
    {
        session()->forget([self::KUNCI_AKUN, self::KUNCI_PERAN, self::KUNCI_WAKTU]);
        session()->invalidate();
        session()->regenerateToken();
    }

    public static function sudahMasuk(): bool
    {
        return session()->has(self::KUNCI_AKUN) || session()->has(self::KUNCI_PERAN);
    }

    public static function wajibMasuk(): bool
    {
        return (bool) config('sigenrabtor.akun.wajib_masuk', true);
    }

    /** Akun di sesi kini nonaktif atau sudah tidak ada. */
    public static function nonaktif(): bool
    {
        if (! session()->has(self::KUNCI_AKUN)) {
            return false;
        }
        $akun = app(PenggunaRepository::class)->temukan((int) session(self::KUNCI_AKUN));

        return $akun === null || ! ($akun['is_active'] ?? true);
    }

    /** @return array{id: int, name: string, email: string|null, role: string, jabatan: string, leadings: list<string>, masuk_pada: string|null} */
    public static function pengguna(): array
    {
        $peranDemo = session(self::KUNCI_PERAN) === 'admin' ? 'admin' : 'leading';
        $bawaan = $peranDemo === 'admin'
            ? ['id' => 2, 'name' => 'Koordinator PUSKOM', 'email' => null, 'role' => 'admin', 'jabatan' => 'Admin Aplikasi', 'leadings' => []]
            : ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'email' => null, 'role' => 'leading', 'jabatan' => 'Tim Penjamin Mutu', 'leadings' => ['1.6']];
        $id = session()->has(self::KUNCI_AKUN) ? (int) session(self::KUNCI_AKUN) : $bawaan['id'];
        $akun = app(PenggunaRepository::class)->temukan($id);
        if ($akun === null) {
            return $bawaan + ['masuk_pada' => null];
        }
        // Akun yang masuk lewat halaman Masuk memakai perannya sendiri; persona demo tetap peran demonya.
        $peran = session()->has(self::KUNCI_AKUN) && ! isset(DataAkun::PERSONA[$id]) ? (string) $akun['role'] : (DataAkun::PERSONA[$id] ?? $peranDemo);

        return [
            'id' => $id,
            'name' => (string) $akun['name'],
            'email' => $akun['email'] ?? null,
            'role' => $peran,
            'jabatan' => (string) ($akun['jabatan'] ?? ($id === $bawaan['id'] ? $bawaan['jabatan'] : '')),
            'leadings' => $peran === 'admin' ? [] : array_values($akun['leadings'] ?? []),
            'masuk_pada' => session(self::KUNCI_WAKTU),
        ];
    }
}
