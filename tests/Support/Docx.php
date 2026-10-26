<?php

namespace Tests\Support;

use ZipArchive;

/**
 * Pembuat dokumen Word .docx minimal untuk tes (dan berkas contoh E2E di tests/fixtures). Setiap paragraf berupa daftar
 * "run"; memecah variabel ke beberapa run meniru Word yang memotong teks karena cek ejaan/format.
 */
final class Docx
{
    /**
     * @param  list<list<string>|string>  $paragraf  Paragraf isi; string = satu run.
     * @param  list<string>  $header  Paragraf header (satu run tiap paragraf).
     */
    public static function isi(array $paragraf, array $header = []): string
    {
        $run = fn (string $teks) => '<w:r><w:t xml:space="preserve">'.htmlspecialchars($teks, ENT_XML1).'</w:t></w:r>';
        $p = fn (array|string $runs) => '<w:p>'.implode('', array_map($run, (array) $runs)).'</w:p>';
        $ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';

        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'.($header ? '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>' : '').'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $sectPr = $header ? '<w:sectPr><w:headerReference w:type="default" r:id="rIdH1"/></w:sectPr>' : '';
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document '.$ns.'><w:body>'.implode('', array_map($p, $paragraf)).$sectPr.'</w:body></w:document>');

        if ($header) {
            $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdH1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/></Relationships>');
            $zip->addFromString('word/header1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:hdr '.$ns.'>'.implode('', array_map($p, $header)).'</w:hdr>');
        }

        $zip->close();
        $isi = (string) file_get_contents($path);
        unlink($path);

        return $isi;
    }
}
