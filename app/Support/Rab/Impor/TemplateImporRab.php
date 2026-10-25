<?php

namespace App\Support\Rab\Impor;

use App\Livewire\Forms\ItemBelanjaForm;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Format tabel rincian RAB untuk diisi lalu diimpor (opsional — tabel dari RAB lama juga bisa langsung diunggah).
 * Kolom Jumlah berisi rumus Vol × Harga sehingga pengisi melihat hasilnya; aplikasi tetap menghitung ulang.
 */
final class TemplateImporRab
{
    public const LEMBAR = 'Rincian';

    public const KOLOM = ['No', 'Kode', 'Uraian', 'Vol', 'Satuan', 'Harga Satuan (Rp)', 'Jumlah (Rp)'];

    public const BARIS_ISIAN = 100;

    public static function namaBerkas(): string
    {
        return 'format-rincian-rab.xlsx';
    }

    public static function buat(): Spreadsheet
    {
        $buku = new Spreadsheet;
        $buku->getProperties()->setTitle('Format Rincian RAB')->setCreator(config('sigenrabtor.aplikasi.nama'));

        $lembar = $buku->getActiveSheet()->setTitle(self::LEMBAR);
        $lembar->fromArray(self::KOLOM, null, 'A1');
        $lembar->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F6F50']],
        ]);
        $lembar->freezePane('A2');
        foreach (['A' => 6, 'B' => 12, 'C' => 55, 'D' => 8, 'E' => 12, 'F' => 18, 'G' => 18] as $kolom => $lebar) {
            $lembar->getColumnDimension($kolom)->setWidth($lebar);
        }

        $akhir = self::BARIS_ISIAN + 1;
        for ($baris = 2; $baris <= $akhir; $baris++) {
            $lembar->setCellValue("G{$baris}", "=IF(AND(D{$baris}<>\"\",F{$baris}<>\"\"),ROUND(D{$baris}*F{$baris},0),\"\")");
        }
        $lembar->getStyle("F2:G{$akhir}")->getNumberFormat()->setFormatCode('#,##0');

        // Pilihan satuan & kode akun sebagai bantuan; isian lain tetap diterima (gaya peringatan, bukan larangan).
        foreach (['B' => array_keys(ItemBelanjaForm::KODE_AKUN), 'E' => ItemBelanjaForm::SATUAN] as $kolom => $pilihan) {
            $validasi = new DataValidation;
            $validasi->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_INFORMATION)
                ->setAllowBlank(true)
                ->setShowDropDown(true)
                ->setShowErrorMessage(false)
                ->setFormula1('"'.implode(',', $pilihan).'"');
            $lembar->setDataValidation("{$kolom}2:{$kolom}{$akhir}", $validasi);
        }

        $petunjuk = $buku->createSheet()->setTitle('Petunjuk');
        $petunjuk->fromArray([
            ['Petunjuk Impor Rincian RAB'],
            [],
            ['1. Satu baris = satu item belanja. Uraian, Vol, Satuan, dan Harga Satuan wajib; Kode akun opsional.'],
            ['2. Tabel dari RAB lama juga bisa langsung diunggah selama ada judul kolom seperti Uraian, Vol, Satuan, Harga Satuan, Jumlah.'],
            ['3. Vol boleh ditulis bersama satuan, mis. "6 OJ"; desimal memakai koma, mis. 1,5.'],
            ['4. Bila Jumlah tidak sama dengan Vol × Harga (mis. "2 OJ … (6 orang)" = 300.000), volume disesuaikan otomatis agar jumlahnya sama.'],
            ['5. Baris judul kelompok (mis. "521211 Belanja Bahan") dilewati; kodenya dipakai item di bawahnya. Baris TOTAL/JUMLAH dipakai untuk mencocokkan total.'],
            ['6. Kelompok belanja (bahan/jasa/lain-lain) ditebak dari uraian dan bisa diubah setelah impor.'],
        ], null, 'A1');
        $petunjuk->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $buku->setActiveSheetIndex(0);

        return $buku;
    }
}
