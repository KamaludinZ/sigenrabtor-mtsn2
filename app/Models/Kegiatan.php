<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kegiatan (tabel activities). Diisi hanya lewat EloquentKegiatanRepository, yang menerjemahkan bentuk data
 * KegiatanRepository (kunci `leading`, `sumber_dana`, `panitia`, `riwayat_status`, `duplikat_dari`) ke kolom & tabel anak.
 * Kolom tanggal sengaja tanpa cast: disimpan & dibaca sebagai teks Y-m-d seperti di data kegiatan.
 */
#[Table('activities', dateFormat: 'Y-m-d H:i:s.uP')]
#[Unguarded]
class Kegiatan extends Model
{
    protected function casts(): array
    {
        return [
            'tahun_anggaran' => 'integer',
            'cover_manual' => 'boolean',
            'laporan_otomatis' => 'boolean',
            'hari_kerja' => 'boolean',
            'bulan_pelaksanaan' => 'array',
            'kode_hilang' => 'array',
            'riwayat_kode' => 'array',
            'dikunci_pada' => 'datetime',
            'konteks_diperbarui_pada' => 'datetime',
        ];
    }

    /** @return HasMany<AnggaranKegiatan, $this> */
    public function anggaran(): HasMany
    {
        return $this->hasMany(AnggaranKegiatan::class, 'activity_id');
    }

    /** @return HasMany<Panitia, $this> */
    public function panitia(): HasMany
    {
        return $this->hasMany(Panitia::class, 'activity_id')->orderBy('urutan');
    }

    /** @return HasMany<RiwayatStatusKegiatan, $this> */
    public function riwayatStatus(): HasMany
    {
        return $this->hasMany(RiwayatStatusKegiatan::class, 'activity_id')->orderBy('id');
    }

    /** @return BelongsTo<Kegiatan, $this> */
    public function duplikatDari(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplikat_dari_id');
    }
}
