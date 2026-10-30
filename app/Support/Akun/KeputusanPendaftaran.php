<?php

namespace App\Support\Akun;

use App\Contracts\PenggunaRepository;
use App\Contracts\PermohonanAkunRepository;
use App\Support\Pengaturan\KelolaAkun;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Pengaturan\RiwayatPengaturan;
use App\Support\Teks;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Keputusan admin atas permohonan pendaftaran akun (PRD §8 "akun dibuat/diaktifkan admin") — satu-satunya jalur
 * menyetujui/menolak (khusus admin → AuthorizationException; permohonan tidak ada → RuntimeException `PESAN_TIDAK_ADA`;
 * sudah diputuskan → PengaturanBerubah):
 * - `daftar($pengguna)` = permohonan menunggu (terlama dulu) + yang sudah diputuskan (terbaru dulu, maks. `BATAS_RIWAYAT`)
 *   dalam bentuk `PendaftaranAkun::tampil`, beserta jumlah menunggu;
 * - `setujui($id, $pengguna, $leadings?)` = akun leading dibuat lewat `KelolaAkun::simpan` (aturan AturanAkun: surel unik,
 *   leading aktif — `$leadings` menggantikan leading yang diminta; galat berkunci `name|email|leadings|…`), sandi akun =
 *   hash sandi permohonan (`PenggunaRepository::gantiSandi`, tidak pernah sandi asli), permohonan `disetujui` + `akun_id`,
 *   riwayat `setujui_permohonan_akun`;
 * - `tolak($id, $alasan, $pengguna)` = alasan wajib ≤ `MAKS_ALASAN` (galat `alasan`), riwayat `tolak_permohonan_akun`.
 */
final class KeputusanPendaftaran
{
    public const MAKS_ALASAN = 300;

    public const BATAS_RIWAYAT = 20;

    public const PESAN_TIDAK_ADA = 'Permohonan akun tidak ditemukan.';

    /**
     * @param  array{role: string}  $pengguna
     * @return array{menunggu: list<array<string, mixed>>, diputuskan: list<array<string, mixed>>, jumlah_menunggu: int}
     *
     * @throws AuthorizationException
     */
    public static function daftar(array $pengguna): array
    {
        self::pastikanAdmin($pengguna);
        $semua = array_map(PendaftaranAkun::tampil(...), app(PermohonanAkunRepository::class)->semua());
        $menunggu = array_values(array_filter($semua, fn (array $p) => $p['status'] === 'menunggu'));
        usort($menunggu, fn (array $a, array $b) => strcmp((string) $a['diajukan_pada'], (string) $b['diajukan_pada']));
        $diputuskan = array_values(array_filter($semua, fn (array $p) => $p['status'] !== 'menunggu'));
        usort($diputuskan, fn (array $a, array $b) => strcmp((string) $b['diputuskan_pada'], (string) $a['diputuskan_pada']));

        return ['menunggu' => $menunggu, 'diputuskan' => array_slice($diputuskan, 0, self::BATAS_RIWAYAT), 'jumlah_menunggu' => count($menunggu)];
    }

    /**
     * @param  array{id?: int|null, name: string, role: string}  $pengguna
     * @param  list<string>|null  $leadings
     * @return array{permohonan: array<string, mixed>, akun: array<string, mixed>|null, pesan: string}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws PengaturanBerubah
     * @throws RuntimeException
     */
    public static function setujui(string $id, array $pengguna, ?array $leadings = null): array
    {
        $p = self::menunggu($id, $pengguna);
        $hasil = KelolaAkun::simpan([
            'name' => $p['nama'], 'email' => $p['surel'], 'role' => 'leading', 'jabatan' => (string) ($p['jabatan'] ?? ''),
            'nip' => (string) ($p['nip'] ?? ''), 'leadings' => $leadings ?? $p['leadings'],
        ], null, $pengguna);
        $akun = app(PenggunaRepository::class);
        if (filled($p['sandi_hash'] ?? null)) {
            $akun->gantiSandi((int) $hasil['id'], (string) $p['sandi_hash']);
        }
        $putus = app(PermohonanAkunRepository::class)->putuskan($id, 'disetujui', isset($pengguna['id']) ? (int) $pengguna['id'] : null, null, (int) $hasil['id'])
            ?? throw new PengaturanBerubah('Permohonan ini baru saja diputuskan di tempat lain — muat ulang.');
        $leadingAkun = $akun->temukan((int) $hasil['id'])['leadings'] ?? [];
        RiwayatPengaturan::catat('setujui_permohonan_akun', "{$p['nama']} ({$p['surel']}) — akun leading dibuat".($leadingAkun ? ', leading '.implode(', ', $leadingAkun) : ''));

        return [
            'permohonan' => PendaftaranAkun::tampil($putus),
            'akun' => $akun->temukan((int) $hasil['id']),
            'pesan' => "Permohonan {$p['nama']} disetujui — akun leading sudah aktif dan bisa dipakai masuk dengan surel {$p['surel']}.",
        ];
    }

    /**
     * @param  array{id?: int|null, name: string, role: string}  $pengguna
     * @return array{permohonan: array<string, mixed>, pesan: string}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws PengaturanBerubah
     * @throws RuntimeException
     */
    public static function tolak(string $id, mixed $alasan, array $pengguna): array
    {
        $p = self::menunggu($id, $pengguna);
        $alasan = Teks::rapikan(is_string($alasan) ? $alasan : '');
        if ($alasan === '' || mb_strlen($alasan) > self::MAKS_ALASAN) {
            throw ValidationException::withMessages(['alasan' => $alasan === '' ? 'Tulis alasan penolakan agar pemohon tahu yang perlu diperbaiki.' : 'Alasan paling banyak '.self::MAKS_ALASAN.' karakter.']);
        }
        $putus = app(PermohonanAkunRepository::class)->putuskan($id, 'ditolak', isset($pengguna['id']) ? (int) $pengguna['id'] : null, $alasan)
            ?? throw new PengaturanBerubah('Permohonan ini baru saja diputuskan di tempat lain — muat ulang.');
        RiwayatPengaturan::catat('tolak_permohonan_akun', "{$p['nama']} ({$p['surel']}) — {$alasan}");

        return ['permohonan' => PendaftaranAkun::tampil($putus), 'pesan' => "Permohonan {$p['nama']} ditolak."];
    }

    /**
     * @param  array{role: string}  $pengguna
     * @return array<string, mixed>
     */
    private static function menunggu(string $id, array $pengguna): array
    {
        self::pastikanAdmin($pengguna);
        $p = app(PermohonanAkunRepository::class)->temukan($id) ?? throw new RuntimeException(self::PESAN_TIDAK_ADA);
        if ($p['status'] !== 'menunggu') {
            throw new PengaturanBerubah('Permohonan ini sudah '.mb_strtolower(PendaftaranAkun::STATUS[$p['status']] ?? $p['status']).'.');
        }

        return $p;
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanAdmin(array $pengguna): void
    {
        if (($pengguna['role'] ?? null) !== 'admin') {
            throw new AuthorizationException('Hanya admin yang bisa memeriksa permohonan akun.');
        }
    }
}
