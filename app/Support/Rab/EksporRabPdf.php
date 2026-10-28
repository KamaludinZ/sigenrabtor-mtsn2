<?php

namespace App\Support\Rab;

use App\Contracts\PengubahPdf;
use App\Support\Dokumen\GagalUbahPdf;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Ekspor RAB satu kegiatan ke PDF (PRD F21): buku EksporRabExcel yang sama (pengaturan cetak A4 tegak, muat selebar
 * halaman, kepala tabel diulang) diubah lewat PengubahPdf — angka PDF selalu sama dengan Excel & dokumen RAB. Berkas
 * sementara selalu dihapus; nama berkas mengikuti EksporRabExcel::namaBerkas (akhiran _DRAF bila belum siap).
 */
final class EksporRabPdf
{
    /**
     * @param  array<string, mixed>  $kegiatan
     * @return array{isi: string, nama: string}
     *
     * @throws GagalUbahPdf Konverter tidak tersedia, kehabisan waktu, atau tidak menghasilkan PDF.
     */
    public static function buat(array $kegiatan, ?string $sumber = null): array
    {
        ['buku' => $buku, 'nama' => $namaExcel] = EksporRabExcel::buat($kegiatan, $sumber);
        $sementara = storage_path('framework/cache/rab-'.Str::random(12).'.xlsx');

        try {
            (new Xlsx($buku))->save($sementara);
            $isi = app(PengubahPdf::class)->ubah($sementara);
        } finally {
            @unlink($sementara);
        }

        return ['isi' => $isi, 'nama' => Str::beforeLast($namaExcel, '.xlsx').'.pdf'];
    }
}
