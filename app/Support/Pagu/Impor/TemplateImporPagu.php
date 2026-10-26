<?php

namespace App\Support\Pagu\Impor;

use App\Support\Kegiatan\Impor\TemplateImporKegiatan;
use App\Support\MasterKode;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template impor Data Pagu: kolom Nama Kegiatan, Leading, Pagu DIPA, Pagu Komite — judulnya sama dengan template impor
 * kegiatan sehingga berkas dari cascade Perkin yang sama bisa dipakai untuk keduanya.
 */
final class TemplateImporPagu
{
    public const LEMBAR = 'Data Pagu';

    public const KOLOM = ['no' => 'No', 'nama_kegiatan' => 'Nama Kegiatan *', 'leading' => 'Leading', 'pagu_dipa' => 'Pagu DIPA', 'pagu_komite' => 'Pagu Komite'];

    public static function namaBerkas(int $tahun): string
    {
        return "template-data-pagu-{$tahun}.xlsx";
    }

    public static function buat(int $tahun): Spreadsheet
    {
        $buku = new Spreadsheet;
        $buku->getProperties()->setTitle("Template Data Pagu {$tahun}")->setCreator(config('sigenrabtor.aplikasi.nama'));
        $leadings = MasterKode::leadings();

        $lembar = $buku->getActiveSheet()->setTitle(self::LEMBAR);
        $lembar->fromArray(array_values(self::KOLOM), null, 'A1');
        $lembar->getStyle('A1:E1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F6F50']],
        ]);
        $lembar->freezePane('A2');
        foreach (['A' => 6, 'B' => 60, 'C' => 30, 'D' => 16, 'E' => 16] as $kolom => $lebar) {
            $lembar->getColumnDimension($kolom)->setWidth($lebar);
        }
        $akhir = TemplateImporKegiatan::MAKS_BARIS + 1;
        $lembar->getStyle("D2:E{$akhir}")->getNumberFormat()->setFormatCode('#,##0');

        $referensi = $buku->createSheet()->setTitle('Referensi');
        $referensi->setCellValue('A1', 'Leading');
        $referensi->fromArray(array_map(fn (array $l) => [TemplateImporKegiatan::labelLeading($l)], $leadings), null, 'A2');
        $referensi->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $pilihan = new DataValidation;
        $pilihan->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank(true)->setShowDropDown(true)
            ->setShowErrorMessage(true)->setErrorTitle('Leading tidak dikenal')->setError('Pilih leading dari daftar master tahun anggaran aktif.')
            ->setFormula1("'Referensi'!\$A\$2:\$A\$".(count($leadings) + 1));
        $lembar->setDataValidation("C2:C{$akhir}", $pilihan);

        $petunjuk = $buku->createSheet()->setTitle('Petunjuk');
        $petunjuk->fromArray([
            ["Petunjuk Impor Data Pagu — Tahun Anggaran {$tahun}"],
            [],
            ['1. Isi lembar "'.self::LEMBAR.'" mulai baris 2; satu baris = satu nama kegiatan. Jangan ubah judul kolom di baris 1.'],
            ['2. Nama Kegiatan wajib (maks. 255 karakter). Nama yang sudah ada di Data Pagu dikenali: bila pagunya sama dilewati, bila berbeda pagunya diperbarui.'],
            ['3. Leading opsional; pilih dari dropdown (kode atau nama unit juga diterima). Tanpa leading, nama ini bisa dipilih semua leading.'],
            ['4. Pagu DIPA / Pagu Komite: angka rupiah bulat, mis. 10000000. Isi salah satu atau keduanya.'],
            ['5. Template impor kegiatan juga diterima (kolom Bulan diabaikan).'],
        ], null, 'A1');
        $petunjuk->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $buku->setActiveSheetIndex(0);

        return $buku;
    }
}
