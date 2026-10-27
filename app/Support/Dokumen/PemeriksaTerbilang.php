<?php

namespace App\Support\Dokumen;

use App\Support\Format;
use App\Support\Terbilang;

/**
 * Memeriksa pasangan "Rp… (terbilang)" di teks dokumen: terbilang harus sesuai nominalnya, dan bila
 * total yang diharapkan diberikan, nominalnya pun harus sama. Menangkap kesalahan dokumen lama seperti
 * "Rp32.000.000 (Tiga Puluh Juta)" atau "(#NAME?)".
 */
class PemeriksaTerbilang
{
    /** "Rp10.000.000,- (Sepuluh Juta Rupiah)", "Rp. 10000000 (…)", "Rp 2.320.000,00 (…)". */
    private const POLA = '/Rp\.?\s*(\d[\d.]*)(?:,-|,00)?\s*\(([^()]*)\)/u';

    /**
     * @return list<array{nominal: int, tertulis: string, seharusnya: string, masalah: string}>
     */
    public static function periksa(string $teks, ?int $totalDiharapkan = null): array
    {
        preg_match_all(self::POLA, $teks, $cocok, PREG_SET_ORDER);
        $temuan = [];

        foreach ($cocok as [, $angka, $tertulis]) {
            $nominal = (int) Format::parseRupiah($angka);
            $tertulis = trim(preg_replace('/\s+/u', ' ', $tertulis));

            if ($nominal > Terbilang::MAKSIMUM) {
                $temuan[] = ['nominal' => $nominal, 'tertulis' => $tertulis, 'seharusnya' => '—', 'masalah' => 'Nominal terlalu besar untuk diterbilangkan (maksimal 999 triliun).'];
            } elseif (mb_strtolower($tertulis) !== mb_strtolower($seharusnya = Terbilang::rupiah($nominal))) {
                $temuan[] = ['nominal' => $nominal, 'tertulis' => $tertulis, 'seharusnya' => $seharusnya, 'masalah' => 'Terbilang tidak sesuai nominal.'];
            }

            if ($totalDiharapkan !== null && $nominal !== $totalDiharapkan) {
                $temuan[] = ['nominal' => $nominal, 'tertulis' => Format::rupiah($nominal), 'seharusnya' => Format::rupiah($totalDiharapkan), 'masalah' => 'Nominal berbeda dari total RAB.'];
            }
        }

        return $temuan;
    }

    /** Jumlah pasangan nominal + terbilang yang ditemukan di teks. */
    public static function jumlahPasangan(string $teks): int
    {
        return preg_match_all(self::POLA, $teks);
    }
}
