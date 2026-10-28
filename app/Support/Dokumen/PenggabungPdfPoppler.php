<?php

namespace App\Support\Dokumen;

use App\Contracts\PenggabungPdf;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\RuntimeException as ProsesGagal;

/**
 * PenggabungPdf lewat poppler-utils: `pdfunite` (gabung, argumen array tanpa shell) dan `pdfinfo` (jumlah halaman).
 * Lokasi program & batas waktu di config `sigenrabtor.pdf` (`pdfunite`, `pdfinfo`, `batas_detik`). Satu berkas disalin
 * apa adanya (pdfunite butuh minimal dua).
 */
class PenggabungPdfPoppler implements PenggabungPdf
{
    public function gabung(array $berkas, string $tujuan): void
    {
        if ($berkas === []) {
            throw new GagalGabungPdf('Tidak ada dokumen yang bisa digabung menjadi buku.');
        }
        foreach ($berkas as $b) {
            if (! is_file($b)) {
                throw new GagalGabungPdf('Salah satu dokumen buku tidak ditemukan — susun ulang buku.');
            }
        }
        if (count($berkas) === 1) {
            copy($berkas[0], $tujuan) || throw new GagalGabungPdf('PDF buku tidak bisa disimpan.');

            return;
        }

        $hasil = $this->jalankan([(string) config('sigenrabtor.pdf.pdfunite', 'pdfunite'), ...array_values($berkas), $tujuan], 'pdfunite');
        if (! $hasil->successful() || ! is_file($tujuan)) {
            Log::warning('Penggabungan PDF gagal', ['kode' => $hasil->exitCode(), 'galat' => Str::limit($hasil->errorOutput(), 500)]);

            throw new GagalGabungPdf('PDF buku belum bisa digabung: penggabung PDF di server tidak berhasil. Coba lagi.');
        }
    }

    public function jumlahHalaman(string $berkas): int
    {
        $hasil = $this->jalankan([(string) config('sigenrabtor.pdf.pdfinfo', 'pdfinfo'), $berkas], 'pdfinfo');
        if (! $hasil->successful() || ! preg_match('/^Pages:\s+(\d+)/m', $hasil->output(), $cocok)) {
            throw new GagalGabungPdf('Jumlah halaman PDF buku tidak bisa dibaca.');
        }

        return (int) $cocok[1];
    }

    /** @param  list<string>  $perintah */
    private function jalankan(array $perintah, string $nama): ProcessResult
    {
        try {
            return Process::timeout((int) config('sigenrabtor.pdf.batas_detik', 90))->run($perintah);
        } catch (ProcessTimedOutException) {
            throw new GagalGabungPdf('PDF buku belum bisa digabung: proses terlalu lama. Coba lagi.');
        } catch (ProcessStartFailedException|ProsesGagal $e) {
            Log::warning("{$nama} tidak bisa dijalankan", ['galat' => $e->getMessage()]);

            throw new GagalGabungPdf("PDF buku belum bisa digabung: penggabung PDF ({$nama}) belum terpasang di server.");
        }
    }
}
