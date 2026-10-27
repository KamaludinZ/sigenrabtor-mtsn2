<?php

namespace App\Support\Master\Impor;

use App\Support\Impor\LembarExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pembacaan & pemeriksaan kode SK dari sheet Perkin (atau sheet/CSV khusus SK): kolom Kode SK + Uraian SK, satu baris
 * pratinjau per kode (Perkin mengulang SK di setiap baris IKSK — baris berulang dengan uraian sama digabung), lalu
 * dibandingkan dengan master tahun itu (PratinjauMaster::bandingkan).
 */
final class PemeriksaSk
{
    /** Kunci isian → judul kolom yang dikenali (dinormalkan LembarExcel::normalkanJudul). */
    public const JUDUL = [
        'kode' => ['kode sk', 'kode sasaran kegiatan', 'kode sasaran', 'kode'],
        'uraian' => ['uraian sk', 'uraian sasaran kegiatan', 'sasaran kegiatan', 'nama sk', 'uraian'],
    ];

    /** "sk 10", "SK10", "SK-10" → "SK.10"; null bila bukan kode SK. */
    public static function normalkanKode(string $kode): ?string
    {
        return preg_match('/^SK\s*[.\-]?\s*(\d+)$/i', trim($kode), $cocok) ? 'SK.'.((int) $cocok[1]) : null;
    }

    /**
     * Baris pratinjau dari lembar (belum dibandingkan dengan master).
     *
     * @return list<array{baris: list<int>, kode: string, tampil: array{uraian: string}, status: string|null, pesan: list<string>}>
     *
     * @throws GagalPratinjauMaster Judul kolom tidak ditemukan.
     */
    public static function baca(Worksheet $lembar): array
    {
        $judul = LembarExcel::cariJudul($lembar, self::JUDUL, ['kode', 'uraian']);
        if ($judul === null) {
            throw new GagalPratinjauMaster('Judul kolom "Kode SK" dan "Uraian SK" tidak ditemukan di sheet '.$lembar->getTitle().'.');
        }

        $hasil = [];
        $posisi = [];
        for ($baris = $judul['baris'] + 1; $baris <= $lembar->getHighestDataRow(); $baris++) {
            $mentah = trim((string) LembarExcel::nilaiSel($lembar, $judul['kolom']['kode'].$baris));
            $uraian = trim((string) LembarExcel::nilaiSel($lembar, $judul['kolom']['uraian'].$baris));
            if ($mentah === '' && $uraian === '') {
                continue;
            }

            $kode = self::normalkanKode($mentah);
            $pesan = array_values(array_filter([
                $mentah === '' ? 'Kode SK kosong.' : ($kode === null ? "Kode \"{$mentah}\" tidak berformat SK.n (mis. SK.10)." : null),
                $uraian === '' ? 'Uraian SK kosong.' : null,
            ]));
            if ($pesan !== []) {
                $hasil[] = ['baris' => [$baris], 'kode' => $kode ?? $mentah, 'tampil' => ['uraian' => $uraian], 'status' => 'gagal', 'pesan' => $pesan];

                continue;
            }

            if (isset($posisi[$kode])) {
                $awal = &$hasil[$posisi[$kode]];
                if ($awal['tampil']['uraian'] === $uraian) {
                    $awal['baris'][] = $baris;
                } else {
                    $hasil[] = ['baris' => [$baris], 'kode' => $kode, 'tampil' => ['uraian' => $uraian], 'status' => 'gagal',
                        'pesan' => ["Uraian berbeda dengan baris {$awal['baris'][0]} untuk kode yang sama."]];
                }
                unset($awal);

                continue;
            }

            $posisi[$kode] = count($hasil);
            $hasil[] = ['baris' => [$baris], 'kode' => $kode, 'tampil' => ['uraian' => $uraian], 'status' => null, 'pesan' => []];
        }

        return $hasil;
    }
}
