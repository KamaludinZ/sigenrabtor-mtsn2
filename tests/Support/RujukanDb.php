<?php

namespace Tests\Support;

use App\Support\Stub\StubLeadingRepository;
use App\Support\Stub\StubPenggunaRepository;
use Illuminate\Support\Facades\DB;

/**
 * Baris rujukan PostgreSQL yang dipakai data contoh: tahun anggaran 2025 & 2026 (2026 aktif, 2025 terkunci), leading
 * contoh (StubLeadingRepository), dan akun contoh (StubPenggunaRepository, id sama). Rujukan dari activities/master
 * kode/data pagu ke tabel ini bersifat DEFERRABLE INITIALLY DEFERRED — tes yang memeriksa constraint dengan
 * `SET CONSTRAINTS ALL IMMEDIATE` atau mengubah tabel di tengah transaksi perlu baris ini lebih dulu.
 */
final class RujukanDb
{
    public static function siapkan(): void
    {
        DB::table('tahun_anggaran')->insert([
            ['tahun' => 2025, 'is_active' => false, 'is_locked' => true],
            ['tahun' => 2026, 'is_active' => true, 'is_locked' => false],
        ]);
        DB::table('leadings')->insert(StubLeadingRepository::contoh());
        foreach (StubPenggunaRepository::contoh() as $akun) {
            DB::table('users')->insert([
                'id' => $akun['id'], 'name' => $akun['name'], 'email' => $akun['email'], 'password' => '-',
                'role' => $akun['role'], 'nip' => $akun['nip'], 'jabatan' => $akun['jabatan'], 'is_active' => $akun['is_active'],
            ]);
            DB::table('user_leading')->insert(array_map(fn (string $kode) => ['user_id' => $akun['id'], 'kode_leading' => $kode], $akun['leadings']));
        }
        DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), (SELECT max(id) FROM users))");
    }

    /** siapkan() lalu periksa semua constraint tertunda seketika sampai akhir transaksi (tanpa antrean trigger). */
    public static function segera(): void
    {
        self::siapkan();
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }
}
