<?php

namespace App\Support\Rab;

use App\Support\Dokumen\StatusCetak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Dokumen\TabelRab;
use App\Support\Pengaturan\Nip;
use App\Support\Stub\StubData;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor RAB satu kegiatan ke Excel (PRD F21 "RAB juga dapat diekspor ke Excel", Fase 3 "Unduh Excel — rincian RAB"):
 * satu lembar per sumber dana (atau satu sumber saja), isinya sama dengan dokumen RAB (SusunanRab) — judul, header
 * (Kementerian, Satker, kegiatan & kode, jumlah yang diajukan), tabel No/(Kode)/Uraian/Vol/Satuan/Harga Satuan/Jumlah
 * dengan TOTAL KESELURUHAN & terbilang dari TabelRab (struktur yang sama dengan tabel RAB dokumen), Mekanisme Pembayaran, dan lembar pengesahan; uang berupa
 * bilangan berformat ribuan, kode ditulis teks; Jumlah tiap baris = rumus ROUND(Vol × Harga) dan TOTAL = SUM (lembar tetap
 * terhitung bila disunting — sama dengan penyusun Excel peramban `resources/js/excel-rab.js`). Dokumen yang masih DRAF
 * (StatusCetak) diberi baris keterangan DRAF, kaki halaman "DRAF — alasan", dan akhiran _DRAF pada nama berkas; kaki kanan
 * "Halaman x dari y"; A4 tegak, muat selebar halaman, kepala tabel diulang.
 */
final class EksporRabExcel
{
    private const UANG = '#,##0';

    private const VOLUME = '#,##0.##';

    /**
     * @param  array<string, mixed>  $kegiatan  Data KegiatanRepository.
     * @param  string|null  $sumber  null = semua sumber dana yang dipilih (satu lembar per sumber).
     * @return array{buku: Spreadsheet, nama: string, draf: bool}
     */
    public static function buat(array $kegiatan, ?string $sumber = null): array
    {
        $daftar = $sumber !== null ? [$sumber] : array_values(array_unique(array_column($kegiatan['sumber_dana'] ?? [], 'sumber')));
        $buku = new Spreadsheet;
        $buku->getProperties()->setCreator(config('sigenrabtor.aplikasi.nama', 'SIRABTOR'))->setTitle('RAB '.trim((string) ($kegiatan['nama_kegiatan'] ?? '')));
        $buku->removeSheetByIndex(0);

        $draf = false;
        foreach ($daftar as $s) {
            $susunan = SusunanRab::dari($kegiatan, $s);
            $cetak = StatusCetak::dari($kegiatan, $s, rab: $susunan);
            $draf = $draf || $cetak->rab->draf;
            self::lembar($buku->createSheet()->setTitle("RAB {$s}"), $susunan, $cetak);
        }
        $buku->setActiveSheetIndex(0);

        $nama = self::namaBerkas($kegiatan, $sumber, $draf, 'xlsx');

        return ['buku' => $buku, 'nama' => $nama, 'draf' => $draf];
    }

    private static function lembar(Worksheet $lembar, SusunanRab $susunan, StatusCetak $cetak): void
    {
        // Kolom, baris, total & terbilang dari struktur yang sama dengan tabel RAB dokumen (TabelRab).
        $tabel = $susunan->tabel();
        $denganKode = in_array('kode', $tabel->kolom, true);
        $kolom = $tabel->judul();
        $akhir = chr(ord('A') + count($kolom) - 1);
        $baris = 1;

        $lembar->setCellValue("A{$baris}", $susunan->judul)->mergeCells("A{$baris}:{$akhir}{$baris}");
        $lembar->getStyle("A{$baris}")->getFont()->setBold(true)->setSize(13);
        $lembar->getStyle("A{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $baris++;
        if ($cetak->rab->draf) {
            $lembar->setCellValue("A{$baris}", 'DRAF — '.($cetak->rab->alasan[0]['teks'] ?? 'belum siap dicetak'))->mergeCells("A{$baris}:{$akhir}{$baris}");
            $lembar->getStyle("A{$baris}")->getFont()->setBold(true)->getColor()->setRGB('B91C1C');
            $baris++;
        }
        $baris++;

        foreach ($susunan->kepala as $k) {
            $lembar->setCellValue("A{$baris}", $k['label']);
            $lembar->setCellValueExplicit("C{$baris}", (string) ($k['nilai'] ?? '…'), DataType::TYPE_STRING);
            if ($k['uraian']) {
                $lembar->setCellValueExplicit("D{$baris}", $k['uraian'], DataType::TYPE_STRING);
            }
            $lembar->getStyle("A{$baris}")->getFont()->setBold(true);
            $baris++;
        }
        $baris++;

        $awalTabel = $baris;
        $lembar->fromArray($kolom, null, "A{$baris}");
        $lembar->getStyle("A{$baris}:{$akhir}{$baris}")->getFont()->setBold(true);
        $lembar->getStyle("A{$baris}:{$akhir}{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $lembar->getStyle("A{$baris}:{$akhir}{$baris}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F5F4');
        $baris++;

        $huruf = fn (string $kunci) => chr(ord('A') + (int) array_search($kunci, $tabel->kolom, true));
        [$vol, $harga, $jumlah] = [$huruf('volume'), $huruf('harga'), $huruf('jumlah')];
        $awalRincian = $baris;
        foreach ($tabel->baris as $item) {
            $lembar->fromArray(array_values($item['nilai']), null, "A{$baris}", true);
            if ($denganKode) {
                $lembar->setCellValueExplicit("B{$baris}", $item['nilai']['kode'], DataType::TYPE_STRING);
            }
            $lembar->setCellValue("{$jumlah}{$baris}", "=ROUND({$vol}{$baris}*{$harga}{$baris},0)");
            $baris++;
        }
        $akhirRincian = $baris - 1;
        if (! $tabel->kosong()) {
            $lembar->getStyle("{$vol}{$awalRincian}:{$vol}{$akhirRincian}")->getNumberFormat()->setFormatCode(self::VOLUME);
            $lembar->getStyle("A{$awalRincian}:{$akhir}{$akhirRincian}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            $lembar->getStyle("A{$awalRincian}:A{$akhirRincian}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $lembar->getStyle("{$vol}{$awalRincian}:{$vol}{$akhirRincian}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        if ($tabel->kosong()) {
            $lembar->setCellValue("A{$baris}", 'Belum ada rincian belanja RAB '.$susunan->sumber.'.')->mergeCells("A{$baris}:{$akhir}{$baris}");
            $baris++;
        }

        $kolomUang = [chr(ord($akhir) - 1), $akhir];
        $lembar->setCellValue("A{$baris}", TabelRab::LABEL_TOTAL)->mergeCells("A{$baris}:".chr(ord($akhir) - 1).$baris);
        $lembar->setCellValue("{$akhir}{$baris}", $tabel->kosong() ? 0 : "=SUM({$jumlah}{$awalRincian}:{$jumlah}{$akhirRincian})");
        $lembar->getStyle("A{$baris}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $lembar->getStyle("A{$baris}:{$akhir}{$baris}")->getFont()->setBold(true);
        $lembar->getStyle("A{$awalTabel}:{$akhir}{$baris}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $lembar->getStyle("{$kolomUang[0]}".($awalTabel + 1).":{$akhir}{$baris}")->getNumberFormat()->setFormatCode(self::UANG);
        $baris++;
        if ($tabel->terbilang) {
            $lembar->setCellValue("A{$baris}", 'Terbilang: '.$tabel->terbilang)->mergeCells("A{$baris}:{$akhir}{$baris}");
            $lembar->getStyle("A{$baris}")->getFont()->setItalic(true);
            $baris++;
        }

        if ($susunan->mekanisme !== []) {
            $baris++;
            $lembar->setCellValue("A{$baris}", 'Mekanisme Pembayaran');
            $lembar->getStyle("A{$baris}")->getFont()->setBold(true);
            $baris++;
            foreach ($susunan->mekanisme as $m) {
                $lembar->setCellValue("A{$baris}", $m['label']);
                $lembar->setCellValueExplicit("C{$baris}", $m['nilai'], DataType::TYPE_STRING);
                $baris++;
            }
        }

        $baris++;
        $kolomTtd = ['A', 'D'];
        foreach (array_values($susunan->pengesahan) as $i => $p) {
            $c = $kolomTtd[$i % 2];
            $r = $baris + intdiv($i, 2) * 7;
            $isi = [$p['tempat_tanggal'] ?? '', $p['peran'], $p['jabatan'], '', '', $p['nama'] ?? '…', $p['nip'] ? 'NIP. '.Nip::format($p['nip']) : ''];
            foreach ($isi as $j => $teks) {
                $lembar->setCellValueExplicit($c.($r + $j), $teks, DataType::TYPE_STRING);
            }
            $lembar->getStyle($c.($r + 5))->getFont()->setBold(true)->setUnderline(true);
        }

        $lebar = $denganKode ? [6, 12, 46, 8, 12, 18, 18] : [6, 52, 8, 12, 18, 18];
        foreach ($lebar as $i => $l) {
            $lembar->getColumnDimension(chr(ord('A') + $i))->setWidth($l);
        }
        $lembar->getStyle(($denganKode ? 'C' : 'B').($awalTabel + 1).':'.($denganKode ? 'C' : 'B').$baris)->getAlignment()->setWrapText(true);
        $lembar->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4)->setOrientation(PageSetup::ORIENTATION_PORTRAIT)->setFitToWidth(1)->setFitToHeight(0);
        $lembar->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($awalTabel, $awalTabel);
        $lembar->getPageMargins()->setLeft(0.6)->setRight(0.6)->setTop(0.7)->setBottom(0.7)->setHeader(0.3)->setFooter(0.3);
        $lembar->getHeaderFooter()->setOddFooter(self::kaki($cetak));
    }

    /** Kaki halaman: kiri "DRAF — alasan pertama" (merah) selama DRAF, kanan "Halaman x dari y". */
    public static function kaki(StatusCetak $cetak): string
    {
        $draf = $cetak->rab->draf ? '&L&KB91C1CDRAF — '.str_replace('&', '&&', (string) ($cetak->rab->alasan[0]['teks'] ?? 'belum siap dicetak')) : '';

        return $draf.'&RHalaman &P dari &N';
    }

    /**
     * Nama berkas: satu sumber = StatusCetak::namaBerkas('RAB', …); semua sumber = "RAB_{nama}_{tahun}[_DRAF].{ekstensi}".
     */
    public static function namaBerkas(array $kegiatan, ?string $sumber, bool $draf, string $ekstensi): string
    {
        if ($sumber !== null) {
            return StatusCetak::dari($kegiatan, $sumber)->namaBerkas('RAB', $ekstensi);
        }
        $nama = trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', trim((string) ($kegiatan['nama_kegiatan'] ?? ''))), '_') ?: 'Kegiatan_'.($kegiatan['id'] ?? '');

        return 'RAB_'.rtrim(mb_substr($nama, 0, 60), '_').'_'.StubData::tahunAktif()['tahun'].($draf ? '_'.StatusCetak::TANDA_AIR : '').'.'.$ekstensi;
    }
}
