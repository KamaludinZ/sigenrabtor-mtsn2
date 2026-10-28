<?php

namespace App\Support\Master\Impor;

use App\Support\Impor\LembarExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pembacaan & pemeriksaan 5 Budaya Mutu dari sheet "5 Budaya Mutu" (atau CSV khusus): No (opsional — kosong memakai
 * urutan baris) dan nama butir. Nomor & nama tidak boleh dobel; jumlah butir selain lima hanya diberi peringatan.
 */
final class PemeriksaBudayaMutu
{
    public const JUMLAH_BAKU = 5;

    public const JUDUL = [
        'nomor' => ['no', 'nomor', 'no urut', 'urutan'],
        'nama' => ['budaya mutu', 'nama budaya mutu', 'nama', 'butir', 'uraian'],
    ];

    /**
     * @return list<array{baris: list<int>, kode: string, tampil: array{nama: string}, status: string|null, pesan: list<string>}>
     *
     * @throws GagalPratinjauMaster Judul kolom tidak ditemukan.
     */
    public static function baca(Worksheet $lembar): array
    {
        $judul = KolomMaster::judul($lembar, 'budaya');

        $hasil = [];
        $nomorDipakai = [];
        $namaDipakai = [];
        $urutan = 0;
        for ($baris = $judul['baris'] + 1; $baris <= $lembar->getHighestDataRow(); $baris++) {
            $mentah = isset($judul['kolom']['nomor']) ? trim((string) LembarExcel::nilaiSel($lembar, $judul['kolom']['nomor'].$baris)) : '';
            $nama = trim((string) LembarExcel::nilaiSel($lembar, $judul['kolom']['nama'].$baris));
            if ($mentah === '' && $nama === '') {
                continue;
            }

            $urutan++;
            $nomor = $mentah === '' ? $urutan : (preg_match('/^\d+(\.0+)?$/', $mentah) ? (int) $mentah : null);
            $kode = $nomor !== null ? (string) $nomor : $mentah;
            $kunciNama = mb_strtolower($nama);
            $pesan = array_values(array_filter([
                $nomor === null || $nomor < 1 ? "Nomor \"{$mentah}\" harus bilangan bulat mulai 1." : null,
                $nama === '' ? 'Nama budaya mutu kosong.' : null,
                ...KolomMaster::galatPanjang('budaya', ['nama' => $nama]),
                $nomor !== null && isset($nomorDipakai[$nomor]) ? "Nomor {$nomor} sudah dipakai di baris {$nomorDipakai[$nomor]}." : null,
                $nama !== '' && isset($namaDipakai[$kunciNama]) ? "Nama sama dengan baris {$namaDipakai[$kunciNama]}." : null,
            ]));
            $hasil[] = ['baris' => [$baris], 'kode' => $kode, 'tampil' => ['nama' => $nama], 'status' => $pesan === [] ? null : 'gagal', 'pesan' => $pesan];
            if ($pesan === []) {
                $nomorDipakai[$nomor] = $baris;
                $namaDipakai[$kunciNama] = $baris;
            }
        }

        return $hasil;
    }

    /** Peringatan jumlah butir sah (selain gagal) yang tidak lima; null bila lima. */
    public static function peringatanJumlah(int $jumlah): ?string
    {
        return $jumlah === self::JUMLAH_BAKU ? null : "Berkas berisi {$jumlah} butir budaya mutu — biasanya ".self::JUMLAH_BAKU.' butir. Periksa lagi sebelum menyimpan.';
    }
}
