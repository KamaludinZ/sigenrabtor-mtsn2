<?php

namespace App\Support\Kegiatan\Impor;

use App\Support\Impor\GagalBacaImpor;
use App\Support\Impor\LembarExcel;

/**
 * Membaca berkas impor kegiatan (xlsx/xls/csv) menjadi baris mentah berkunci isian template.
 *
 * Judul kolom dicari di 10 baris teratas sehingga urutan kolom boleh berbeda dari template asal judulnya sama.
 * Kesalahan tingkat berkas (bukan template, kosong, terlalu banyak baris) dilempar sebagai GagalBacaImpor.
 */
final class PembacaImporKegiatan
{
    private const CARI_JUDUL_DI = 10;

    /**
     * @return list<array{baris: int, nilai: array<string, string|int|float|null>}>
     *
     * @throws GagalBacaImpor
     */
    public static function baca(string $path, ?string $namaAsli = null): array
    {
        $lembar = LembarExcel::buka($path, $namaAsli ?? $path, TemplateImporKegiatan::LEMBAR, 'Gunakan template .xlsx dari tombol "Unduh template".');
        $judul = LembarExcel::cariJudul($lembar, array_map(fn ($j) => [$j], TemplateImporKegiatan::KOLOM), ['nama_kegiatan'], self::CARI_JUDUL_DI)
            ?? throw new GagalBacaImpor('Kolom "Nama Kegiatan" tidak ditemukan. Pakai template dari tombol "Unduh template" tanpa mengubah judul kolom.');

        $hasil = [];

        foreach ($lembar->getRowIterator($judul['baris'] + 1) as $baris) {
            $nomor = $baris->getRowIndex();
            $nilai = [];

            foreach ($judul['kolom'] as $kunci => $huruf) {
                $nilai[$kunci] = LembarExcel::nilaiSel($lembar, $huruf.$nomor);
            }

            // Baris kosong (termasuk yang hanya bernomor urut) tidak dihitung.
            $isi = array_filter(array_diff_key($nilai, ['no' => true]), fn ($v) => $v !== null && $v !== '');
            if ($isi === []) {
                continue;
            }

            if (count($hasil) >= TemplateImporKegiatan::MAKS_BARIS) {
                throw new GagalBacaImpor('Berkas berisi lebih dari '.TemplateImporKegiatan::MAKS_BARIS.' baris kegiatan. Bagi menjadi beberapa berkas.');
            }

            $hasil[] = ['baris' => $nomor, 'nilai' => $nilai];
        }

        if ($hasil === []) {
            throw new GagalBacaImpor('Berkas tidak berisi data kegiatan. Isi lembar "'.TemplateImporKegiatan::LEMBAR.'" mulai baris 2.');
        }

        return $hasil;
    }
}
