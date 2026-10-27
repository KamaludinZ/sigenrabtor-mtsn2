<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/** Satu termin pembayaran RAB (tabel termin_pembayaran) milik anggaran_kegiatan; nomor_termin = urutan. */
#[Table('termin_pembayaran', timestamps: false)]
#[Unguarded]
class Termin extends Model
{
    protected function casts(): array
    {
        return ['nomor_termin' => 'integer', 'nominal' => 'integer'];
    }
}
