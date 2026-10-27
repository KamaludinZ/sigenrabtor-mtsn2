<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RAB satu kegiatan × sumber dana (tabel anggaran_kegiatan): pagu (null = belum diisi) dan induk rincian & termin.
 * dipilih = sumber dana ini dipilih di Data Kegiatan; baris tetap ada walau tidak dipilih agar RAB-nya tidak hilang.
 */
#[Table('anggaran_kegiatan', dateFormat: 'Y-m-d H:i:s.uP')]
#[Unguarded]
class AnggaranKegiatan extends Model
{
    protected function casts(): array
    {
        return ['dipilih' => 'boolean', 'pagu' => 'integer', 'total_rab' => 'integer'];
    }

    /** @return BelongsTo<Kegiatan, $this> */
    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'activity_id');
    }
}
