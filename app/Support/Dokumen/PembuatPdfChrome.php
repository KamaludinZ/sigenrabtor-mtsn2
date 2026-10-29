<?php

namespace App\Support\Dokumen;

use App\Contracts\PembuatPdfDokumen;
use Illuminate\Foundation\Vite;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\RuntimeException as ProsesGagal;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * PembuatPdfDokumen sungguhan: halaman cetak `dokumen.cetak` (CetakDokumen::untuk — lembar terpilih, tanda air & kaki
 * DRAF, nomor "Halaman x dari y", @page A4 & sampul tanpa margin) dirender di proses PHP lalu dicetak ke PDF oleh Chrome/
 * Chromium headless (`--print-to-pdf`, media cetak) sehingga hasilnya sama dengan Cetak › Simpan sebagai PDF di peramban.
 * `html()` merender dalam mode build Vite (tanpa server dev) dan mengarahkan aset `/build/…` ke berkas `public/build`
 * (file://); gambar cover tertanam sebagai data URI (CoverDokumen::gambarData) karena rutenya butuh sesi. Setiap pembuatan
 * memakai folder kerja & profil peramban sementara sendiri (dihapus sesudahnya), argumen array tanpa shell. Program dari
 * config `sigenrabtor.pdf.chrome` (kosong = dicari: `PROGRAM`), batas waktu `sigenrabtor.pdf.batas_detik`. Gagal →
 * GagalUbahPdf berpesan siap tampil (aset belum dibangun, Chrome tidak ada, terlalu lama, hasil bukan PDF).
 */
class PembuatPdfChrome implements PembuatPdfDokumen
{
    /** Nama program yang dicari bila config `sigenrabtor.pdf.chrome` kosong. */
    public const PROGRAM = ['google-chrome', 'google-chrome-stable', 'chromium', 'chromium-browser'];

    public function buat(array $kegiatan, ?string $sumber, array $lembar): string
    {
        $program = self::program();
        if ($program === null) {
            throw new GagalUbahPdf('PDF belum bisa dibuat: perender PDF (Chrome/Chromium) belum terpasang di server. Gunakan Cetak › Simpan sebagai PDF untuk sementara.');
        }
        $html = $this->html($kegiatan, $sumber, $lembar);

        $kerja = storage_path('framework/cache/pdf-chrome-'.Str::random(16));
        File::ensureDirectoryExists($kerja.'/profil');
        try {
            File::put($kerja.'/dokumen.html', $html);
            $hasil = Process::timeout((int) config('sigenrabtor.pdf.batas_detik', 90))->env(['HOME' => $kerja])->run([
                $program,
                '--headless=new',
                '--no-sandbox',
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--disable-extensions',
                '--no-first-run',
                '--no-default-browser-check',
                '--hide-scrollbars',
                '--mute-audio',
                '--allow-file-access-from-files',
                '--no-pdf-header-footer',
                '--print-to-pdf-no-header',
                '--run-all-compositor-stages-before-draw',
                '--virtual-time-budget=10000',
                '--user-data-dir='.$kerja.'/profil',
                '--print-to-pdf='.$kerja.'/dokumen.pdf',
                'file://'.$kerja.'/dokumen.html',
            ]);
            $pdf = $kerja.'/dokumen.pdf';
            $isi = is_file($pdf) ? (string) file_get_contents($pdf) : '';
            if (! str_starts_with($isi, '%PDF-')) {
                Log::warning('Pembuatan PDF Chrome gagal', ['kode' => $hasil->exitCode(), 'galat' => Str::limit($hasil->errorOutput(), 500)]);

                throw new GagalUbahPdf('PDF belum bisa dibuat: perender PDF (Chrome) di server tidak berhasil. Coba lagi atau gunakan Cetak › Simpan sebagai PDF.');
            }

            return $isi;
        } catch (ProcessTimedOutException) {
            throw new GagalUbahPdf('PDF belum bisa dibuat: penyusunan terlalu lama. Coba lagi atau gunakan Cetak › Simpan sebagai PDF.');
        } catch (ProcessStartFailedException|ProsesGagal $e) {
            Log::warning('Chrome tidak bisa dijalankan', ['galat' => $e->getMessage()]);

            throw new GagalUbahPdf('PDF belum bisa dibuat: perender PDF (Chrome/Chromium) tidak bisa dijalankan di server. Gunakan Cetak › Simpan sebagai PDF untuk sementara.');
        } finally {
            File::deleteDirectory($kerja);
        }
    }

    /**
     * HTML halaman cetak siap dibuka dari berkas lokal: mode build Vite, aset /build → file:// public/build, cover data URI.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  list<string>  $lembar
     *
     * @throws GagalUbahPdf Aset tampilan belum dibangun (manifest Vite tidak ada).
     */
    public function html(array $kegiatan, ?string $sumber, array $lembar): string
    {
        $data = CetakDokumen::untuk($kegiatan, $sumber, $lembar);
        if ($data['cover']['gambar'] !== null) {
            $data['cover']['gambar'] = CoverDokumen::gambarData();
        }

        $vite = app(Vite::class);
        $hot = $vite->hotFile();
        $vite->useHotFile(storage_path('framework/tanpa-hot'));
        try {
            $html = view('dokumen.cetak', ['kegiatanId' => (int) ($kegiatan['id'] ?? 0), 'dataKegiatan' => $kegiatan, 'otomatis' => false] + $data)->render();
        } catch (Throwable $e) {
            Log::warning('Halaman cetak gagal dirender untuk PDF', ['galat' => $e->getMessage()]);

            throw new GagalUbahPdf('PDF belum bisa dibuat: tampilan dokumen belum siap di server (aset belum dibangun). Gunakan Cetak › Simpan sebagai PDF untuk sementara.');
        } finally {
            $vite->useHotFile($hot);
        }

        return str_replace(rtrim(asset('build'), '/').'/', 'file://'.public_path('build').'/', $html);
    }

    /** Program Chrome/Chromium: config `sigenrabtor.pdf.chrome` (path yang bisa dijalankan atau nama di PATH) atau yang pertama ditemukan dari PROGRAM; null = tidak ada. */
    public static function program(): ?string
    {
        $pencari = new ExecutableFinder;
        $diatur = trim((string) config('sigenrabtor.pdf.chrome', ''));
        if ($diatur !== '') {
            return str_contains($diatur, '/') ? (is_executable($diatur) ? $diatur : null) : $pencari->find($diatur);
        }
        foreach (self::PROGRAM as $nama) {
            if (($ketemu = $pencari->find($nama)) !== null) {
                return $ketemu;
            }
        }

        return null;
    }
}
