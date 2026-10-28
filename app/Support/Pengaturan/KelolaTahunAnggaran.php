<?php

namespace App\Support\Pengaturan;

use App\Contracts\DasarHukumRepository;
use App\Contracts\DataPaguRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\PejabatRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Kelola tahun anggaran di server (PRD F6, tabel tahun_anggaran; hanya admin): daftar beserta pemakaiannya, tambah tahun
 * baru (2000–2100, belum ada), kunci/buka kunci (tahun aktif tidak bisa dikunci), dan hapus tahun yang tidak aktif dan
 * tidak dipakai (`pemakaian()`: kegiatan, master kode, Data Pagu, pejabat yang sudah diisi, dasar hukum yang sudah
 * diatur). Mengganti tahun aktif tetap lewat KontrolTahunAktif::aktifkan. Setiap perubahan tercatat di riwayat pengaturan
 * (`tambah_tahun`, `kunci_tahun`, `hapus_tahun`). Galat → ValidationException berkunci `tahun`.
 */
final class KelolaTahunAnggaran
{
    public const RENTANG = [2000, 2100];

    /** Label pemakaian yang menghalangi penghapusan. */
    public const PEMAKAIAN = [
        'kegiatan' => 'kegiatan',
        'master_kode' => 'master kode',
        'data_pagu' => 'Data Pagu',
        'pejabat' => 'pejabat penanda tangan',
        'dasar_hukum' => 'dasar hukum',
    ];

    /**
     * Semua tahun (terbaru dulu) beserta pemakaiannya dan apakah boleh dihapus.
     *
     * @return list<array<string, mixed>>
     */
    public static function daftar(): array
    {
        $kegiatan = app(KegiatanRepository::class)->semua();

        return array_map(function (array $t) use ($kegiatan) {
            $pakai = self::pemakaian($t['tahun'], $kegiatan);

            return $t + ['pemakaian' => $pakai, 'boleh_dihapus' => ! $t['is_active'] && array_filter($pakai) === []];
        }, app(TahunAnggaranRepository::class)->semua());
    }

    /**
     * @param  list<array<string, mixed>>|null  $kegiatan  Bawaan: semua kegiatan.
     * @return array{kegiatan: int, master_kode: bool, data_pagu: int, pejabat: bool, dasar_hukum: bool}
     */
    public static function pemakaian(int $tahun, ?array $kegiatan = null): array
    {
        $pejabat = app(PejabatRepository::class)->untukTahun($tahun);

        return [
            'kegiatan' => count(array_filter($kegiatan ?? app(KegiatanRepository::class)->semua(), fn (array $k) => TahunKegiatan::dari($k) === $tahun)),
            'master_kode' => MasterKode::tersedia($tahun),
            'data_pagu' => count(app(DataPaguRepository::class)->semua($tahun)),
            'pejabat' => collect([$pejabat['kepala'], $pejabat['komite'], ...array_values($pejabat['leading'])])->contains(fn (array $p) => filled($p['nama'])),
            'dasar_hukum' => (bool) app(DasarHukumRepository::class)->untukTahun($tahun)['diatur'],
        ];
    }

    /** Galat isian tahun baru; null bila sah. */
    public static function galatTahun(mixed $tahun): ?string
    {
        [$min, $maks] = self::RENTANG;
        $angka = filter_var(is_string($tahun) ? trim($tahun) : $tahun, FILTER_VALIDATE_INT);

        return match (true) {
            $angka === false || $angka < $min || $angka > $maks => "Tahun berupa angka {$min}–{$maks}.",
            collect(app(TahunAnggaranRepository::class)->semua())->contains('tahun', $angka) => "Tahun Anggaran {$angka} sudah ada.",
            default => null,
        };
    }

    /** @param  array{role: string}  $pengguna */
    public static function tambah(mixed $tahun, array $pengguna): int
    {
        self::pastikanAdmin($pengguna);
        if ($galat = self::galatTahun($tahun)) {
            throw ValidationException::withMessages(['tahun' => $galat]);
        }

        $tahun = (int) $tahun;
        app(TahunAnggaranRepository::class)->tambah($tahun);
        RiwayatPengaturan::catat('tambah_tahun', "Tahun Anggaran {$tahun} ditambahkan.");

        return $tahun;
    }

    /** @param  array{role: string}  $pengguna */
    public static function kunci(int $tahun, bool $terkunci, array $pengguna): void
    {
        self::pastikanAdmin($pengguna);
        $data = self::temukan($tahun);
        if ($terkunci && $data['is_active']) {
            throw ValidationException::withMessages(['tahun' => "Tahun Anggaran {$tahun} sedang aktif — aktifkan tahun lain sebelum menguncinya."]);
        }
        if ($data['is_locked'] === $terkunci) {
            return;
        }

        app(TahunAnggaranRepository::class)->kunci($tahun, $terkunci);
        RiwayatPengaturan::catat('kunci_tahun', "Tahun Anggaran {$tahun} ".($terkunci ? 'dikunci.' : 'dibuka kuncinya.'));
    }

    /** @param  array{role: string}  $pengguna */
    public static function hapus(int $tahun, array $pengguna): void
    {
        self::pastikanAdmin($pengguna);
        if (self::temukan($tahun)['is_active']) {
            throw ValidationException::withMessages(['tahun' => "Tahun Anggaran {$tahun} sedang aktif dan tidak bisa dihapus."]);
        }
        $dipakai = array_keys(array_filter(self::pemakaian($tahun)));
        if ($dipakai !== []) {
            throw ValidationException::withMessages(['tahun' => "Tahun Anggaran {$tahun} masih dipakai (".implode(', ', array_map(fn (string $k) => self::PEMAKAIAN[$k], $dipakai)).') sehingga tidak bisa dihapus.']);
        }

        app(TahunAnggaranRepository::class)->hapus($tahun);
        RiwayatPengaturan::catat('hapus_tahun', "Tahun Anggaran {$tahun} dihapus.");
    }

    /** @return array<string, mixed> */
    private static function temukan(int $tahun): array
    {
        return collect(app(TahunAnggaranRepository::class)->semua())->firstWhere('tahun', $tahun)
            ?? throw ValidationException::withMessages(['tahun' => "Tahun Anggaran {$tahun} tidak ditemukan."]);
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanAdmin(array $pengguna): void
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Tahun anggaran diatur admin.');
        }
    }
}
