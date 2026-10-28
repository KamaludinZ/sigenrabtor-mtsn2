<?php

namespace App\Support\Dokumen;

/**
 * Penampil dokumen tab Pratinjau (PRD F5 "Tampilan Seperti Cetak"): cover, KAK/TOR, dan RAB sebagai lembar A4 (`KERTAS`)
 * dengan perbesaran `PERBESARAN` — `pas` = selebar ruang (maks. 100%), angka = persen ukuran asli, `layar` = mengikuti
 * lebar layar (tanpa lembar tetap; bawaan di layar sempit) — beserta perkiraan batas halaman A4 & penunjuk halaman yang
 * dihitung di klien (Alpine `penampilDokumen`, resources/js/penampil-dokumen.js), dan `isi()` = daftar isi dokumen
 * (cover, sembilan bagian KAK/TOR dengan jumlah isian kurangnya, RAB) berjangkar ke dokumen di halaman yang sama.
 */
final class PenampilDokumen
{
    /** Perbesaran lembar → label tombol. */
    public const PERBESARAN = ['pas' => 'Pas', '75' => '75%', '100' => '100%', '125' => '125%', 'layar' => 'Lebar layar'];

    public const BAWAAN = 'pas';

    /** Ukuran kertas cetak (mm). */
    public const KERTAS = ['lebar' => 210, 'tinggi' => 297];

    /** Lembar dokumen penampil → label (urutan cetak). */
    public const LEMBAR = ['cover' => 'Cover', 'kak' => 'KAK/TOR', 'rab' => 'RAB'];

    /**
     * Daftar isi dokumen yang ditampilkan: kunci, lembar (cover|kak|rab), label, id jangkar, dan jumlah isian kurang.
     *
     * @return list<array{kunci: string, lembar: string, label: string, jangkar: string, kurang: int}>
     */
    public static function isi(SusunanKak $kak, SusunanRab $rab): array
    {
        $isi = [['kunci' => 'cover', 'lembar' => 'cover', 'label' => 'Cover', 'jangkar' => 'dok-cover', 'kurang' => 0]];
        foreach ($kak->bagian as $b) {
            $isi[] = [
                'kunci' => "kak-{$b['nomor']}",
                'lembar' => 'kak',
                'label' => "{$b['nomor']}. {$b['judul']}",
                'jangkar' => "cetak-kak-bagian-{$b['nomor']}",
                'kurang' => count($kak->kurangPerBagian[$b['nomor']] ?? []),
            ];
        }
        $isi[] = ['kunci' => 'rab', 'lembar' => 'rab', 'label' => 'RAB'.($rab->sumber ? " {$rab->sumber}" : ''), 'jangkar' => 'dok-rab', 'kurang' => count($rab->kurang)];

        return $isi;
    }
}
