<?php

namespace App\Support\Pengaturan;

/**
 * Aturan isian leading (PRD F30): kode bertingkat "n.n" (1.1, 1.10) unik, nama unit unik (tanpa memandang huruf
 * besar/spasi), jabatan penanda tangan wajib, urutan tampil opsional 0–999.
 */
final class AturanLeading
{
    public const MAKS_NAMA = 100;

    public const MAKS_JABATAN = 150;

    /** Spasi dirapatkan. */
    public static function rapikan(string $teks): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $teks));
    }

    /** "1 . 10", "01.1" → "1.10", "1.1"; null bila bukan dua bagian angka. */
    public static function normalkanKode(string $kode): ?string
    {
        return preg_match('/^(\d+)\s*\.\s*(\d+)$/', trim($kode), $cocok) ? ((int) $cocok[1]).'.'.((int) $cocok[2]) : null;
    }

    /**
     * Galat per kolom (`kode`, `nama_unit`, `jabatan_penanda_tangan`, `urutan`) terhadap leading lain.
     *
     * @param  array{kode: string, nama_unit: string, jabatan_penanda_tangan: string, urutan: string|int|null}  $isian  Sudah dirapikan.
     * @param  list<array{kode: string, nama_unit: string}>  $leading  Semua leading.
     * @return array<string, string>
     */
    public static function galat(array $isian, array $leading, ?string $kodeLama = null): array
    {
        $lain = array_values(array_filter($leading, fn (array $l) => $l['kode'] !== $kodeLama));
        $galat = [];

        if ($isian['kode'] === '') {
            $galat['kode'] = 'Kode leading wajib diisi.';
        } elseif (self::normalkanKode($isian['kode']) === null) {
            $galat['kode'] = 'Kode berformat angka.angka, mis. 1.1 atau 1.10.';
        } elseif (in_array(self::normalkanKode($isian['kode']), array_column($lain, 'kode'), true)) {
            $galat['kode'] = 'Kode '.self::normalkanKode($isian['kode']).' sudah dipakai leading lain.';
        }

        $kunciNama = mb_strtolower($isian['nama_unit']);
        $sama = collect($lain)->first(fn (array $l) => mb_strtolower($l['nama_unit']) === $kunciNama);
        $galat['nama_unit'] = match (true) {
            $isian['nama_unit'] === '' => 'Nama unit wajib diisi.',
            mb_strlen($isian['nama_unit']) > self::MAKS_NAMA => 'Terlalu panjang (maksimal '.self::MAKS_NAMA.' karakter).',
            $sama !== null => "Nama unit sudah dipakai leading {$sama['kode']}.",
            default => null,
        };
        $galat['jabatan_penanda_tangan'] = match (true) {
            $isian['jabatan_penanda_tangan'] === '' => 'Jabatan penanda tangan wajib diisi — tercetak di kolom "Pembuat".',
            mb_strlen($isian['jabatan_penanda_tangan']) > self::MAKS_JABATAN => 'Terlalu panjang (maksimal '.self::MAKS_JABATAN.' karakter).',
            default => null,
        };
        $urutan = (string) ($isian['urutan'] ?? '');
        $galat['urutan'] = $urutan === '' || (ctype_digit($urutan) && (int) $urutan <= 999) ? null : 'Urutan berupa bilangan bulat 0–999.';

        return array_filter($galat);
    }
}
