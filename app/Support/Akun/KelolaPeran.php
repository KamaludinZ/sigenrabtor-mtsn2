<?php

namespace App\Support\Akun;

use App\Contracts\PenggunaRepository;
use App\Support\Pengaturan\KelolaAkun;
use App\Support\Pengaturan\KelolaLeading;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Manajemen peran akun oleh admin (PRD §8 "Peran Pengguna", §15) — satu-satunya jalur endpoint `pengaturan.peran` &
 * `pengaturan.akun.peran` (hak `kelola_akun`):
 * - `daftar($pengguna)` = tiap peran PeranAkun::DAFTAR {label, keterangan, perlu_leading, hak (HakAkses::perPeran + label),
 *   jumlah akun aktif/nonaktif, akun} + `versi` (KelolaLeading::sidik);
 * - `ubah($akunId, $peran, $leadings?, $pengguna, $versi?)` = peran baru lewat KelolaAkun::simpan (aturan AturanAkun: peran
 *   akun sendiri terkunci, minimal satu admin aktif, akun leading wajib ≥ 1 leading aktif — `$leadings` null = tautan lama;
 *   admin tanpa tautan; riwayat `ubah_akun` "Peran: … → …"; versi usang → PengaturanBerubah); peran tak dikenal → galat
 *   `role`; akun tidak ada → RuntimeException `PESAN_TIDAK_ADA`.
 */
final class KelolaPeran
{
    public const PESAN_TIDAK_ADA = 'Akun tidak ditemukan.';

    /**
     * @param  array{role: string}  $pengguna
     * @return array{peran: list<array<string, mixed>>, versi: string}
     *
     * @throws AuthorizationException
     */
    public static function daftar(array $pengguna): array
    {
        HakAkses::pastikan($pengguna, 'kelola_akun');
        $akun = app(PenggunaRepository::class)->semua();
        $hak = HakAkses::perPeran();

        return [
            'peran' => array_map(function (string $kode, array $p) use ($akun, $hak) {
                $anggota = array_values(array_filter($akun, fn (array $a) => $a['role'] === $kode));

                return [
                    'kode' => $kode,
                    'label' => $p['label'],
                    'keterangan' => $p['keterangan'],
                    'perlu_leading' => $p['perlu_leading'],
                    'hak' => array_map(fn (string $h) => ['kunci' => $h, 'label' => HakAkses::HAK[$h][0]], $hak[$kode]),
                    'jumlah_aktif' => count(array_filter($anggota, fn (array $a) => $a['is_active'])),
                    'jumlah_nonaktif' => count(array_filter($anggota, fn (array $a) => ! $a['is_active'])),
                    'akun' => array_map(fn (array $a) => ['id' => $a['id'], 'nama' => $a['name'], 'surel' => $a['email'], 'aktif' => $a['is_active'], 'leading' => $a['leadings']], $anggota),
                ];
            }, array_keys(PeranAkun::DAFTAR), PeranAkun::DAFTAR),
            'versi' => KelolaLeading::sidik(),
        ];
    }

    /**
     * @param  list<string>|null  $leadings
     * @param  array{id?: int|null, role: string}  $pengguna
     * @return array{akun: array<string, mixed>, ringkasan: string|null, versi: string, pesan: string}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws PengaturanBerubah
     * @throws RuntimeException
     */
    public static function ubah(int $akunId, mixed $peran, ?array $leadings, array $pengguna, ?string $versi = null): array
    {
        HakAkses::pastikan($pengguna, 'kelola_akun');
        $akun = app(PenggunaRepository::class)->temukan($akunId) ?? throw new RuntimeException(self::PESAN_TIDAK_ADA);
        if (! is_string($peran) || ! isset(PeranAkun::DAFTAR[$peran])) {
            throw ValidationException::withMessages(['role' => 'Pilih peran '.implode(' atau ', PeranAkun::LABEL).'.']);
        }
        $hasil = KelolaAkun::simpan([
            'name' => $akun['name'], 'email' => $akun['email'], 'role' => $peran, 'jabatan' => (string) ($akun['jabatan'] ?? ''),
            'nip' => (string) ($akun['nip'] ?? ''), 'leadings' => $peran === 'admin' ? [] : ($leadings ?? $akun['leadings']),
        ], $akunId, $pengguna, $versi);
        $label = PeranAkun::LABEL[$peran];

        return [
            'akun' => $hasil['akun'],
            'ringkasan' => $hasil['ringkasan'],
            'versi' => KelolaLeading::sidik(),
            'pesan' => $hasil['ringkasan'] === null ? "Peran {$akun['name']} tidak berubah." : "Akun {$akun['name']} kini berperan {$label}.",
        ];
    }
}
