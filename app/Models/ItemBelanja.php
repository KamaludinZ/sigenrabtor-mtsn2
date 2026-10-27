<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu rincian belanja RAB (tabel budget_items) milik anggaran_kegiatan; id uuid (id item RabRepository), jumlah
 * dihitung database. Diisi hanya lewat EloquentRabRepository.
 */
#[Table('budget_items', keyType: 'string', incrementing: false, dateFormat: 'Y-m-d H:i:s.uP')]
#[Unguarded]
class ItemBelanja extends Model
{
    protected function casts(): array
    {
        return ['harga_satuan' => 'integer', 'jumlah' => 'integer', 'urutan' => 'integer'];
    }
}
