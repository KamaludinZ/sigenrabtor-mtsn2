<?php

namespace App\Repositories;

use App\Contracts\RiwayatKunciRepository;
use App\Support\Kegiatan\RiwayatKunci;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Log kunci di PostgreSQL (activity_logs dengan activity_id, aksi RiwayatKunci::AKSI; trigger hanya-tambah), padanan
 * StubRiwayatKunciRepository: id & nama pengguna saat itu, tahun anggaran kegiatan, waktu = waktu peristiwa; terbaru
 * dulu (waktu sama → yang dicatat belakangan dulu).
 */
class EloquentRiwayatKunciRepository implements RiwayatKunciRepository
{
    public function catat(int $kegiatanId, string $aksi, string $keterangan, array $pengguna, string $pada, ?int $tahun): array
    {
        $id = DB::table('activity_logs')->insertGetId([
            'user_id' => $pengguna['id'] ?? null,
            'pengguna' => $pengguna['name'],
            'activity_id' => $kegiatanId,
            'tahun_anggaran' => $tahun,
            'aksi' => $aksi,
            'keterangan' => $keterangan,
            'created_at' => CarbonImmutable::parse($pada)->format('Y-m-d H:i:s.uP'),
        ]);

        return self::entri(DB::table('activity_logs')->find($id));
    }

    public function untukKegiatan(int $kegiatanId): array
    {
        return DB::table('activity_logs')->where('activity_id', $kegiatanId)->whereIn('aksi', array_keys(RiwayatKunci::AKSI))
            ->orderByDesc('created_at')->orderByDesc('id')->get()->map(self::entri(...))->all();
    }

    /** @return array{id: string, kegiatan_id: int, aksi: string, keterangan: string, user_id: int|null, pengguna: string, tahun: int|null, pada: string} */
    private static function entri(object $r): array
    {
        return [
            'id' => (string) $r->id,
            'kegiatan_id' => (int) $r->activity_id,
            'aksi' => $r->aksi,
            'keterangan' => $r->keterangan,
            'user_id' => $r->user_id === null ? null : (int) $r->user_id,
            'pengguna' => $r->pengguna,
            'tahun' => $r->tahun_anggaran === null ? null : (int) $r->tahun_anggaran,
            'pada' => CarbonImmutable::parse($r->created_at)->setTimezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
