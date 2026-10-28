<?php

namespace App\Support\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Support\Stub\StubPengaturan;

/**
 * Agregasi belanja per kelompok & per operator (PRD §11 Monitoring) untuk endpoint `monitoring.agregasi` — kegiatan
 * yang cocok dengan saringan Monitoring (SaringanMonitoring), lalu RingkasanAnggaran::perKelompok() (RekapKelompok:
 * total & porsi per kelompok belanja per sumber dana, jumlah item & kegiatan, item terbesar `MAKS_ITEM` per kelompok,
 * rekap per kode akun) dan perOperator() (pembuat kegiatan dari akun aplikasi: jumlah per status, leading, perubahan
 * terakhir, angka anggaran gabungan & per sumber dana; "Pembuat tidak tercatat" untuk yang tidak dikenal).
 */
final class AgregasiBelanja
{
    /** Item terbesar yang disertakan per kelompok (daftar lengkap ada di rekap halaman/ekspor). */
    public const MAKS_ITEM = 10;

    /**
     * @param  array<string, mixed>  $nilai  Saringan mentah (parameter URL Monitoring).
     * @return array<string, mixed>
     */
    public static function untuk(array $nilai): array
    {
        $saringan = SaringanMonitoring::dari($nilai);
        $ringkasan = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), $saringan->untukRingkasan());
        $rekap = $ringkasan->perKelompok();

        return [
            'tahun' => $saringan->tahunDipantau(),
            'saringan' => $saringan->query(),
            'jumlah_kegiatan' => $ringkasan->jumlahKegiatan(),
            'kelompok' => [
                'sumber' => $rekap['sumber'],
                'total' => $rekap['total'],
                'total_per_sumber' => $rekap['totalPerSumber'],
                'jumlah_item' => $rekap['jumlahItem'],
                'kelompok' => array_map(fn (array $k) => [
                    'kunci' => $k['kunci'],
                    'label' => $k['label'],
                    'per_sumber' => $k['perSumber'],
                    'total' => $k['total'],
                    'persen' => $k['persen'],
                    'jumlah_item' => $k['jumlahItem'],
                    'jumlah_kegiatan' => $k['kegiatan'],
                    'item_terbesar' => array_map(
                        fn (array $i) => ['kegiatan_id' => $i['kegiatan_id'], 'kegiatan' => $i['kegiatan'], 'sumber' => $i['sumber'], 'kode' => $i['kode'] ?? '', 'uraian' => $i['uraian'], 'jumlah' => $i['jumlah']],
                        array_slice($k['item'], 0, self::MAKS_ITEM),
                    ),
                ], $rekap['kelompok']),
                'kode_akun' => array_map(fn (array $k) => ['kode' => $k['kode'], 'label' => $k['label'], 'per_sumber' => $k['perSumber'], 'total' => $k['total']], $rekap['kode']),
            ],
            'operator' => array_map(fn (array $o) => [
                'user_id' => $o['user_id'], 'nama' => $o['nama'], 'peran' => $o['peran'], 'jabatan' => $o['jabatan'],
                'jumlah' => $o['jumlah'], 'per_status' => $o['perStatus'], 'leading' => $o['leading'], 'terakhir' => $o['terakhir'],
                ...array_intersect_key($o, array_flip(['pagu', 'total', 'selisih', 'persen', 'rab', 'melebihi', 'tanpa_pagu'])),
                'sumber' => $o['sumber'],
            ], $ringkasan->perOperator(StubPengaturan::pengguna())),
        ];
    }
}
