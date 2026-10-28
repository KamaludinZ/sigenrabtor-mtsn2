<?php

namespace App\Repositories;

use App\Contracts\CatatanPemeriksaanRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Draf catatan pemeriksaan di PostgreSQL (tabel catatan_pemeriksaan, satu baris per kegiatan), padanan
 * StubCatatanPemeriksaanRepository: simpan = upsert per kegiatan dengan waktu bermikrodetik (pengenal versi draf).
 */
class EloquentCatatanPemeriksaanRepository implements CatatanPemeriksaanRepository
{
    public function draf(int $kegiatanId): ?array
    {
        $baris = DB::table('catatan_pemeriksaan')->where('activity_id', $kegiatanId)->first();

        return $baris === null ? null : [
            'catatan' => $baris->catatan,
            'user_id' => $baris->user_id === null ? null : (int) $baris->user_id,
            'oleh' => $baris->oleh,
            'diperbarui_pada' => CarbonImmutable::parse($baris->diperbarui_pada)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s.uP'),
        ];
    }

    public function simpan(int $kegiatanId, string $catatan, array $pengguna): array
    {
        DB::table('catatan_pemeriksaan')->upsert([
            'activity_id' => $kegiatanId,
            'user_id' => $pengguna['id'] ?? null,
            'oleh' => $pengguna['name'],
            'catatan' => $catatan,
            'diperbarui_pada' => now()->format('Y-m-d H:i:s.uP'),
        ], ['activity_id'], ['user_id', 'oleh', 'catatan', 'diperbarui_pada']);

        return $this->draf($kegiatanId);
    }

    public function hapus(int $kegiatanId): void
    {
        DB::table('catatan_pemeriksaan')->where('activity_id', $kegiatanId)->delete();
    }
}
