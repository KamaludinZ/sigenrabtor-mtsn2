<?php

namespace App\Support\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Support\Kegiatan\BatasAi;
use App\Support\Stub\StubPengaturan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Batas kuota permintaan AI harian di server (PRD §21 "Batas Pemakaian"; khusus admin) — dipakai tab Pemakaian halaman
 * Pengaturan AI (`simpanBatas`, `simpanKhusus`, `hapusKhusus`) dan endpoint admin `pengaturan.ai.batas*`:
 * - `data()`: batas umum, rentang & preset (BatasAi), pemakaian hari ini per akun aktif (terbanyak dulu: terpakai, batas
 *   berlaku, khusus, sisa), dan perkiraan kapasitas harian (jumlah batas berlaku akun aktif);
 * - `simpanUmum($batas)`: BatasAi::galat (bulat BatasAi::RENTANG);
 * - `simpanKhusus($akun, $batas)`: batas per akun aktif; sama dengan batas umum atau null = kembali ke batas umum (boleh
 *   untuk akun nonaktif); akun tidak ada → RuntimeException (404);
 * - versi & penulisan lewat KelolaPenyediaAi::pastikanVersi()/tulis(), riwayat `ubah_batas_ai` hanya bila berubah.
 * Galat → ValidationException berkunci `batas`/`akun`.
 */
final class KelolaBatasAi
{
    /** @return array<string, mixed> */
    public static function data(): array
    {
        $ai = app(PengaturanAiRepository::class)->ambil();
        $pemakaian = array_map(fn (array $p) => [
            'id' => (int) $p['id'], 'nama' => $p['name'], 'peran' => $p['role'],
            'terpakai' => BatasAi::terpakai($p), 'batas' => BatasAi::batas($p), 'khusus' => BatasAi::khusus($p), 'sisa' => BatasAi::sisa($p),
        ], array_values(array_filter(StubPengaturan::pengguna(), fn (array $p) => $p['is_active'])));
        usort($pemakaian, fn (array $a, array $b) => [$b['terpakai'], $a['nama']] <=> [$a['terpakai'], $b['nama']]);

        return [
            'versi' => $ai['diperbarui_pada'],
            'batas_umum' => BatasAi::batas(),
            'rentang' => BatasAi::RENTANG,
            'preset' => BatasAi::PRESET,
            'kapasitas' => array_sum(array_column($pemakaian, 'batas')),
            'akun' => $pemakaian,
        ];
    }

    /**
     * @param  array{role: string}  $pengguna
     * @return array{pesan: string, berubah: bool, batas: int}
     *
     * @throws AuthorizationException
     * @throws ValidationException Berkunci `batas`.
     * @throws PengaturanBerubah
     * @throws RuntimeException
     */
    public static function simpanUmum(mixed $batas, array $pengguna, ?string $versi, bool $periksaVersi = true): array
    {
        self::pastikanAdmin($pengguna);
        if ($galat = BatasAi::galat($batas)) {
            throw ValidationException::withMessages(['batas' => $galat]);
        }

        $batas = (int) $batas;
        $lama = app(PengaturanAiRepository::class)->ambil();
        if ($periksaVersi) {
            KelolaPenyediaAi::pastikanVersi($lama, $versi);
        }
        $ringkas = fn (array $p) => ['batas' => $p['batas_per_user_per_hari'].' per akun per hari'];
        $berubah = KelolaPenyediaAi::tulis($lama, ['batas_per_user_per_hari' => $batas] + $lama, $ringkas, ['batas' => 'Batas umum'], '', null, 'ubah_batas_ai');

        return ['pesan' => "Batas umum tersimpan: {$batas} permintaan AI per akun per hari.", 'berubah' => $berubah, 'batas' => $batas];
    }

    /**
     * @param  mixed  $batas  null/'' = kembali ke batas umum.
     * @param  array{role: string}  $pengguna
     * @return array{pesan: string, berubah: bool, batas: int|null}
     *
     * @throws AuthorizationException
     * @throws ValidationException Berkunci `batas`/`akun`.
     * @throws PengaturanBerubah
     * @throws RuntimeException Akun tidak ada atau gagal simpan.
     */
    public static function simpanKhusus(int $akun, mixed $batas, array $pengguna, ?string $versi, bool $periksaVersi = true): array
    {
        self::pastikanAdmin($pengguna);
        $data = collect(StubPengaturan::pengguna())->firstWhere('id', $akun) ?? throw new RuntimeException('Akun tidak ditemukan.');
        if ($batas === null || $batas === '') {
            $batas = null;
        } else {
            if ($galat = BatasAi::galat($batas)) {
                throw ValidationException::withMessages(['batas' => $galat]);
            }
            if (! $data['is_active']) {
                throw ValidationException::withMessages(['akun' => "{$data['name']} nonaktif — aktifkan akunnya dulu untuk memberi batas khusus."]);
            }
            $batas = (int) $batas === BatasAi::batas() ? null : (int) $batas;
        }

        $lama = app(PengaturanAiRepository::class)->ambil();
        if ($periksaVersi) {
            KelolaPenyediaAi::pastikanVersi($lama, $versi);
        }
        $baru = $lama;
        $baru['batas_khusus'] = array_filter(array_replace((array) ($lama['batas_khusus'] ?? []), [(string) $akun => $batas]), fn ($n) => $n !== null);
        $ringkas = fn (array $p) => ['khusus' => isset($p['batas_khusus'][(string) $akun]) ? $p['batas_khusus'][(string) $akun].' per hari' : 'batas umum'];
        $berubah = KelolaPenyediaAi::tulis($lama, $baru, $ringkas, ['khusus' => 'Batas'], $data['name'].' — ', null, 'ubah_batas_ai');

        return ['pesan' => $batas === null ? "{$data['name']} kembali memakai batas umum." : "Batas khusus {$data['name']}: {$batas} permintaan per hari.", 'berubah' => $berubah, 'batas' => $batas];
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanAdmin(array $pengguna): void
    {
        if (($pengguna['role'] ?? null) !== 'admin') {
            throw new AuthorizationException('Hanya admin yang bisa mengubah Pengaturan AI.');
        }
    }
}
