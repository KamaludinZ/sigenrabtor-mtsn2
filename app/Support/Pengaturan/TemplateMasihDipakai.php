<?php

namespace App\Support\Pengaturan;

use RuntimeException;

/**
 * Template master tidak boleh dihapus karena masih dipilih kegiatan (PRD Fase 5: "template yang sudah dipakai tidak boleh
 * dihapus, hanya dinonaktifkan"); `$kegiatan` = kegiatan pemakainya {id, nama}.
 */
final class TemplateMasihDipakai extends RuntimeException
{
    /** @param  list<array{id: int, nama: string}>  $kegiatan */
    public function __construct(public readonly array $kegiatan)
    {
        parent::__construct(count($kegiatan).' kegiatan masih memilih template ini — nonaktifkan saja agar tidak muncul di pilihan baru; dokumen lama tetap utuh.');
    }
}
