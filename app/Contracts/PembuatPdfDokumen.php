<?php

namespace App\Contracts;

use App\Support\Dokumen\GagalUbahPdf;

/**
 * Penyusun PDF set dokumen satu kegiatan × sumber dana (PRD §6 "Cetak per Kegiatan … PDF", F12/F16) untuk unduhan
 * langsung `kegiatan.cetak.pdf`: lembar CetakDokumen::LEMBAR terpilih (cover, KAK/TOR, RAB) dengan tanda DRAF selama
 * belum siap. Tiruan `StubPembuatPdfDokumen` menulis PDF teks (PdfSederhana); backend menggantinya dengan perender halaman
 * cetak `dokumen.cetak`. Gagal → GagalUbahPdf berpesan siap tampil.
 */
interface PembuatPdfDokumen
{
    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  list<string>  $lembar
     * @return string Isi berkas PDF.
     *
     * @throws GagalUbahPdf
     */
    public function buat(array $kegiatan, ?string $sumber, array $lembar): string;
}
