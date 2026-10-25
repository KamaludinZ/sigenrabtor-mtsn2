<?php

namespace App\Support\Kegiatan;

use App\Support\Format;
use App\Support\Terbilang;
use Carbon\CarbonImmutable;

/**
 * Kalimat baku KAK yang disusun dari data (bukan diketik), mengikuti template KAK madrasah.
 */
class KalimatKak
{
    /**
     * "Kegiatan Workshop …" → "Workshop …": template KAK sudah menulis kata "kegiatan" sendiri.
     */
    public static function tanpaAwalanKegiatan(string $nama): string
    {
        return trim(preg_replace('/^kegiatan\s+/iu', '', trim($nama)));
    }

    /**
     * Bagian 4b "Pelaksanaan": "Kegiatan Workshop … akan dilaksanakan selama 3 hari, 14 – 16 Juli 2026,
     * diikuti oleh 56 orang peserta (Guru dan tenaga kependidikan)." disusul uraian bentuk pelaksanaan bila ada.
     */
    public static function pelaksanaan(string $nama, int $lamaHari, CarbonImmutable $mulai, CarbonImmutable $selesai, ?int $pesertaJumlah = null, ?string $pesertaJenis = null, ?string $uraian = null): string
    {
        $peserta = match (true) {
            $pesertaJumlah > 0 && filled($pesertaJenis) => ", diikuti oleh {$pesertaJumlah} orang peserta ({$pesertaJenis})",
            $pesertaJumlah > 0 => ", diikuti oleh {$pesertaJumlah} orang peserta",
            filled($pesertaJenis) => ", diikuti oleh {$pesertaJenis}",
            default => '',
        };
        $kalimat = 'Kegiatan '.self::tanpaAwalanKegiatan($nama)." akan dilaksanakan selama {$lamaHari} hari, ".JadwalPelaksanaan::teksRentang($mulai, $selesai).$peserta.'.';

        if (filled($uraian = trim((string) $uraian))) {
            $kalimat .= ' '.mb_strtoupper(mb_substr($uraian, 0, 1)).mb_substr(rtrim($uraian, '. '), 1).'.';
        }

        return $kalimat;
    }

    /** Bagian 4c "Pelaporan": batas penyerahan laporan ke kepala madrasah. */
    public static function pelaporan(string $nama, CarbonImmutable $batas): string
    {
        return 'Laporan pelaksanaan kegiatan '.self::tanpaAwalanKegiatan($nama).' disampaikan kepada kepala madrasah selambat-lambatnya tanggal '
            .Format::tanggal($batas->toDateString()).'.';
    }

    /** Bagian 5 "Tempat Pelaksanaan": "Kegiatan Workshop … bertempat di Aula Madrasah." */
    public static function tempat(string $nama, string $tempat): string
    {
        return 'Kegiatan '.self::tanpaAwalanKegiatan($nama).' bertempat di '.rtrim(trim($tempat), '.').'.';
    }

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
