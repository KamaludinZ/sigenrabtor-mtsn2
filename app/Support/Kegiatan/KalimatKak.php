<?php

namespace App\Support\Kegiatan;

use App\Enums\SumberDana;
use App\Support\Format;
use App\Support\Pengaturan\TeksBaku;
use App\Support\Stub\StubData;
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
     * Bagian 1: kalimat penutup latar belakang yang menghubungkan kegiatan dengan Perjanjian Kinerja kepala madrasah —
     * template per tahun dari Pengaturan › Dasar hukum & latar belakang ($tahun null = tahun aktif), variabel kegiatan diisi
     * data $kegiatan (yang belum diisi tercetak "…"; daftarnya lewat TeksBaku::kosong()).
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function latarBelakangKegiatan(array $kegiatan, ?int $tahun = null): string
    {
        $tahun ??= StubData::tahunAktif()['tahun'];

        return TeksBaku::cetak(StubData::kalimatPenutupLatar($tahun), $tahun, TeksBaku::nilaiKegiatan($kegiatan));
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

    /** Format 5 bab I.C "Maksud dan Tujuan": "Maksud dan tujuan dilaksanakannya kegiatan {nama} adalah untuk {tujuan}". */
    public static function maksudTujuan(string $nama, string $tujuan): string
    {
        $tujuan = self::kalimat($tujuan);
        $tujuan = preg_match('/^untuk\s/iu', $tujuan) ? $tujuan : 'untuk '.mb_strtolower(mb_substr($tujuan, 0, 1)).mb_substr($tujuan, 1);

        return 'Maksud dan tujuan dilaksanakannya kegiatan '.self::tanpaAwalanKegiatan($nama).' adalah '.$tujuan;
    }

    /** Format 5 bab II.2 "Peserta": "{jenis peserta} sebanyak {n} orang." (salah satu saja bila yang lain kosong). */
    public static function peserta(?int $jumlah, ?string $jenis): ?string
    {
        $jenis = trim((string) $jenis);

        return match (true) {
            $jumlah > 0 && $jenis !== '' => mb_strtoupper(mb_substr($jenis, 0, 1)).mb_substr(rtrim($jenis, '. '), 1)." sebanyak {$jumlah} orang.",
            $jumlah > 0 => "{$jumlah} orang peserta.",
            $jenis !== '' => mb_strtoupper(mb_substr($jenis, 0, 1)).mb_substr(rtrim($jenis, '. '), 1).'.',
            default => null,
        };
    }

    /**
     * Format 5 bab II.3 "Waktu dan Tempat": "Pelaksanaan kegiatan {nama} dilaksanakan pada {rentang tanggal} bertempat di
     * {tempat}." — bagian yang belum diisi tidak disebut (null bila keduanya kosong).
     */
    public static function waktuTempat(string $nama, ?CarbonImmutable $mulai, ?CarbonImmutable $selesai, string $tempat): ?string
    {
        $waktu = $mulai && $selesai ? ' dilaksanakan pada '.JadwalPelaksanaan::teksRentang($mulai, $selesai) : '';
        $tempat = trim($tempat) !== '' ? ' bertempat di '.rtrim(trim($tempat), '.') : '';

        return $waktu === '' && $tempat === '' ? null : 'Pelaksanaan kegiatan '.self::tanpaAwalanKegiatan($nama).$waktu.$tempat.'.';
    }

    /**
     * Format 5 bab III.2 "Pelaksanaan": "Kegiatan {nama} akan dilaksanakan selama {n} hari sesuai dengan jadwal yang sudah
     * ditetapkan." disusul uraian bentuk pelaksanaan bila ada.
     */
    public static function pelaksanaanLama(string $nama, int $lamaHari, ?string $uraian = null): string
    {
        $kalimat = 'Kegiatan '.self::tanpaAwalanKegiatan($nama)." akan dilaksanakan selama {$lamaHari} hari sesuai dengan jadwal yang sudah ditetapkan.";
        if (filled($uraian = trim((string) $uraian))) {
            $kalimat .= ' '.mb_strtoupper(mb_substr($uraian, 0, 1)).mb_substr(rtrim($uraian, '. '), 1).'.';
        }

        return $kalimat;
    }

    /** Format 5 bab II.5a "Penanggung jawab": kepala madrasah. */
    public static function penanggungJawab(string $nama, string $madrasah): string
    {
        return 'Penanggung jawab kegiatan '.self::tanpaAwalanKegiatan($nama).' adalah Kepala '.$madrasah.'.';
    }

    /** Format 5 bab II.6 "Narasumber / Fasilitator dan Materi" (teks baku; disesuaikan per kegiatan). */
    public static function narasumber(string $nama, string $madrasah): string
    {
        return 'Narasumber/fasilitator kegiatan '.self::tanpaAwalanKegiatan($nama).' adalah tim internal '.$madrasah.' sesuai materi kegiatan.';
    }

    /** Format 5 bab IV.1 "Output" (teks baku; disesuaikan per kegiatan). */
    public static function output(string $nama): string
    {
        return 'Terlaksananya kegiatan '.self::tanpaAwalanKegiatan($nama).' bagi peserta.';
    }

    /** Format 5 bab IV.2 "Outcome" (teks baku; disesuaikan per kegiatan). */
    public static function outcome(string $nama): string
    {
        return 'Terwujudnya peningkatan pengetahuan dan keterampilan peserta melalui kegiatan '.self::tanpaAwalanKegiatan($nama).' secara maksimal.';
    }

    /** Format 5 bab V "Penutup". */
    public static function penutup(string $nama, string $madrasahSingkat): string
    {
        return 'Demikianlah, Kerangka Acuan Kerja (KAK) atau Term of Reference (TOR) pelaksanaan kegiatan '.self::tanpaAwalanKegiatan($nama)
            .' di lingkungan '.$madrasahSingkat.' telah kami susun sebagai pengajuan anggaran kegiatan dimaksud.';
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
        return 'Biaya yang terkait dengan kegiatan ini diperlukan sebesar '.Terbilang::denganAngka($total)
            .' yang dibebankan pada dana '.self::sumberDana($sumber, $madrasah, $tahun)
            .', sebagaimana RAB terlampir.';
    }

    /**
     * Sebutan sumber dana di dokumen, mis. "KOMITE MTsN 2 Kota Malang Tahun 2026" (sama dengan header RAB).
     */
    public static function sumberDana(string $sumber, string $madrasah, int $tahun): string
    {
        return SumberDana::tryFrom($sumber)?->sebutanDokumen($madrasah, $tahun) ?? mb_strtoupper($sumber)." {$madrasah} Tahun {$tahun}";
    }
}
