<?php

namespace App\Support\Master;

use App\Support\Kegiatan\KelengkapanKode;
use App\Support\Kegiatan\TahunKegiatan;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validasi kode master kegiatan berdasarkan tahun anggaran (PRD F7, aturan 7 "Kode wajib valid", §17): kode RKTM, SK,
 * IKSK, EDM, dan 5 Budaya Mutu harus ada di master **tahun anggaran kegiatannya** (`tahun()` = TahunKegiatan::dari —
 * kegiatan baru/isian tanpa tahun = tahun aktif), dan IKSK harus indikator SK-nya di tahun yang sama (IkskPerSk::galat).
 * Kosong tidak ditolak di sini (kode wajib diperiksa saat pengajuan, KelengkapanKode). Dipakai ValidasiKegiatan::aturan
 * (Data Kegiatan, Kode & Klasifikasi, Kalimat KAK, impor, UbahDataKegiatan, SimpanKlasifikasi) sehingga master tahun lain
 * tidak pernah diterima.
 */
final class ValidasiKodeMaster
{
    /** Isian kode kegiatan → jenis master (JenisKode::DAFTAR). */
    public const ISIAN = [
        'kode_rktm' => 'rktm',
        'kode_sk' => 'sk',
        'kode_iksk' => 'iksk',
        'kode_edm' => 'edm',
        'budaya_mutu' => 'budaya',
    ];

    /** @param  array<string, mixed>  $data */
    public static function tahun(array $data): int
    {
        return TahunKegiatan::dari($data);
    }

    /** "Kode SK SK.99 tidak ada di master TA 2026." */
    public static function pesanAsing(string $isian, string $kode, int $tahun): string
    {
        return KelengkapanKode::KODE[$isian][0]." {$kode} tidak ada di master TA {$tahun}.";
    }

    /**
     * Aturan Laravel untuk isian kode (kunci ISIAN).
     *
     * @param  array<string, mixed>  $data  Dibaca: kode SK (untuk IKSK) dan tahun (bila $tahun null).
     * @return array<string, list<mixed>>
     */
    public static function aturan(array $data, ?int $tahun = null): array
    {
        $tahun ??= self::tahun($data);
        $aturan = [];
        foreach (self::ISIAN as $isian => $jenis) {
            $aturan[$isian] = ['nullable', self::adaDiMaster($isian, $jenis, $tahun)];
        }
        $aturan['kode_iksk'][] = function (string $attribute, mixed $value, Closure $fail) use ($data, $tahun) {
            if ($pesan = IkskPerSk::galat(filled($data['kode_sk'] ?? null) ? (string) $data['kode_sk'] : null, filled($value) ? (string) $value : null, $tahun)) {
                $fail($pesan);
            }
        };

        return $aturan;
    }

    /**
     * Pesan galat kode yang tidak sah, berkunci isian (kosong = semua sah).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function galat(array $data, ?int $tahun = null): array
    {
        return array_map(fn (array $pesan) => $pesan[0], self::validator($data, $tahun)->errors()->toArray());
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException Berkunci isian kode.
     */
    public static function periksa(array $data, ?int $tahun = null): void
    {
        self::validator($data, $tahun)->validate();
    }

    /** @param  array<string, mixed>  $data */
    private static function validator(array $data, ?int $tahun): \Illuminate\Validation\Validator
    {
        return Validator::make(array_intersect_key($data, self::ISIAN), self::aturan($data, $tahun));
    }

    private static function adaDiMaster(string $isian, string $jenis, int $tahun): Closure
    {
        $kunci = JenisKode::DAFTAR[$jenis]['kunci'];
        $kode = array_flip(array_map('strval', array_column(JenisKode::baris($jenis, $tahun), $kunci)));

        return function (string $attribute, mixed $value, Closure $fail) use ($isian, $tahun, $kode) {
            if (! is_scalar($value) || ! isset($kode[(string) $value])) {
                $fail(self::pesanAsing($isian, is_scalar($value) ? (string) $value : '?', $tahun));
            }
        };
    }
}
