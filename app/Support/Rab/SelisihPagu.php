<?php

namespace App\Support\Rab;

/**
 * Selisih pagu dan RAB (PRD F12, aturan 5) — satu-satunya aturan selisih untuk satu RAB maupun gabungan beberapa RAB
 * (tabel Pagu & RAB, Monitoring, Dashboard, endpoint; kembaran database: monitoring_rab.selisih_pagu & kelebihan):
 * - selisih = pagu − total (negatif = melebihi); gabungan hanya bila ada RAB dan semua pagunya terisi — selain itu null,
 *   karena RAB tanpa pagu tidak bisa dibandingkan;
 * - persen pemakaian = TabelPagu::persen (dibulatkan menjauhi 100), null bila selisih null atau pagu 0;
 * - kelebihan = jumlah kelebihan tiap RAB atas pagunya sendiri dan sisa = jumlah sisa tiap RAB berpagu: kelebihan satu
 *   RAB tidak pernah "tertutup" sisa RAB lain;
 * - melebihi / tanpa_pagu = jumlah RAB-nya.
 */
final readonly class SelisihPagu
{
    public function __construct(
        public int $pagu,
        public int $total,
        public ?int $selisih,
        public ?float $persen,
        public int $kelebihan,
        public int $sisa,
        public int $rab,
        public int $melebihi,
        public int $tanpaPagu,
    ) {}

    /** Satu RAB: total terhadap pagunya (null = pagu belum diisi). */
    public static function rab(int $total, ?int $pagu): self
    {
        return self::gabungan([[$pagu, $total]]);
    }

    /**
     * Gabungan beberapa RAB.
     *
     * @param  iterable<array{0: int|null, 1: int}>  $rab  [pagu|null, total] tiap RAB.
     */
    public static function gabungan(iterable $rab): self
    {
        [$pagu, $total, $kelebihan, $sisa, $jumlah, $melebihi, $tanpaPagu] = [0, 0, 0, 0, 0, 0, 0];
        foreach ($rab as [$p, $t]) {
            $jumlah++;
            $total += $t;
            if ($p === null) {
                $tanpaPagu++;

                continue;
            }
            $pagu += $p;
            $kelebihan += max(0, $t - $p);
            $sisa += max(0, $p - $t);
            $melebihi += $t > $p ? 1 : 0;
        }

        return self::dariJumlah($pagu, $total, $jumlah, $tanpaPagu, $kelebihan, $sisa, $melebihi);
    }

    /**
     * Gabungan RAB sumber dana yang dipilih.
     *
     * @param  iterable<AnggaranSumber>  $anggaran
     */
    public static function dariAnggaran(iterable $anggaran): self
    {
        $rab = [];
        foreach ($anggaran as $a) {
            if ($a->dipilih) {
                $rab[] = [$a->pagu(), $a->total()];
            }
        }

        return self::gabungan($rab);
    }

    /**
     * Dari angka yang sudah dijumlahkan (pagu terisi, total, jumlah RAB, RAB tanpa pagu) — selisih & persen gabungan.
     */
    public static function dariJumlah(int $pagu, int $total, int $rab, int $tanpaPagu, int $kelebihan = 0, int $sisa = 0, int $melebihi = 0): self
    {
        $bisa = $rab > 0 && $tanpaPagu === 0;

        return new self(
            pagu: $pagu,
            total: $total,
            selisih: $bisa ? $pagu - $total : null,
            persen: $bisa ? TabelPagu::persen($total, $pagu ?: null) : null,
            kelebihan: $kelebihan,
            sisa: $sisa,
            rab: $rab,
            melebihi: $melebihi,
            tanpaPagu: $tanpaPagu,
        );
    }

    /** @return array{pagu: int, total: int, selisih: int|null, persen: float|null, kelebihan: int, sisa: int, rab: int, melebihi: int, tanpa_pagu: int} */
    public function toArray(): array
    {
        return [
            'pagu' => $this->pagu, 'total' => $this->total, 'selisih' => $this->selisih, 'persen' => $this->persen,
            'kelebihan' => $this->kelebihan, 'sisa' => $this->sisa, 'rab' => $this->rab, 'melebihi' => $this->melebihi, 'tanpa_pagu' => $this->tanpaPagu,
        ];
    }
}
