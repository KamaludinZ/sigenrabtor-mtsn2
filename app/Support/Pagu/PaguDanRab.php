<?php

namespace App\Support\Pagu;

use App\Contracts\KegiatanRepository;
use App\Enums\SumberDana;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;

/**
 * Pagu dan RAB per Data Pagu (PRD F12 "Batas pagu") untuk endpoint `pagu.rab`: setiap data pagu yang terlihat akun
 * (DataPagu::untuk) beserta kegiatan pemakainya (DataPagu::pemakaian) dan, per sumber dana, pagu Data Pagu, pagu yang
 * tercatat di kegiatan (`selaras` = keduanya terisi dan sama), total RAB, serta status pagu (StatusPagu: total RAB terhadap pagu kegiatan).
 * Angka RAB kegiatan yang tidak boleh dibaca akun (AksesKegiatan, mis. data pagu tanpa leading yang dipakai leading
 * lain) tidak disertakan (`kegiatan.boleh_lihat` = false). Ringkasan: jumlah data, yang sudah dipakai, dan total pagu &
 * RAB per sumber dana (RAB hanya dari kegiatan yang boleh dibaca).
 */
final class PaguDanRab
{
    /**
     * @param  array{id: int, role: string, leadings: list<string>}  $pengguna
     * @return array{tahun: int, ringkasan: array<string, mixed>, data: list<array<string, mixed>>}
     */
    public static function untuk(array $pengguna, ?int $tahun = null): array
    {
        $tahun ??= StubData::tahunAktif()['tahun'];
        $dataPagu = DataPagu::untuk($pengguna, $tahun);
        $repo = app(KegiatanRepository::class);
        $semuaKegiatan = $repo->semua();
        $pemakaian = DataPagu::pemakaian($dataPagu, $semuaKegiatan);
        $kegiatan = array_filter(
            array_column($semuaKegiatan, null, 'id'),
            fn (array $k) => AksesKegiatan::bolehLihat($k, $pengguna),
        );
        AnggaranKegiatan::muat(array_values(array_filter(array_map(fn ($p) => $p ? $kegiatan[$p['id']] ?? null : null, $pemakaian))));

        $nol = array_fill_keys(SumberDana::urutan(), 0);
        $ringkasan = ['jumlah' => count($dataPagu), 'dipakai' => 0, 'pagu' => $nol, 'total_rab' => $nol];
        $data = [];

        foreach ($dataPagu as $p) {
            $pemakai = $pemakaian[$p['id']] ?? null;
            $anggaran = $pemakai && isset($kegiatan[$pemakai['id']]) ? AnggaranKegiatan::perSumber($kegiatan[$pemakai['id']]) : [];
            $ringkasan['dipakai'] += $pemakai ? 1 : 0;
            $pemakai = $pemakai ? $pemakai + ['boleh_lihat' => isset($kegiatan[$pemakai['id']])] : null;

            $sumber = [];
            foreach (SumberDana::urutan() as $s) {
                $a = $anggaran[$s] ?? null;
                if ($p['pagu'][$s] === null && $a === null) {
                    continue;
                }
                $ringkasan['pagu'][$s] += (int) $p['pagu'][$s];
                $ringkasan['total_rab'][$s] += $a?->total() ?? 0;
                $sumber[] = [
                    'sumber' => $s,
                    'pagu_data' => $p['pagu'][$s],
                    'pagu_kegiatan' => $a?->pagu(),
                    'selaras' => $a?->pagu() !== null && $a->pagu() === $p['pagu'][$s],
                    'total_rab' => $a?->total(),
                    'selisih' => $a?->keteranganPagu()->selisih,
                    'status_pagu' => $a?->keteranganPagu()->status,
                    'keterangan_pagu' => $a?->keteranganPagu()->teks(),
                ];
            }

            $data[] = [
                'id' => $p['id'],
                'nama_kegiatan' => $p['nama_kegiatan'],
                'leading' => $p['leading'],
                'asal' => $p['asal'],
                'kegiatan' => $pemakai,
                'sumber' => $sumber,
            ];
        }

        return ['tahun' => $tahun, 'ringkasan' => $ringkasan, 'data' => $data];
    }
}
