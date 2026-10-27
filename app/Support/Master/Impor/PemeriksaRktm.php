<?php

namespace App\Support\Master\Impor;

use App\Support\Impor\LembarExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pembacaan & pemeriksaan kode RKTM dari sheet SNP (atau sheet/CSV khusus RKTM): Kode (n.n.n), Standar (SNP), Kegiatan,
 * Sub kegiatan — satu baris pratinjau per kode. SNP & kegiatan yang dikosongkan di bawah baris pertama kelompoknya (gaya
 * sel gabungan Excel) diisi dari baris di atasnya. SNP yang berbeda untuk nomor standar yang sama hanya diberi peringatan.
 */
final class PemeriksaRktm
{
    public const JUDUL = [
        'kode' => ['kode rktm', 'kode'],
        'snp' => ['snp', 'standar snp', 'standar nasional pendidikan', 'standar'],
        'kegiatan' => ['kegiatan', 'program'],
        'sub_kegiatan' => ['sub kegiatan', 'subkegiatan', 'uraian sub kegiatan', 'uraian'],
    ];

    /** "6.17.12", " 06 . 17 . 12", "6,17,12" → "6.17.12"; null bila bukan tiga bagian angka. */
    public static function normalkanKode(string $kode): ?string
    {
        $kode = str_replace([' ', ','], ['', '.'], trim($kode));

        return preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $kode, $cocok) ? ((int) $cocok[1]).'.'.((int) $cocok[2]).'.'.((int) $cocok[3]) : null;
    }

    /**
     * @return list<array{baris: list<int>, kode: string, tampil: array{sub_kegiatan: string, kegiatan: string, snp: string}, status: string|null, pesan: list<string>}>
     *
     * @throws GagalPratinjauMaster Judul kolom tidak ditemukan.
     */
    public static function baca(Worksheet $lembar): array
    {
        $judul = LembarExcel::cariJudul($lembar, self::JUDUL, ['kode', 'snp', 'kegiatan', 'sub_kegiatan']);
        if ($judul === null) {
            throw new GagalPratinjauMaster('Judul kolom "Kode", "SNP", "Kegiatan", dan "Sub Kegiatan" tidak ditemukan di sheet '.$lembar->getTitle().'.');
        }

        $hasil = [];
        $posisi = [];
        $snpPerStandar = [];
        $sebelum = ['snp' => '', 'kegiatan' => ''];
        for ($baris = $judul['baris'] + 1; $baris <= $lembar->getHighestDataRow(); $baris++) {
            $sel = fn (string $kunci) => trim((string) LembarExcel::nilaiSel($lembar, $judul['kolom'][$kunci].$baris));
            [$mentah, $snp, $kegiatan, $sub] = [$sel('kode'), $sel('snp'), $sel('kegiatan'), $sel('sub_kegiatan')];
            if ($mentah === '' && $sub === '') {
                // Baris judul kelompok (mis. hanya SNP/kegiatan) menjadi isian untuk baris di bawahnya.
                $sebelum = ['snp' => $snp !== '' ? $snp : $sebelum['snp'], 'kegiatan' => $kegiatan !== '' ? $kegiatan : $sebelum['kegiatan']];

                continue;
            }

            $snp = $snp !== '' ? $snp : $sebelum['snp'];
            $kegiatan = $kegiatan !== '' ? $kegiatan : $sebelum['kegiatan'];
            $kode = self::normalkanKode($mentah);
            $tampil = ['sub_kegiatan' => $sub, 'kegiatan' => $kegiatan, 'snp' => $snp];
            $pesan = array_values(array_filter([
                $mentah === '' ? 'Kode RKTM kosong.' : ($kode === null ? "Kode \"{$mentah}\" tidak berformat n.n.n (mis. 6.17.12) — format sel Teks agar tidak terbaca angka." : null),
                $snp === '' ? 'Standar (SNP) kosong.' : null,
                $kegiatan === '' ? 'Kegiatan kosong.' : null,
                $sub === '' ? 'Sub kegiatan kosong.' : null,
            ]));
            if ($pesan !== []) {
                $hasil[] = ['baris' => [$baris], 'kode' => $kode ?? $mentah, 'tampil' => $tampil, 'status' => 'gagal', 'pesan' => $pesan];

                continue;
            }
            $sebelum = ['snp' => $snp, 'kegiatan' => $kegiatan];

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

            $standar = strtok($kode, '.');
            $snpPerStandar[$standar] ??= ['snp' => $snp, 'baris' => $baris];
            $posisi[$kode] = count($hasil);
            $hasil[] = ['baris' => [$baris], 'kode' => $kode, 'tampil' => $tampil, 'status' => null,
                'pesan' => $snpPerStandar[$standar]['snp'] === $snp ? [] : ["SNP berbeda dengan kode {$standar}.x di baris {$snpPerStandar[$standar]['baris']} ({$snpPerStandar[$standar]['snp']})."]];
        }

        return $hasil;
    }
}
