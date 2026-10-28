<?php

namespace App\Support\Basisdata;

use App\Repositories\EloquentPenggunaRepository;
use App\Support\Stub\StubPenggunaRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sinkronkan akun dari penyimpanan tiruan (berkas JSON) ke PostgreSQL — boleh pada database yang sudah berisi (perintah
 * `php artisan sigenrabtor:pindah-akun`; jalankan sesudah leading tersinkron): per id, akun yang belum ada ditambahkan
 * (id sama, sandi acak) dan yang sudah ada diperbarui (nama, surel, peran, NIP, jabatan, status, waktu dibuat & masuk
 * terakhir — sandi tidak diubah), tautan leading diganti sesuai tiruan, dan riwayat masuk tiruan yang belum ada disalin.
 * Akun yang hanya ada di database dibiarkan dan dilaporkan. Satu transaksi lalu diverifikasi lewat
 * EloquentPenggunaRepository (akun & riwayat masuk sama persis); selisih atau penolakan database membatalkan semuanya.
 * `uji` = jalankan & verifikasi lalu batalkan.
 */
final class PindahAkun
{
    /**
     * @return array{tambah: list<int>, ubah: list<int>, tetap: list<int>, hanya_database: list<int>, riwayat_masuk: int}
     *
     * @throws RuntimeException
     */
    public static function jalankan(bool $uji = false): array
    {
        $hasil = null;
        try {
            DB::transaction(function () use ($uji, &$hasil) {
                $hasil = self::sinkronkan();
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                $selisih = self::verifikasi();
                if ($selisih !== []) {
                    throw new RuntimeException("Verifikasi gagal, pemindahan akun dibatalkan:\n- ".implode("\n- ", $selisih));
                }
                if ($uji) {
                    throw new UjiPindahSelesai;
                }
            });
        } catch (UjiPindahSelesai) {
            // Mode uji: transaksi sengaja dibatalkan sesudah verifikasi lolos.
        }

        return $hasil;
    }

    /** @return array{tambah: list<int>, ubah: list<int>, tetap: list<int>, hanya_database: list<int>, riwayat_masuk: int} */
    private static function sinkronkan(): array
    {
        $tiruan = new StubPenggunaRepository;
        $kodeLeading = DB::table('leadings')->pluck('kode')->all();
        $ada = DB::table('users')->get()->keyBy('id');
        $hasil = ['tambah' => [], 'ubah' => [], 'tetap' => [], 'hanya_database' => [], 'riwayat_masuk' => 0];

        foreach ($tiruan->semua() as $a) {
            if (($hilang = array_diff($a['leadings'], $kodeLeading)) !== []) {
                throw new RuntimeException("Akun {$a['id']} bertaut ke leading yang belum ada di database (".implode(', ', $hilang).') — jalankan sigenrabtor:pindah-leading dulu.');
            }
            $baris = [
                'name' => $a['name'], 'email' => mb_strtolower($a['email']), 'role' => $a['role'], 'nip' => $a['nip'], 'jabatan' => $a['jabatan'],
                'is_active' => $a['is_active'], 'terakhir_masuk' => $a['terakhir_masuk'], 'created_at' => $a['dibuat_pada'] ?? now()->format('Y-m-d H:i:s.uP'),
            ];
            $lama = $ada[$a['id']] ?? null;
            if ($lama === null) {
                DB::table('users')->insert(['id' => $a['id'], 'password' => Hash::make(Str::random(40)), 'updated_at' => now()->format('Y-m-d H:i:s.uP')] + $baris);
                $hasil['tambah'][] = $a['id'];
            } elseif ((new EloquentPenggunaRepository)->temukan($a['id']) !== $a) {
                DB::table('users')->where('id', $a['id'])->update($baris + ['updated_at' => now()->format('Y-m-d H:i:s.uP')]);
                $hasil['ubah'][] = $a['id'];
            } else {
                $hasil['tetap'][] = $a['id'];
            }

            DB::table('user_leading')->where('user_id', $a['id'])->delete();
            DB::table('user_leading')->insert(array_map(fn (string $kode) => ['user_id' => $a['id'], 'kode_leading' => $kode], $a['leadings']));

            $sudah = DB::table('riwayat_masuk')->where('user_id', $a['id'])->pluck('waktu')->map(fn ($w) => strtotime((string) $w))->all();
            foreach (array_reverse($tiruan->riwayatMasuk($a['id'], StubPenggunaRepository::MAKS_RIWAYAT_MASUK)) as $r) {
                if (! in_array(strtotime($r['waktu']), $sudah, true)) {
                    DB::table('riwayat_masuk')->insert(['user_id' => $a['id'], 'pengguna' => $a['name'], 'waktu' => $r['waktu'], 'alamat_ip' => $r['alamat_ip'], 'agen' => $r['agen']]);
                    $hasil['riwayat_masuk']++;
                }
            }
        }
        $hasil['hanya_database'] = array_values(array_diff($ada->keys()->map(fn ($id) => (int) $id)->all(), array_column($tiruan->semua(), 'id')));
        DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), (SELECT max(id) FROM users))");

        return $hasil;
    }

    /** @return list<string> */
    private static function verifikasi(): array
    {
        $tiruan = new StubPenggunaRepository;
        $db = new EloquentPenggunaRepository;
        $selisih = [];
        foreach ($tiruan->semua() as $a) {
            if ($db->temukan($a['id']) !== $a) {
                $selisih[] = "Akun {$a['id']} berbeda di database.";
            }
            $waktuTiruan = array_map(fn (array $r) => strtotime($r['waktu']), $tiruan->riwayatMasuk($a['id']));
            $waktuDb = array_map(fn (array $r) => strtotime($r['waktu']), $db->riwayatMasuk($a['id'], 1000));
            if (array_diff($waktuTiruan, $waktuDb) !== []) {
                $selisih[] = "Riwayat masuk akun {$a['id']} belum lengkap di database.";
            }
        }

        return $selisih;
    }
}
