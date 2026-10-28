<?php

namespace App\Support\Kegiatan;

/**
 * Matrik tahapan KAK/TOR (PRD F10, bagian 4 "Tahapan Kegiatan"): tiga tahapan baku — Persiapan, Pelaksanaan,
 * Evaluasi dan Pelaporan — masing-masing berisi butir kegiatan yang ditandai per bulan 1–12, seperti tabel matrik
 * pada KAK madrasah ("v" di kolom bulan).
 */
final readonly class MatrikTahapan
{
    public const TAHAPAN = ['persiapan' => 'Persiapan', 'pelaksanaan' => 'Pelaksanaan', 'evaluasi_pelaporan' => 'Evaluasi dan Pelaporan'];

    /** Panjang maksimal uraian satu butir (aturan isian & CHECK matrik_tahapan_uraian_check). */
    public const MAKS_URAIAN = 150;

    /** Butir baku tiap tahapan (template KAK madrasah); dapat diubah per kegiatan. */
    public const BUTIR_BAKU = [
        'persiapan' => ['Rapat dan pembagian job deskripsi', 'Penyerahan anggaran per seksi'],
        'pelaksanaan' => ['Persiapan pelaksanaan', 'Pengawasan kegiatan'],
        'evaluasi_pelaporan' => ['Penyusunan laporan', 'Penyerahan laporan'],
    ];

    /**
     * @param  array<string, list<array{uraian: string, bulan: list<int>}>>  $butir  Kunci tahapan → butirnya.
     */
    public function __construct(
        public array $butir,
    ) {}

    /**
     * Matrik tersimpan di data kegiatan (kunci "matrik"); tahapan yang belum pernah diisi memakai butir baku tanpa tanda.
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function dari(array $kegiatan): self
    {
        $tersimpan = is_array($kegiatan['matrik'] ?? null) ? $kegiatan['matrik'] : [];
        $butir = [];

        foreach (array_keys(self::TAHAPAN) as $kunci) {
            $daftar = is_array($tersimpan[$kunci] ?? null)
                ? $tersimpan[$kunci]
                : array_map(fn (string $uraian) => ['uraian' => $uraian, 'bulan' => []], self::BUTIR_BAKU[$kunci]);

            $butir[$kunci] = array_values(array_map(fn ($b) => [
                'uraian' => trim((string) ($b['uraian'] ?? '')),
                'bulan' => self::rapikanBulan($b['bulan'] ?? []),
            ], array_filter($daftar, 'is_array')));
        }

        return new self($butir);
    }

    /**
     * Bulan yang ditandai pada satu tahapan (gabungan semua butirnya), urut naik.
     *
     * @return list<int>
     */
    public function bulanTahapan(string $tahapan): array
    {
        return self::rapikanBulan(array_merge([], ...array_column($this->butir[$tahapan] ?? [], 'bulan')));
    }

    /** Jumlah tanda (butir × bulan) di seluruh matrik. */
    public function jumlahTanda(): int
    {
        return array_sum(array_map(fn (array $daftar) => array_sum(array_map(fn ($b) => count($b['bulan']), $daftar)), $this->butir));
    }

    /** Lengkap bila setiap tahapan punya minimal satu bulan bertanda (syarat cek kelengkapan saat cetak, PRD aturan 8). */
    public function lengkap(): bool
    {
        foreach (array_keys(self::TAHAPAN) as $kunci) {
            if ($this->bulanTahapan($kunci) === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<int>
     */
    public static function rapikanBulan(mixed $bulan): array
    {
        $bulan = array_values(array_unique(array_filter(array_map('intval', (array) $bulan), fn (int $b) => $b >= 1 && $b <= 12)));
        sort($bulan);

        return $bulan;
    }
}
