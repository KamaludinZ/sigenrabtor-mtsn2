<?php

namespace App\Support\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;

/**
 * Data halaman Monitoring Anggaran terfilter & terpaginasi dalam satu permintaan (endpoint `monitoring.data`): saringan
 * SaringanMonitoring (label, rentang terbalik), ringkasan (jumlah, tanpa jadwal, per status, gabungan & per sumber
 * dana — RingkasanAnggaran::sel), tabel Total per leading (urutan `urut_leading`/`arah_leading` = urutLeading(),
 * dilepas bila kolomnya tersembunyi saringan sumber — `urutLeadingSah()`; saringan leading = barisnya saja), dan satu
 * halaman rincian kegiatan (urutan DaftarKegiatanMonitoring::urutan, `hal`, ukuran `per` dari PER_HALAMAN).
 */
final class DataMonitoring
{
    /** Ukuran halaman rincian yang boleh diminta. */
    public const PER_HALAMAN = [10, 20, 50, 100];

    /**
     * Urutan Total per leading yang sah & tampil: kode/jumlah selalu; kolom sumber dana hanya bila sumbernya tidak
     * disaring keluar.
     */
    public static function urutLeadingSah(string $urut, string $sumber): bool
    {
        if (! isset(RingkasanAnggaran::urutLeading()[$urut])) {
            return false;
        }
        $grup = explode('_', $urut)[0];

        return in_array($grup, ['kode', 'jumlah'], true) || $sumber === '' || $grup === mb_strtolower($sumber);
    }

    /**
     * @param  array<string, mixed>  $nilai  Parameter URL Monitoring (+ `per`).
     * @return array<string, mixed>
     */
    public static function untuk(array $nilai): array
    {
        $saringan = SaringanMonitoring::dari($nilai);
        $ringkasan = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), $saringan->untukRingkasan());

        $urutLeading = is_string($nilai['urut_leading'] ?? null) && self::urutLeadingSah($nilai['urut_leading'], $saringan->sumber) ? $nilai['urut_leading'] : 'kode';
        $arahLeading = in_array($nilai['arah_leading'] ?? null, ['asc', 'desc'], true) ? $nilai['arah_leading'] : RingkasanAnggaran::urutLeading()[$urutLeading][1];
        $perLeading = array_values(array_filter(
            $ringkasan->perLeading(urut: $urutLeading, arah: $arahLeading),
            fn (array $b) => $saringan->leading === '' || $b['kode'] === $saringan->leading,
        ));

        [$urut, $arah] = DaftarKegiatanMonitoring::urutan($nilai);
        $per = in_array((int) ($nilai['per'] ?? 0), self::PER_HALAMAN, true) ? (int) $nilai['per'] : DaftarKegiatanMonitoring::PER_HALAMAN;
        $rincian = $ringkasan->rincianKegiatan($urut, $arah);
        $jumlahHalaman = max(1, (int) ceil(count($rincian) / $per));
        $halaman = max(1, min((int) ($nilai['hal'] ?? 1), $jumlahHalaman));

        return [
            'tahun' => $saringan->tahunDipantau(),
            'saringan' => $saringan->query(),
            'label_saringan' => $saringan->label(),
            'rentang_terbalik' => $saringan->rentangTerbalik(),
            'ringkasan' => [
                'jumlah_kegiatan' => $ringkasan->jumlahKegiatan(),
                'tanpa_jadwal' => $ringkasan->tanpaJadwal,
                'per_status' => array_map(fn (StatusKegiatan $s) => ['status' => $s->value, 'label' => $s->label(), 'jumlah' => $ringkasan->perStatus()[$s->value]], StatusKegiatan::cases()),
                'gabungan' => RingkasanAnggaran::sel($ringkasan->total()),
                'per_sumber' => array_map(fn (array $s) => RingkasanAnggaran::sel($s), array_intersect_key($ringkasan->perSumber(), array_flip(SumberDana::urutan()))),
            ],
            'per_leading' => [
                'urut' => $urutLeading,
                'arah' => $arahLeading,
                'data' => array_map(fn (array $b) => [
                    'kode' => $b['kode'], 'nama_unit' => $b['nama_unit'], 'jumlah' => $b['jumlah'], 'per_status' => $b['perStatus'],
                    ...array_intersect_key($b, array_flip(['pagu', 'total', 'selisih', 'persen', 'rab', 'melebihi', 'tanpa_pagu'])),
                    'sumber' => $b['sumber'],
                ], $perLeading),
            ],
            'kegiatan' => [
                'urut' => $urut,
                'arah' => $arah,
                'halaman' => $halaman,
                'per_halaman' => $per,
                'jumlah_halaman' => $jumlahHalaman,
                'jumlah' => count($rincian),
                'data' => array_map(DaftarKegiatanMonitoring::baris(...), array_slice($rincian, ($halaman - 1) * $per, $per)),
            ],
        ];
    }
}
