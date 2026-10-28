<?php

namespace App\Support\Rab;

use App\Contracts\DataPaguRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Pagu\DataPagu;
use App\Support\Stub\StubData;
use App\Support\Terbilang;

/**
 * Data sumber dana satu kegiatan (PRD F5 "Sumber Dana", F12) untuk endpoint `kegiatan.sumber_dana`: setiap SumberDana
 * dalam urutan baku — keterangan, label RAB, sebutan dokumen tahun aktif (sama dengan Pembiayaan KAK & header RAB),
 * dipilih/tidak, pagu & terbilangnya, total RAB, jumlah rincian, status pagu (StatusPagu), dan RAB yang tetap tersimpan
 * walau sumbernya tidak dipilih (`terlepas`, tidak dicetak) — beserta gabungan sumber yang dipilih, apakah isiannya masih
 * bisa diubah, dan Data Pagu kegiatan (tertaut `data_pagu_id`, atau nama sama di tahun kegiatan yang belum dipakai kegiatan
 * lain — DataPagu::pemakaian) dengan `pagu_data` & `selaras` per sumber (dipilih tepat bila Data Pagu berpagu, pagu sama).
 */
final class DataSumberDana
{
    /**
     * @param  array<string, mixed>  $kegiatan  Data KegiatanRepository.
     * @return array<string, mixed>
     */
    public static function untuk(array $kegiatan): array
    {
        $anggaran = [];
        foreach (AnggaranKegiatan::untuk($kegiatan, sertakanTerlepas: true) as $a) {
            $anggaran[$a->sumber] = $a;
        }
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
        $tahun = StubData::tahunAktif()['tahun'];
        $madrasah = StubData::madrasah()['nama_singkat'];
        $dataPagu = self::dataPagu($kegiatan);

        $sumber = array_map(function (SumberDana $s) use ($anggaran, $tahun, $madrasah, $dataPagu) {
            $a = $anggaran[$s->value] ?? null;
            $dipilih = $a?->dipilih ?? false;
            $pagu = $dipilih ? $a->keteranganPagu() : null;
            $paguData = $dataPagu['pagu'][$s->value] ?? null;

            return [
                'sumber' => $s->value,
                'kunci' => $s->kunci(),
                'keterangan' => $s->keterangan(),
                'label_rab' => $s->labelRab(),
                'sebutan_dokumen' => KalimatKak::sumberDana($s->value, $madrasah, $tahun),
                'dipilih' => $dipilih,
                'pagu' => $dipilih ? $a->pagu() : null,
                'pagu_terbilang' => $dipilih ? Terbilang::tampil($a->pagu()) : null,
                'total_rab' => $a?->total() ?? 0,
                'jumlah_item' => $a?->jumlahItem() ?? 0,
                'status_pagu' => $pagu?->status,
                'selisih_pagu' => $pagu?->selisih,
                'keterangan_pagu' => $pagu?->teks(),
                'terlepas' => ! $dipilih && ($a?->jumlahItem() ?? 0) > 0,
                'pagu_data' => $paguData,
                // Selaras: dipilih tepat bila Data Pagu berpagu untuk sumber ini, dan pagunya sama.
                'selaras' => $dataPagu ? $dipilih === ($paguData !== null) && (! $dipilih || $a->pagu() === $paguData) : null,
            ];
        }, SumberDana::cases());

        $gabungan = AnggaranKegiatan::gabungan(array_values($anggaran));

        return [
            'kegiatan_id' => (int) $kegiatan['id'],
            'nama_kegiatan' => (string) ($kegiatan['nama_kegiatan'] ?? ''),
            'dapat_diubah' => $status->dapatDiedit(),
            'sumber' => $sumber,
            'dipilih' => array_values(array_column(array_filter($sumber, fn (array $s) => $s['dipilih']), 'sumber')),
            'gabungan' => $gabungan + ['terbilang' => Terbilang::tampil($gabungan['total'])],
            'data_pagu' => $dataPagu ? ['id' => $dataPagu['id'], 'nama_kegiatan' => $dataPagu['nama_kegiatan'], 'tertaut' => ($kegiatan['data_pagu_id'] ?? null) === $dataPagu['id']] : null,
        ];
    }

    /**
     * Data Pagu kegiatan: yang ditautkan lewat data_pagu_id, atau yang namanya sama di tahun kegiatan (DataPagu::pemakaian).
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, mixed>|null
     */
    private static function dataPagu(array $kegiatan): ?array
    {
        $repo = app(DataPaguRepository::class);
        if (filled($kegiatan['data_pagu_id'] ?? null) && ($tertaut = $repo->temukan((string) $kegiatan['data_pagu_id']))) {
            return $tertaut;
        }
        $semua = $repo->semua(TahunKegiatan::dari($kegiatan));
        $pemakaian = DataPagu::pemakaian($semua);

        return collect($semua)->first(fn (array $p) => ($pemakaian[$p['id']]['id'] ?? null) === (int) $kegiatan['id']);
    }
}
