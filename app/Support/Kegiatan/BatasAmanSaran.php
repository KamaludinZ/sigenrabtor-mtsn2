<?php

namespace App\Support\Kegiatan;

use App\Support\Stub\StubData;

/**
 * Pemeriksaan "Batas Aman" saran AI sebelum dipakai (PRD §20): AI tidak boleh menambah angka, kode, atau nama pejabat.
 * Saran yang memuat angka yang tidak ada di data kegiatan, kode master (SK, IKSK, RKTM), nama pejabat, atau NIP diberi
 * peringatan di modal saran — leading tetap memutuskan; saran tidak diubah diam-diam.
 */
final class BatasAmanSaran
{
    private const POLA_KODE = [
        '/\bIKSK\s*\d+(?:[.\-]\d+)?\b/u',
        '/\bSK\s*[.\-]?\s*\d+\b/u',
        '/\b\d+\.\d+\.\d+\b/u',
    ];

    private const POLA_NIP = '/(?<!\d)\d{18}(?!\d)|\d{8}\s\d{6}\s\d\s\d{3}/';

    /**
     * @param  array<string, mixed>  $kegiatan  Data kegiatan (dengan teks yang sedang disunting).
     * @return list<string>
     */
    public static function peringatan(string $teks, array $kegiatan): array
    {
        $peringatan = [];

        if (preg_match(self::POLA_NIP, $teks)) {
            $peringatan[] = 'Saran memuat NIP — hapus sebelum dipakai; NIP hanya tercetak di lembar pengesahan.';
        }

        $kode = [];
        foreach (self::POLA_KODE as $pola) {
            preg_match_all($pola, $teks, $cocok);
            array_push($kode, ...$cocok[0]);
        }
        if ($kode !== []) {
            $peringatan[] = 'Saran menyebut kode '.implode(', ', array_unique($kode)).' — kode dipilih di Kode & Klasifikasi, bukan ditulis di kalimat.';
        }

        // Angka di luar kode & NIP harus berasal dari data kegiatan.
        $sisa = preg_replace([...self::POLA_KODE, self::POLA_NIP], ' ', $teks);
        preg_match_all('/\d+(?:[.,]\d+)*/u', (string) $sisa, $angka);
        $dikenal = self::angkaDikenal($kegiatan);
        $baru = array_values(array_unique(array_filter($angka[0], fn (string $a) => ! in_array($a, $dikenal, true))));
        if ($baru !== []) {
            $peringatan[] = 'Saran memuat angka '.implode(', ', $baru).' yang tidak ada di data kegiatan — pastikan benar sebelum dipakai.';
        }

        $nama = array_filter(array_map(fn ($p) => is_array($p) ? trim((string) ($p['nama'] ?? '')) : '', [...array_values(StubData::pejabat()), ...array_values(StubData::pejabatLeading())]), fn (string $n) => mb_strlen($n) >= 4);
        $disebut = array_values(array_filter($nama, fn (string $n) => mb_stripos($teks, $n) !== false));
        if ($disebut !== []) {
            $peringatan[] = 'Saran menyebut nama pejabat ('.implode(', ', array_unique($disebut)).') — nama pejabat tercetak otomatis di lembar pengesahan.';
        }

        return $peringatan;
    }

    /**
     * Angka yang memang ada di data kegiatan (nama, pelaksanaan, catatan konteks, isian teks) dan tahun anggaran aktif.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return list<string>
     */
    private static function angkaDikenal(array $kegiatan): array
    {
        $sumber = [StubData::tahunAktif()['tahun']];
        foreach (['nama_kegiatan', 'tempat', 'peserta_jumlah', 'peserta_jenis', 'lama_hari', 'konteks', 'latar_belakang', 'tujuan', 'sasaran', 'uraian_pelaksanaan'] as $kolom) {
            $sumber[] = is_scalar($kegiatan[$kolom] ?? null) ? (string) $kegiatan[$kolom] : '';
        }
        preg_match_all('/\d+(?:[.,]\d+)*/u', implode(' ', $sumber), $angka);

        return $angka[0];
    }
}
