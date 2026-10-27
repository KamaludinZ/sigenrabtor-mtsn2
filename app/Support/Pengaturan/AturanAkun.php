<?php

namespace App\Support\Pengaturan;

/**
 * Aturan isian akun (PRD F29): nama & surel wajib (surel unik tanpa memandang huruf besar), peran admin/leading, akun
 * leading wajib tertaut ke minimal satu leading aktif, NIP opsional (Nip::galat). Pengaman: peran akun sendiri tidak
 * diubah dan minimal satu admin aktif tetap ada.
 */
final class AturanAkun
{
    public const MAKS_NAMA = 150;

    public const MAKS_JABATAN = 150;

    public static function rapikan(string $teks): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $teks));
    }

    /**
     * Galat per kolom (`name`, `email`, `role`, `jabatan`, `nip`, `leadings`).
     *
     * @param  array{name: string, email: string, role: string, jabatan: string, nip: string, leadings: list<string>}  $isian  Sudah dirapikan.
     * @param  list<array<string, mixed>>  $akun  Semua akun.
     * @param  list<string>  $leadingAktif  Kode leading aktif.
     * @return array<string, string>
     */
    public static function galat(array $isian, array $akun, array $leadingAktif, ?int $idLama, int $idSaya): array
    {
        $lama = $idLama === null ? null : collect($akun)->firstWhere('id', $idLama);
        $lain = array_values(array_filter($akun, fn (array $a) => $a['id'] !== $idLama));
        $sama = collect($lain)->first(fn (array $a) => mb_strtolower($a['email']) === mb_strtolower($isian['email']));
        $adminAktifLain = count(array_filter($lain, fn (array $a) => $a['role'] === 'admin' && $a['is_active']));

        return array_filter([
            'name' => match (true) {
                $isian['name'] === '' => 'Nama wajib diisi.',
                mb_strlen($isian['name']) > self::MAKS_NAMA => 'Terlalu panjang (maksimal '.self::MAKS_NAMA.' karakter).',
                default => null,
            },
            'email' => match (true) {
                $isian['email'] === '' => 'Surel wajib diisi — dipakai untuk masuk.',
                filter_var($isian['email'], FILTER_VALIDATE_EMAIL) === false => 'Surel tidak valid.',
                $sama !== null => 'Surel sudah dipakai akun '.$sama['name'].(str_ends_with($sama['name'], '.') ? '' : '.'),
                default => null,
            },
            'role' => match (true) {
                ! in_array($isian['role'], ['admin', 'leading'], true) => 'Pilih peran Admin atau Leading.',
                $lama !== null && $idLama === $idSaya && $isian['role'] !== $lama['role'] => 'Peran akun Anda sendiri tidak bisa diubah.',
                $lama !== null && $lama['role'] === 'admin' && $lama['is_active'] && $isian['role'] !== 'admin' && $adminAktifLain === 0 => 'Minimal satu admin aktif harus tetap ada.',
                default => null,
            },
            'jabatan' => mb_strlen($isian['jabatan']) > self::MAKS_JABATAN ? 'Terlalu panjang (maksimal '.self::MAKS_JABATAN.' karakter).' : null,
            'nip' => $isian['nip'] === '' ? null : Nip::galat($isian['nip']),
            'leadings' => $isian['role'] !== 'leading' ? null : match (true) {
                $isian['leadings'] === [] => 'Akun leading wajib ditautkan ke minimal satu leading.',
                array_diff($isian['leadings'], $leadingAktif, $lama['leadings'] ?? []) !== [] => 'Leading yang dipilih tidak dikenal atau nonaktif.',
                default => null,
            },
        ]);
    }
}
