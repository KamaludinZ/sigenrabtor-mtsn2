<?php

namespace App\Support\Dokumen\Generasi;

use RuntimeException;
use ZipArchive;

/**
 * Penulis berkas Word (.docx, OOXML WordprocessingML) tanpa pustaka luar, sepadan dengan PdfSederhana: potongan XML
 * `run()` (teks berformat; tab → <w:tab/>, baris baru → <w:br/>), `nomorHalaman()` (bidang PAGE/NUMPAGES), `paragraf()`
 * (perataan, jarak, inden, tab, tetap bersama), `sel()`/`baris()`/`tabel()` (tabel lebar tetap, garis tipis atau tanpa
 * garis, kepala berulang, baris tidak terbelah), lalu `tulis($bagian, $meta)` menyusun paket ZIP: tiap bagian = section
 * sendiri (A4, margin, kepala & kaki eksplisit — section tanpa referensi mewarisi kepala section sebelumnya).
 * Huruf baku Times New Roman 12 pt (`FONT`, `UKURAN` setengah poin); satuan panjang twip (`CM` = 567).
 */
final class DocxSederhana
{
    public const FONT = 'Times New Roman';

    /** Ukuran huruf baku dalam setengah poin (12 pt). */
    public const UKURAN = 24;

    /** Twip per cm. */
    public const CM = 567;

    /** Kertas A4 dalam twip [lebar, tinggi]. */
    public const A4 = [11906, 16838];

    public const MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private const NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';

    /** Teks aman untuk XML (karakter kendali yang tidak sah di XML 1.0 dibuang). */
    public static function teks(?string $teks): string
    {
        return htmlspecialchars((string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $teks), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Satu run teks. Opsi: tebal, miring, garis_bawah (bool), ukuran (setengah poin), warna (hex tanpa #).
     *
     * @param  array{tebal?: bool, miring?: bool, garis_bawah?: bool, ukuran?: int, warna?: string|null}  $opsi
     */
    public static function run(?string $teks, array $opsi = []): string
    {
        $isi = [];
        foreach (explode("\n", (string) $teks) as $i => $baris) {
            if ($i > 0) {
                $isi[] = '<w:br/>';
            }
            foreach (explode("\t", $baris) as $j => $potong) {
                if ($j > 0) {
                    $isi[] = '<w:tab/>';
                }
                if ($potong !== '') {
                    $isi[] = '<w:t xml:space="preserve">'.self::teks($potong).'</w:t>';
                }
            }
        }

        return '<w:r>'.self::rPr($opsi).implode('', $isi).'</w:r>';
    }

    /**
     * Bidang nomor halaman: `PAGE` (halaman ini) atau `NUMPAGES` (jumlah halaman).
     *
     * @param  array{tebal?: bool, ukuran?: int, warna?: string|null}  $opsi
     */
    public static function nomorHalaman(string $bidang, array $opsi = []): string
    {
        $rPr = self::rPr($opsi);

        return '<w:r>'.$rPr.'<w:fldChar w:fldCharType="begin"/></w:r><w:r>'.$rPr.'<w:instrText xml:space="preserve"> '.$bidang.' </w:instrText></w:r>'
            .'<w:r>'.$rPr.'<w:fldChar w:fldCharType="separate"/></w:r><w:r>'.$rPr.'<w:t>1</w:t></w:r><w:r>'.$rPr.'<w:fldChar w:fldCharType="end"/></w:r>';
    }

    /**
     * Paragraf. Opsi: rata (left|center|right|both), sebelum/sesudah/baris (twip; baris = jarak antarbaris 240 = tunggal),
     * kiri/gantung/pertama (inden twip), tab (list [jenis left|right|center, posisi]), tetap_bersama, sect (XML sectPr).
     *
     * @param  string|list<string>  $run
     * @param  array<string, mixed>  $opsi
     */
    public static function paragraf(string|array $run, array $opsi = []): string
    {
        $run = is_array($run) ? implode('', $run) : (str_starts_with($run, '<w:') ? $run : self::run($run));
        $pPr = '';
        if ($opsi['tetap_bersama'] ?? false) {
            $pPr .= '<w:keepNext/>';
        }
        if (! empty($opsi['tab'])) {
            $pPr .= '<w:tabs>'.implode('', array_map(fn (array $t) => '<w:tab w:val="'.$t[0].'" w:pos="'.(int) $t[1].'"/>', $opsi['tab'])).'</w:tabs>';
        }
        $pPr .= '<w:spacing w:before="'.(int) ($opsi['sebelum'] ?? 0).'" w:after="'.(int) ($opsi['sesudah'] ?? 120).'" w:line="'.(int) ($opsi['baris'] ?? 300).'" w:lineRule="auto"/>';
        if (isset($opsi['kiri']) || isset($opsi['gantung']) || isset($opsi['pertama'])) {
            $pPr .= '<w:ind w:left="'.(int) ($opsi['kiri'] ?? 0).'"'.(isset($opsi['gantung']) ? ' w:hanging="'.(int) $opsi['gantung'].'"' : '').(isset($opsi['pertama']) ? ' w:firstLine="'.(int) $opsi['pertama'].'"' : '').'/>';
        }
        $pPr .= '<w:jc w:val="'.($opsi['rata'] ?? 'left').'"/>';
        $pPr .= $opsi['sect'] ?? '';

        return '<w:p><w:pPr>'.$pPr.'</w:pPr>'.$run.'</w:p>';
    }

    /**
     * Sel tabel. Opsi: span (gabung kolom), isian (warna latar hex), tanpa_garis, margin [atas, bawah, kiri, kanan].
     *
     * @param  string|list<string>  $isi  Paragraf (XML) atau teks polos (10 pt).
     * @param  array<string, mixed>  $opsi
     */
    public static function sel(string|array $isi, array $opsi = []): string
    {
        $isi = array_map(fn (string $p) => str_starts_with($p, '<w:') ? $p : self::paragraf([self::run($p, ['ukuran' => 20])], ['sesudah' => 0, 'baris' => 240]), is_array($isi) ? $isi : [$isi]);
        [$atas, $bawah, $kiri, $kanan] = $opsi['margin'] ?? [40, 40, 80, 80];
        $tcPr = (isset($opsi['span']) && $opsi['span'] > 1 ? '<w:gridSpan w:val="'.(int) $opsi['span'].'"/>' : '')
            .(($opsi['tanpa_garis'] ?? false) ? '<w:tcBorders>'.self::garis('nil').'</w:tcBorders>' : '')
            .(isset($opsi['isian']) ? '<w:shd w:val="clear" w:color="auto" w:fill="'.$opsi['isian'].'"/>' : '')
            .'<w:tcMar><w:top w:w="'.$atas.'" w:type="dxa"/><w:left w:w="'.$kiri.'" w:type="dxa"/><w:bottom w:w="'.$bawah.'" w:type="dxa"/><w:right w:w="'.$kanan.'" w:type="dxa"/></w:tcMar>'
            .'<w:vAlign w:val="top"/>';

        return '<w:tc><w:tcPr>'.$tcPr.'</w:tcPr>'.implode('', $isi ?: [self::paragraf('', ['sesudah' => 0])]).'</w:tc>';
    }

    /**
     * Baris tabel. Opsi: kepala (diulang tiap halaman), utuh (tidak terbelah antarhalaman).
     *
     * @param  list<string>  $sel
     * @param  array{kepala?: bool, utuh?: bool}  $opsi
     */
    public static function baris(array $sel, array $opsi = []): string
    {
        $trPr = (($opsi['kepala'] ?? false) ? '<w:tblHeader/>' : '').(($opsi['utuh'] ?? false) ? '<w:cantSplit/>' : '');

        return '<w:tr>'.($trPr !== '' ? '<w:trPr>'.$trPr.'</w:trPr>' : '').implode('', $sel).'</w:tr>';
    }

    /**
     * Tabel lebar tetap. Opsi: tanpa_garis.
     *
     * @param  list<string>  $baris
     * @param  list<int>  $lebar  Lebar kolom (twip).
     * @param  array{tanpa_garis?: bool}  $opsi
     */
    public static function tabel(array $baris, array $lebar, array $opsi = []): string
    {
        $garis = ($opsi['tanpa_garis'] ?? false) ? self::garis('nil') : self::garis('single');

        return '<w:tbl><w:tblPr><w:tblW w:w="'.array_sum($lebar).'" w:type="dxa"/><w:tblBorders>'.$garis.'<w:insideH '.self::atributGaris($opsi).'/><w:insideV '.self::atributGaris($opsi).'/></w:tblBorders><w:tblLayout w:type="fixed"/></w:tblPr>'
            .'<w:tblGrid>'.implode('', array_map(fn (int $l) => '<w:gridCol w:w="'.$l.'"/>', $lebar)).'</w:tblGrid>'
            .implode('', $baris).'</w:tbl>';
    }

    /**
     * Paket .docx.
     *
     * @param  list<array{isi: list<string>, kepala?: list<string>, kaki?: list<string>, margin?: array{atas: int, bawah: int, kiri: int, kanan: int}}>  $bagian
     * @param  array{judul?: string, deskripsi?: string, pembuat?: string}  $meta
     */
    public static function tulis(array $bagian, array $meta = []): string
    {
        $bagian = $bagian ?: [['isi' => [self::paragraf('')]]];
        $berkas = [];
        $relasi = [];
        $badan = '';
        foreach (array_values($bagian) as $i => $b) {
            $n = $i + 1;
            $berkas["word/header{$n}.xml"] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:hdr '.self::NS.'>'.implode('', ($b['kepala'] ?? []) ?: [self::paragraf('', ['sesudah' => 0])]).'</w:hdr>';
            $berkas["word/footer{$n}.xml"] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:ftr '.self::NS.'>'.implode('', ($b['kaki'] ?? []) ?: [self::paragraf('', ['sesudah' => 0])]).'</w:ftr>';
            $relasi[] = '<Relationship Id="rIdH'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header'.$n.'.xml"/>';
            $relasi[] = '<Relationship Id="rIdF'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer'.$n.'.xml"/>';
            $m = ($b['margin'] ?? []) + ['atas' => 2 * self::CM, 'bawah' => 2 * self::CM, 'kiri' => 2 * self::CM, 'kanan' => 2 * self::CM];
            $sect = '<w:sectPr><w:headerReference w:type="default" r:id="rIdH'.$n.'"/><w:footerReference w:type="default" r:id="rIdF'.$n.'"/>'
                .'<w:pgSz w:w="'.self::A4[0].'" w:h="'.self::A4[1].'"/>'
                .'<w:pgMar w:top="'.$m['atas'].'" w:right="'.$m['kanan'].'" w:bottom="'.$m['bawah'].'" w:left="'.$m['kiri'].'" w:header="'.self::CM.'" w:footer="'.self::CM.'" w:gutter="0"/>'
                .'</w:sectPr>';
            $badan .= implode('', $b['isi']);
            $badan .= $i < count($bagian) - 1 ? self::paragraf('', ['sesudah' => 0, 'baris' => 240, 'sect' => $sect]) : $sect;
        }

        $berkas['word/document.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document '.self::NS.'><w:body>'.$badan.'</w:body></w:document>';
        $berkas['word/styles.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles '.self::NS.'><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="'.self::FONT.'" w:hAnsi="'.self::FONT.'" w:cs="'.self::FONT.'" w:eastAsia="'.self::FONT.'"/><w:sz w:val="'.self::UKURAN.'"/><w:szCs w:val="'.self::UKURAN.'"/><w:lang w:val="id-ID"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
            .'<w:style w:type="table" w:default="1" w:styleId="TableNormal"><w:name w:val="Normal Table"/><w:tblPr><w:tblInd w:w="0" w:type="dxa"/><w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="108" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/><w:right w:w="108" w:type="dxa"/></w:tblCellMar></w:tblPr></w:style></w:styles>';
        $berkas['word/settings.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:settings '.self::NS.'><w:defaultTabStop w:val="720"/><w:characterSpacingControl w:val="doNotCompress"/><w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat></w:settings>';
        $berkas['word/_rels/document.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rIdSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>'
            .implode('', $relasi).'</Relationships>';
        $berkas['_rels/.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
        $waktu = gmdate('Y-m-d\TH:i:s\Z');
        $berkas['docProps/core.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.self::teks($meta['judul'] ?? '').'</dc:title><dc:description>'.self::teks($meta['deskripsi'] ?? '').'</dc:description><dc:creator>'.self::teks($meta['pembuat'] ?? config('sigenrabtor.aplikasi.nama', 'SIRABTOR')).'</dc:creator>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$waktu.'</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">'.$waktu.'</dcterms:modified></cp:coreProperties>';
        $berkas['docProps/app.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>'.self::teks(config('sigenrabtor.aplikasi.nama', 'SIRABTOR')).'</Application></Properties>';
        $berkas['[Content_Types].xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>'
            .implode('', array_map(fn (int $n) => '<Override PartName="/word/header'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/><Override PartName="/word/footer'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>', range(1, count($bagian))))
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>';

        return self::zip(['[Content_Types].xml' => $berkas['[Content_Types].xml']] + $berkas);
    }

    /** @param  array<string, string>  $berkas */
    private static function zip(array $berkas): string
    {
        $sementara = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        if ($sementara === false || $zip->open($sementara, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Berkas Word sementara tidak bisa dibuat.');
        }
        try {
            foreach ($berkas as $nama => $isi) {
                $zip->addFromString($nama, $isi);
            }
            $zip->close();

            return (string) file_get_contents($sementara);
        } finally {
            @unlink($sementara);
        }
    }

    /** @param  array<string, mixed>  $opsi */
    private static function rPr(array $opsi): string
    {
        $ukuran = (int) ($opsi['ukuran'] ?? self::UKURAN);

        return '<w:rPr><w:rFonts w:ascii="'.self::FONT.'" w:hAnsi="'.self::FONT.'" w:cs="'.self::FONT.'"/>'
            .(($opsi['tebal'] ?? false) ? '<w:b/><w:bCs/>' : '').(($opsi['miring'] ?? false) ? '<w:i/><w:iCs/>' : '')
            .(! empty($opsi['warna']) ? '<w:color w:val="'.ltrim((string) $opsi['warna'], '#').'"/>' : '')
            .'<w:sz w:val="'.$ukuran.'"/><w:szCs w:val="'.$ukuran.'"/>'
            .(($opsi['garis_bawah'] ?? false) ? '<w:u w:val="single"/>' : '').'</w:rPr>';
    }

    private static function garis(string $jenis): string
    {
        $atribut = $jenis === 'nil' ? 'w:val="nil"' : 'w:val="single" w:sz="4" w:space="0" w:color="57534E"';

        return '<w:top '.$atribut.'/><w:left '.$atribut.'/><w:bottom '.$atribut.'/><w:right '.$atribut.'/>';
    }

    /** @param  array{tanpa_garis?: bool}  $opsi */
    private static function atributGaris(array $opsi): string
    {
        return ($opsi['tanpa_garis'] ?? false) ? 'w:val="nil"' : 'w:val="single" w:sz="4" w:space="0" w:color="57534E"';
    }
}
