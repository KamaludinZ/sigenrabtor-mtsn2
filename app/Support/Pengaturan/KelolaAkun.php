<?php

namespace App\Support\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\LeadingRepository;
use App\Contracts\PenggunaRepository;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Kelola akun di server (PRD F29; ubah hanya admin) — dipakai tab Akun `Pengaturan\LeadingUser` dan endpoint
 * `pengaturan.akun.data`:
 * - `simpan($isian, $id, $pengguna, $versi)`: tambah/ubah — dirapikan (nama & jabatan satu baris, surel huruf kecil, NIP
 *   tanpa pemisah, tautan leading urut daftar leading tanpa dobel; admin tanpa tautan), diperiksa AturanAkun (galat
 *   berkunci kolom; peran akun sendiri terkunci, minimal satu admin aktif), tanpa perubahan tidak disimpan; riwayat
 *   `tambah_akun`/`ubah_akun`.
 * - `ubahStatus($id, $aktif, …)`: nonaktif ditolak untuk akun sendiri & admin aktif terakhir (`alasanTolakNonaktif()`,
 *   galat `status`); mengaktifkan akun leading tanpa leading aktif ditandai `tanpa_leading_aktif`; riwayat
 *   `ubah_status_akun`.
 * Aksi tulis menerima `$versi` (KelolaLeading::sidik — data leading & akun) → PengaturanBerubah bila usang.
 */
final class KelolaAkun
{
    /** Peran → label (sama dengan LeadingUser::PERAN). */
    public const LABEL_PERAN = ['admin' => 'Admin', 'leading' => 'Leading'];

    /**
     * @param  array<string, mixed>  $isian  {name, email, role, jabatan, nip, leadings}
     * @param  array{id: int, role: string}  $pengguna
     * @return array{id: int, akun: array<string, mixed>, ringkasan: string|null} ringkasan null pada ubah = tidak disimpan.
     *
     * @throws ValidationException Berkunci kolom isian (atau `id`).
     * @throws AuthorizationException
     * @throws PengaturanBerubah
     */
    public static function simpan(array $isian, ?int $id, array $pengguna, ?string $versi = null): array
    {
        self::pastikanBoleh($pengguna, $versi);
        $repo = app(PenggunaRepository::class);
        $semua = $repo->semua();
        $lama = $id === null ? null : collect($semua)->firstWhere('id', $id);
        if ($id !== null && $lama === null) {
            throw ValidationException::withMessages(['id' => "Akun {$id} tidak ditemukan."]);
        }

        $isian = self::rapikan($isian);
        $leading = app(LeadingRepository::class)->semua();
        $galat = AturanAkun::galat($isian, $semua, array_column(array_filter($leading, fn (array $l) => $l['is_active']), 'kode'), $id, (int) ($pengguna['id'] ?? 0));
        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        $baru = ['name' => $isian['name'], 'email' => $isian['email'], 'role' => $isian['role'], 'jabatan' => $isian['jabatan'] ?: null, 'nip' => $isian['nip'] ?: null, 'leadings' => $isian['role'] === 'admin' ? [] : $isian['leadings']];
        $ringkasan = null;
        if ($lama !== null) {
            $tampil = fn (array $a) => ['role' => self::LABEL_PERAN[$a['role']], 'nip' => $a['nip'] ? Nip::format($a['nip']) : null] + $a;
            $ringkasan = RiwayatPengaturan::perubahan($tampil($lama), $tampil($baru), ['name' => 'Nama', 'email' => 'Surel', 'role' => 'Peran', 'jabatan' => 'Jabatan', 'nip' => 'NIP', 'leadings' => 'Leading']);
            if ($ringkasan === null) {
                return ['id' => $id, 'akun' => $lama, 'ringkasan' => null];
            }
        }

        $idBaru = $repo->simpan($baru, $id);
        RiwayatPengaturan::catat($lama === null ? 'tambah_akun' : 'ubah_akun', $lama === null
            ? "Akun {$baru['name']} ({$baru['email']}) — ".self::LABEL_PERAN[$baru['role']].($baru['leadings'] ? ' '.implode(', ', $baru['leadings']) : '')
            : "Akun {$lama['name']} — {$ringkasan}");

        return ['id' => $idBaru, 'akun' => $repo->temukan($idBaru), 'ringkasan' => $ringkasan ?? 'Akun baru'];
    }

    /**
     * Isian akun dirapikan seperti tersimpan.
     *
     * @param  array<string, mixed>  $isian
     * @return array{name: string, email: string, role: string, jabatan: string, nip: string, leadings: list<string>}
     */
    public static function rapikan(array $isian): array
    {
        $urutan = array_column(app(LeadingRepository::class)->semua(), 'kode');

        return [
            'name' => AturanAkun::rapikan((string) ($isian['name'] ?? '')),
            'email' => mb_strtolower(AturanAkun::rapikan((string) ($isian['email'] ?? ''))),
            'role' => (string) ($isian['role'] ?? 'leading'),
            'jabatan' => AturanAkun::rapikan((string) ($isian['jabatan'] ?? '')),
            'nip' => Nip::bersihkan((string) ($isian['nip'] ?? '')),
            // Urut seperti daftar leading, tanpa dobel.
            'leadings' => array_values(array_intersect($urutan, array_map('strval', (array) ($isian['leadings'] ?? [])))),
        ];
    }

    /**
     * Alasan akun $akun tidak boleh dinonaktifkan, atau null.
     *
     * @param  array<string, mixed>  $akun
     * @param  list<array<string, mixed>>  $semua
     */
    public static function alasanTolakNonaktif(array $akun, array $semua, int $idSaya): ?string
    {
        return match (true) {
            $akun['id'] === $idSaya => 'Akun Anda sendiri tidak bisa dinonaktifkan.',
            $akun['role'] === 'admin' && $akun['is_active'] && count(array_filter($semua, fn (array $a) => $a['role'] === 'admin' && $a['is_active'])) === 1 => 'Minimal satu admin aktif harus tetap ada.',
            default => null,
        };
    }

    /**
     * @param  array{id: int, role: string}  $pengguna
     * @return array{akun: array<string, mixed>, tanpa_leading_aktif: bool}
     *
     * @throws ValidationException Berkunci `id` atau `status`.
     */
    public static function ubahStatus(int $id, bool $aktif, array $pengguna, ?string $versi = null): array
    {
        self::pastikanBoleh($pengguna, $versi);
        $repo = app(PenggunaRepository::class);
        $semua = $repo->semua();
        $akun = collect($semua)->firstWhere('id', $id) ?? throw ValidationException::withMessages(['id' => "Akun {$id} tidak ditemukan."]);
        if (! $aktif && ($alasan = self::alasanTolakNonaktif($akun, $semua, (int) ($pengguna['id'] ?? 0))) !== null) {
            throw ValidationException::withMessages(['status' => $alasan]);
        }

        if ($akun['is_active'] !== $aktif) {
            $repo->simpan(['is_active' => $aktif], $id);
            RiwayatPengaturan::catat('ubah_status_akun', "Akun {$akun['name']}".($aktif ? ' diaktifkan kembali' : ' dinonaktifkan'));
        }
        $leadingAktif = array_column(array_filter(app(LeadingRepository::class)->semua(), fn (array $l) => $l['is_active']), 'kode');

        return [
            'akun' => ['is_active' => $aktif] + $akun,
            'tanpa_leading_aktif' => $aktif && $akun['role'] === 'leading' && array_intersect($akun['leadings'], $leadingAktif) === [],
        ];
    }

    /** Jumlah kegiatan (semua tahun) yang dibuat akun $id — tetap tercatat atas namanya bila akun dinonaktifkan. */
    public static function kegiatanBuatan(int $id): int
    {
        return count(array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => (int) ($k['user_id'] ?? 0) === $id));
    }

    /**
     * Data akun untuk endpoint: tiap akun beserta kegiatan buatannya (semua tahun & tahun aktif) dan lima riwayat masuk
     * terakhir, ringkasan per peran & status, dan versi data.
     *
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $kegiatan = app(KegiatanRepository::class)->semua();
        $repo = app(PenggunaRepository::class);
        $akun = array_map(fn (array $a) => $a + [
            'kegiatan_buatan' => count(array_filter($kegiatan, fn (array $k) => (int) ($k['user_id'] ?? 0) === $a['id'])),
            'kegiatan_tahun_aktif' => count(array_filter($kegiatan, fn (array $k) => (int) ($k['user_id'] ?? 0) === $a['id'] && TahunKegiatan::dari($k) === $tahun)),
            'riwayat_masuk' => $repo->riwayatMasuk($a['id'], 5),
        ], $repo->semua());
        $aktif = array_filter($akun, fn (array $a) => $a['is_active']);

        return [
            'tahun' => $tahun,
            'versi' => KelolaLeading::sidik(),
            'ringkasan' => ['aktif' => count($aktif), 'nonaktif' => count($akun) - count($aktif), 'per_peran' => array_merge(['admin' => 0, 'leading' => 0], array_count_values(array_column($aktif, 'role')))],
            'akun' => $akun,
        ];
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanBoleh(array $pengguna, ?string $versi): void
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Akun diatur admin.');
        }
        if ($versi !== null && $versi !== KelolaLeading::sidik()) {
            throw new PengaturanBerubah('Data leading & akun baru saja diubah di tempat lain.');
        }
    }
}
