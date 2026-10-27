<?php

namespace App\Support\Master\Impor;

use App\Support\Impor\LembarExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pembacaan & pemeriksaan indikator EDM dari sheet EDM (atau CSV khusus EDM): Kode (huruf komponen + nomor, mis. A1),
 * Uraian, dan Komponen (nama, opsional — kosong memakai nama komponen huruf itu di master). Nama komponen yang berbeda
 * untuk huruf yang sama di berkas hanya diberi peringatan.
 */
final class PemeriksaEdm
{
    public const JUDUL = [
        'kode' => ['kode edm', 'kode indikator', 'kode'],
        'uraian' => ['uraian indikator', 'uraian edm', 'indikator', 'uraian'],
        'komponen' => ['komponen', 'nama komponen', 'komponen edm'],
    ];

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
        $judul = LembarExcel::cariJudul($lembar, self::JUDUL, ['kode', 'uraian']);
        if ($judul === null) {
            throw new GagalPratinjauMaster('Judul kolom "Kode" dan "Uraian" tidak ditemukan di sheet '.$lembar->getTitle().'.');
        }

        $hasil = [];
        $posisi = [];
        $namaPerHuruf = [];
        for ($baris = $judul['baris'] + 1; $baris <= $lembar->getHighestDataRow(); $baris++) {
            $sel = fn (string $kunci) => isset($judul['kolom'][$kunci]) ? trim((string) LembarExcel::nilaiSel($lembar, $judul['kolom'][$kunci].$baris)) : '';
            [$mentah, $uraian, $namaKomponen] = [$sel('kode'), $sel('uraian'), $sel('komponen')];
            if ($mentah === '' && $uraian === '') {
                continue;
            }

            $kode = self::normalkanKode($mentah);
            $huruf = $kode !== null ? $kode[0] : '';
            $komponen = $namaKomponen !== '' ? $namaKomponen : ($komponenMaster[$huruf] ?? $huruf);
            $tampil = ['uraian' => $uraian, 'komponen' => $komponen];
            $pesan = array_values(array_filter([
                $mentah === '' ? 'Kode EDM kosong.' : ($kode === null ? "Kode \"{$mentah}\" tidak berformat huruf + nomor (mis. A1, C3)." : null),
                $uraian === '' ? 'Uraian indikator kosong.' : null,
            ]));
            if ($pesan !== []) {
                $hasil[] = ['baris' => [$baris], 'kode' => $kode ?? $mentah, 'tampil' => $tampil, 'status' => 'gagal', 'pesan' => $pesan];

                continue;
            }

            if (isset($posisi[$kode])) {
                $awal = &$hasil[$posisi[$kode]];
                if ($awal['tampil'] === $tampil) {
                    $awal['baris'][] = $baris;
                } else {
                    $hasil[] = ['baris' => [$baris], 'kode' => $kode, 'tampil' => $tampil, 'status' => 'gagal',
                        'pesan' => ["Isi berbeda dengan baris {$awal['baris'][0]} untuk kode yang sama."]];
                }
                unset($awal);

                continue;
            }

            $namaPerHuruf[$huruf] ??= ['nama' => $komponen, 'baris' => $baris];
            $posisi[$kode] = count($hasil);
            $hasil[] = ['baris' => [$baris], 'kode' => $kode, 'tampil' => $tampil, 'status' => null,
                'pesan' => $namaPerHuruf[$huruf]['nama'] === $komponen ? [] : ["Nama komponen {$huruf} berbeda dengan baris {$namaPerHuruf[$huruf]['baris']} ({$namaPerHuruf[$huruf]['nama']})."]];
        }

        return $hasil;
    }
}
