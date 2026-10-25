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

    /** Bagian 1: kalimat penghubung kegiatan dengan Perjanjian Kinerja kepala madrasah. */
    public static function latarBelakangKegiatan(string $nama): string
    {
        return 'Salah satu kegiatan yang relevan dan mendukung program Perjanjian Kinerja (Perkin) Kepala Madrasah adalah kegiatan '
            .self::tanpaAwalanKegiatan($nama).'.';
    }

    /** Bagian 3A "Tujuan": "Kegiatan {nama} bertujuan {tujuan}". */
    public static function tujuan(string $nama, string $tujuan): string
    {
        return 'Kegiatan '.self::tanpaAwalanKegiatan($nama).' bertujuan '.self::kalimat($tujuan);
    }

    /** Bagian 3B "Sasaran": "Terwujudnya {sasaran}". */
    public static function sasaran(string $sasaran): string
    {
        return 'Terwujudnya '.self::kalimat($sasaran);
    }

    /**
     * Bagian 4a "Persiapan": tiga butir baku.
     *
     * @return list<string>
     */
    public static function persiapan(string $nama): array
    {
        return [
            'Melakukan rapat persiapan (distribusi surat tugas dan pembagian tugas) untuk menentukan berbagai hal terkait teknis penyelenggaraan kegiatan '
                .self::tanpaAwalanKegiatan($nama).'.',
            'Menentukan jadwal dan membuat daftar periksa kegiatan.',
            'Menyiapkan berbagai perlengkapan yang dibutuhkan.',
        ];
    }

    /** Bagian 4c "Pelaporan": butir baku pertama (analisis hasil); butir kedua batas laporan lewat pelaporan(). */
    public static function analisisHasil(string $nama): string
    {
        return 'Menganalisis hasil kegiatan '.self::tanpaAwalanKegiatan($nama).' baik dalam proses persiapan, pelaksanaan maupun hasil evaluasinya.';
    }

    /** Bagian 6a "Pelaksana": jabatan penanda tangan leading. */
    public static function pelaksana(string $nama, string $jabatan): string
    {
        return 'Pelaksana kegiatan '.self::tanpaAwalanKegiatan($nama).' adalah '.$jabatan.'.';
    }

    /** Bagian 6b "Pengarah": kepala madrasah. */
    public static function pengarah(string $nama, string $madrasah): string
    {
        return 'Pengarah kegiatan '.self::tanpaAwalanKegiatan($nama).' adalah Kepala '.$madrasah.'.';
    }

    /** Isian bebas sebagai lanjutan kalimat: spasi dirapikan dan diakhiri titik. */
    private static function kalimat(string $teks): string
    {
        return rtrim(trim(preg_replace('/\s+/u', ' ', $teks)), '. ').'.';
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
