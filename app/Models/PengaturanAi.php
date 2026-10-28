<?php

namespace App\Models;

use App\Casts\ObjekJson;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan AI umum (tabel ai_settings, tepat satu baris ber-id 1): penyedia aktif & cadangan (wajib siap — trigger
 * periksa_penyedia_ai_siap), parameter jawaban (ParameterAi), batas harian umum & per akun (BatasAi), dan simulasi
 * kegagalan (KegagalanAi). `updated_at` = `diperbarui_pada` PengaturanAiRepository.
 */
#[Table('ai_settings', dateFormat: 'Y-m-d H:i:s.uP')]
#[Unguarded]
class PengaturanAi extends Model
{
    public $incrementing = false;

    protected function casts(): array
    {
        return ['batas_per_user_per_hari' => 'integer', 'parameter' => ObjekJson::class, 'batas_khusus' => ObjekJson::class, 'simulasi_gagal' => ObjekJson::class];
    }
}
