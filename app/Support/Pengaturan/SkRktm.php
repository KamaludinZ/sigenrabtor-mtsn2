<?php

namespace App\Support\Pengaturan;

use App\Contracts\TahunAnggaranRepository;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * SK RKTM/RKAM per tahun anggaran (PRD §13, kolom tahun_anggaran.no_sk_rktm/tanggal_sk_rktm): nomor & tanggal yang
 * tercetak di butir dasar hukum lewat penanda `{no_sk_rktm}`/`{tanggal_sk_rktm}` (selama kosong tercetak "…").
 */
final class SkRktm
{
    public const NOMOR = '{no_sk_rktm}';

    public const TANGGAL = '{tanggal_sk_rktm}';

    /** Penanda SK → label. */
    public const PENANDA = [
        self::NOMOR => 'Nomor SK RKTM/RKAM',
        self::TANGGAL => 'Tanggal SK RKTM/RKAM',
    ];

    public const MAKS_NOMOR = 100;

    /**
     * Nomor & tanggal (Y-m-d) tersimpan tahun $tahun; null bila belum diisi atau tahunnya belum dibuat.
     *
     * @return array{nomor: string|null, tanggal: string|null}
     */
    public static function untuk(int $tahun): array
    {
        $data = collect(app(TahunAnggaranRepository::class)->semua())->firstWhere('tahun', $tahun);

        return ['nomor' => $data['no_sk_rktm'] ?? null, 'tanggal' => $data['tanggal_sk_rktm'] ?? null];
    }

    /** Nomor & tanggal sudah lengkap. */
    public static function lengkap(int $tahun): bool
    {
        $sk = self::untuk($tahun);

        return filled($sk['nomor']) && filled($sk['tanggal']);
    }

    /**
     * Nilai penanda siap cetak (tanggal "6 Januari 2025"); null = belum diisi.
     *
     * @return array<string, string|null>
     */
    public static function nilai(int $tahun): array
    {
        $sk = self::untuk($tahun);

        return [self::NOMOR => $sk['nomor'], self::TANGGAL => $sk['tanggal'] ? Format::tanggal($sk['tanggal']) : null];
    }

    /** Teks memakai penanda SK. */
    public static function dipakai(string $teks): bool
    {
        return str_contains($teks, self::NOMOR) || str_contains($teks, self::TANGGAL);
    }

    public static function rapikanNomor(string $nomor): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $nomor));
    }

    /**
     * Galat isian per kolom (`nomor`/`tanggal`); kosong keduanya sah (SK dikosongkan).
     *
     * @return array<string, string>
     */
    public static function galat(string $nomor, string $tanggal): array
    {
        if ($nomor === '' && $tanggal === '') {
            return [];
        }

        $galat = [];
        if ($nomor === '') {
            $galat['nomor'] = 'Nomor SK wajib diisi bersama tanggalnya.';
        } elseif (mb_strlen($nomor) > self::MAKS_NOMOR) {
            $galat['nomor'] = 'Terlalu panjang (maksimal '.self::MAKS_NOMOR.' karakter).';
        }
        if ($tanggal === '') {
            $galat['tanggal'] = 'Tanggal SK wajib diisi bersama nomornya.';
        } elseif (self::tanggalSah($tanggal) === null) {
            $galat['tanggal'] = 'Tanggal tidak valid.';
        }

        return $galat;
    }

    /** Peringatan (tidak memblokir) bila tanggal SK di luar akhir tahun sebelumnya s.d. tahun anggarannya. */
    public static function peringatanTanggal(?string $tanggal, int $tahun): ?string
    {
        $tgl = $tanggal ? self::tanggalSah($tanggal) : null;
        if ($tgl === null || in_array($tgl->year, [$tahun - 1, $tahun], true)) {
            return null;
        }

        return "Tanggal SK ({$tgl->year}) tidak sejalan dengan TA {$tahun} — SK RKTM/RKAM biasanya ditetapkan akhir tahun sebelumnya atau awal tahun anggaran.";
    }

    private static function tanggalSah(string $tanggal): ?CarbonImmutable
    {
        try {
            $tgl = CarbonImmutable::createFromFormat('!Y-m-d', $tanggal);
        } catch (Throwable) {
            return null;
        }

        return $tgl !== false && $tgl->format('Y-m-d') === $tanggal ? $tgl : null;
    }
}
