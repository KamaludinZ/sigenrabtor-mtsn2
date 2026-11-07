<?php

namespace App\Support\Pengaturan;

use App\Contracts\PejabatRepository;
use App\Support\Format;
use App\Support\Kegiatan\TahunKegiatan;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Tanggal dokumen yang tercetak di lembar pengesahan KAK/TOR & RAB ("{kota}, {tanggal}") — satu aturan untuk SusunanKak,
 * TanggalDokumen, NilaiVariabel `${tanggal_dokumen}`, dan checklist isian:
 * 1. tanggal dokumen kegiatan itu sendiri (Data Kegiatan, tetap bisa disunting per kegiatan) bila diisi;
 * 2. selain itu tanggal dokumen leading kegiatan untuk tahun itu (tab Pejabat, PejabatRepository::tanggalDokumen);
 * 3. selain itu kosong (penanda "lengkapi tanggal dokumen").
 * Tahun = tahun susunan dokumen (sama dengan pejabatnya); null = tahun anggaran kegiatan.
 */
final class TanggalPengesahan
{
    /** Asal tanggal → keterangan. */
    public const ASAL = ['kegiatan' => 'tanggal dokumen kegiatan', 'leading' => 'tanggal dokumen leading (Pengaturan › Pejabat)'];

    /**
     * @param  array<string, mixed>  $kegiatan
     * @return array{tanggal: string|null, asal: string|null, leading: string|null}
     */
    public static function untuk(array $kegiatan, ?int $tahun = null): array
    {
        $sendiri = self::rapikan($kegiatan['tanggal_dokumen'] ?? null);
        if ($sendiri !== null) {
            return ['tanggal' => $sendiri, 'asal' => 'kegiatan', 'leading' => null];
        }
        $leading = filled($kegiatan['leading'] ?? null) ? (string) $kegiatan['leading'] : null;
        $tanggal = $leading !== null ? self::leading($leading, $tahun ?? TahunKegiatan::dari($kegiatan)) : null;

        return $tanggal !== null ? ['tanggal' => $tanggal, 'asal' => 'leading', 'leading' => $leading] : ['tanggal' => null, 'asal' => null, 'leading' => null];
    }

    /** Tanggal (Y-m-d) yang berlaku untuk kegiatan, atau null. */
    public static function tanggal(array $kegiatan, ?int $tahun = null): ?string
    {
        return self::untuk($kegiatan, $tahun)['tanggal'];
    }

    /** Tanggal dokumen leading $kode tahun $tahun (Y-m-d) atau null. */
    public static function leading(string $kode, int $tahun): ?string
    {
        return self::rapikan(app(PejabatRepository::class)->tanggalDokumen($tahun)[$kode] ?? null);
    }

    /**
     * Galat tanggal dokumen leading untuk tahun $tahun (null = sah): tanggal Y-m-d dalam rentang yang sama dengan tanggal
     * dokumen kegiatan (1 Januari tahun sebelumnya s.d. 31 Desember tahun itu — ValidasiKegiatan).
     */
    public static function galat(?string $tanggal, int $tahun): ?string
    {
        if ($tanggal === null || $tanggal === '') {
            return null;
        }
        $nilai = self::rapikan($tanggal);
        if ($nilai === null) {
            return 'Tanggal dokumen tidak valid.';
        }
        if ($nilai < ($tahun - 1).'-01-01' || $nilai > "{$tahun}-12-31") {
            return 'Tanggal dokumen harus antara 1 Januari '.($tahun - 1)." dan 31 Desember {$tahun}.";
        }

        return null;
    }

    /** Teks terbaca, mis. "5 Januari 2026" (null bila kosong). */
    public static function teks(?string $tanggal): ?string
    {
        return $tanggal === null ? null : Format::tanggal($tanggal);
    }

    private static function rapikan(mixed $nilai): ?string
    {
        if (! is_string($nilai) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai)) {
            return null;
        }
        try {
            $tanggal = CarbonImmutable::createFromFormat('!Y-m-d', $nilai);
        } catch (Throwable) {
            return null;
        }

        return $tanggal !== false && $tanggal->format('Y-m-d') === $nilai ? $nilai : null;
    }
}
