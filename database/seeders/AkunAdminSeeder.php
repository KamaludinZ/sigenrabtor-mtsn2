<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Akun admin awal untuk instalasi baru (config `sigenrabtor.admin_awal`): dibuat hanya bila belum ada admin aktif —
 * aman dijalankan ulang. Sandi acak, tidak ditampilkan; diatur ulang saat autentikasi nyata dipasang.
 */
class AkunAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('users')->where('role', 'admin')->where('is_active', true)->exists()) {
            return;
        }

        $waktu = now()->format('Y-m-d H:i:s.uP');
        $email = mb_strtolower((string) config('sigenrabtor.admin_awal.email'));
        DB::table('users')->upsert(
            [['name' => (string) config('sigenrabtor.admin_awal.nama'), 'email' => $email, 'password' => Hash::make(Str::random(40)), 'role' => 'admin', 'is_active' => true, 'jabatan' => 'Admin Aplikasi', 'created_at' => $waktu, 'updated_at' => $waktu]],
            ['email'],
            ['role', 'is_active', 'updated_at'],
        );
        DB::table('user_leading')->whereIn('user_id', DB::table('users')->where('email', $email)->select('id'))->delete();
    }
}
