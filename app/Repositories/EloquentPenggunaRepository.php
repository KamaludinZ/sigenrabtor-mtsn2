<?php

namespace App\Repositories;

use App\Contracts\PenggunaRepository;
use App\Support\Stub\StubPenggunaRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Akun di PostgreSQL (tabel users + user_leading, riwayat_masuk), padanan StubPenggunaRepository: urut id, tautan leading
 * urut alami, akun admin tanpa tautan; akun baru diberi sandi acak (diatur saat autentikasi nyata dipasang); waktu
 * `dibuat_pada`/`terakhir_masuk` ISO 8601 zona aplikasi. `catatMasuk()` memperbarui users.terakhir_masuk dan menambah
 * entri riwayat_masuk (nama akun saat itu). Penolakan database (surel ganda, minimal satu admin aktif) dilaporkan sebagai
 * RuntimeException berpesan ramah.
 */
class EloquentPenggunaRepository implements PenggunaRepository
{
    public function semua(): array
    {
        $tautan = DB::table('user_leading')->get()->groupBy('user_id');

        return DB::table('users')->orderBy('id')->get()->map(fn (object $u) => $this->bentuk($u, $tautan[$u->id] ?? collect()))->all();
    }

    public function temukan(int $id): ?array
    {
        $u = DB::table('users')->where('id', $id)->first();

        return $u === null ? null : $this->bentuk($u, DB::table('user_leading')->where('user_id', $id)->get());
    }

    public function simpan(array $data, ?int $id = null): int
    {
        $waktu = now()->format('Y-m-d H:i:s.uP');

        try {
            return DB::transaction(function () use ($data, $id, $waktu) {
                $lama = $id === null ? null : $this->temukan($id);
                $akun = array_merge($lama ?? ['role' => 'leading', 'nip' => null, 'jabatan' => null, 'leadings' => [], 'is_active' => true], array_intersect_key($data, array_flip(['name', 'email', 'role', 'nip', 'jabatan', 'leadings', 'is_active'])));
                $role = $akun['role'] === 'admin' ? 'admin' : 'leading';
                $baris = [
                    'name' => (string) $akun['name'],
                    'email' => mb_strtolower((string) $akun['email']),
                    'role' => $role,
                    'nip' => filled($akun['nip']) ? (string) $akun['nip'] : null,
                    'jabatan' => filled($akun['jabatan']) ? (string) $akun['jabatan'] : null,
                    'is_active' => (bool) $akun['is_active'],
                    'updated_at' => $waktu,
                ];
                if (array_key_exists('terakhir_masuk', $data)) {
                    $baris['terakhir_masuk'] = $data['terakhir_masuk'];
                }

                if ($lama === null) {
                    $id = DB::table('users')->insertGetId($baris + ['password' => Hash::make(Str::random(40)), 'created_at' => $data['dibuat_pada'] ?? $waktu] + ($id !== null ? ['id' => $id] : []));
                } else {
                    DB::table('users')->where('id', $id)->update($baris);
                }

                DB::table('user_leading')->where('user_id', $id)->delete();
                if ($role === 'leading') {
                    DB::table('user_leading')->insert(array_map(fn (string $kode) => ['user_id' => $id, 'kode_leading' => $kode], array_values(array_unique(array_map('strval', (array) $akun['leadings'])))));
                }

                return (int) $id;
            });
        } catch (QueryException $e) {
            throw new RuntimeException('Akun '.($data['email'] ?? '').' gagal disimpan ke database — periksa surel (tidak boleh dipakai akun lain), leading, dan minimal satu admin aktif.', previous: $e);
        }
    }

    public function catatMasuk(int $id, ?string $alamatIp = null, ?string $agen = null): void
    {
        $akun = DB::table('users')->where('id', $id)->first(['id', 'name']);
        if ($akun === null) {
            return;
        }
        $waktu = now()->format('Y-m-d H:i:s.uP');
        DB::transaction(function () use ($akun, $waktu, $alamatIp, $agen) {
            DB::table('users')->where('id', $akun->id)->update(['terakhir_masuk' => $waktu]);
            DB::table('riwayat_masuk')->insert(['user_id' => $akun->id, 'pengguna' => $akun->name, 'waktu' => $waktu, 'alamat_ip' => $alamatIp, 'agen' => $agen === null ? null : mb_substr($agen, 0, 255)]);
        });
    }

    public function sandi(int $id): ?array
    {
        $u = DB::table('users')->where('id', $id)->first(['password', 'sandi_diganti_pada']);

        return $u === null || blank($u->password) ? null : ['hash' => (string) $u->password, 'diganti_pada' => $u->sandi_diganti_pada === null ? null : $this->iso($u->sandi_diganti_pada, mikro: true)];
    }

    public function gantiSandi(int $id, string $hash): void
    {
        try {
            $diubah = DB::table('users')->where('id', $id)->update(['password' => $hash, 'sandi_diganti_pada' => now()->format('Y-m-d H:i:s.uP'), 'remember_token' => null]);
        } catch (QueryException $e) {
            report($e);
            throw new RuntimeException('Sandi gagal disimpan — coba lagi.', previous: $e);
        }
        if ($diubah === 0) {
            throw new RuntimeException('Akun tidak ditemukan.');
        }
    }

    public function riwayatMasuk(int $id, int $batas = 20): array
    {
        return DB::table('riwayat_masuk')->where('user_id', $id)->orderByDesc('waktu')->orderByDesc('id')->limit($batas)->get()
            ->map(fn (object $r) => ['waktu' => $this->iso($r->waktu, mikro: true), 'alamat_ip' => $r->alamat_ip, 'agen' => $r->agen])->all();
    }

    /**
     * @param  Collection<int, object>  $tautan
     * @return array<string, mixed>
     */
    private function bentuk(object $u, $tautan): array
    {
        $role = $u->role === 'admin' ? 'admin' : 'leading';

        return [
            'id' => (int) $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $role,
            'nip' => $u->nip,
            'jabatan' => $u->jabatan,
            'leadings' => $role === 'admin' ? [] : StubPenggunaRepository::urutKode($tautan->pluck('kode_leading')->map(fn ($k) => (string) $k)->all()),
            'is_active' => (bool) $u->is_active,
            'terakhir_masuk' => $u->terakhir_masuk === null ? null : $this->iso($u->terakhir_masuk),
            'dibuat_pada' => $u->created_at === null ? null : $this->iso($u->created_at),
        ];
    }

    private function iso(string $waktu, bool $mikro = false): string
    {
        $t = CarbonImmutable::parse($waktu)->setTimezone(config('app.timezone'));

        return $mikro ? $t->format('Y-m-d\TH:i:s.uP') : $t->toIso8601String();
    }
}
