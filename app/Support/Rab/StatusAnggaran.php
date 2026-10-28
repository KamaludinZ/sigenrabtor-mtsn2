<?php

namespace App\Support\Rab;

/**
 * Status anggaran (PRD F12 "Batas pagu", aturan 5) — satu-satunya aturan kondisi anggaran per RAB dan per kegiatan,
 * dipakai Data Kegiatan (kolom & saringan kondisi), Monitoring (jumlah RAB per kondisi), dan kembarannya di database
 * (view `status_anggaran_kegiatan`). Per RAB (`rab()`): pagu belum diisi → `tanpa_pagu`, tanpa rincian → `rab_kosong`,
 * selain itu StatusPagu (`sesuai`/`sisa`/`melebihi`). Per kegiatan (`kegiatan()`, hanya sumber dana yang dipilih):
 * tanpa sumber dana → `tanpa_sumber`, semua RAB kosong → `rab_kosong`, ada yang melebihi → `melebihi`, ada yang tanpa pagu
 * → `tanpa_pagu`, semua tepat pagu → `sesuai`, selain itu `sisa`. `dari()` merangkum satu kegiatan beserta RAB yang
 * melebihi pagu (BatasPagu: menghalangi pengajuan & persetujuan).
 */
final readonly class StatusAnggaran
{
    /** Kondisi satu RAB terhadap pagunya. */
    public const KONDISI_RAB = [
        'sesuai' => 'Sesuai pagu',
        'sisa' => 'Di bawah pagu',
        'melebihi' => 'Melebihi pagu',
        'tanpa_pagu' => 'Pagu belum diisi',
        'rab_kosong' => 'RAB belum diisi',
    ];

    /** Kondisi anggaran satu kegiatan (saringan Data Kegiatan memakai semua kecuali `tanpa_sumber`). */
    public const KONDISI_KEGIATAN = [
        ...self::KONDISI_RAB,
        'tanpa_sumber' => 'Sumber dana belum dipilih',
    ];

    /** Nada tampilan: 'aman', 'perhatian' (perlu dilengkapi), 'bahaya' (melebihi pagu), atau 'netral'. */
    public const NADA = [
        'sesuai' => 'aman',
        'sisa' => 'aman',
        'melebihi' => 'bahaya',
        'tanpa_pagu' => 'perhatian',
        'rab_kosong' => 'perhatian',
        'tanpa_sumber' => 'netral',
    ];

    /**
     * @param  string  $kondisi  Kunci KONDISI_KEGIATAN.
     * @param  array<string, string>  $perSumber  Sumber dana dipilih → kunci KONDISI_RAB.
     * @param  array<string, string>  $terlampaui  Sumber dana → pesan BatasPagu.
     */
    public function __construct(
        public string $kondisi,
        public array $perSumber,
        public array $terlampaui,
    ) {}

    /** @param  array<string, mixed>  $kegiatan  Data KegiatanRepository. */
    public static function dari(array $kegiatan): self
    {
        return self::dariAnggaran(AnggaranKegiatan::untuk($kegiatan));
    }

    /** @param  list<AnggaranSumber>  $anggaran */
    public static function dariAnggaran(array $anggaran): self
    {
        $dipilih = array_values(array_filter($anggaran, fn (AnggaranSumber $a) => $a->dipilih));
        $perSumber = [];
        foreach ($dipilih as $a) {
            $perSumber[$a->sumber] = self::rab($a);
        }

        return new self(self::kegiatan($dipilih), $perSumber, BatasPagu::terlampaui($dipilih));
    }

    public static function rab(AnggaranSumber $a): string
    {
        return match (true) {
            $a->pagu() === null => 'tanpa_pagu',
            $a->jumlahItem() === 0 => 'rab_kosong',
            default => $a->statusPagu(),
        };
    }

    /** @param  list<AnggaranSumber>  $anggaran */
    public static function kegiatan(array $anggaran): string
    {
        $anggaran = array_filter($anggaran, fn (AnggaranSumber $a) => $a->dipilih);
        $status = array_map(fn (AnggaranSumber $a) => $a->statusPagu(), $anggaran);

        return match (true) {
            $anggaran === [] => 'tanpa_sumber',
            array_sum(array_map(fn (AnggaranSumber $a) => $a->jumlahItem(), $anggaran)) === 0 => 'rab_kosong',
            in_array('melebihi', $status, true) => 'melebihi',
            in_array('tanpa_pagu', $status, true) => 'tanpa_pagu',
            array_values(array_unique($status)) === ['sesuai'] => 'sesuai',
            default => 'sisa',
        };
    }

    public function label(): string
    {
        return self::KONDISI_KEGIATAN[$this->kondisi];
    }

    public function nada(): string
    {
        return self::NADA[$this->kondisi];
    }

    /** Pagu tidak menghalangi pengajuan/persetujuan (tidak ada RAB terpilih yang melebihi pagunya). */
    public function bolehDiajukan(): bool
    {
        return $this->terlampaui === [];
    }
}
