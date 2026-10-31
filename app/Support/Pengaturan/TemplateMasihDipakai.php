<?php

namespace App\Support\Pengaturan;

use RuntimeException;

/**
 * Template master tidak boleh dihapus karena masih dipakai (PRD §6 `document_templates`: "template yang sudah dipakai
 * dokumen tidak boleh dihapus, hanya dinonaktifkan"; PemakaianTemplate): `$kegiatan` = kegiatan yang memilihnya {id, nama},
 * `$dokumen` = dokumen yang tercatat memakainya (hasil susunan & Riwayat Dokumen).
 */
final class TemplateMasihDipakai extends RuntimeException
{
    /**
     * @param  list<array{id: int, nama: string}>  $kegiatan
     * @param  list<array<string, mixed>>  $dokumen
     */
    public function __construct(public readonly array $kegiatan, public readonly array $dokumen = [])
    {
        $pemakai = array_filter([
            $kegiatan !== [] ? count($kegiatan).' kegiatan masih memilihnya' : null,
            $dokumen !== [] ? count($dokumen).' dokumen tercatat memakainya' : null,
        ]);
        parent::__construct('Template tidak bisa dihapus — '.implode(' dan ', $pemakai).'. Nonaktifkan saja agar tidak muncul di pilihan baru; dokumen lama tetap utuh.');
    }
}
