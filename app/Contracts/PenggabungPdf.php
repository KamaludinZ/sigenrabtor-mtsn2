<?php

namespace App\Contracts;

use App\Support\Dokumen\GagalGabungPdf;

/**
 * Penggabung berkas PDF (PRD §18 F19 "Gabung PDF": seluruh dokumen kegiatan dalam satu berkas siap cetak) — dipakai
 * GabungPdfBuku. Implementasi server: poppler-utils (`pdfunite`/`pdfinfo`, PenggabungPdfPoppler).
 */
interface PenggabungPdf
{
    /**
     * Gabungkan $berkas (path lokal, berurutan) menjadi satu PDF di $tujuan.
     *
     * @param  list<string>  $berkas
     *
     * @throws GagalGabungPdf
     */
    public function gabung(array $berkas, string $tujuan): void;

    /** @throws GagalGabungPdf */
    public function jumlahHalaman(string $berkas): int;
}
