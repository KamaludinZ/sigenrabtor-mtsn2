<?php

namespace App\Support\Kegiatan;

use App\Support\Stub\StubData;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Tahun anggaran pemilik kegiatan (activities.tahun_anggaran_id, PRD aturan 3): dicatat KegiatanRepository saat kegiatan
 * dibuat (`tahun_anggaran`). Kegiatan lama tanpa kolom itu dianggap milik tahun dibuatnya, lalu tahun aktif.
 */
final class TahunKegiatan
{
    /**
     * @param  array<string, mixed>  $kegiatan
     */
    public static function dari(array $kegiatan): int
    {
        if (filter_var($kegiatan['tahun_anggaran'] ?? null, FILTER_VALIDATE_INT)) {
            return (int) $kegiatan['tahun_anggaran'];
        }

        try {
            return isset($kegiatan['dibuat_pada']) ? CarbonImmutable::parse($kegiatan['dibuat_pada'])->year : StubData::tahunAktif()['tahun'];
        } catch (Throwable) {
            return StubData::tahunAktif()['tahun'];
        }
    }
}
