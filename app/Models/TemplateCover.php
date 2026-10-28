<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Desain cover per tahun anggaran (tabel cover_templates): gambar + kotak teks `teks_items` (bentuk TeksCover::rapikan(),
 * diperiksa trigger periksa_teks_cover), satu default per tahun × leading × sumber dana.
 */
#[Table('cover_templates', dateFormat: 'Y-m-d H:i:s.uP')]
#[Unguarded]
class TemplateCover extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['tahun_anggaran' => 'integer', 'lebar' => 'integer', 'tinggi' => 'integer', 'teks_items' => 'array', 'is_default' => 'boolean'];
    }
}
