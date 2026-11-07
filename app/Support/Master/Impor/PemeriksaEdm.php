<?php

namespace App\Support\Master\Impor;

use App\Support\Impor\LembarExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pembacaan & pemeriksaan indikator EDM dari sheet EDM (atau CSV khusus EDM, atau sheet gabungan EDM ↔ RKTM seperti
 * 5BM_SNP): Kode (huruf komponen + nomor, mis. A1), Uraian, Komponen (nama, opsional — kosong memakai nama komponen huruf
 * itu dari baris di atasnya, lalu master; awalan huruf "A. Kedisiplinan" dibuang), dan Kode RKTM terkait (opsional —
 * kode sub kegiatan yang direkomendasikan untuk indikator itu; boleh beberapa kode dipisah koma dalam satu sel atau di
 * baris-baris di bawah indikatornya). Relasi itu (tampil `rktm`, kode dipisah ", ") hanya ada bila kolomnya ada.
 * Nama komponen yang berbeda untuk huruf yang sama di berkas hanya diberi peringatan.
 */
final class PemeriksaEdm
{
    public const JUDUL = [
        'kode' => ['kode edm', 'kode indikator', 'kode', 'no urut', 'nomor indikator'],
        'uraian' => ['uraian indikator', 'uraian edm', 'indikator', 'uraian', 'pertanyaan per indikator', 'pertanyaan indikator'],
        'komponen' => ['komponen', 'nama komponen', 'komponen edm', 'kategori'],
        'rktm' => ['kode rktm', 'kode rktm terkait', 'rktm terkait', 'kode sub kegiatan'],
    ];

    /** "A. Kedisiplinan" → "Kedisiplinan" (awalan huruf komponennya sendiri). */
    public static function namaKomponen(string $nama, string $huruf): string
    {
        return $huruf !== '' ? trim((string) preg_replace('/^'.preg_quote($huruf, '/').'\s*[.)\-:]\s*/u', '', $nama)) : $nama;
    }

    /** "a1", "A.1", "A-01" → "A1"; null bila bukan kode EDM. */
    public static function normalkanKode(string $kode): ?string
    {
        return preg_match('/^([A-Z])\s*[.\-]?\s*(\d+)$/i', trim($kode), $cocok) ? mb_strtoupper($cocok[1]).((int) $cocok[2]) : null;
    }

    /**
     * @param  array<string, string>  $komponenMaster  Huruf → nama komponen master tahun itu (cadangan kolom Komponen).
     * @return list<array{baris: list<int>, kode: string, tampil: array{uraian: string, komponen: string}, status: string|null, pesan: list<string>}>
     *
     * @throws GagalPratinjauMaster Judul kolom tidak ditemukan.
     */
    public static function baca(Worksheet $lembar, array $komponenMaster = []): array
    {
        $judul = KolomMaster::judul($lembar, 'edm');

        $hasil = [];
        $posisi = [];
        $namaPerHuruf = [];
        $komponenBerkas = [];
        $relasi = isset($judul['kolom']['rktm']);
        $terakhir = null; // Indeks baris hasil terakhir yang sah (penampung kode RKTM di baris lanjutan).
        for ($baris = $judul['baris'] + 1; $baris <= $lembar->getHighestDataRow(); $baris++) {
            $sel = fn (string $kunci) => isset($judul['kolom'][$kunci]) ? trim((string) LembarExcel::nilaiSel($lembar, $judul['kolom'][$kunci].$baris)) : '';
            [$mentah, $uraian, $namaKomponen, $rktmMentah] = [$sel('kode'), $sel('uraian'), $sel('komponen'), $sel('rktm')];
            if ($mentah === '' && $uraian === '') {
                // Baris lanjutan indikator di atasnya: hanya membawa kode RKTM terkait (sel kode & uraian digabung).
                if ($relasi && $terakhir !== null && $rktmMentah !== '') {
                    $hasil[$terakhir]['tampil']['rktm'] = self::gabungRktm($hasil[$terakhir]['tampil']['rktm'], $rktmMentah);
                }

                continue;
            }

            $kode = self::normalkanKode($mentah);
            $huruf = $kode !== null ? $kode[0] : '';
            $namaKomponen = self::namaKomponen($namaKomponen, $huruf);
            if ($namaKomponen !== '' && $huruf !== '') {
                $komponenBerkas[$huruf] = $namaKomponen;
            }
            $komponen = $namaKomponen !== '' ? $namaKomponen : ($komponenBerkas[$huruf] ?? $komponenMaster[$huruf] ?? $huruf);
            $tampil = ['uraian' => $uraian, 'komponen' => $komponen] + ($relasi ? ['rktm' => self::gabungRktm('', $rktmMentah)] : []);
            $pesan = array_values(array_filter([
                $mentah === '' ? 'Kode EDM kosong.' : ($kode === null ? "Kode \"{$mentah}\" tidak berformat huruf + nomor (mis. A1, C3)." : null),
                $uraian === '' ? 'Uraian indikator kosong.' : null,
                ...KolomMaster::galatPanjang('edm', $tampil),
            ]));
            if ($pesan !== []) {
                $hasil[] = ['baris' => [$baris], 'kode' => $kode ?? $mentah, 'tampil' => $tampil, 'status' => 'gagal', 'pesan' => $pesan];

                continue;
            }

            if (isset($posisi[$kode])) {
                $awal = &$hasil[$posisi[$kode]];
                if (array_diff_key($awal['tampil'], ['rktm' => true]) === array_diff_key($tampil, ['rktm' => true])) {
                    $awal['baris'][] = $baris;
                    if ($relasi) {
                        $awal['tampil']['rktm'] = self::gabungRktm($awal['tampil']['rktm'], $tampil['rktm']);
                    }
                    $terakhir = $posisi[$kode];
                } else {
                    $hasil[] = ['baris' => [$baris], 'kode' => $kode, 'tampil' => $tampil, 'status' => 'gagal',
                        'pesan' => ["Isi berbeda dengan baris {$awal['baris'][0]} untuk kode yang sama."]];
                }
                unset($awal);

                continue;
            }

            $namaPerHuruf[$huruf] ??= ['nama' => $komponen, 'baris' => $baris];
            $posisi[$kode] = count($hasil);
            $terakhir = $posisi[$kode];
            $hasil[] = ['baris' => [$baris], 'kode' => $kode, 'tampil' => $tampil, 'status' => null,
                'pesan' => $namaPerHuruf[$huruf]['nama'] === $komponen ? [] : ["Nama komponen {$huruf} berbeda dengan baris {$namaPerHuruf[$huruf]['baris']} ({$namaPerHuruf[$huruf]['nama']})."]];
        }

        return array_map(function (array $b) {
            if (isset($b['tampil']['rktm']) && ($asing = self::rktmAsing($b['tampil']['rktm'])) !== []) {
                $b['status'] = 'gagal';
                $b['pesan'][] = 'Kode RKTM terkait tidak berformat n.n.n: '.implode(', ', $asing).'.';
            }

            return $b;
        }, $hasil);
    }

    /**
     * Daftar kode RKTM dari teks sel ("2.3.1, 2.3.2" / baris baru / titik koma).
     *
     * @return list<string>
     */
    public static function daftarRktm(string $teks): array
    {
        $hasil = [];
        foreach (preg_split('/[,;\n]+/', $teks) ?: [] as $bagian) {
            $bagian = trim($bagian);
            if (preg_match('/\d/', $bagian) && $bagian !== '0') { // Penanda tanpa angka (mis. "n" di baris contoh) diabaikan.
                $hasil[] = PemeriksaRktm::normalkanKode($bagian) ?? $bagian;
            }
        }

        return array_values(array_unique($hasil));
    }

    /** Gabung kode RKTM (urut munculnya, tanpa dobel) menjadi teks "a, b, c". */
    private static function gabungRktm(string $ada, string $tambah): string
    {
        return implode(', ', array_values(array_unique([...self::daftarRktm($ada), ...self::daftarRktm($tambah)])));
    }

    /** @return list<string> */
    private static function rktmAsing(string $teks): array
    {
        return array_values(array_filter(self::daftarRktm($teks), fn (string $k) => ! preg_match('/^\d+\.\d+\.\d+$/', $k)));
    }
}
