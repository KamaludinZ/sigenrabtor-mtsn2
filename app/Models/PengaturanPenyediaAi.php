<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi satu penyedia AI (tabel ai_provider_settings, kunci = kunci PenyediaAi::DAFTAR): alamat server/API, model,
 * batas waktu, dan kunci API terenkripsi (Crypt) beserta 4 karakter akhirnya — kunci hanya untuk penyedia cloud.
 */
#[Table('ai_provider_settings', key: 'penyedia', keyType: 'string', incrementing: false, dateFormat: 'Y-m-d H:i:s.uP')]
#[Unguarded]
class PengaturanPenyediaAi extends Model
{
    protected $hidden = ['kunci_terenkripsi'];

    protected function casts(): array
    {
        return ['batas_waktu' => 'integer'];
    }
}
