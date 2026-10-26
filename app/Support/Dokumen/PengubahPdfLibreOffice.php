<?php

namespace App\Support\Dokumen;

use App\Contracts\PengubahPdf;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\RuntimeException as ProsesGagal;

/**
 * PengubahPdf lewat LibreOffice headless (`soffice --convert-to pdf`). Argumen dikirim sebagai array (tanpa shell);
 * setiap konversi memakai profil pengguna sementara sendiri agar beberapa permintaan bersamaan tidak saling mengunci,
 * lalu folder kerjanya dihapus. Lokasi program & batas waktu di config `sigenrabtor.pdf`.
 */
class PengubahPdfLibreOffice implements PengubahPdf
{
    public function ubah(string $berkas): string
    {
        if (! is_file($berkas)) {
            throw new GagalUbahPdf('Berkas yang akan diubah ke PDF tidak ditemukan.');
        }

        $kerja = storage_path('framework/cache/pdf-'.Str::random(16));
        File::ensureDirectoryExists($kerja.'/profil');

        try {
            // Locale Indonesia: angka berformat ribuan tercetak "6.000.000" & desimal "102,3" seperti di layar.
            $hasil = Process::timeout((int) config('sigenrabtor.pdf.batas_detik', 90))->env(['LC_ALL' => 'id_ID.UTF-8', 'LANG' => 'id_ID.UTF-8'])->run([
                (string) config('sigenrabtor.pdf.soffice', 'soffice'),
                '-env:UserInstallation=file://'.$kerja.'/profil',
                '--headless',
                '--norestore',
                '--convert-to',
                'pdf',
                '--outdir',
                $kerja,
                $berkas,
            ]);
            $pdf = $kerja.'/'.pathinfo($berkas, PATHINFO_FILENAME).'.pdf';

            if (! $hasil->successful() || ! is_file($pdf)) {
                Log::warning('Konversi PDF LibreOffice gagal', ['kode' => $hasil->exitCode(), 'galat' => Str::limit($hasil->errorOutput(), 500)]);

                throw new GagalUbahPdf('PDF belum bisa dibuat: pengubah PDF (LibreOffice) di server tidak berhasil. Coba lagi atau unduh versi Excel.');
            }

            return (string) file_get_contents($pdf);
        } catch (ProcessTimedOutException) {
            throw new GagalUbahPdf('PDF belum bisa dibuat: konversi terlalu lama. Coba lagi atau unduh versi Excel.');
        } catch (ProcessStartFailedException|ProsesGagal $e) {
            Log::warning('LibreOffice tidak bisa dijalankan', ['galat' => $e->getMessage()]);

            throw new GagalUbahPdf('PDF belum bisa dibuat: pengubah PDF (LibreOffice) belum terpasang di server. Unduh versi Excel untuk sementara.');
        } finally {
            File::deleteDirectory($kerja);
        }
    }
}
