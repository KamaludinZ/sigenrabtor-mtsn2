<?php

namespace App\Support\Dokumen;

use RuntimeException;
use ZipArchive;

/**
 * Membaca master .docx (isi, header, footer — bagian yang diisi PengisiTemplateDocx): variabel ${…} beserta jumlah &
 * bagian kemunculannya, paragraf teks untuk pratinjau, dan penanda yang tidak tertutup. Word sering memecah satu variabel
 * ke beberapa "run" XML (mis. karena cek ejaan), jadi teks tiap bagian disambung lebih dulu — tanda paragraf & ganti baris
 * menjadi baris baru — sebelum dicari polanya. Yang bukan teks dokumen dibuang lebih dulu: teks terhapus lewat lacak
 * perubahan (`w:del`), kode field (`w:instrText`), dan salinan cadangan kotak teks (`mc:Fallback`, agar tidak terhitung
 * dua kali).
 *
 * Berkas diperiksa sebelum dibaca (`buka()`): bukan arsip Word / dokumen terenkripsi atau format .doc lama (tanda OLE),
 * berisi makro (`vbaProject.bin`), atau isi terurai melebihi `MAKS_UKURAN_ISI` / `MAKS_BERKAS` entri (arsip bom) →
 * RuntimeException dengan pesan siap tampil.
 */
final class PembacaTemplateDocx
{
    /** Batas jumlah ukuran isi terurai arsip .docx (byte). */
    public const MAKS_UKURAN_ISI = 50 * 1024 * 1024;

    /** Batas jumlah entri arsip .docx. */
    public const MAKS_BERKAS = 2000;

    /** Tanda berkas OLE (dokumen Word terenkripsi atau format .doc lama). */
    private const TANDA_OLE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    /**
     * @return array<string, int> Nama variabel (apa adanya, dirapikan spasinya) → jumlah kemunculan, urut kemunculan pertama.
     *
     * @throws RuntimeException Berkas bukan .docx yang sah.
     */
    public static function variabel(string $path): array
    {
        return array_map(fn (array $v) => $v['jumlah'], self::baca($path)['variabel']);
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
        return self::baca($path)['paragraf'];
    }

    /**
     * Hasil baca lengkap satu master.
     *
     * @return array{variabel: array<string, array{jumlah: int, bagian: list<string>}>, paragraf: list<array{bagian: 'header'|'isi'|'footer', teks: string}>, tidak_tertutup: list<string>}
     *
     * @throws RuntimeException Berkas bukan .docx yang sah, terenkripsi, bermakro, atau terlalu besar.
     */
    public static function baca(string $path): array
    {
        $zip = self::buka($path);

        try {
            // Variabel: isi dulu, lalu header/footer menurut urutan arsip (urutan kemunculan pertama).
            $bagian = ['word/document.xml' => 'isi'];
            $urutan = ['header' => [], 'isi' => ['word/document.xml'], 'footer' => []];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nama = (string) $zip->getNameIndex($i);
                if (preg_match('#^word/(header|footer)\d*\.xml$#', $nama, $cocok)) {
                    $bagian[$nama] = $cocok[1];
                    $urutan[$cocok[1]][] = $nama;
                }
            }

            $teks = array_map(fn (string $nama) => self::teks((string) $zip->getFromName($nama)), array_combine(array_keys($bagian), array_keys($bagian)));
            $variabel = [];
            $tidakTertutup = [];
            foreach ($bagian as $nama => $jenisBagian) {
                preg_match_all('/\$\{([^{}]*)\}/u', $teks[$nama], $cocok);
                foreach ($cocok[1] as $v) {
                    $v = trim((string) preg_replace('/\s+/u', ' ', $v));
                    $variabel[$v]['jumlah'] = ($variabel[$v]['jumlah'] ?? 0) + 1;
                    $variabel[$v]['bagian'] = array_values(array_unique([...($variabel[$v]['bagian'] ?? []), $jenisBagian]));
                }
                foreach (explode("\n", $teks[$nama]) as $baris) {
                    // Penanda tanpa kurung tutup di paragraf yang sama: ${ … tanpa }.
                    $sisa = (string) preg_replace('/\$\{[^{}\n]*\}/u', '', $baris);
                    if (str_contains($sisa, '${')) {
                        $tidakTertutup[] = mb_strimwidth(trim(mb_substr($sisa, (int) mb_strpos($sisa, '${'))), 0, 40, '…');
                    }
                }
            }

            $paragraf = [];
            foreach ($urutan as $jenisBagian => $daftar) {
                sort($daftar);
                foreach ($daftar as $nama) {
                    foreach (explode("\n", $teks[$nama]) as $baris) {
                        if (($baris = trim((string) preg_replace('/[ \t]+/u', ' ', $baris))) !== '') {
                            $paragraf[] = ['bagian' => $jenisBagian, 'teks' => $baris];
                        }
                    }
                }
            }

            return ['variabel' => $variabel, 'paragraf' => $paragraf, 'tidak_tertutup' => array_values(array_unique($tidakTertutup))];
        } finally {
            $zip->close();
        }
    }

    /**
     * Buka arsip .docx setelah diperiksa (bukan OLE/terenkripsi, tanpa makro, ukuran & jumlah entri wajar).
     *
     * @throws RuntimeException
     */
    private static function buka(string $path): ZipArchive
    {
        $awal = is_file($path) ? (string) file_get_contents($path, false, null, 0, 8) : '';
        if ($awal === self::TANDA_OLE) {
            throw new RuntimeException('Berkas terlindungi kata sandi atau berformat Word lama (.doc) — buka di Word lalu simpan sebagai .docx tanpa kata sandi.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Berkas bukan dokumen Word (.docx) yang sah.');
        }

        $galat = null;
        $ukuran = 0;
        if ($zip->numFiles > self::MAKS_BERKAS) {
            $galat = 'Berkas Word terlalu rumit (terlalu banyak bagian) untuk dibaca.';
        }
        for ($i = 0; $galat === null && $i < $zip->numFiles; $i++) {
            $info = $zip->statIndex($i);
            $ukuran += (int) ($info['size'] ?? 0);
            if (str_ends_with(strtolower((string) ($info['name'] ?? '')), 'vbaproject.bin')) {
                $galat = 'Berkas berisi makro — simpan ulang di Word sebagai dokumen .docx biasa (bukan .docm).';
            } elseif ($ukuran > self::MAKS_UKURAN_ISI) {
                $galat = 'Isi berkas Word terlalu besar untuk dibaca (lebih dari '.(self::MAKS_UKURAN_ISI / 1024 / 1024).' MB setelah diurai).';
            }
        }
        if ($galat === null && $zip->locateName('word/document.xml') === false) {
            $galat = 'Berkas bukan dokumen Word (.docx) yang sah — isi dokumen tidak ditemukan.';
        }
        if ($galat !== null) {
            $zip->close();

            throw new RuntimeException($galat);
        }

        return $zip;
    }

    /**
     * Teks polos satu bagian XML Word: teks terhapus, kode field, & salinan cadangan kotak teks dibuang; paragraf/ganti
     * baris → baris baru, tab → spasi, entitas XML diurai.
     */
    private static function teks(string $xml): string
    {
        $xml = (string) preg_replace([
            '#<w:del\b[^>]*>.*?</w:del>#s',
            '#<w:instrText\b[^>]*>.*?</w:instrText>#s',
            '#<mc:Fallback\b[^>]*>.*?</mc:Fallback>#s',
        ], '', $xml);
        $xml = (string) preg_replace(['#</w:p>#', '#<w:tab\b[^>]*/>#', '#<w:(?:br|cr)\b[^>]*/>#'], ["\n", ' ', "\n"], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
