<?php

namespace App\Support\Rab;

use App\Enums\SumberDana;
use App\Support\Terbilang;

/**
 * Anggaran satu kegiatan × sumber dana (PRD tabel anggaran_kegiatan): pagu dari data kegiatan, rincian & total
 * hasil hitung, terbilang, dan termin pembayaran opsional. Setiap sumber dana punya RAB dan dokumen sendiri.
 */
final readonly class AnggaranSumber
{
    /**
     * @param  list<array{tanggal: string|null, nominal: int}>  $termin
     * @param  bool  $dipilih  Sumber masih dipilih di data kegiatan (false = RAB tersisa dari sumber yang sudah dilepas).
     */
    public function __construct(
        public string $sumber,
        public HasilRab $hasil,
        public array $termin = [],
        public bool $dipilih = true,
    ) {}

    /** Jenis sumber dana; null hanya untuk data lama dengan nama sumber di luar enum. */
    public function jenis(): ?SumberDana
    {
        return SumberDana::tryFrom($this->sumber);
    }

    public function pagu(): ?int
    {
        return $this->hasil->pagu;
    }

    public function total(): int
    {
        return $this->hasil->total;
    }

    /** Terbilang total; null bila belum ada rincian (tidak menampilkan "Nol Rupiah"). */
    public function terbilang(): ?string
    {
        return Terbilang::tampil($this->total());
    }

    public function jumlahItem(): int
    {
        return $this->hasil->jumlahItem();
    }

    /** 'tanpa_pagu', 'sesuai', 'sisa', atau 'melebihi'. */
    public function statusPagu(): string
    {
        return $this->hasil->statusPagu();
    }

    public function keteranganPagu(): StatusPagu
    {
        return $this->hasil->keteranganPagu();
    }

    public function adaTermin(): bool
    {
        return $this->termin !== [];
    }

    public function totalTermin(): int
    {
        return array_sum(array_column($this->termin, 'nominal'));
    }

    /** Total RAB dikurangi jumlah termin; 0 berarti termin pas. */
    public function selisihTermin(): int
    {
        return $this->total() - $this->totalTermin();
    }

    /** Tanpa termin dianggap sesuai; dengan termin, jumlahnya harus sama dengan total RAB (PRD aturan 6). */
    public function terminSesuai(): bool
    {
        return ! $this->adaTermin() || $this->selisihTermin() === 0;
    }
}
