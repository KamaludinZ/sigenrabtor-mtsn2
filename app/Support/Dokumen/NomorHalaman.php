<?php

namespace App\Support\Dokumen;

/**
 * Aturan nomor halaman buku per leading × sumber dana (PRD F20, tabel page_number_settings): format dengan penanda {x}
 * (nomor halaman) dan {y} (nomor halaman terakhir), posisi, nomor awal, halaman sampul/daftar isi tanpa nomor, dan apakah
 * halaman tanpa nomor tetap dihitung. Nomor di daftar isi = halaman awal tiap kegiatan menurut aturan yang sama.
 */
final class NomorHalaman
{
    /** Format siap pakai: format → contoh. */
    public const PRESET = [
        'Halaman {x} dari {y}' => 'Halaman 3 dari 18',
        '{x}' => '3',
        '- {x} -' => '- 3 -',
        'Hal. {x}' => 'Hal. 3',
        '{x} / {y}' => '3 / 18',
    ];

    public const POSISI = ['bawah_tengah' => 'Bawah tengah', 'bawah_kanan' => 'Bawah kanan', 'atas_kanan' => 'Atas kanan'];

    public const BAWAAN = [
        'format' => 'Halaman {x} dari {y}',
        'posisi' => 'bawah_tengah',
        'mulai_dari' => 1,
        'sampul_tanpa_nomor' => true,
        'daftar_isi_tanpa_nomor' => false,
        'hitung_tanpa_nomor' => true,
        'urut_mengikuti_daftar' => true,
    ];

    /** Perkiraan halaman satu kegiatan untuk simulasi: cover, isi KAK/TOR, RAB. */
    public const HALAMAN_KEGIATAN = ['cover' => 1, 'kak' => 5, 'rab' => 2];

    /** Pesan galat format (null bila sah): wajib memuat {x}, hanya {x}/{y}, maks. 40 karakter. */
    public static function galatFormat(string $format): ?string
    {
        $format = trim($format);

        return match (true) {
            $format === '' => 'Isi format nomor halaman.',
            mb_strlen($format) > 40 => 'Format maksimal 40 karakter.',
            ! str_contains($format, '{x}') => 'Format harus memuat {x} (nomor halaman).',
            (bool) preg_match('/\{(?![xy]\})[^}]*\}|\{(?![xy]\})/', $format) => 'Hanya penanda {x} dan {y} yang dikenal.',
            default => null,
        };
    }

    /**
     * @param  array<mixed>  $aturan
     * @return array{format: string, posisi: string, mulai_dari: int, sampul_tanpa_nomor: bool, daftar_isi_tanpa_nomor: bool, hitung_tanpa_nomor: bool, urut_mengikuti_daftar: bool}
     */
    public static function rapikan(array $aturan): array
    {
        $format = is_string($aturan['format'] ?? null) ? trim((string) preg_replace('/\s+/u', ' ', $aturan['format'])) : self::BAWAAN['format'];
        $mulai = filter_var($aturan['mulai_dari'] ?? null, FILTER_VALIDATE_INT);

        return [
            'format' => self::galatFormat($format) === null ? $format : self::BAWAAN['format'],
            'posisi' => array_key_exists($aturan['posisi'] ?? null, self::POSISI) ? $aturan['posisi'] : self::BAWAAN['posisi'],
            'mulai_dari' => $mulai === false ? self::BAWAAN['mulai_dari'] : max(1, min(999, $mulai)),
            'sampul_tanpa_nomor' => (bool) ($aturan['sampul_tanpa_nomor'] ?? self::BAWAAN['sampul_tanpa_nomor']),
            'daftar_isi_tanpa_nomor' => (bool) ($aturan['daftar_isi_tanpa_nomor'] ?? self::BAWAAN['daftar_isi_tanpa_nomor']),
            'hitung_tanpa_nomor' => (bool) ($aturan['hitung_tanpa_nomor'] ?? self::BAWAAN['hitung_tanpa_nomor']),
            'urut_mengikuti_daftar' => (bool) ($aturan['urut_mengikuti_daftar'] ?? self::BAWAAN['urut_mengikuti_daftar']),
        ];
    }

    public static function label(string $format, int $x, int $y): string
    {
        return strtr($format, ['{x}' => (string) $x, '{y}' => (string) $y]);
    }

    /**
     * Simulasi buku: cover buku, daftar isi, lalu tiap kegiatan (cover, KAK/TOR, RAB) dengan label halaman sesuai aturan,
     * serta halaman awal tiap kegiatan (angka yang tercetak di kolom Halaman daftar isi).
     *
     * @param  array<string, mixed>  $aturan  Sudah dirapikan.
     * @param  list<string>  $kegiatan  Nama kegiatan berurutan seperti di daftar isi.
     * @return array{halaman: list<array{jenis: string, judul: string, kegiatan: int|null, nomor: int|null, label: string|null}>, mulai: list<int|null>, terakhir: int}
     */
    public static function simulasi(array $aturan, array $kegiatan): array
    {
        $urutan = [['cover_buku', 'Cover buku', null], ['daftar_isi', 'Daftar isi', null]];
        foreach ($kegiatan as $i => $nama) {
            $urutan[] = ['cover_kegiatan', "Cover {$nama}", $i];
            for ($h = 1; $h <= self::HALAMAN_KEGIATAN['kak']; $h++) {
                $urutan[] = ['kak', "KAK/TOR {$h}", $i];
            }
            for ($h = 1; $h <= self::HALAMAN_KEGIATAN['rab']; $h++) {
                $urutan[] = ['rab', "RAB {$h}", $i];
            }
        }

        $penghitung = $aturan['mulai_dari'] - 1;
        $halaman = [];
        $mulai = array_fill(0, count($kegiatan), null);

        foreach ($urutan as [$jenis, $judul, $i]) {
            $tanpaNomor = ($aturan['sampul_tanpa_nomor'] && in_array($jenis, ['cover_buku', 'cover_kegiatan'], true))
                || ($aturan['daftar_isi_tanpa_nomor'] && $jenis === 'daftar_isi');
            $dihitung = ! $tanpaNomor || $aturan['hitung_tanpa_nomor'];
            $nomor = $dihitung ? ++$penghitung : null;

            if ($i !== null && $mulai[$i] === null && $nomor !== null) {
                $mulai[$i] = $nomor;
            }

            $halaman[] = ['jenis' => $jenis, 'judul' => $judul, 'kegiatan' => $i, 'nomor' => $tanpaNomor ? null : $nomor, 'label' => null];
        }

        return [
            'halaman' => array_map(fn (array $h) => ['label' => $h['nomor'] === null ? null : self::label($aturan['format'], $h['nomor'], $penghitung)] + $h, $halaman),
            'mulai' => $mulai,
            'terakhir' => $penghitung,
        ];
    }
}
