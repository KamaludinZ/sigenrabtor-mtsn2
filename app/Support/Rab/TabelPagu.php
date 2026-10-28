<?php

namespace App\Support\Rab;

/**
 * Tabel Pagu dan RAB (PRD F12): satu baris per sumber dana terpilih — pagu, total RAB, selisih, pemakaian pagu,
 * dan kondisinya — serta baris jumlah kegiatan. Setiap RAB dibandingkan dengan pagunya sendiri; baris jumlah
 * hanya ringkasan (RAB yang melebihi pagu tidak "tertutup" oleh sisa pagu sumber lain).
 */
final readonly class TabelPagu
{
    /**
     * @param  list<AnggaranSumber>  $anggaran  Sumber dana terpilih, urut baku.
     */
    public function __construct(
        public array $anggaran,
    ) {}

    /**
     * @param  list<AnggaranSumber>  $anggaran  Hasil AnggaranKegiatan::untuk(); RAB sumber yang dilepas diabaikan.
     */
    public static function dari(array $anggaran): self
    {
        return new self(array_values(array_filter($anggaran, fn (AnggaranSumber $a) => $a->dipilih)));
    }

    /**
     * @return list<array{sumber: string, pagu: int|null, total: int, selisih: int|null, persen: float|null, jumlah_item: int, status: StatusPagu}>
     */
    public function baris(): array
    {
        return array_map(fn (AnggaranSumber $a) => [
            'sumber' => $a->sumber,
            'pagu' => $a->pagu(),
            'total' => $a->total(),
            'selisih' => $a->keteranganPagu()->selisih,
            'persen' => self::persen($a->total(), $a->pagu()),
            'jumlah_item' => $a->jumlahItem(),
            'status' => $a->keteranganPagu(),
        ], $this->anggaran);
    }

    /**
     * Baris jumlah kegiatan (SelisihPagu::dariAnggaran). Pagu = jumlah pagu yang sudah diisi; selisih & pemakaian hanya
     * dihitung bila ada RAB dan semua pagu terisi; kelebihan = jumlah kelebihan tiap RAB atas pagunya sendiri.
     *
     * @return array{pagu: int, total: int, selisih: int|null, persen: float|null, jumlah_item: int, pagu_kosong: int, melebihi: int, kelebihan: int}
     */
    public function jumlah(): array
    {
        $gabungan = SelisihPagu::dariAnggaran($this->anggaran);

        return [
            'pagu' => $gabungan->pagu,
            'total' => $gabungan->total,
            'selisih' => $gabungan->selisih,
            'persen' => $gabungan->persen,
            'jumlah_item' => array_sum(array_map(fn (AnggaranSumber $a) => $a->jumlahItem(), $this->anggaran)),
            'pagu_kosong' => $gabungan->tanpaPagu,
            'melebihi' => $gabungan->melebihi,
            'kelebihan' => $gabungan->kelebihan,
        ];
    }

    /** Kalimat kondisi seluruh RAB kegiatan untuk kepala tabel. */
    public function ringkasan(): string
    {
        $jumlah = $this->jumlah();
        $bagian = [];

        if ($jumlah['melebihi'] > 0) {
            $bagian[] = "{$jumlah['melebihi']} RAB melebihi pagu";
        }
        if ($jumlah['pagu_kosong'] > 0) {
            $bagian[] = "{$jumlah['pagu_kosong']} pagu belum diisi";
        }

        return $bagian === [] ? 'Semua RAB dalam batas pagu' : implode(' · ', $bagian);
    }

    /** 'bahaya' bila ada RAB melebihi pagu, 'netral' bila ada pagu kosong, selain itu 'aman'. */
    public function nada(): string
    {
        $jumlah = $this->jumlah();

        return match (true) {
            $jumlah['melebihi'] > 0 => 'bahaya',
            $jumlah['pagu_kosong'] > 0 => 'netral',
            default => 'aman',
        };
    }

    /**
     * Pemakaian pagu dalam persen, satu desimal; null bila pagu belum diisi (atau nol).
     * Dibulatkan menjauhi 100 sehingga "100%" hanya tampil bila total tepat sama dengan pagu (Rp1 di bawah pagu = 99,9%).
     */
    public static function persen(int $total, ?int $pagu): ?float
    {
        if (! $pagu) {
            return null;
        }

        $perseribu = $total <= $pagu ? intdiv($total * 1000, $pagu) : intdiv($total * 1000 + $pagu - 1, $pagu);

        return $perseribu / 10;
    }
}
