<?php

namespace App\Support\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Rab\StatusPagu;

/**
 * Daftar kegiatan beserta total RAB (PRD §11 "Rincian per Kegiatan") untuk endpoint `monitoring.kegiatan`: kegiatan yang
 * cocok dengan saringan Monitoring (SaringanMonitoring), diurutkan seperti tabel halaman (RingkasanAnggaran::
 * rincianKegiatan, kunci URUT_RINCIAN, arah bawaan per kunci), per halaman PER_HALAMAN — sama dengan Monitoring\Anggaran.
 * Tiap baris: identitas, leading, status berlabel, RAB per sumber dana (pagu, total, item, persen, status pagu & teksnya)
 * dan angka gabungan (pagu, total, selisih, persen, kelebihan, jumlah RAB, melebihi, tanpa pagu).
 */
final class DaftarKegiatanMonitoring
{
    public const PER_HALAMAN = 20;

    /**
     * @param  array<string, mixed>  $nilai  Saringan + `urut`, `arah`, `hal` mentah (parameter URL Monitoring).
     * @return array<string, mixed>
     */
    public static function untuk(array $nilai): array
    {
        $saringan = SaringanMonitoring::dari($nilai);
        $urut = is_string($nilai['urut'] ?? null) && isset(RingkasanAnggaran::URUT_RINCIAN[$nilai['urut']]) ? $nilai['urut'] : 'total';
        $arah = in_array($nilai['arah'] ?? null, ['asc', 'desc'], true) ? $nilai['arah'] : RingkasanAnggaran::URUT_RINCIAN[$urut][1];

        $semua = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), $saringan->untukRingkasan())->rincianKegiatan($urut, $arah);
        $jumlahHalaman = max(1, (int) ceil(count($semua) / self::PER_HALAMAN));
        $halaman = max(1, min((int) ($nilai['hal'] ?? 1), $jumlahHalaman));

        return [
            'tahun' => $saringan->tahunDipantau(),
            'saringan' => $saringan->query(),
            'urut' => $urut,
            'arah' => $arah,
            'halaman' => $halaman,
            'jumlah_halaman' => $jumlahHalaman,
            'jumlah' => count($semua),
            'data' => array_map(self::baris(...), array_slice($semua, ($halaman - 1) * self::PER_HALAMAN, self::PER_HALAMAN)),
        ];
    }

    /**
     * @param  array<string, mixed>  $b  Baris RingkasanAnggaran::rincianKegiatan().
     * @return array<string, mixed>
     */
    private static function baris(array $b): array
    {
        /** @var StatusKegiatan $status */
        $status = $b['status'];

        return [
            'id' => $b['id'],
            'nama' => $b['nama'],
            'kode_rktm' => $b['kode_rktm'],
            'leading' => $b['leading'],
            'leading_nama' => $b['leading_nama'],
            'status' => $status->value,
            'status_label' => $status->label(),
            'rab' => array_map(fn (array $r) => [
                'sumber' => $r['sumber'], 'pagu' => $r['pagu'], 'total' => $r['total'], 'item' => $r['item'], 'persen' => $r['persen'],
                'status_pagu' => self::status($r['status'])['status'], 'keterangan_pagu' => self::status($r['status'])['teks'],
            ], $b['rab']),
            ...array_intersect_key($b, array_flip(['pagu', 'total', 'selisih', 'persen', 'kelebihan', 'rab_jumlah', 'melebihi', 'tanpa_pagu'])),
            'tautan' => route('pemeriksaan.periksa', ['kegiatan' => $b['id']]),
        ];
    }

    /** @return array{status: string, teks: string} */
    private static function status(StatusPagu $s): array
    {
        return ['status' => $s->status, 'teks' => $s->teks()];
    }
}
