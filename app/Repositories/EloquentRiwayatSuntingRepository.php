<?php

namespace App\Repositories;

use App\Contracts\RiwayatSuntingRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Jejak sunting di PostgreSQL (tabel `riwayat_sunting_dokumen`, migrasi 000041), padanan StubRiwayatSuntingRepository:
 * hanya tambah (dijaga trigger), terbaru dulu — waktu sama → yang dicatat belakangan dulu. Penolakan database →
 * RuntimeException berpesan ramah. Binding masih tiruan.
 */
class EloquentRiwayatSuntingRepository implements RiwayatSuntingRepository
{
    public const PESAN_GAGAL = 'Riwayat sunting gagal disimpan — coba lagi.';

    public function catat(int $kegiatanId, array $entri): array
    {
        try {
            $id = DB::table('riwayat_sunting_dokumen')->insertGetId([
                'activity_id' => $kegiatanId,
                'bidang' => $entri['bidang'],
                'nomor_bagian' => (int) $entri['nomor_bagian'],
                'asal' => $entri['asal'],
                'teks_lama' => $entri['teks_lama'],
                'teks_baru' => $entri['teks_baru'],
                'user_id' => $entri['user_id'] ?? null,
                'pengguna' => $entri['pengguna'],
                'dibuat_pada' => now()->format('Y-m-d H:i:s.uP'),
            ]);
        } catch (QueryException) {
            throw new RuntimeException(self::PESAN_GAGAL);
        }

        return $this->bentuk(DB::table('riwayat_sunting_dokumen')->where('id', $id)->first());
    }

    public function untukKegiatan(int $kegiatanId, ?string $bidang = null, int $batas = 50): array
    {
        return DB::table('riwayat_sunting_dokumen')->where('activity_id', $kegiatanId)
            ->when($bidang !== null, fn ($q) => $q->where('bidang', $bidang))
            ->orderByDesc('dibuat_pada')->orderByDesc('id')->limit(max(0, $batas))
            ->get()->map(fn (object $b) => $this->bentuk($b))->all();
    }

    /** @return array<string, mixed> Bentuk RiwayatSuntingRepository (urutan kunci sama dengan tiruan). */
    private function bentuk(object $baris): array
    {
        return [
            'id' => (string) $baris->id,
            'kegiatan_id' => (int) $baris->activity_id,
            'bidang' => $baris->bidang,
            'nomor_bagian' => (int) $baris->nomor_bagian,
            'asal' => $baris->asal,
            'teks_lama' => $baris->teks_lama,
            'teks_baru' => $baris->teks_baru,
            'user_id' => $baris->user_id === null ? null : (int) $baris->user_id,
            'pengguna' => $baris->pengguna,
            'dibuat_pada' => CarbonImmutable::parse($baris->dibuat_pada)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s.uP'),
        ];
    }
}
