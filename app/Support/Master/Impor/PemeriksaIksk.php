<?php

namespace App\Support\Master\Impor;

use App\Support\Impor\LembarExcel;
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

    /** "iksk 10.2", "IKSK10-2" → "IKSK 10.2"; null bila bukan kode IKSK. */
    public static function normalkanKode(string $kode): ?string
    {
        return preg_match('/^IKSK\s*(\d+)\s*[.\-]\s*(\d+)$/i', trim($kode), $cocok) ? 'IKSK '.((int) $cocok[1]).'.'.((int) $cocok[2]) : null;
    }

    /**
     * @return list<array{baris: list<int>, kode: string, tampil: array{kode_sk: string, uraian: string}, status: string|null, pesan: list<string>}>
     *
     * @throws GagalPratinjauMaster Judul kolom tidak ditemukan.
     */
    public static function baca(Worksheet $lembar): array
    {
        $judul = LembarExcel::cariJudul($lembar, self::JUDUL, ['kode', 'kode_sk', 'uraian']);
        if ($judul === null) {
            throw new GagalPratinjauMaster('Judul kolom "Kode IKSK", "Kode SK", dan "Uraian IKSK" tidak ditemukan di sheet '.$lembar->getTitle().'.');
        }

        $hasil = [];
        $posisi = [];
        for ($baris = $judul['baris'] + 1; $baris <= $lembar->getHighestDataRow(); $baris++) {
            $sel = fn (string $kunci) => trim((string) LembarExcel::nilaiSel($lembar, $judul['kolom'][$kunci].$baris));
            [$mentah, $skMentah, $uraian] = [$sel('kode'), $sel('kode_sk'), $sel('uraian')];
            if ($mentah === '' && $uraian === '') {
                continue; // Baris SK tanpa IKSK atau baris kosong.
            }

            $kode = self::normalkanKode($mentah);
            $sk = PemeriksaSk::normalkanKode($skMentah);
            $tampil = ['kode_sk' => $sk ?? $skMentah, 'uraian' => $uraian];
            $pesan = array_values(array_filter([
                $mentah === '' ? 'Kode IKSK kosong.' : ($kode === null ? "Kode \"{$mentah}\" tidak berformat IKSK n.m (mis. IKSK 10.2)." : null),
                $skMentah === '' ? 'Kode SK induk kosong.' : ($sk === null ? "SK induk \"{$skMentah}\" tidak berformat SK.n." : null),
                $uraian === '' ? 'Uraian IKSK kosong.' : null,
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

            $nomorSk = (int) substr($sk, 3);
            $nomorIksk = (int) substr($kode, 5);
            $posisi[$kode] = count($hasil);
            $hasil[] = ['baris' => [$baris], 'kode' => $kode, 'tampil' => $tampil, 'status' => null,
                'pesan' => $nomorSk === $nomorIksk ? [] : ["Nomor {$kode} tidak sejalan dengan SK induk {$sk} — periksa lagi."]];
        }

        return $hasil;
    }
}
