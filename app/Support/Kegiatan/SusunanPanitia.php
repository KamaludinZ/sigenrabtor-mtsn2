<?php

namespace App\Support\Kegiatan;

/**
 * Susunan pelaksana & panitia kegiatan (PRD F3, calon tabel committees): satu-satunya tempat operasi daftar anggota —
 * tambah, hapus, sisipkan kembali, geser, pindahkan (seret-lepas), urutkan sesuai peran, susunan baku, deteksi nama
 * ganda — dan perapian sebelum disimpan. Semua fungsi murni atas daftar {nama, jabatan, peran} (baris form juga
 * membawa `key` yang ikut dipertahankan); urutan daftar = urutan cetak.
 */
final class SusunanPanitia
{
    /** Saran peran di isian (datalist). */
    public const PERAN = ['Ketua', 'Wakil Ketua', 'Sekretaris', 'Bendahara', 'Koordinator', 'Anggota', 'Narasumber', 'Pengarah'];

    /** Hierarki peran untuk "Urutkan sesuai peran". */
    public const URUTAN_PERAN = ['Pengarah', 'Penanggung Jawab', 'Ketua', 'Wakil Ketua', 'Sekretaris', 'Bendahara', 'Koordinator', 'Narasumber', 'Anggota'];

    /** Susunan baku panitia kegiatan madrasah (dipakai saat daftar masih kosong). */
    public const SUSUNAN_BAKU = ['Ketua', 'Sekretaris', 'Bendahara', 'Anggota'];

    public const MAKS = ValidasiKegiatan::MAKS_PANITIA;

    /**
     * @template T of array
     *
     * @param  list<T>  $panitia
     * @param  T  $anggota
     * @return list<T>|null null bila susunan sudah mencapai MAKS.
     */
    public static function tambah(array $panitia, array $anggota): ?array
    {
        return count($panitia) >= self::MAKS ? null : [...array_values($panitia), $anggota];
    }

    /** @return list<array<string, mixed>> */
    public static function hapus(array $panitia, int $index): array
    {
        unset($panitia[$index]);

        return array_values($panitia);
    }

    /**
     * Kembalikan anggota (mis. yang baru dihapus) ke posisi semula; posisi di luar daftar dijepit ke ujung.
     *
     * @return list<array<string, mixed>>
     */
    public static function sisipkan(array $panitia, int $index, array $anggota): array
    {
        $panitia = array_values($panitia);
        array_splice($panitia, max(0, min($index, count($panitia))), 0, [$anggota]);

        return $panitia;
    }

    /**
     * Geser anggota ke atas (-1) atau ke bawah (+1); di ujung daftar diabaikan.
     *
     * @return list<array<string, mixed>>
     */
    public static function geser(array $panitia, int $index, int $arah): array
    {
        $panitia = array_values($panitia);
        $tujuan = $index + ($arah > 0 ? 1 : -1);

        if (isset($panitia[$index], $panitia[$tujuan])) {
            [$panitia[$index], $panitia[$tujuan]] = [$panitia[$tujuan], $panitia[$index]];
        }

        return $panitia;
    }

    /**
     * Pindahkan anggota berkunci $key ke posisi baru (seret-lepas); key tak dikenal diabaikan.
     *
     * @return list<array<string, mixed>>
     */
    public static function pindahkan(array $panitia, string $key, int $posisi): array
    {
        $panitia = array_values($panitia);
        $index = array_search($key, array_column($panitia, 'key'), true);

        if ($index === false) {
            return $panitia;
        }

        $anggota = $panitia[$index];
        array_splice($panitia, $index, 1);

        return self::sisipkan($panitia, $posisi, $anggota);
    }

    /**
     * Urutkan sesuai hierarki peran (Pengarah → Ketua → … → Anggota). Peran di luar daftar diletakkan sebelum Anggota;
     * urutan antar anggota berperan sama dipertahankan.
     *
     * @return list<array<string, mixed>>
     */
    public static function urutkanSesuaiPeran(array $panitia): array
    {
        $urutan = array_map('mb_strtolower', self::URUTAN_PERAN);
        $posisiAnggota = array_search('anggota', $urutan, true);

        $peringkat = function (array $anggota) use ($urutan, $posisiAnggota): float {
            $i = array_search(mb_strtolower(self::rapikanTeks((string) ($anggota['peran'] ?? ''))), $urutan, true);

            return $i === false ? $posisiAnggota - 0.5 : $i;
        };

        $berindeks = array_map(null, array_keys(array_values($panitia)), array_values($panitia));
        usort($berindeks, fn ($a, $b) => [$peringkat($a[1]), $a[0]] <=> [$peringkat($b[1]), $b[0]]);

        return array_column($berindeks, 1);
    }

    /**
     * Indeks baris yang namanya sama dengan baris lain (tanpa membedakan huruf/spasi).
     *
     * @return list<int>
     */
    public static function ganda(array $panitia): array
    {
        $nama = array_map(fn ($a) => mb_strtolower(self::rapikanTeks((string) ($a['nama'] ?? ''))), array_values($panitia));
        $hitung = array_count_values(array_filter($nama));

        return array_keys(array_filter($nama, fn ($n) => $n !== '' && $hitung[$n] > 1));
    }

    /**
     * Bentuk simpan: {nama, jabatan, peran} dengan spasi dirapikan, dan peran baku ditulis dengan huruf bakunya
     * ("ketua" → "Ketua"). Baris form lain (`key`) tidak ikut; urutan tetap.
     *
     * @return list<array{nama: string, jabatan: string, peran: string}>
     */
    public static function rapikan(array $panitia): array
    {
        return array_map(fn (array $a) => [
            'nama' => self::rapikanTeks((string) ($a['nama'] ?? '')),
            'jabatan' => self::rapikanTeks((string) ($a['jabatan'] ?? '')),
            'peran' => self::peranBaku((string) ($a['peran'] ?? '')),
        ], array_values($panitia));
    }

    /** Peran dirapikan; bila sama dengan peran baku (tanpa beda huruf besar/kecil) memakai tulisan bakunya. */
    public static function peranBaku(string $peran): string
    {
        $peran = self::rapikanTeks($peran);

        foreach ([...self::URUTAN_PERAN, ...self::PERAN] as $baku) {
            if (mb_strtolower($baku) === mb_strtolower($peran)) {
                return $baku;
            }
        }

        return $peran;
    }

    private static function rapikanTeks(string $teks): string
    {
        return trim(preg_replace('/\s+/u', ' ', $teks));
    }
}
