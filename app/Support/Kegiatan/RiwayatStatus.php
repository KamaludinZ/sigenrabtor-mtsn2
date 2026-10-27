<?php

namespace App\Support\Kegiatan;

use App\Enums\StatusKegiatan;

/**
 * Entri riwayat_status kegiatan (PRD N7: siapa & kapan; calon tabel pemeriksaan_dokumen untuk keputusan admin):
 * {aksi, status, oleh, peran, pada} ditambah catatan pemeriksa bila ada. Dipakai panel status Data Kegiatan dan
 * halaman Pemeriksaan agar bentuknya sama.
 */
final class RiwayatStatus
{
    public const MAKS_CATATAN = 2000;

    /**
     * @param  array{name: string, role: string}  $pengguna
     * @return array{aksi: string, status: string, oleh: string, peran: string, pada: string, catatan?: string}
     */
    public static function entri(string $aksi, StatusKegiatan $ke, array $pengguna, ?string $catatan = null): array
    {
        $entri = [
            'aksi' => $aksi,
            'status' => $ke->value,
            'oleh' => $pengguna['name'],
            'peran' => $pengguna['role'],
            'pada' => now()->toIso8601String(),
        ];

        $catatan = self::rapikanCatatan((string) $catatan);

        return $catatan === '' ? $entri : $entri + ['catatan' => $catatan];
    }

    /** Spasi tiap baris dirapikan, baris kosong beruntun jadi satu, tepi dibuang; paragraf tetap. */
    public static function rapikanCatatan(string $catatan): string
    {
        $baris = array_map(fn (string $b) => trim(preg_replace('/[ \t]+/u', ' ', $b)), preg_split('/\R/u', $catatan));

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $baris)));
    }
}
