<?php

namespace App\Support\Impor;

use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Pembukaan berkas impor (xlsx/xls/csv) dan pembacaan sel yang dipakai bersama pembaca impor kegiatan & rincian RAB.
 */
final class LembarExcel
{
    /** Ekstensi berkas → pembaca PhpSpreadsheet; isi berkas harus sesuai ekstensinya. */
    private const PEMBACA = ['xlsx' => 'Xlsx', 'xls' => 'Xls', 'csv' => 'Csv'];

    /**
     * Buka lembar bernama $namaLembar (bila ada) atau lembar pertama.
     *
     * @throws GagalBacaImpor
     */
    public static function buka(string $path, string $namaAsli, ?string $namaLembar = null, string $saran = 'Gunakan berkas .xlsx, .xls, atau .csv.'): Worksheet
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
                // CSV dibaca sebagai teks apa adanya: "600.000" (titik ribuan) jangan sampai menjadi 600 desimal.
                $pembaca->setValueBinder(new StringValueBinder);
            }

            $pembaca->setReadDataOnly(true);
            $buku = $pembaca->load($path);
        } catch (Throwable) {
            throw new GagalBacaImpor('Berkas "'.basename($namaAsli).'" tidak dapat dibaca sebagai Excel/CSV. '.$saran);
        }

        return ($namaLembar ? $buku->getSheetByName($namaLembar) : null) ?? $buku->getSheet(0);
    }

    /**
     * Cari baris judul tabel di $batas baris teratas: baris pertama yang memuat semua judul $wajib.
     *
     * @param  array<string, list<string>>  $sinonim  Kunci isian → judul kolom yang dikenali (sudah dinormalkan).
     * @param  list<string>  $wajib
     * @return array{baris: int, kolom: array<string, string>}|null Kolom: kunci isian → huruf kolom.
     */
    public static function cariJudul(Worksheet $lembar, array $sinonim, array $wajib, int $batas = 30): ?array
    {
        $kenal = [];
        foreach ($sinonim as $kunci => $daftar) {
            foreach ($daftar as $judul) {
                $kenal[self::normalkanJudul($judul)] ??= $kunci;
            }
        }

        $akhir = min($batas, $lembar->getHighestDataRow());
        $kolomTertinggi = $lembar->getHighestDataColumn();

        for ($baris = 1; $baris <= $akhir; $baris++) {
            $peta = [];
            foreach ($lembar->getRowIterator($baris, $baris)->current()->getCellIterator('A', $kolomTertinggi) as $sel) {
                $kunci = $kenal[self::normalkanJudul((string) $sel->getValue())] ?? null;
                if ($kunci !== null && ! isset($peta[$kunci])) {
                    $peta[$kunci] = $sel->getColumn();
                }
            }

            if (array_diff($wajib, array_keys($peta)) === []) {
                return ['baris' => $baris, 'kolom' => $peta];
            }
        }

        return null;
    }

    /** "Harga Satuan (Rp.)" → "harga satuan rp": huruf kecil, selain huruf/angka jadi spasi. */
    public static function normalkanJudul(string $judul): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($judul)));
    }

    /** Nilai sel: angka apa adanya, teks dirapikan spasinya, rumus dihitung bila bisa. */
    public static function nilaiSel(Worksheet $lembar, string $alamat): string|int|float|null
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
