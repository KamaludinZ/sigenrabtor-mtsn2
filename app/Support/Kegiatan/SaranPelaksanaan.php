<?php

namespace App\Support\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Support\Stub\StubData;

/**
 * Saran isian tempat & jenis peserta pada bagian Pelaksanaan (datalist; isian tetap bebas).
 *
 * Isian yang pernah dipakai kegiatan lain didahulukan supaya penulisan seragam antar dokumen
 * (mis. "Aula Madrasah", bukan "aula mts"), disusul saran baku madrasah. Duplikat beda huruf besar/kecil dibuang.
 */
final class SaranPelaksanaan
{
    public const BATAS_RIWAYAT = 8;

    /**
     * @return array{tempat: list<array{nilai: string, riwayat: bool}>, peserta: list<array{nilai: string, riwayat: bool}>}
     */
    public static function untuk(KegiatanRepository $repo): array
    {
        $baku = StubData::saranPelaksanaan();

        return [
            'tempat' => self::gabung($repo->riwayatIsian('tempat', self::BATAS_RIWAYAT), $baku['tempat']),
            'peserta' => self::gabung($repo->riwayatIsian('peserta_jenis', self::BATAS_RIWAYAT), $baku['peserta']),
        ];
    }

    /**
     * @param  list<string>  $riwayat
     * @param  list<string>  $baku
     * @return list<array{nilai: string, riwayat: bool}>
     */
    private static function gabung(array $riwayat, array $baku): array
    {
        $hasil = [];

        foreach ([[$riwayat, true], [$baku, false]] as [$daftar, $dariRiwayat]) {
            foreach ($daftar as $nilai) {
                $nilai = trim($nilai);
                $kunci = mb_strtolower($nilai);

                if ($kunci !== '' && ! isset($hasil[$kunci])) {
                    $hasil[$kunci] = ['nilai' => $nilai, 'riwayat' => $dariRiwayat];
                }
            }
        }

        return array_values($hasil);
    }
}
