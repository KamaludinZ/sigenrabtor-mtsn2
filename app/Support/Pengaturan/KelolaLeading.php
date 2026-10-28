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
 * Kelola leading di server (PRD F30; ubah hanya admin) — dipakai tab Leading `Pengaturan\LeadingUser` dan endpoint
 * `pengaturan.leading.data`:
 * - `simpan($isian, $kodeLama, …)`: tambah/ubah (dirapikan & diperiksa AturanLeading, galat berkunci kolom; kode yang
 *   dipakai kegiatan/akun `kodeTerpakai()` tidak berganti; tanpa perubahan tidak disimpan) + riwayat
 *   `tambah_leading`/`ubah_leading`.
 * - `ubahStatus($kode, $aktif, …)`: aktif/nonaktif (minimal satu leading aktif) + riwayat `ubah_status_leading`;
 *   `dampakNonaktif()` = kegiatan semua tahun & akun aktif yang hanya tertaut ke leading itu.
 * - `tautkan($kode, $akunId, …)` / `lepas($kode, $akunId, …)`: lewat TautanAkunLeading (aturan tautan bersama).
 * Setiap aksi tulis menerima `$versi` (`sidik()` isi data leading & akun saat ditampilkan; waktu masuk tidak dihitung) —
 * berbeda → PengaturanBerubah, sehingga perubahan dari tab/admin lain tidak tertimpa.
 */
final class KelolaLeading
{
    /** Sidik isi data leading & akun (padanan updated_at terbaru di backend). */
    public static function sidik(): string
    {
        $akun = array_map(fn (array $a) => array_diff_key($a, ['terakhir_masuk' => true]), app(PenggunaRepository::class)->semua());

        return sha1((string) json_encode([app(LeadingRepository::class)->semua(), $akun]));
    }

    /**
     * @param  array<string, mixed>  $isian  {kode, nama_unit, jabatan_penanda_tangan, urutan}
     * @param  array{role: string}  $pengguna
     * @return array{leading: array<string, mixed>, ringkasan: string|null} ringkasan null pada ubah = tidak ada yang berubah (tidak disimpan).
     *
     * @throws ValidationException Berkunci kolom isian.
     * @throws AuthorizationException
     * @throws PengaturanBerubah
     */
    public static function simpan(array $isian, ?string $kodeLama, array $pengguna, ?string $versi = null): array
    {
        self::pastikanBoleh($pengguna, $versi);
        $semua = app(LeadingRepository::class)->semua();
        $lama = $kodeLama === null ? null : self::cari($semua, $kodeLama);
        if ($kodeLama !== null && $lama === null) {
            throw ValidationException::withMessages(['kode' => "Leading {$kodeLama} tidak ditemukan."]);
        }

        $isian = array_map(fn (mixed $v) => AturanLeading::rapikan((string) $v), array_merge(['kode' => '', 'nama_unit' => '', 'jabatan_penanda_tangan' => '', 'urutan' => ''], array_intersect_key($isian, array_flip(['kode', 'nama_unit', 'jabatan_penanda_tangan', 'urutan']))));
        if ($kodeLama !== null && self::kodeTerpakai($kodeLama) > 0) {
            $isian['kode'] = $kodeLama;
        }
        $galat = array_filter(AturanLeading::galat($isian, $semua, $kodeLama));
        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        $baru = [
            'kode' => AturanLeading::normalkanKode($isian['kode']),
            'nama_unit' => $isian['nama_unit'],
            'jabatan_penanda_tangan' => $isian['jabatan_penanda_tangan'],
        ] + ($isian['urutan'] === '' ? [] : ['urutan' => (int) $isian['urutan']]);

        $ringkasan = null;
        if ($lama !== null) {
            $ringkasan = RiwayatPengaturan::perubahan(
                ['urutan' => (string) $lama['urutan']] + $lama,
                ['urutan' => (string) ($baru['urutan'] ?? $lama['urutan'])] + $baru,
                ['kode' => 'Kode', 'nama_unit' => 'Nama unit', 'jabatan_penanda_tangan' => 'Jabatan', 'urutan' => 'Urutan'],
            );
            if ($ringkasan === null) {
                return ['leading' => $lama, 'ringkasan' => null];
            }
        }

        app(LeadingRepository::class)->simpan($baru, $kodeLama);
        RiwayatPengaturan::catat($lama === null ? 'tambah_leading' : 'ubah_leading', $lama === null
            ? "Leading {$baru['kode']} {$baru['nama_unit']} — jabatan {$baru['jabatan_penanda_tangan']}"
            : "Leading {$kodeLama} — {$ringkasan}");

        return ['leading' => self::cari(app(LeadingRepository::class)->semua(), $baru['kode']), 'ringkasan' => $ringkasan ?? 'Leading baru'];
    }

    /**
     * @param  array{role: string}  $pengguna
     * @return array<string, mixed> Leading sesudah diubah.
     *
     * @throws ValidationException Berkunci `kode` (tidak ditemukan) atau `status` (minimal satu aktif).
     */
    public static function ubahStatus(string $kode, bool $aktif, array $pengguna, ?string $versi = null): array
    {
        self::pastikanBoleh($pengguna, $versi);
        $semua = app(LeadingRepository::class)->semua();
        $leading = self::cari($semua, $kode) ?? throw ValidationException::withMessages(['kode' => "Leading {$kode} tidak ditemukan."]);
        if ($leading['is_active'] === $aktif) {
            return $leading;
        }
        if (! $aktif && count(array_filter($semua, fn (array $l) => $l['is_active'])) === 1) {
            throw ValidationException::withMessages(['status' => 'Minimal satu leading harus tetap aktif.']);
        }

        app(LeadingRepository::class)->simpan(['is_active' => $aktif] + $leading, $kode);
        RiwayatPengaturan::catat('ubah_status_leading', "Leading {$kode} {$leading['nama_unit']}".($aktif ? ' diaktifkan kembali' : ' dinonaktifkan'));

        return ['is_active' => $aktif] + $leading;
    }

    /**
     * Tautkan akun berperan leading ke leading aktif $kode (TautanAkunLeading::tautkan).
     *
     * @param  array{role: string}  $pengguna
     * @return string Keterangan riwayat.
     *
     * @throws ValidationException Berkunci `akun`.
     */
    public static function tautkan(string $kode, int $akunId, array $pengguna, ?string $versi = null): string
    {
        return TautanAkunLeading::tautkan($kode, $akunId, $pengguna, $versi);
    }

    /**
     * Lepas tautan akun $akunId dari leading $kode (TautanAkunLeading::lepas).
     *
     * @param  array{role: string}  $pengguna
     * @return array{keterangan: string, tanpa_tautan: bool} tanpa_tautan = akun aktif kini tidak tertaut ke leading mana pun.
     *
     * @throws ValidationException Berkunci `akun`.
     */
    public static function lepas(string $kode, int $akunId, array $pengguna, ?string $versi = null): array
    {
        return TautanAkunLeading::lepas($kode, $akunId, $pengguna, $versi);
    }

    /** Banyaknya kegiatan (semua tahun) & akun yang memakai kode leading — kode yang terpakai tidak bisa diganti. */
    public static function kodeTerpakai(string $kode): int
    {
        $kegiatan = count(array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => ($k['leading'] ?? null) === $kode));

        return $kegiatan + count(array_filter(app(PenggunaRepository::class)->semua(), fn (array $a) => in_array($kode, $a['leadings'], true)));
    }

    /**
     * Dampak menonaktifkan leading: kegiatan semua tahun yang memakainya dan akun aktif yang hanya tertaut ke leading ini.
     *
     * @return array{kegiatan: int, akun: list<string>}
     */
    public static function dampakNonaktif(string $kode): array
    {
        $kegiatan = count(array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => ($k['leading'] ?? null) === $kode));
        $akun = array_values(array_column(array_filter(app(PenggunaRepository::class)->semua(), fn (array $a) => $a['is_active'] && $a['leadings'] === [$kode]), 'name'));

        return ['kegiatan' => $kegiatan, 'akun' => $akun];
    }

    /**
     * Data leading untuk endpoint: tiap leading beserta akun tertaut, pejabat penanda tangan tahun aktif, jumlah kegiatan
     * tahun aktif, pemakaian kode, dan sidik versi.
     *
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $kegiatan = array_count_values(array_map('strval', array_filter(array_map(fn (array $k) => TahunKegiatan::dari($k) === $tahun ? ($k['leading'] ?? null) : null, app(KegiatanRepository::class)->semua()))));
        $akun = app(PenggunaRepository::class)->semua();
        $pejabat = PejabatBerlaku::tahun($tahun);

        return [
            'tahun' => $tahun,
            'versi' => self::sidik(),
            'leading' => array_map(fn (array $l) => $l + [
                'akun' => array_values(array_map(fn (array $a) => ['id' => $a['id'], 'name' => $a['name'], 'is_active' => $a['is_active']], array_filter($akun, fn (array $a) => in_array($l['kode'], $a['leadings'], true)))),
                'pejabat' => $pejabat->pejabatLeading($l['kode']),
                'kegiatan_tahun_aktif' => $kegiatan[$l['kode']] ?? 0,
                'kode_terpakai' => self::kodeTerpakai($l['kode']) > 0,
            ], app(LeadingRepository::class)->semua()),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $semua
     * @return array<string, mixed>|null
     */
    private static function cari(array $semua, string $kode): ?array
    {
        return collect($semua)->firstWhere('kode', '===', $kode);
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanBoleh(array $pengguna, ?string $versi): void
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Leading & akun diatur admin.');
        }
        if ($versi !== null && $versi !== self::sidik()) {
            throw new PengaturanBerubah('Data leading & akun baru saja diubah di tempat lain.');
        }
    }
}
