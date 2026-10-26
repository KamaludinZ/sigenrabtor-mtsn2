<?php

namespace App\Support\Dokumen;

use RuntimeException;
use ZipArchive;

/**
 * Membaca variabel ${…} dari master .docx (isi, header, footer) beserta jumlah kemunculannya. Word sering memecah satu
 * variabel ke beberapa "run" XML (mis. karena cek ejaan), jadi teks tiap bagian disambung lebih dulu — tanda paragraf
 * menjadi baris baru — sebelum dicari polanya.
 */
final class PembacaTemplateDocx
{
    /**
     * @return array<string, int> Nama variabel (apa adanya, dirapikan spasinya) → jumlah kemunculan, urut kemunculan pertama.
     *
     * @throws RuntimeException Berkas bukan .docx yang sah.
     */
    public static function variabel(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Berkas bukan dokumen Word (.docx) yang sah.');
        }

        try {
            if ($zip->locateName('word/document.xml') === false) {
                throw new RuntimeException('Berkas bukan dokumen Word (.docx) yang sah — isi dokumen tidak ditemukan.');
            }

            $bagian = ['word/document.xml'];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nama = (string) $zip->getNameIndex($i);
                if (preg_match('#^word/(header|footer)\d*\.xml$#', $nama)) {
                    $bagian[] = $nama;
                }
            }

            $hasil = [];
            foreach ($bagian as $nama) {
                $teks = self::teks((string) $zip->getFromName($nama));
                preg_match_all('/\$\{([^{}]*)\}/u', $teks, $cocok);

                foreach ($cocok[1] as $variabel) {
                    $variabel = trim((string) preg_replace('/\s+/u', ' ', $variabel));
                    $hasil[$variabel] = ($hasil[$variabel] ?? 0) + 1;
                }
            }

            return $hasil;
        } finally {
            $zip->close();
        }
    }

    /**
     * Paragraf berisi teks dalam urutan baca untuk pratinjau: header, isi, lalu footer (paragraf kosong dilewati).
     *
     * @return list<array{bagian: 'header'|'isi'|'footer', teks: string}>
     *
     * @throws RuntimeException Berkas bukan .docx yang sah.
     */
    public static function paragraf(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Berkas bukan dokumen Word (.docx) yang sah.');
        }

        if ($zip->locateName('word/document.xml') === false) {
            $zip->close();

            throw new RuntimeException('Berkas bukan dokumen Word (.docx) yang sah — isi dokumen tidak ditemukan.');
        }

        try {
            $urutan = ['header' => [], 'isi' => ['word/document.xml'], 'footer' => []];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                if (preg_match('#^word/(header|footer)\d*\.xml$#', (string) $zip->getNameIndex($i), $cocok)) {
                    $urutan[$cocok[1]][] = (string) $zip->getNameIndex($i);
                }
            }

            $hasil = [];
            foreach ($urutan as $bagian => $daftar) {
                sort($daftar);
                foreach ($daftar as $nama) {
                    foreach (explode("\n", self::teks((string) $zip->getFromName($nama))) as $baris) {
                        if (($baris = trim((string) preg_replace('/[ \t]+/u', ' ', $baris))) !== '') {
                            $hasil[] = ['bagian' => $bagian, 'teks' => $baris];
                        }
                    }
                }
            }

            return $hasil;
        } finally {
            $zip->close();
        }
    }

    /** Teks polos satu bagian XML Word: paragraf → baris, tab → spasi, entitas XML diurai. */
    private static function teks(string $xml): string
    {
        $xml = (string) preg_replace(['#</w:p>#', '#<w:tab/>#', '#<w:br/>#'], ["\n", ' ', "\n"], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
