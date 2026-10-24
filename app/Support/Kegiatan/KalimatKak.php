<?php

namespace App\Support\Kegiatan;

use App\Support\Format;
use App\Support\Terbilang;

/**
 * Kalimat baku KAK yang disusun dari data (bukan diketik), mengikuti template KAK madrasah.
 */
class KalimatKak
{
    /**
     * Bagian 8 "Pembiayaan": total + terbilang + sumber dana + "sebagaimana RAB terlampir".
     * Contoh: "Biaya yang terkait dengan kegiatan ini diperlukan sebesar Rp10.000.000,- (Sepuluh Juta Rupiah)
     * yang dibebankan pada dana KOMITE MTsN 2 Kota Malang Tahun 2026, sebagaimana RAB terlampir."
     */
    public static function pembiayaan(int $total, string $sumber, int $tahun, string $madrasah): string
    {
        return 'Biaya yang terkait dengan kegiatan ini diperlukan sebesar '.Format::rupiahDokumen($total)
            .' ('.Terbilang::rupiah($total).') yang dibebankan pada dana '.mb_strtoupper($sumber)
            ." {$madrasah} Tahun {$tahun}, sebagaimana RAB terlampir.";
    }
}
