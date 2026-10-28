<?php

namespace App\Repositories;

use App\Contracts\PemakaianAiRepository;
use Illuminate\Support\Facades\DB;

/**
 * Pemakaian AI di PostgreSQL (tabel ai_usage: user_id × tanggal × fitur → jumlah ≥ 1), padanan StubPemakaianAiRepository:
 * rincian urut fitur; catat = upsert jumlah + 1; ambilJatah = satu transaksi dengan kunci nasihat per akun
 * (pg_advisory_xact_lock) — jumlah hari ini diperiksa lalu dicatat sehingga permintaan bersamaan akun yang sama
 * antre dan tidak melewati batas; kembalikan = jumlah − 1 (baris terakhir dihapus, aturan jumlah ≥ 1). Riwayat hari lalu
 * tetap tersimpan (tiruan hanya menyimpan 7 hari).
 */
class EloquentPemakaianAiRepository implements PemakaianAiRepository
{
    /** Kunci nasihat pertama (ruang kunci pemakaian AI); kunci kedua = id akun. */
    private const KUNCI = 2104;

    public function jumlah(int $userId, string $tanggal): int
    {
        return (int) DB::table('ai_usage')->where('user_id', $userId)->where('tanggal', $tanggal)->sum('jumlah');
    }

    public function rincian(int $userId, string $tanggal): array
    {
        return DB::table('ai_usage')->where('user_id', $userId)->where('tanggal', $tanggal)->orderBy('fitur')->pluck('jumlah', 'fitur')->map(fn ($n) => (int) $n)->all();
    }

    public function catat(int $userId, string $fitur): void
    {
        DB::statement('INSERT INTO ai_usage (user_id, tanggal, fitur, jumlah) VALUES (?, ?, ?, 1) ON CONFLICT (user_id, tanggal, fitur) DO UPDATE SET jumlah = ai_usage.jumlah + 1', [$userId, now()->toDateString(), $fitur]);
    }

    public function ambilJatah(int $userId, string $fitur, int $batas): bool
    {
        return DB::transaction(function () use ($userId, $fitur, $batas) {
            DB::select('SELECT pg_advisory_xact_lock(?, ?)', [self::KUNCI, $userId]);
            if ($this->jumlah($userId, now()->toDateString()) >= $batas) {
                return false;
            }
            $this->catat($userId, $fitur);

            return true;
        });
    }

    public function kembalikan(int $userId, string $fitur): void
    {
        DB::transaction(function () use ($userId, $fitur) {
            DB::select('SELECT pg_advisory_xact_lock(?, ?)', [self::KUNCI, $userId]);
            $baris = DB::table('ai_usage')->where('user_id', $userId)->where('tanggal', now()->toDateString())->where('fitur', $fitur);
            if ((clone $baris)->where('jumlah', '>', 1)->decrement('jumlah') === 0) {
                $baris->where('jumlah', 1)->delete();
            }
        });
    }
}
