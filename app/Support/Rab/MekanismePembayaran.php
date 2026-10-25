<?php

namespace App\Support\Rab;

use App\Support\Format;
use Carbon\CarbonImmutable;

/**
 * Mekanisme pembayaran satu RAB (PRD F11 & aturan 6 "Termin harus pas"): sekaligus, atau bertahap lewat termin yang
 * jumlahnya harus sama dengan total RAB. Juga menyusun blok "Mekanisme Pembayaran" di dokumen RAB persis seperti
 * Form RAB madrasah:
 *
 *   Mekanisme Pembayaran : Termin
 *   Total Keseluruhan    : Rp10.000.000,00
 *   Termin 1 (17/06/2026): Rp7.680.000,00
 */
final readonly class MekanismePembayaran
{
    public const MAKS_TERMIN = 12;

    /**
     * @param  list<array{tanggal: string|null, nominal: int}>  $termin
     */
    public function __construct(
        public int $total,
        public array $termin = [],
    ) {}

    public static function dari(AnggaranSumber $anggaran): self
    {
        return new self($anggaran->total(), $anggaran->termin);
    }

    public function bertermin(): bool
    {
        return $this->termin !== [];
    }

    public function jumlah(): int
    {
        return array_sum(array_map(fn ($t) => (int) $t['nominal'], $this->termin));
    }

    /** Total RAB dikurangi jumlah termin: positif = masih kurang, negatif = berlebih. */
    public function selisih(): int
    {
        return $this->total - $this->jumlah();
    }

    /** 'sekaligus' (tanpa termin), 'pas', 'kurang', atau 'lebih'. */
    public function status(): string
    {
        return match (true) {
            ! $this->bertermin() => 'sekaligus',
            $this->selisih() === 0 => 'pas',
            $this->selisih() > 0 => 'kurang',
            default => 'lebih',
        };
    }

    public function teksStatus(): string
    {
        return match ($this->status()) {
            'sekaligus' => 'Dibayar sekaligus',
            'pas' => 'Jumlah termin sama dengan total RAB',
            'kurang' => 'Termin kurang '.Format::rupiah($this->selisih()).' dari total RAB',
            default => 'Termin lebih '.Format::rupiah(-$this->selisih()).' dari total RAB',
        };
    }

    /** Bagian nominal terhadap total RAB, satu desimal (0 bila RAB masih kosong). */
    public function persen(int $nominal): float
    {
        return $this->total > 0 ? round($nominal / $this->total * 100, 1) : 0.0;
    }

    /**
     * Baris blok "Mekanisme Pembayaran" dokumen RAB; kosong bila dibayar sekaligus (blok tidak dicetak).
     *
     * @return list<array{label: string, nilai: string}>
     */
    public function barisDokumen(): array
    {
        if (! $this->bertermin()) {
            return [];
        }

        $baris = [
            ['label' => 'Mekanisme Pembayaran', 'nilai' => 'Termin'],
            ['label' => 'Total Keseluruhan', 'nilai' => self::rupiah($this->total)],
        ];

        foreach (array_values($this->termin) as $i => $termin) {
            $tanggal = self::tanggal($termin['tanggal'] ?? null);
            $baris[] = ['label' => 'Termin '.($i + 1).($tanggal ? " ({$tanggal})" : ''), 'nilai' => self::rupiah((int) $termin['nominal'])];
        }

        return $baris;
    }

    /** "Rp7.680.000,00" — gaya blok mekanisme pembayaran Form RAB. */
    public static function rupiah(int $nilai): string
    {
        return 'Rp'.Format::angka($nilai).',00';
    }

    /** "2026-06-17" → "17/06/2026"; null bila kosong/tidak valid. */
    private static function tanggal(?string $tanggal): ?string
    {
        return filled($tanggal) ? rescue(fn () => CarbonImmutable::parse($tanggal)->format('d/m/Y'), null, false) : null;
    }
}
