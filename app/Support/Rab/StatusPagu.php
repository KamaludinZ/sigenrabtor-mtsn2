<?php

namespace App\Support\Rab;

use App\Support\Format;

/**
 * Status total RAB terhadap pagu satu sumber dana (PRD F12) beserta teks & ikon yang seragam di semua layar:
 * "Pagu belum diisi", "Sesuai pagu", "Sisa pagu Rp…", "Melebihi pagu Rp…".
 */
final readonly class StatusPagu
{
    /**
     * @param  string  $status  'tanpa_pagu', 'sesuai', 'sisa', atau 'melebihi'.
     * @param  int|null  $selisih  Pagu dikurangi total (negatif = melebihi); null bila pagu belum diisi.
     */
    public function __construct(
        public string $status,
        public ?int $selisih,
    ) {}

    public static function hitung(int $total, ?int $pagu): self
    {
        return new self(
            match (true) {
                $pagu === null => 'tanpa_pagu',
                $total === $pagu => 'sesuai',
                $total < $pagu => 'sisa',
                default => 'melebihi',
            },
            $pagu === null ? null : $pagu - $total,
        );
    }

    public function teks(): string
    {
        return match ($this->status) {
            'tanpa_pagu' => 'Pagu belum diisi',
            'sesuai' => 'Sesuai pagu',
            'sisa' => 'Sisa pagu '.Format::rupiah($this->selisih),
            default => 'Melebihi pagu '.Format::rupiah(-$this->selisih),
        };
    }

    public function ikon(): string
    {
        return match ($this->status) {
            'tanpa_pagu' => 'info',
            'melebihi' => 'alert',
            default => 'check-circle',
        };
    }

    /** 'netral' (pagu belum diisi), 'aman' (sesuai/sisa), atau 'bahaya' (melebihi). */
    public function nada(): string
    {
        return match ($this->status) {
            'tanpa_pagu' => 'netral',
            'melebihi' => 'bahaya',
            default => 'aman',
        };
    }

    public function melebihi(): bool
    {
        return $this->status === 'melebihi';
    }
}
