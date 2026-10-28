<?php

namespace Tests\Support;

use App\Contracts\PenggabungPdf;
use App\Support\Dokumen\GagalGabungPdf;
use App\Support\Dokumen\PdfSederhana;

/**
 * Penggabung PDF untuk tes tanpa poppler-utils: jumlah halaman dibaca dari `/Count` berkas PdfSederhana, hasil gabungan
 * ditulis ulang sebagai PdfSederhana berjumlah halaman sama. `$galat` diisi = setiap penggabungan gagal dengan pesan itu.
 */
class PenggabungPdfPalsu implements PenggabungPdf
{
    /** @var list<list<string>> Berkas tiap pemanggilan gabung(). */
    public array $panggilan = [];

    public function __construct(public ?string $galat = null) {}

    public function gabung(array $berkas, string $tujuan): void
    {
        if ($this->galat !== null) {
            throw new GagalGabungPdf($this->galat);
        }
        $this->panggilan[] = $berkas;
        $halaman = array_sum(array_map(fn (string $b) => $this->jumlahHalaman($b), $berkas));
        file_put_contents($tujuan, PdfSederhana::tulis(array_fill(0, $halaman, ['Gabungan'])));
    }

    public function jumlahHalaman(string $berkas): int
    {
        preg_match('/\/Type \/Pages \/Kids \[[^\]]*\] \/Count (\d+)/', (string) file_get_contents($berkas), $cocok);

        return (int) ($cocok[1] ?? 0);
    }
}
