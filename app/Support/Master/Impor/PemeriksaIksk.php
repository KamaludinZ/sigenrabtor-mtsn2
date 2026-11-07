<?php

namespace App\Support\Master\Impor;

use App\Support\Impor\LembarExcel;
use App\Support\Master\FormatKode;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pembacaan & pemeriksaan kode IKSK dari sheet Perkin (atau sheet/CSV khusus IKSK): Kode IKSK, SK induk, dan uraian
 * indikator — satu baris pratinjau per kode. SK induk wajib; keberadaannya diperiksa PratinjauMaster terhadap SK berkas
 * atau master. Nomor IKSK yang tidak sejalan dengan SK induknya (IKSK 10.x di bawah SK.11) hanya diberi peringatan.
 */
final class PemeriksaIksk
{
    public const JUDUL = [
        'kode' => ['kode iksk', 'kode indikator', 'kode'],
        'kode_sk' => ['kode sk', 'sk induk', 'sk'],
        'uraian' => ['uraian iksk', 'uraian indikator', 'indikator', 'uraian'],
    ];

    /** "iksk 10.2", "IKSK10-2" → "IKSK 10.2"; "IKSK 6708.1.4" → "IKSK.6708.1.4"; null bila bukan IKSK (FormatKode::iksk). */
    public static function normalkanKode(string $kode): ?string
    {
        return FormatKode::iksk($kode);
    }

    /**
     * @return list<array{baris: list<int>, kode: string, tampil: array{kode_sk: string, uraian: string}, status: string|null, pesan: list<string>}>
     *
     * @throws GagalPratinjauMaster Judul kolom tidak ditemukan.
     */
    public static function baca(Worksheet $lembar): array
    {
        $judul = KolomMaster::judul($lembar, 'iksk');

        $hasil = [];
        $posisi = [];
        // Tata letak cascade: SK yang tidak ditulis ulang di baris IKSK mengikuti SK terakhir di atasnya.
        $cascade = (bool) ($judul['cascade'] ?? false);
        $skTerakhir = '';
        for ($baris = $judul['baris'] + 1; $baris <= $lembar->getHighestDataRow(); $baris++) {
            $sel = fn (string $kunci) => trim((string) LembarExcel::nilaiSel($lembar, $judul['kolom'][$kunci].$baris));
            [$mentah, $skMentah, $uraian] = [$sel('kode'), $sel('kode_sk'), $sel('uraian')];
            if ($cascade) {
                $skTerakhir = $skMentah !== '' ? $skMentah : $skTerakhir;
                $skMentah = $skMentah !== '' ? $skMentah : $skTerakhir;
            }
            if ($mentah === '' && $uraian === '') {
                continue; // Baris SK tanpa IKSK atau baris kosong.
            }

            $kode = self::normalkanKode($mentah);
            if ($kode === null && KolomMaster::judulKelompok($mentah, [$uraian])) {
                continue;
            }
            $sk = PemeriksaSk::normalkanKode($skMentah);
            $tampil = ['kode_sk' => $sk ?? $skMentah, 'uraian' => $uraian];
            $pesan = array_values(array_filter([
                $mentah === '' ? 'Kode IKSK kosong.' : ($kode === null ? "Kode \"{$mentah}\" tidak berformat kode IKSK (mis. ".FormatKode::CONTOH_IKSK.').' : null),
                $skMentah === '' ? 'Kode SK induk kosong.' : ($sk === null ? "SK induk \"{$skMentah}\" tidak berformat kode SK." : null),
                $uraian === '' ? 'Uraian IKSK kosong.' : null,
                ...KolomMaster::galatPanjang('iksk', ['uraian' => $uraian]),
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
                        'pesan' => ["SK induk atau uraian berbeda dengan baris {$awal['baris'][0]} untuk kode yang sama."]];
                }
                unset($awal);

                continue;
            }

            $posisi[$kode] = count($hasil);
            $hasil[] = ['baris' => [$baris], 'kode' => $kode, 'tampil' => $tampil, 'status' => null,
                'pesan' => FormatKode::sejalan($kode, $sk) ? [] : ["Nomor {$kode} tidak sejalan dengan SK induk {$sk} — periksa lagi."]];
        }

        return $hasil;
    }
}
