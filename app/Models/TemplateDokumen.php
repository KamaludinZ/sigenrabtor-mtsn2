<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Master dokumen .docx ber-variabel (tabel document_templates): jenis KAK+TOR/KAK/TOR/RAB, `variabel` = nama variabel
 * terdaftar di template_variabel (trigger periksa_variabel_template), satu default per tahun × jenis × leading.
 */
#[Table('document_templates', dateFormat: 'Y-m-d H:i:s.uP')]
#[Unguarded]
class TemplateDokumen extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['tahun_anggaran' => 'integer', 'variabel' => 'array', 'is_default' => 'boolean', 'aktif' => 'boolean'];
    }
}
