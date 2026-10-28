<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Template prompt per bagian AI (tabel prompt_templates, kunci = kunci BagianAi::BAGIAN atau `umum` untuk gaya bahasa
 * baku): instruksi (PromptAi::PANJANG_INSTRUKSI, hanya PromptAi::PENANDA, tanpa NIP), gaya bahasa khusus, aktif.
 * Bagian yang belum diubah admin tidak punya baris (PromptAi::BAWAAN).
 */
#[Table('prompt_templates', key: 'bagian', keyType: 'string', incrementing: false, dateFormat: 'Y-m-d H:i:s.uP')]
#[Unguarded]
class TemplatePrompt extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
