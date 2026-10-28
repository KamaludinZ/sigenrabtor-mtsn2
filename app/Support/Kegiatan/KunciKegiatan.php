<?php

namespace App\Support\Kegiatan;

use App\Contracts\TahunAnggaranRepository;
use App\Enums\StatusKegiatan;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Siapa yang boleh mengubah isian kegiatan (PRD F6 "Tahun lalu dikunci" & aturan 9): isian hanya bisa diubah selama
 * status kegiatan Draf/Dikembalikan (StatusKegiatan::dapatDiedit) dan tahun anggaran pemilik kegiatan (TahunKegiatan)
 * tidak terkunci — kegiatan tahun yang dikunci hanya bisa dilihat, termasuk perubahan statusnya. Kegiatan baru memakai
 * tahun aktif. Dipakai semua layanan simpan & tab ruang kerja kegiatan, banner terkunci, dan panel status/keputusan.
 */
final class KunciKegiatan
{
    /**
     * Tahun anggaran kegiatan bila tahun itu terkunci; null bila terbuka (atau tidak terdaftar).
     *
     * @param  array<string, mixed>  $kegiatan  Data kegiatan ([] = kegiatan baru, tahun aktif).
     */
    public static function tahunTerkunci(array $kegiatan): ?int
    {
        $tahun = TahunKegiatan::dari($kegiatan);
        $data = collect(app(TahunAnggaranRepository::class)->semua())->firstWhere('tahun', $tahun);

        return ($data['is_locked'] ?? false) ? $tahun : null;
    }

    /** @param  array<string, mixed>  $kegiatan */
    public static function dapatDiubah(array $kegiatan): bool
    {
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;

        return $status->dapatDiedit() && self::tahunTerkunci($kegiatan) === null;
    }

    public static function pesanTahun(int $tahun): string
    {
        return "Tahun Anggaran {$tahun} terkunci — kegiatan tahun itu hanya bisa dilihat.";
    }

    /**
     * Tolak perubahan bila tahun anggaran kegiatan terkunci (pemeriksaan status tetap di pemanggil, dengan pesannya sendiri).
     *
     * @param  array<string, mixed>  $kegiatan
     *
     * @throws AuthorizationException
     */
    public static function pastikanTahunTerbuka(array $kegiatan): void
    {
        if (($tahun = self::tahunTerkunci($kegiatan)) !== null) {
            throw new AuthorizationException(self::pesanTahun($tahun));
        }
    }
}
