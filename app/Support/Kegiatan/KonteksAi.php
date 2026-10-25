<?php

namespace App\Support\Kegiatan;

use App\Support\Format;
use App\Support\MasterKode;
use App\Support\Stub\StubData;

/**
 * Konteks kegiatan sebagai bahan tombol "Buat dengan AI" (PRD F23, N8).
 *
 * - Panduan empat aspek catatan leading (latar khusus, peserta, narasumber, output) beserta deteksi aspek yang sudah ditulis.
 * - Bahan yang otomatis diketahui AI dari isian lain, sehingga leading tidak perlu mengulangnya di catatan.
 * - Redaksi privasi: NIP (18 digit) tidak pernah ikut terkirim; nama/NIP pejabat memang tidak termasuk bahan AI.
 */
final class KonteksAi
{
    /** Aspek catatan: kunci → [label, awalan yang disisipkan, kata penanda]. */
    public const ASPEK = [
        'latar' => ['Latar khusus', 'Latar khusus: ', ['latar', 'lanjutan', 'tahun lalu', 'evaluasi', 'karena', 'kebutuhan', 'masalah']],
        'peserta' => ['Peserta', 'Peserta: ', ['peserta', 'guru', 'siswa', 'orang tua', 'wali', 'tenaga kependidikan']],
        'narasumber' => ['Narasumber', 'Narasumber: ', ['narasumber', 'pemateri', 'pembicara', 'fasilitator', 'pelatih', 'pengawas']],
        'output' => ['Output yang diharapkan', 'Output: ', ['output', 'hasil', 'luaran', 'dokumen', 'produk', 'target']],
    ];

    private const POLA_NIP = '/(?<!\d)\d{18}(?!\d)|\d{8}\s\d{6}\s\d\s\d{3}/';

    /**
     * @return array<string, bool> Aspek → sudah disebut di catatan.
     */
    public static function aspekTerisi(string $konteks): array
    {
        $teks = mb_strtolower($konteks);

        return array_map(
            fn (array $aspek) => collect($aspek[2])->contains(fn (string $kata) => str_contains($teks, $kata)),
            self::ASPEK,
        );
    }

    /** Peringatan privasi untuk catatan (null bila aman). */
    public static function peringatanPrivasi(string $konteks): ?string
    {
        if (preg_match(self::POLA_NIP, $konteks) || preg_match('/\bNIP\b/i', $konteks)) {
            return 'Catatan memuat NIP. Hapus dari sini — NIP disembunyikan otomatis dan tidak pernah dikirim ke penyedia AI.';
        }

        return null;
    }

    /** Catatan yang aman dikirim: NIP diganti penanda. */
    public static function redaksi(string $konteks): string
    {
        return trim(preg_replace(['/\bNIP\.?\s*:?\s*/i', self::POLA_NIP], ['', '[NIP disembunyikan]'], $konteks));
    }

    /**
     * Bahan dari isian lain yang ikut dikirim ke AI (tanpa nama/NIP pejabat).
     *
     * @param  array<string, mixed>  $kegiatan  Data form/kegiatan (kunci kolom activities).
     * @return list<array{label: string, nilai: string|null}>
     */
    public static function dariIsian(array $kegiatan): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $pelaksanaan = DataPelaksanaan::dari($kegiatan);
        $leading = MasterKode::cari(MasterKode::leadings(), $kegiatan['leading'] ?? null);
        $rktm = MasterKode::cari(MasterKode::kodeRktm(), $kegiatan['kode_rktm'] ?? null);
        $peserta = trim(($pelaksanaan->pesertaJumlah ? $pelaksanaan->pesertaJumlah.' orang ' : '').$pelaksanaan->pesertaJenis);
        $sumber = array_map(
            fn ($s) => $s['sumber'].($s['pagu'] ? ' '.Format::rupiah((int) $s['pagu']) : ''),
            array_filter((array) ($kegiatan['sumber_dana'] ?? []), fn ($s) => is_array($s) && isset($s['sumber'])),
        );

        return [
            ['label' => 'Nama kegiatan', 'nilai' => trim((string) ($kegiatan['nama_kegiatan'] ?? '')) ?: null],
            ['label' => 'Leading', 'nilai' => $leading['nama_unit'] ?? null],
            ['label' => 'Program RKTM', 'nilai' => $rktm ? $rktm['kode'].' '.($rktm['sub_kegiatan'] ?? $rktm['kegiatan'] ?? '') : null],
            ['label' => 'Waktu', 'nilai' => $pelaksanaan->teksBulan($tahun)],
            ['label' => 'Lama', 'nilai' => $pelaksanaan->lamaHari ? $pelaksanaan->lamaHari.' hari' : null],
            ['label' => 'Tempat', 'nilai' => $pelaksanaan->tempat ?: null],
            ['label' => 'Peserta', 'nilai' => $peserta ?: null],
            ['label' => 'Sumber dana', 'nilai' => $sumber === [] ? null : implode(' · ', $sumber)],
        ];
    }

    /**
     * Seluruh bahan AI dalam satu teks (dipakai fitur "Buat dengan AI"): isian terisi lalu catatan yang sudah diredaksi.
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function teks(array $kegiatan): string
    {
        $baris = array_map(fn ($b) => "{$b['label']}: {$b['nilai']}", array_filter(self::dariIsian($kegiatan), fn ($b) => $b['nilai'] !== null));
        $catatan = self::redaksi((string) ($kegiatan['konteks'] ?? ''));

        return implode("\n", $baris).($catatan !== '' ? "\n\nCatatan leading:\n{$catatan}" : '');
    }
}
