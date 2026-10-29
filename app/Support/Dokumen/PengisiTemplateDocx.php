<?php

namespace App\Support\Dokumen;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Pengisi master KAK/TOR .docx (PRD §10 "Template KAK/TOR Variabel"): salinan master dengan variabel ${…} terdaftar
 * (VariabelTemplate::DAFTAR) diganti nilai kegiatan × sumber dana (NilaiVariabel::untuk) di isi, header, dan footer.
 * Seperti PembacaTemplateDocx, teks run satu paragraf disambung dulu (Word memecah variabel ke beberapa run):
 * - paragraf yang isinya hanya satu variabel bernilai daftar/blok/tabel (list baris) → satu paragraf per baris (gaya
 *   paragraf & run pertama dipertahankan; daftar diberi nomor "1. ");
 * - selain itu variabel diganti teks di tempat (list disambung baris baru) dan seluruh teks paragraf ditulis ke run
 *   pertama yang memuat teks — format campuran di dalam paragraf itu menyatu mengikuti run pertama;
 * - variabel kosong → `KOSONG` ("…"), variabel tidak terdaftar dibiarkan apa adanya.
 * Gagal membaca/menulis → RuntimeException.
 */
final class PengisiTemplateDocx
{
    public const KOSONG = '…';

    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * @param  array<string, string|list<string>|null>  $nilai  NilaiVariabel::untuk.
     * @return string Isi berkas .docx terisi.
     */
    public static function isi(string $path, array $nilai): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true || $zip->locateName('word/document.xml') === false) {
            throw new RuntimeException('Master KAK/TOR bukan dokumen Word (.docx) yang sah.');
        }
        $sementara = tempnam(sys_get_temp_dir(), 'isi-docx');
        $keluar = new ZipArchive;
        if ($sementara === false || $keluar->open($sementara, ZipArchive::OVERWRITE) !== true) {
            $zip->close();
            throw new RuntimeException('Berkas Word sementara tidak bisa dibuat.');
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nama = (string) $zip->getNameIndex($i);
                $isi = (string) $zip->getFromIndex($i);
                if ($nama === 'word/document.xml' || preg_match('#^word/(header|footer)\d*\.xml$#', $nama)) {
                    $isi = self::bagian($isi, $nilai);
                }
                $keluar->addFromString($nama, $isi);
            }
            $keluar->close();
            $zip->close();

            return (string) file_get_contents($sementara);
        } finally {
            @unlink($sementara);
        }
    }

    /** @param  array<string, string|list<string>|null>  $nilai */
    private static function bagian(string $xml, array $nilai): string
    {
        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml)) {
            throw new RuntimeException('Isi master KAK/TOR rusak.');
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::W);
        foreach (iterator_to_array($xpath->query('//w:p')) as $p) {
            /** @var DOMElement $p */
            $teks = array_values(iterator_to_array($xpath->query('.//w:t', $p)));
            $gabung = implode('', array_map(fn ($t) => $t->textContent, $teks));
            if (! str_contains($gabung, '${') || $teks === []) {
                continue;
            }
            if (preg_match('/^\s*\$\{([^{}]*)\}\s*$/u', $gabung, $satu) && VariabelTemplate::terdaftar($nama = self::nama($satu[1])) && is_array($nilai[$nama] ?? null) && $nilai[$nama] !== []) {
                self::pecah($dom, $p, $nilai[$nama], (VariabelTemplate::DAFTAR[$nama][2] ?? '') === 'daftar');

                continue;
            }
            $baru = (string) preg_replace_callback('/\$\{([^{}]*)\}/u', function (array $m) use ($nilai) {
                $nama = self::nama($m[1]);
                if (! VariabelTemplate::terdaftar($nama)) {
                    return $m[0];
                }
                $v = $nilai[$nama] ?? null;

                return match (true) {
                    is_array($v) => $v === [] ? self::KOSONG : implode("\n", $v),
                    $v === null || $v === '' => self::KOSONG,
                    default => (string) $v,
                };
            }, $gabung);
            self::tulisRun($dom, $teks[0], $baru);
            foreach (array_slice($teks, 1) as $t) {
                $t->nodeValue = '';
            }
        }

        return (string) $dom->saveXML();
    }

    private static function nama(string $mentah): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $mentah));
    }

    /** Teks (boleh berbaris) ditulis ke run tempat $t berada: baris baru → <w:br/>. */
    private static function tulisRun(DOMDocument $dom, DOMElement $t, string $teks): void
    {
        $baris = explode("\n", $teks);
        $t->nodeValue = '';
        $t->appendChild($dom->createTextNode(array_shift($baris)));
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $sebelum = $t;
        foreach ($baris as $b) {
            $br = $dom->createElementNS(self::W, 'w:br');
            $tBaru = $dom->createElementNS(self::W, 'w:t');
            $tBaru->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $tBaru->appendChild($dom->createTextNode($b));
            $sebelum->parentNode->insertBefore($br, $sebelum->nextSibling);
            $br->parentNode->insertBefore($tBaru, $br->nextSibling);
            $sebelum = $tBaru;
        }
    }

    /**
     * Paragraf satu variabel daftar → satu paragraf per baris (salinan paragraf asli).
     *
     * @param  list<string>  $baris
     */
    private static function pecah(DOMDocument $dom, DOMElement $p, array $baris, bool $bernomor): void
    {
        $induk = $p->parentNode;
        foreach (array_values($baris) as $i => $teks) {
            /** @var DOMElement $salinan */
            $salinan = $p->cloneNode(true);
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', self::W);
            $t = array_values(iterator_to_array($xpath->query('.//w:t', $salinan)));
            self::tulisRun($dom, $t[0], ($bernomor ? ($i + 1).'. ' : '').$teks);
            foreach (array_slice($t, 1) as $lain) {
                $lain->nodeValue = '';
            }
            $induk->insertBefore($salinan, $p);
        }
        $induk->removeChild($p);
    }
}
