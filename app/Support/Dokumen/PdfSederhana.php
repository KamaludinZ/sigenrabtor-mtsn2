<?php

namespace App\Support\Dokumen;

/**
 * Penulis PDF teks sederhana (A4, Helvetica WinAnsi, tanpa pustaka): satu halaman per daftar baris. Dipakai berkas
 * pengganti selama mesin dokumen belum menyusun KAK/TOR & RAB sebagai PDF (bagian buku tiruan, StubPembuatPdfBuku).
 */
final class PdfSederhana
{
    /**
     * @param  list<list<string>>  $halaman  Baris teks tiap halaman (minimal satu halaman).
     */
    public static function tulis(array $halaman): string
    {
        $halaman = $halaman === [] ? [[]] : array_values($halaman);
        $aman = fn (string $t) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) iconv('UTF-8', 'windows-1252//TRANSLIT', $t));
        $jumlah = count($halaman);
        $font = 3 + 2 * $jumlah;

        // 1 katalog, 2 daftar halaman, lalu pasangan (halaman, isi) per halaman, terakhir font.
        $objek = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        $anak = [];
        foreach ($halaman as $i => $baris) {
            $nomor = 3 + 2 * $i;
            $anak[] = "{$nomor} 0 R";
            $isi = 'BT /F1 11 Tf 56 790 Td 15 TL '.implode(' ', array_map(fn (string $b) => '('.$aman($b).') Tj T*', $baris)).' ET';
            $objek[$nomor] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents '.($nomor + 1)." 0 R /Resources << /Font << /F1 {$font} 0 R >> >> >>";
            $objek[$nomor + 1] = '<< /Length '.strlen($isi)." >>\nstream\n{$isi}\nendstream";
        }
        $objek[2] = '<< /Type /Pages /Kids ['.implode(' ', $anak)."] /Count {$jumlah} >>";
        $objek[$font] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        ksort($objek);

        $pdf = "%PDF-1.4\n";
        $posisi = [];
        foreach ($objek as $nomor => $o) {
            $posisi[] = strlen($pdf);
            $pdf .= "{$nomor} 0 obj\n{$o}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objek) + 1)."\n0000000000 65535 f \n".implode('', array_map(fn (int $p) => sprintf("%010d 00000 n \n", $p), $posisi));

        return $pdf."trailer\n<< /Size ".(count($objek) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
