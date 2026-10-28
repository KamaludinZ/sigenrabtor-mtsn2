<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Tahun anggaran (tabel tahun_anggaran): tahun unik, tepat satu aktif (indeks parsial tahun_anggaran_satu_aktif),
 * terkunci, SK RKTM/RKAM (nomor & tanggal berpasangan), dan jejak aktivasi. Tabel lain merujuk kolom `tahun`.
 */
#[Table('tahun_anggaran', dateFormat: 'Y-m-d H:i:s.uP')]
#[Unguarded]
class TahunAnggaran extends Model
{
    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
            'tanggal_sk_rktm' => 'immutable_date',
            'diaktifkan_pada' => 'immutable_datetime',
        ];
    }
}
