<?php

namespace App\Support\Stub;

use App\Contracts\PenyusunUsulanJadwal;
use App\Support\Kegiatan\PermintaanUsulanJadwal;

/**
 * Usulan jadwal tiruan untuk panel "Usulkan dengan AI" di Matrik & Jadwal (PRD F10/F23) selama penyedia AI belum
 * tersambung. Tiga versi — lengkap 6 baris, persiapan lebih awal 5 baris, ringkas 4 baris — disusun dari langkah baku
 * per jenis kegiatan dan bulan acuan Data Kegiatan; backend menggantinya dengan driver AI dalam bentuk array yang sama.
 */
class StubUsulanJadwal implements PenyusunUsulanJadwal
{
    /**
     * Langkah per jenis kegiatan: [uraian, waktu, prioritas]. Waktu: persiapan (bulan sebelum pelaksanaan), menjelang
     * (persiapan s.d. bulan pelaksanaan pertama), pelaksanaan (seluruh bulan pelaksanaan), akhir (bulan pelaksanaan
     * terakhir), laporan (bulan batas laporan). Versi yang lebih ringkas membuang langkah berprioritas 3 lebih dulu.
     */
    private const LANGKAH = [
        'workshop' => [
            ['Pembentukan panitia dan penyusunan TOR workshop', 'persiapan', 1],
            ['Koordinasi narasumber dan undangan peserta', 'persiapan', 2],
            ['Penyiapan bahan, tempat, dan perlengkapan workshop', 'menjelang', 3],
            ['Pelaksanaan workshop', 'pelaksanaan', 1],
            ['Pendampingan penyelesaian dokumen hasil workshop', 'akhir', 3],
            ['Penyusunan dan penyerahan laporan kegiatan', 'laporan', 1],
        ],
        'supervisi' => [
            ['Penyusunan jadwal dan instrumen supervisi', 'persiapan', 1],
            ['Sosialisasi jadwal supervisi kepada guru', 'persiapan', 2],
            ['Rapat koordinasi tim supervisor', 'menjelang', 3],
            ['Pelaksanaan supervisi dan observasi kelas', 'pelaksanaan', 1],
            ['Refleksi dan umpan balik hasil supervisi', 'akhir', 3],
            ['Penyusunan laporan dan rencana tindak lanjut', 'laporan', 1],
        ],
        'lomba' => [
            ['Pembentukan panitia dan penyusunan petunjuk teknis lomba', 'persiapan', 1],
            ['Sosialisasi dan pendaftaran peserta', 'menjelang', 2],
            ['Penyiapan juri, tempat, dan perlengkapan lomba', 'menjelang', 3],
            ['Pelaksanaan lomba', 'pelaksanaan', 1],
            ['Penilaian dan pengumuman juara', 'akhir', 3],
            ['Penyusunan dan penyerahan laporan kegiatan', 'laporan', 1],
        ],
        'siswa' => [
            ['Pembentukan panitia dan rapat persiapan', 'persiapan', 1],
            ['Koordinasi dengan pembina, wali kelas, dan narasumber', 'persiapan', 2],
            ['Penyiapan tempat, perlengkapan, dan konsumsi', 'menjelang', 3],
            ['Pelaksanaan kegiatan', 'pelaksanaan', 1],
            ['Evaluasi kegiatan bersama panitia', 'akhir', 3],
            ['Penyusunan dan penyerahan laporan kegiatan', 'laporan', 1],
        ],
        'umum' => [
            ['Pembentukan kepanitiaan', 'persiapan', 1],
            ['Rapat koordinasi panitia', 'persiapan', 2],
            ['Persiapan sarana dan bahan kegiatan', 'menjelang', 3],
            ['Pelaksanaan kegiatan', 'pelaksanaan', 1],
            ['Evaluasi kegiatan', 'akhir', 3],
            ['Penyusunan dan penyerahan laporan', 'laporan', 1],
        ],
    ];

    /** Versi: [ciri, keterangan, jumlah baris, persiapan dimajukan (bulan)]. */
    private const VERSI = [
        ['Lengkap', 'Enam tahap berurutan mengikuti bulan pelaksanaan.', 6, 0],
        ['Persiapan lebih awal', 'Persiapan dimulai sebulan lebih awal agar panitia lebih leluasa.', 5, 1],
        ['Ringkas', 'Empat tahap inti untuk kegiatan singkat.', 4, 0],
    ];

    public function usulkan(PermintaanUsulanJadwal $permintaan, string $penyedia = 'tiruan'): array
    {
        return self::versi($permintaan->jenis, $permintaan->acuan);
    }

    /**
     * @param  array{persiapan: int, pelaksanaan: list<int>, laporan: int}|null  $acuan  Bulan acuan; null bila bulan pelaksanaan belum diisi.
     * @return list<array{judul: string, ciri: string, keterangan: string, baris: list<array{uraian: string, mulai: int|null, sampai: int|null}>}>
     */
    public static function versi(string $jenis, ?array $acuan): array
    {
        $langkah = self::LANGKAH[$jenis] ?? self::LANGKAH['umum'];

        return array_map(fn (array $v, int $i) => [
            'judul' => 'Versi '.($i + 1),
            'ciri' => $v[0],
            'keterangan' => $v[1],
            'baris' => array_map(
                fn (array $l) => ['uraian' => $l[0]] + self::waktu($l[1], $acuan, $v[3]),
                self::pilihLangkah($langkah, $v[2]),
            ),
        ], self::VERSI, array_keys(self::VERSI));
    }

    /**
     * Langkah sebanyak $jumlah dengan urutan tetap; yang berprioritas paling rendah (angka terbesar, paling akhir) dibuang dulu.
     *
     * @param  list<array{string, string, int}>  $langkah
     * @return list<array{string, string, int}>
     */
    private static function pilihLangkah(array $langkah, int $jumlah): array
    {
        $urutBuang = array_keys($langkah);
        usort($urutBuang, fn (int $a, int $b) => [$langkah[$b][2], $b] <=> [$langkah[$a][2], $a]);
        $buang = array_slice($urutBuang, 0, max(0, count($langkah) - $jumlah));

        return array_values(array_diff_key($langkah, array_flip($buang)));
    }

    /**
     * @param  array{persiapan: int, pelaksanaan: list<int>, laporan: int}|null  $acuan
     * @return array{mulai: int|null, sampai: int|null}
     */
    private static function waktu(string $kunci, ?array $acuan, int $majukan): array
    {
        if ($acuan === null) {
            return ['mulai' => null, 'sampai' => null];
        }

        $persiapan = max(1, $acuan['persiapan'] - $majukan);
        $pertama = $acuan['pelaksanaan'][0];
        $terakhir = end($acuan['pelaksanaan']);
        [$mulai, $sampai] = match ($kunci) {
            'persiapan' => [$persiapan, null],
            'menjelang' => [$persiapan, $pertama],
            'pelaksanaan' => [$pertama, $terakhir],
            'akhir' => [$terakhir, null],
            default => [$acuan['laporan'], null],
        };

        return ['mulai' => $mulai, 'sampai' => $sampai !== null && $sampai > $mulai ? $sampai : null];
    }
}
