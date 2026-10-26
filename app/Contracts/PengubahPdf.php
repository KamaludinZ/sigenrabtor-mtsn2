<?php

namespace App\Contracts;

use App\Support\Dokumen\GagalUbahPdf;

/**
 * Konversi berkas kantor (.xlsx/.docx) ke PDF (PRD "Mesin Dokumen": LibreOffice headless agar PDF identik dengan
 * berkas sumbernya). Dipakai ekspor Monitoring dan kelak cetak KAK/RAB & buku.
 */
interface PengubahPdf
{
    /**
     * Isi PDF hasil konversi $berkas (path lokal yang bisa dibaca).
     *
     * @throws GagalUbahPdf Konverter tidak tersedia, kehabisan waktu, atau tidak menghasilkan PDF.
     */
    public function ubah(string $berkas): string;
}
