<?php

namespace App\Support\Kegiatan\Impor;

use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\MasterKode;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Excel impor kegiatan massal (mengikuti kolom cascade Perkin: kegiatan, leading, pagu DIPA/Komite, bulan).
 *
 * Lembar "Kegiatan" diisi pengguna mulai baris 2; lembar "Petunjuk" berisi aturan & contoh, lembar "Referensi"
 * (tersembunyi) menjadi sumber pilihan dropdown Leading dari master tahun anggaran aktif.
 */
final class TemplateImporKegiatan
{
    public const LEMBAR = 'Kegiatan';

    /** Batas baris data per berkas agar pemeriksaan & proses tetap ringan. */
    public const MAKS_BARIS = 500;

    /** Kunci isian → judul kolom (urutan = urutan kolom A, B, C, …). Tanda * = wajib. */
    public const KOLOM = [
        'no' => 'No',
        'nama_kegiatan' => 'Nama Kegiatan *',
        'leading' => 'Leading',
        'pagu_dipa' => 'Pagu DIPA',
        'pagu_komite' => 'Pagu Komite',
        'bulan' => 'Bulan Pelaksanaan',
    ];

    public static function namaBerkas(int $tahun): string
    {
        return "template-impor-kegiatan-{$tahun}.xlsx";
    }

    /** Teks pilihan Leading di template, mis. "1.6 – Tim Penjamin Mutu". */
    public static function labelLeading(array $leading): string
    {
        return $leading['kode'].' – '.$leading['nama_unit'];
    }

    public static function buat(int $tahun): Spreadsheet
    {
        $buku = new Spreadsheet;
        $buku->getProperties()->setTitle("Template Impor Kegiatan {$tahun}")->setCreator(config('sigenrabtor.aplikasi.nama'));

        $leadings = MasterKode::leadings();
        $referensi = self::lembarReferensi($buku, $leadings);
        self::lembarKegiatan($buku->getSheet(0), count($leadings), $referensi->getTitle());
        self::lembarPetunjuk($buku->createSheet(1), $tahun, $leadings);

        $buku->setActiveSheetIndex(0);

        return $buku;
    }

    private static function lembarKegiatan(Worksheet $lembar, int $jumlahLeading, string $namaReferensi): void
    {
        $lembar->setTitle(self::LEMBAR);
        $lembar->fromArray(array_values(self::KOLOM), null, 'A1');

        $akhir = self::MAKS_BARIS + 1;
        $kolomTerakhir = chr(ord('A') + count(self::KOLOM) - 1);

        $lembar->getStyle("A1:{$kolomTerakhir}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F6F50']],
        ]);
        $lembar->freezePane('A2');
        foreach (['A' => 6, 'B' => 60, 'C' => 30, 'D' => 16, 'E' => 16, 'F' => 22] as $kolom => $lebar) {
            $lembar->getColumnDimension($kolom)->setWidth($lebar);
        }
        $lembar->getStyle("D2:E{$akhir}")->getNumberFormat()->setFormatCode('#,##0');

        $leading = new DataValidation;
        $leading->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Leading tidak dikenal')
            ->setError('Pilih leading dari daftar master tahun anggaran aktif.')
            ->setFormula1("'{$namaReferensi}'!\$A\$2:\$A\$".($jumlahLeading + 1));
        $lembar->setDataValidation("C2:C{$akhir}", $leading);

        $pagu = new DataValidation;
        $pagu->setType(DataValidation::TYPE_WHOLE)
            ->setOperator(DataValidation::OPERATOR_GREATERTHANOREQUAL)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowErrorMessage(true)
            ->setErrorTitle('Pagu tidak valid')
            ->setError('Isi pagu dengan angka rupiah bulat tanpa titik, mis. 10000000.')
            ->setFormula1('0');
        $lembar->setDataValidation("D2:E{$akhir}", $pagu);
    }

    /**
     * @param  list<array{kode: string, nama_unit: string}>  $leadings
     */
    private static function lembarReferensi(Spreadsheet $buku, array $leadings): Worksheet
    {
        $lembar = $buku->createSheet();
        $lembar->setTitle('Referensi');
        $lembar->setCellValue('A1', 'Leading');
        $lembar->fromArray(array_map(fn ($l) => [self::labelLeading($l)], $leadings), null, 'A2');
        $lembar->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        return $lembar;
    }

    /**
     * @param  list<array{kode: string, nama_unit: string}>  $leadings
     */
    private static function lembarPetunjuk(Worksheet $lembar, int $tahun, array $leadings): void
    {
        $lembar->setTitle('Petunjuk');
        $baris = [
            ["Petunjuk Impor Kegiatan — Tahun Anggaran {$tahun}"],
            [],
            ['1. Isi lembar "Kegiatan" mulai baris 2; satu baris = satu kegiatan. Jangan ubah judul kolom di baris 1.'],
            ['2. Nama Kegiatan wajib diisi (maks. 255 karakter). Nama yang sudah ada di aplikasi dilewati, jadi berkas yang sama aman diimpor ulang setelah baris gagal diperbaiki.'],
            ['3. Leading dipilih dari dropdown (kode atau nama unit juga diterima). Akun leading hanya dapat mengimpor untuk leading-nya sendiri.'],
            ['4. Pagu DIPA / Pagu Komite: angka rupiah bulat, mis. 10000000. Isi salah satu atau keduanya; sumber dana mengikuti pagu yang terisi.'],
            ['5. Bulan Pelaksanaan: nomor atau nama bulan, mis. 7 · Juli · 7-9 · Juli – September · 3, 6, 9.'],
            ['6. Maksimal '.self::MAKS_BARIS.' baris per berkas. Kegiatan hasil impor tersimpan sebagai Draf; data lain dilengkapi di halaman Data Kegiatan.'],
            [],
            ['Contoh baris'],
            array_values(self::KOLOM),
            [1, 'Workshop Penyusunan Kurikulum Madrasah', self::labelLeading($leadings[0] ?? ['kode' => '1.1', 'nama_unit' => 'Waka Kurikulum']), '', 10000000, 'Juli'],
            [2, 'Supervisi Akademik dan Refleksi Pembelajaran', self::labelLeading($leadings[5] ?? $leadings[0] ?? ['kode' => '1.6', 'nama_unit' => 'Tim Penjamin Mutu']), 6000000, 2500000, '8-9'],
            [],
            ['Nama bulan yang dikenali: '.implode(', ', JadwalPelaksanaan::NAMA_BULAN).' (atau 3 huruf awal).'],
        ];
        $lembar->fromArray($baris, null, 'A1');
        $lembar->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $lembar->getStyle('A10')->getFont()->setBold(true);
        $lembar->getStyle('A11:F11')->getFont()->setBold(true);
        $lembar->getColumnDimension('A')->setWidth(6);
        $lembar->getColumnDimension('B')->setWidth(50);
        $lembar->getColumnDimension('C')->setWidth(30);
        foreach (['D', 'E', 'F'] as $kolom) {
            $lembar->getColumnDimension($kolom)->setWidth(16);
        }
    }
}
