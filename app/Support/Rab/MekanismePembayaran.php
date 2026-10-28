<?php

namespace App\Support\Rab;

use App\Support\Format;
use App\Support\Terbilang;
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

    /** teksStatus() untuk disambung di tengah kalimat: hanya huruf pertama dikecilkan ("RAB" tetap kapital). */
    public function teksStatusKalimat(): string
    {
        $teks = $this->teksStatus();

        return mb_strtolower(mb_substr($teks, 0, 1)).mb_substr($teks, 1);
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

    /**
     * Baris tabel termin: nomor, tanggal, nominal, persen, kumulatif (jumlah s.d. termin ini), dan terbilang.
     *
     * @return list<array{nomor: int, tanggal: CarbonImmutable|null, nominal: int, persen: float, kumulatif: int, terbilang: string|null}>
     */
    public function barisTabel(): array
    {
        $kumulatif = 0;

        return array_map(function (array $termin, int $i) use (&$kumulatif) {
            $nominal = (int) $termin['nominal'];
            $kumulatif += $nominal;

            return [
                'nomor' => $i + 1,
                'tanggal' => filled($termin['tanggal'] ?? null) ? rescue(fn () => CarbonImmutable::parse($termin['tanggal']), null, false) : null,
                'nominal' => $nominal,
                'persen' => $this->persen($nominal),
                'kumulatif' => $kumulatif,
                'terbilang' => Terbilang::tampil($nominal),
            ];
        }, array_values($this->termin), array_keys(array_values($this->termin)));
    }

    /**
     * Nominal termin per bulan (1–12) untuk grid jadwal pembayaran; termin tanpa tanggal di kunci 0.
     *
     * @return array<int, int>
     */
    public function perBulan(): array
    {
        $bulan = [];
        foreach ($this->barisTabel() as $baris) {
            $kunci = $baris['tanggal']?->month ?? 0;
            $bulan[$kunci] = ($bulan[$kunci] ?? 0) + $baris['nominal'];
        }
        ksort($bulan);

        return $bulan;
    }

    /** "Rp7.680.000,00" — gaya blok mekanisme pembayaran Form RAB. */
    public static function rupiah(int $nilai): string
    {
        return Format::rupiahDesimal($nilai);
    }

    /** "2026-06-17" → "17/06/2026"; null bila kosong/tidak valid. */
    private static function tanggal(?string $tanggal): ?string
    {
        return filled($tanggal) ? rescue(fn () => CarbonImmutable::parse($tanggal)->format('d/m/Y'), null, false) : null;
    }
}
