<?php

namespace App\Models;

use App\Casts\ObjekJson;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu catatan Riwayat Dokumen (tabel `riwayat_dokumen`, migrasi 000043): berkas yang dicetak, diunduh, atau diarsipkan
 * beserta cuplikan kegiatannya; id teks, hanya ditambah (trigger `riwayat_dokumen_hanya_tambah`), waktu `dibuat_pada`
 * bermikrodetik. Diisi hanya lewat repositori riwayat dokumen.
 */
#[Table('riwayat_dokumen', keyType: 'string', incrementing: false, timestamps: false, dateFormat: 'Y-m-d H:i:s.uP')]
#[Unguarded]
class BerkasDokumenTercatat extends Model
{
    protected function casts(): array
    {
        return [
            'ringkas' => ObjekJson::class,
            'bertanda_draf' => 'boolean',
            'ukuran' => 'integer',
            'versi' => 'integer',
            'tahun_anggaran' => 'integer',
            'dibuat_pada' => 'immutable_datetime',
        ];
    }
}
