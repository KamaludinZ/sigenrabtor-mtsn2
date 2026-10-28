<?php

namespace App\Support\Master\Impor;

use RuntimeException;

/** Unggahan master kode belum bisa disimpan; `kendala` = alasannya (SimpanMasterKode::kendala). */
class GagalSimpanMaster extends RuntimeException
{
    /** @param  list<string>  $kendala */
    public function __construct(public readonly array $kendala)
    {
        parent::__construct('Master kode belum bisa disimpan: '.implode(' ', $kendala));
    }
}
