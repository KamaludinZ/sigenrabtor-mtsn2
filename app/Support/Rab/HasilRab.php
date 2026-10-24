<?php

namespace App\Support\Rab;

/**
 * Hasil perhitungan satu RAB (per sumber dana).
 */
final readonly class HasilRab
{
    /**
     * @param  list<array<string, mixed>>  $rincian  Item beserta 'jumlah' hasil hitung.
     * @param  array<string, int>  $perKategori  bahan/jasa/lain → subtotal.
     * @param  array<string, int>  $perKode  kode akun ('' = tanpa kode) → subtotal.
     */
    public function __construct(
        public array $rincian,
        public int $total,
        public array $perKategori,
        public array $perKode,
        public ?int $pagu = null,
    ) {}

    /** Pagu dikurangi total; negatif berarti melebihi pagu. Null bila pagu belum diisi. */
    public function selisih(): ?int
    {
        return $this->pagu === null ? null : $this->pagu - $this->total;
    }

    /**
     * Status terhadap pagu: 'tanpa_pagu', 'sesuai' (tepat sama), 'sisa', atau 'melebihi'.
     */
    public function statusPagu(): string
    {
        return match (true) {
            $this->pagu === null => 'tanpa_pagu',
            $this->total === $this->pagu => 'sesuai',
            $this->total < $this->pagu => 'sisa',
            default => 'melebihi',
        };
    }

    /** Persentase pemakaian pagu (0–100, dibatasi) untuk bilah kemajuan. */
    public function persenPagu(): int
    {
        return $this->pagu ? (int) min(100, round($this->total / $this->pagu * 100)) : 0;
    }

    public function jumlahItem(): int
    {
        return count($this->rincian);
    }
}
