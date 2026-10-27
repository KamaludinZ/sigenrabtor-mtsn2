<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/** Satu entri riwayat status kegiatan (RiwayatStatus::entri()); hanya ditambah, tidak pernah diubah. */
#[Table('riwayat_status_kegiatan', dateFormat: 'Y-m-d H:i:s.uP')]
#[Unguarded]
class RiwayatStatusKegiatan extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['sidik' => 'array'];
    }
}
