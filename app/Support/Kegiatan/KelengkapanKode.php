<?php

namespace App\Support\Kegiatan;

use App\Livewire\Forms\KegiatanForm;
use App\Support\Master\IkskPerSk;
use App\Support\MasterKode;
use App\Support\Stub\StubData;

/**
 * Kelengkapan kode master kegiatan di server (PRD F7 & aturan 7 "kode dipilih dari master tahun aktif"): tiap kode —
 * RKTM, SK, IKSK, EDM, 5 Budaya Mutu — berstatus `terisi`, `kosong` (pesan bila wajib, KegiatanForm::KODE_WAJIB),
 * `asing` (tidak ada di master tahun itu), atau `tidak_sesuai` (IKSK bukan indikator SK-nya, IkskPerSk). Satu sumber
 * untuk checklist isian Pratinjau (ChecklistIsian) dan kaidah dokumen (KaidahDokumen).
 */
final class KelengkapanKode
{
    /** Kode master kegiatan → [label, kunci cari di master]. */
    public const KODE = [
        'kode_rktm' => ['Kode RKTM', 'kode'],
        'kode_sk' => ['Kode SK', 'kode'],
        'kode_iksk' => ['IKSK', 'kode'],
        'kode_edm' => ['Kode EDM', 'kode'],
        'budaya_mutu' => ['5 Budaya Mutu', 'nomor'],
    ];

    /**
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, array{label: string, kode: string|null, wajib: bool, status: string, pesan: string|null}>
     */
    public static function periksa(array $kegiatan, ?int $tahun = null): array
    {
        $tahun ??= StubData::tahunAktif()['tahun'];
        $master = [
            'kode_rktm' => MasterKode::kodeRktm($tahun), 'kode_sk' => MasterKode::kodeSk($tahun), 'kode_iksk' => MasterKode::kodeIksk(null, $tahun),
            'kode_edm' => MasterKode::kodeEdm($tahun), 'budaya_mutu' => MasterKode::budayaMutu($tahun),
        ];
        $hasil = [];

        foreach (self::KODE as $isian => [$label, $kunci]) {
            $kode = blank($kegiatan[$isian] ?? null) ? null : (string) $kegiatan[$isian];
            $wajib = isset(KegiatanForm::KODE_WAJIB[$isian]);

            [$status, $pesan] = match (true) {
                $kode === null => ['kosong', $wajib ? KegiatanForm::KODE_WAJIB[$isian] : null],
                MasterKode::cari($master[$isian], $kode, $kunci) === null => ['asing', "{$kode} tidak ada di master TA {$tahun}."],
                $isian === 'kode_iksk' && ($galat = IkskPerSk::galat($kegiatan['kode_sk'] ?? null, $kode, $tahun)) !== null => ['tidak_sesuai', $galat],
                default => ['terisi', null],
            };

            $hasil[$isian] = ['label' => $label, 'kode' => $kode, 'wajib' => $wajib, 'status' => $status, 'pesan' => $pesan];
        }

        return $hasil;
    }

    /**
     * Kode yang menghalangi pengajuan: wajib tetapi kosong, tidak ada di master, atau IKSK bukan indikator SK-nya.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, string> Isian → pesan.
     */
    public static function galatPengajuan(array $kegiatan, ?int $tahun = null): array
    {
        return array_map(fn (array $b) => $b['pesan'], array_filter(self::periksa($kegiatan, $tahun), fn (array $b) => $b['status'] !== 'terisi' && $b['pesan'] !== null));
    }
}
