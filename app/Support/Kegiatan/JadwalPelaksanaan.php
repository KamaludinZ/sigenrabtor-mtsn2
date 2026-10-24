<?php

namespace App\Support\Kegiatan;

use Carbon\CarbonImmutable;

/**
 * Kalkulasi jadwal pelaksanaan kegiatan: tanggal selesai, bulan tercakup, dan batas laporan.
 */
class JadwalPelaksanaan
{
    /** Laporan disampaikan paling lambat 10 hari setelah pelaksanaan (kalimat baku KAK bagian Pelaporan). */
    public const HARI_PELAPORAN = 10;

    public const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /**
     * Tanggal selesai = tanggal mulai + (lama - 1) hari. Dengan $hariKerja, Sabtu & Minggu tidak dihitung
     * (bila tanggal mulai jatuh di akhir pekan, hitungan dimulai hari kerja berikutnya).
     */
    public static function tanggalSelesai(CarbonImmutable $mulai, int $lamaHari, bool $hariKerja = false): CarbonImmutable
    {
        $lamaHari = max(1, $lamaHari);

        if (! $hariKerja) {
            return $mulai->addDays($lamaHari - 1);
        }

        $tanggal = $mulai;
        while ($tanggal->isWeekend()) {
            $tanggal = $tanggal->addDay();
        }

        for ($sisa = $lamaHari - 1; $sisa > 0;) {
            $tanggal = $tanggal->addDay();
            if (! $tanggal->isWeekend()) {
                $sisa--;
            }
        }

        return $tanggal;
    }

    /**
     * Nomor bulan (1–12) yang dilalui rentang tanggal, dibatasi pada tahun tanggal mulai.
     *
     * @return list<int>
     */
    public static function bulanTercakup(CarbonImmutable $mulai, CarbonImmutable $selesai): array
    {
        $akhir = $selesai->year > $mulai->year ? 12 : $selesai->month;

        return range($mulai->month, max($mulai->month, $akhir));
    }

    public static function batasLaporan(CarbonImmutable $selesai): CarbonImmutable
    {
        return $selesai->addDays(self::HARI_PELAPORAN);
    }

    /**
     * Teks bulan untuk dokumen, mis. [7] → "Juli 2026", [7, 8] → "Juli – Agustus 2026", [3, 6, 9] → "Maret, Juni, September 2026".
     *
     * @param  list<int>  $bulan
     */
    public static function teksBulan(array $bulan, int $tahun): ?string
    {
        $bulan = array_values(array_unique(array_filter($bulan, fn ($b) => $b >= 1 && $b <= 12)));
        sort($bulan);

        if ($bulan === []) {
            return null;
        }

        $berurutan = end($bulan) - $bulan[0] === count($bulan) - 1;

        if (count($bulan) > 1 && $berurutan) {
            return self::NAMA_BULAN[$bulan[0]].' – '.self::NAMA_BULAN[end($bulan)].' '.$tahun;
        }

        return implode(', ', array_map(fn ($b) => self::NAMA_BULAN[$b], $bulan)).' '.$tahun;
    }

    /**
     * Rentang tanggal untuk dokumen, mis. "14 Juli 2026", "14 – 16 Juli 2026", "30 Juli – 2 Agustus 2026".
     */
    public static function teksRentang(CarbonImmutable $mulai, CarbonImmutable $selesai): string
    {
        $bulan = fn (CarbonImmutable $t) => self::NAMA_BULAN[$t->month];

        if ($mulai->isSameDay($selesai)) {
            return "{$mulai->day} {$bulan($mulai)} {$mulai->year}";
        }

        if ($mulai->isSameMonth($selesai)) {
            return "{$mulai->day} – {$selesai->day} {$bulan($selesai)} {$selesai->year}";
        }

        if ($mulai->year === $selesai->year) {
            return "{$mulai->day} {$bulan($mulai)} – {$selesai->day} {$bulan($selesai)} {$selesai->year}";
        }

        return "{$mulai->day} {$bulan($mulai)} {$mulai->year} – {$selesai->day} {$bulan($selesai)} {$selesai->year}";
    }
}
