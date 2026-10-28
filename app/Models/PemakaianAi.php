<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Hitungan permintaan AI per akun × tanggal × fitur (tabel ai_usage, PRD §21 "Batas Pemakaian"; fitur = kunci BagianAi
 * atau `usul_jadwal`), dasar BatasAi & StatusKuotaAi.
 */
#[Table('ai_usage', timestamps: false)]
#[Unguarded]
class PemakaianAi extends Model
{
    protected function casts(): array
    {
        return ['user_id' => 'integer', 'tanggal' => 'date:Y-m-d', 'jumlah' => 'integer'];
    }
}
