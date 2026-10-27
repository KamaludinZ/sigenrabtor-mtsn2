<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sumber dana satu kegiatan beserta pagunya (tabel anggaran_kegiatan); pagu null = belum diisi. */
#[Table('anggaran_kegiatan', dateFormat: 'Y-m-d H:i:s.uP')]
#[Unguarded]
class AnggaranKegiatan extends Model
{
    protected function casts(): array
    {
        return ['pagu' => 'integer', 'total_rab' => 'integer'];
    }

    /** @return BelongsTo<Kegiatan, $this> */
    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'activity_id');
    }
}
