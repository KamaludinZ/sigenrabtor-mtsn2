<?php

namespace App\Support\Kegiatan\Impor;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Membaca berkas impor (xlsx/xls/csv) menjadi baris mentah berkunci isian template.
 *
 * Judul kolom dicari di 10 baris teratas sehingga urutan kolom boleh berbeda dari template asal judulnya sama.
 * Kesalahan tingkat berkas (bukan template, kosong, terlalu banyak baris) dilempar sebagai GagalBacaImpor.
 */
final class PembacaImporKegiatan
{
    private const CARI_JUDUL_DI = 10;

    /** Ekstensi berkas → pembaca PhpSpreadsheet; isi berkas harus sesuai ekstensinya. */
    private const PEMBACA = ['xlsx' => 'Xlsx', 'xls' => 'Xls', 'csv' => 'Csv'];

    /**
     * @return list<array{baris: int, nilai: array<string, string|int|float|null>}>
     *
     * @throws GagalBacaImpor
     */
    public static function baca(string $path, ?string $namaAsli = null): array
    {
        $lembar = self::lembar($path, $namaAsli ?? $path);
        $kolom = self::petakanKolom($lembar);
        $hasil = [];

        $barisJudul = $kolom['__baris'];
        unset($kolom['__baris']);

        foreach ($lembar->getRowIterator($barisJudul + 1) as $baris) {
            $nomor = $baris->getRowIndex();
            $nilai = [];

            foreach ($kolom as $kunci => $huruf) {
                $nilai[$kunci] = self::nilaiSel($lembar, $huruf.$nomor);
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

    private static function lembar(string $path, string $namaAsli): Worksheet
    {
        $ekstensi = mb_strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));

        try {
            $pembaca = IOFactory::createReader(self::PEMBACA[$ekstensi] ?? IOFactory::identify($path));

            if (! $pembaca->canRead($path)) {
                throw new GagalBacaImpor('isi tidak sesuai ekstensi');
            }

            if ($pembaca instanceof Csv) {
                $pembaca->setInputEncoding(Csv::GUESS_ENCODING);
                $pembaca->setDelimiter(null);
            }

            $pembaca->setReadDataOnly(true);
            $buku = $pembaca->load($path);
        } catch (Throwable) {
            throw new GagalBacaImpor('Berkas "'.basename($namaAsli).'" tidak dapat dibaca sebagai Excel/CSV. Gunakan template .xlsx dari tombol "Unduh template".');
        }

        return $buku->getSheetByName(TemplateImporKegiatan::LEMBAR) ?? $buku->getSheet(0);
    }

    /**
     * @return array<string, string|int> Kunci isian → huruf kolom, plus '__baris' = nomor baris judul.
     */
    private static function petakanKolom(Worksheet $lembar): array
    {
        $dikenal = [];
        foreach (TemplateImporKegiatan::KOLOM as $kunci => $judul) {
            $dikenal[self::normalkanJudul($judul)] = $kunci;
        }

        $batas = min(self::CARI_JUDUL_DI, $lembar->getHighestDataRow());
        $kolomTertinggi = $lembar->getHighestDataColumn();

        for ($baris = 1; $baris <= $batas; $baris++) {
            $peta = [];
            foreach ($lembar->getRowIterator($baris, $baris)->current()->getCellIterator('A', $kolomTertinggi) as $sel) {
                $kunci = $dikenal[self::normalkanJudul((string) $sel->getValue())] ?? null;
                if ($kunci !== null && ! isset($peta[$kunci])) {
                    $peta[$kunci] = $sel->getColumn();
                }
            }

            if (isset($peta['nama_kegiatan'])) {
                return $peta + ['__baris' => $baris];
            }
        }

        throw new GagalBacaImpor('Kolom "Nama Kegiatan" tidak ditemukan. Pakai template dari tombol "Unduh template" tanpa mengubah judul kolom.');
    }

    private static function normalkanJudul(string $judul): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($judul)));
    }

    private static function nilaiSel(Worksheet $lembar, string $alamat): string|int|float|null
    {
        $sel = $lembar->getCell($alamat);

        try {
            $nilai = $sel->getCalculatedValue();
        } catch (Throwable) {
            $nilai = $sel->getValue();
        }

        return match (true) {
            $nilai === null => null,
            is_int($nilai), is_float($nilai) => $nilai,
            is_bool($nilai) => $nilai ? 'TRUE' : 'FALSE',
            default => trim(preg_replace('/\s+/u', ' ', (string) $nilai)),
        };
    }
}
