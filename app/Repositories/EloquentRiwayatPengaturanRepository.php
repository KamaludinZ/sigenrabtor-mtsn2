<?php

namespace App\Repositories;

use App\Contracts\RiwayatPengaturanRepository;
use App\Support\Stub\StubData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat perubahan pengaturan di PostgreSQL (tabel activity_logs, baris tanpa activity_id; hanya bisa ditambah — trigger
 * activity_logs_hanya_tambah), padanan StubRiwayatPengaturanRepository: dicatat atas nama pengguna yang sedang masuk
 * (id & nama saat itu) pada tahun anggaran aktif; dibaca terbaru dulu (yang dicatat belakangan dulu bila waktunya sama).
 */
class EloquentRiwayatPengaturanRepository implements RiwayatPengaturanRepository
{
    public function catat(string $aksi, string $keterangan): void
    {
        $pengguna = StubData::currentUser();

        DB::table('activity_logs')->insert([
            'user_id' => $pengguna['id'],
            'pengguna' => $pengguna['name'],
            'aksi' => $aksi,
            'keterangan' => $keterangan,
            'tahun_anggaran' => StubData::tahunAktif()['tahun'],
            'created_at' => now()->format('Y-m-d H:i:s.uP'),
        ]);
    }

    public function semua(): array
    {
        return DB::table('activity_logs')->whereNull('activity_id')->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn ($r) => [
                'id' => (string) $r->id,
                'waktu' => CarbonImmutable::parse($r->created_at)->setTimezone(config('app.timezone'))->toIso8601String(),
                'user_id' => $r->user_id === null ? null : (int) $r->user_id,
                'pengguna' => $r->pengguna,
                'aksi' => $r->aksi,
                'keterangan' => $r->keterangan,
                'tahun' => $r->tahun_anggaran === null ? null : (int) $r->tahun_anggaran,
            ])->all();
    }
}
