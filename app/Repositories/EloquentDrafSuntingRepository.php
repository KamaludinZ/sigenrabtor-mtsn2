<?php

namespace App\Repositories;

use App\Contracts\DrafSuntingRepository;
use App\Support\Dokumen\SuntingDokumen;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Draf sunting di PostgreSQL (tabel `draf_sunting_dokumen`, migrasi 000042), padanan StubDrafSuntingRepository: satu baris
 * per kegiatan × akun × bidang (upsert; `teks_awal` & `dibuat_pada` draf yang ditimpa tetap), urut SuntingDokumen::BIDANG.
 * Penolakan database → RuntimeException berpesan ramah. Binding masih tiruan.
 */
class EloquentDrafSuntingRepository implements DrafSuntingRepository
{
    public const PESAN_GAGAL = 'Draf sunting gagal disimpan — coba lagi.';

    public function untuk(int $kegiatanId, int $userId): array
    {
        $urutan = array_flip(array_keys(SuntingDokumen::BIDANG));

        return DB::table('draf_sunting_dokumen')->where('activity_id', $kegiatanId)->where('user_id', $userId)->get()
            ->map(fn (object $b) => $this->bentuk($b))
            ->sortBy(fn (array $d) => $urutan[$d['bidang']] ?? 99)->values()->all();
    }

    public function temukan(int $kegiatanId, int $userId, string $bidang): ?array
    {
        $baris = DB::table('draf_sunting_dokumen')->where('activity_id', $kegiatanId)->where('user_id', $userId)->where('bidang', $bidang)->first();

        return $baris === null ? null : $this->bentuk($baris);
    }

    public function simpan(int $kegiatanId, int $userId, string $bidang, string $teks, ?string $teksAwal): array
    {
        $sekarang = now()->format('Y-m-d H:i:s.uP');
        try {
            DB::table('draf_sunting_dokumen')->upsert(
                [['activity_id' => $kegiatanId, 'user_id' => $userId, 'bidang' => $bidang, 'teks' => $teks, 'teks_awal' => $teksAwal, 'dibuat_pada' => $sekarang, 'diperbarui_pada' => $sekarang]],
                ['activity_id', 'user_id', 'bidang'],
                ['teks', 'diperbarui_pada'],
            );
        } catch (QueryException) {
            throw new RuntimeException(self::PESAN_GAGAL);
        }

        return $this->temukan($kegiatanId, $userId, $bidang);
    }

    public function hapus(int $kegiatanId, int $userId, string $bidang): bool
    {
        try {
            return DB::table('draf_sunting_dokumen')->where('activity_id', $kegiatanId)->where('user_id', $userId)->where('bidang', $bidang)->delete() > 0;
        } catch (QueryException) {
            throw new RuntimeException(self::PESAN_GAGAL);
        }
    }

    /** @return array<string, mixed> Bentuk DrafSuntingRepository (urutan kunci sama dengan tiruan). */
    private function bentuk(object $baris): array
    {
        $waktu = fn (string $w) => CarbonImmutable::parse($w)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s.uP');

        return [
            'id' => (string) $baris->id,
            'kegiatan_id' => (int) $baris->activity_id,
            'user_id' => (int) $baris->user_id,
            'bidang' => $baris->bidang,
            'teks' => $baris->teks,
            'teks_awal' => $baris->teks_awal,
            'dibuat_pada' => $waktu($baris->dibuat_pada),
            'diperbarui_pada' => $waktu($baris->diperbarui_pada),
        ];
    }
}
