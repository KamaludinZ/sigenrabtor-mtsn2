<?php

namespace App\Support\Master\Impor;

use App\Support\Impor\LembarExcel;
use App\Support\Master\FormatKode;
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

    /** "sk 10", "SK10", "SK-10" → "SK.10"; "SK 6708.1" → "SK.6708.1"; null bila bukan kode SK (FormatKode::sk). */
    public static function normalkanKode(string $kode): ?string
    {
        return FormatKode::sk($kode);
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
        $judul = KolomMaster::judul($lembar, 'sk');

        $hasil = [];
        $posisi = [];
        for ($baris = $judul['baris'] + 1; $baris <= $lembar->getHighestDataRow(); $baris++) {
            $mentah = trim((string) LembarExcel::nilaiSel($lembar, $judul['kolom']['kode'].$baris));
            $uraian = trim((string) LembarExcel::nilaiSel($lembar, $judul['kolom']['uraian'].$baris));
            if ($mentah === '' && $uraian === '') {
                continue;
            }

            $kode = self::normalkanKode($mentah);
            if ($kode === null && KolomMaster::judulKelompok($mentah, [$uraian])) {
                continue; // Baris judul kelompok, mis. "BIDANG TATA KELOLA".
            }
            $pesan = array_values(array_filter([
                $mentah === '' ? 'Kode SK kosong.' : ($kode === null ? "Kode \"{$mentah}\" tidak berformat kode SK (mis. ".FormatKode::CONTOH_SK.').' : null),
                $uraian === '' ? 'Uraian SK kosong.' : null,
                ...KolomMaster::galatPanjang('sk', ['uraian' => $uraian]),
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
