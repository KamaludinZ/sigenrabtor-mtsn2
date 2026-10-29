<?php

namespace App\Support\Stub;

use App\Contracts\PembuatPdfDokumen;
use App\Support\Dokumen\CetakDokumen;
use App\Support\Dokumen\PdfSederhana;
use App\Support\Dokumen\StatusDraf;
use App\Support\Dokumen\SusunanRab;

/**
 * PDF set dokumen tiruan: teks cover, sembilan bagian KAK/TOR, dan RAB (kepala, rincian, total & terbilang) dari susunan
 * yang sama dengan halaman cetak (CetakDokumen::untuk), ditulis PdfSederhana — tiap lembar mulai di halaman baru, baris
 * dilipat ±95 karakter, 48 baris per halaman, baris DRAF + alasan di awal lembar selama belum siap. Tata letak akhir
 * mengikuti halaman cetak; berkas ini hanya pengganti sampai perender server terpasang.
 */
class StubPembuatPdfDokumen implements PembuatPdfDokumen
{
    private const LEBAR = 95;

    private const BARIS_PER_HALAMAN = 48;

    /** Kunci blok yang bukan teks tercetak. */
    private const BUKAN_TEKS = ['jenis', 'sunting', 'sunting_butir', 'asal', 'wajib', 'tempat', 'id', 'kunci', 'gaya'];

    public function buat(array $kegiatan, ?string $sumber, array $lembar): string
    {
        $susunan = CetakDokumen::untuk($kegiatan, $sumber, $lembar);
        $halaman = [];
        foreach ($susunan['lembar'] as $kunci) {
            $baris = ['PDF tiruan (pengembangan) - tata letak akhir sama dengan halaman cetak.', ''];
            [$draf, $isi] = match ($kunci) {
                'cover' => [$susunan['cetak']->set, array_values(array_filter($susunan['cover']['nilai'], 'filled'))],
                'kak' => [$susunan['cetak']->kak, $this->kak($susunan['kak']->bagian)],
                default => [$susunan['cetak']->rab, $this->rab($susunan['rab'])],
            };
            array_push($baris, ...$this->tandaDraf($draf), ...$isi);
            $dilipat = [];
            foreach ($baris as $b) {
                array_push($dilipat, ...($b === '' ? [''] : explode("\n", wordwrap($b, self::LEBAR, "\n", true))));
            }
            array_push($halaman, ...array_chunk($dilipat, self::BARIS_PER_HALAMAN));
        }

        return PdfSederhana::tulis($halaman);
    }

    /** @return list<string> */
    private function tandaDraf(StatusDraf $draf): array
    {
        return $draf->draf ? ['DRAF - '.implode(' ', array_column($draf->alasan, 'teks')), ''] : [];
    }

    /**
     * @param  list<array<string, mixed>>  $bagian
     * @return list<string>
     */
    private function kak(array $bagian): array
    {
        $baris = [];
        foreach ($bagian as $b) {
            $baris[] = "{$b['nomor']}. ".mb_strtoupper($b['judul']);
            foreach ($b['isi'] as $blok) {
                array_push($baris, ...$this->teks($blok));
            }
            $baris[] = '';
        }

        return $baris;
    }

    /** @return list<string> */
    private function rab(SusunanRab $rab): array
    {
        $tabel = $rab->tabel();
        $baris = [mb_strtoupper($rab->judul), ''];
        foreach ($rab->kepala as $k) {
            $baris[] = $k['label'].': '.($k['nilai'] ?? '...');
        }
        $baris[] = '';
        $baris[] = implode(' | ', $tabel->judul());
        foreach ($tabel->baris as $b) {
            $baris[] = implode(' | ', array_values($b['teks']));
        }
        $baris[] = 'TOTAL KESELURUHAN: '.$tabel->totalTeks;
        if (filled($tabel->terbilang)) {
            $baris[] = 'Terbilang: '.$tabel->terbilang;
        }

        return $baris;
    }

    /**
     * Teks tercetak satu blok (string di dalamnya, tanpa kunci tampilan).
     *
     * @return list<string>
     */
    private function teks(mixed $nilai, ?string $kunci = null): array
    {
        if (is_string($nilai)) {
            return $kunci !== null && in_array($kunci, self::BUKAN_TEKS, true) || trim($nilai) === '' ? [] : [trim(strip_tags($nilai))];
        }
        if (! is_array($nilai)) {
            return [];
        }
        $hasil = [];
        foreach ($nilai as $k => $v) {
            if (is_string($k) && in_array($k, self::BUKAN_TEKS, true)) {
                continue;
            }
            array_push($hasil, ...$this->teks($v, is_string($k) ? $k : null));
        }

        return $hasil;
    }
}
