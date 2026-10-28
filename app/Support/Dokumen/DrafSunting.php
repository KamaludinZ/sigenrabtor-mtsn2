<?php

namespace App\Support\Dokumen;

use App\Contracts\DrafSuntingRepository;
use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Draf sunting teks di pratinjau (PRD F5 "Edit Teks Langsung", N5 ketahanan data) lewat DrafSuntingRepository: ketikan
 * penyunting satu bidang SuntingDokumen::BIDANG disimpan per akun di server sebelum diterapkan ke data kegiatan, sehingga
 * tidak hilang saat keluar, koneksi terputus, atau berganti perangkat. Dipakai endpoint `kegiatan.sunting` (GET `data()`),
 * `kegiatan.sunting.simpan` (PUT `simpan()`), `kegiatan.sunting.hapus` (DELETE `hapus()`), `kegiatan.sunting.terapkan`
 * (POST `terapkan()`):
 * - menyimpan hanya selama kegiatan bisa diubah (UbahDataKegiatan::alasanTerkunci → AuthorizationException), ketikan ≤
 *   SuntingDokumen::maksDraf (galat `teks`); teks yang sama dengan tersimpan membuang draf (`sama`); `teks_awal` = teks
 *   tersimpan saat draf mulai ditulis → keadaan `data_berubah` bila data berubah sesudahnya;
 * - versi draf = `diperbarui_pada` (`versi()`): simpan/hapus/terapkan dengan versi usang (diubah di tab lain) →
 *   PengaturanBerubah; membuang draf tetap boleh walau kegiatan terkunci;
 * - menerapkan = SuntingDokumen::simpan dengan teks draf (aturan isian, kunci, versi data, RiwayatSunting) lalu draf
 *   dibuang; gagal → draf tetap.
 */
final class DrafSunting
{
    /** Keadaan draf → keterangan. */
    public const KEADAAN = [
        'belum_diterapkan' => 'Draf belum diterapkan ke data kegiatan.',
        'data_berubah' => 'Teks ini berubah di tempat lain sesudah draf mulai ditulis — periksa sebelum menerapkan.',
    ];

    public const PESAN_BENTROK = 'Draf ini baru saja diubah di tab atau perangkat lain. Muat ulang draf sebelum melanjutkan.';

    /** @param  array<string, mixed>|null  $draf */
    public static function versi(?array $draf): ?string
    {
        return $draf['diperbarui_pada'] ?? null;
    }

    /**
     * Bidang yang bisa disunting beserta teks tersimpan & draf akun ini.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id?: int|null}  $pengguna
     * @return array<string, mixed>
     */
    public static function data(array $kegiatan, array $pengguna): array
    {
        $draf = array_column(isset($pengguna['id']) ? app(DrafSuntingRepository::class)->untuk((int) $kegiatan['id'], (int) $pengguna['id']) : [], null, 'bidang');
        $alasan = UbahDataKegiatan::alasanTerkunci($kegiatan);

        return [
            'kegiatan_id' => (int) $kegiatan['id'],
            'dapat_diubah' => $alasan === null,
            'alasan_terkunci' => $alasan,
            'versi' => UbahDataKegiatan::versi($kegiatan),
            'jumlah_draf' => count($draf),
            'bidang' => array_map(fn (string $kunci) => [
                'kunci' => $kunci,
                'label' => SuntingDokumen::label($kunci),
                'nomor_bagian' => SuntingDokumen::BIDANG[$kunci][0],
                'jenis' => SuntingDokumen::jenis($kunci),
                'maks' => SuntingDokumen::maks($kunci),
                'teks' => SuntingDokumen::teks($kegiatan, $kunci),
                'draf' => isset($draf[$kunci]) ? self::untukJson($kegiatan, $draf[$kunci]) : null,
            ], array_keys(SuntingDokumen::BIDANG)),
        ];
    }

    /**
     * Simpan (atau perbarui) draf satu bidang.
     *
     * @param  array{id?: int|null, name?: string, role?: string, leadings?: list<string>}  $pengguna
     * @return array{draf: array<string, mixed>|null, baru: bool, sama: bool, pesan: string}
     *
     * @throws RuntimeException Kegiatan tidak ada atau draf gagal ditulis.
     * @throws AuthorizationException Di luar cakupan, tanpa akun, atau kegiatan terkunci.
     * @throws ValidationException Berkunci `bidang` atau `teks`.
     * @throws PengaturanBerubah Draf berubah di tempat lain sejak $versi.
     */
    public static function simpan(int $id, string $kunci, string $teks, array $pengguna, ?string $versi = null): array
    {
        $kegiatan = self::kegiatan($id, $pengguna);
        if (($alasan = UbahDataKegiatan::alasanTerkunci($kegiatan)) !== null) {
            throw new AuthorizationException($alasan);
        }
        self::periksaBidang($kunci);
        if (mb_strlen($teks) > SuntingDokumen::maksDraf($kunci)) {
            throw ValidationException::withMessages(['teks' => 'Draf '.mb_lcfirst(SuntingDokumen::label($kunci)).' paling banyak '.SuntingDokumen::maksDraf($kunci).' karakter.']);
        }
        $repo = app(DrafSuntingRepository::class);
        $lama = $repo->temukan($id, (int) $pengguna['id'], $kunci);
        self::pastikanVersi($lama, $versi);
        $label = SuntingDokumen::label($kunci);

        if (trim($teks) === trim(SuntingDokumen::teks($kegiatan, $kunci))) {
            $lama !== null && $repo->hapus($id, (int) $pengguna['id'], $kunci);

            return ['draf' => null, 'baru' => false, 'sama' => true, 'pesan' => "{$label} sama dengan yang tersimpan — draf tidak diperlukan."];
        }
        $draf = $repo->simpan($id, (int) $pengguna['id'], $kunci, $teks, RiwayatSunting::teks($kegiatan, $kunci));

        return ['draf' => self::untukJson($kegiatan, $draf), 'baru' => $lama === null, 'sama' => false, 'pesan' => "Draf {$label} tersimpan — terapkan bila sudah selesai."];
    }

    /**
     * Buang draf satu bidang (data kegiatan tidak berubah).
     *
     * @param  array{id?: int|null, role?: string, leadings?: list<string>}  $pengguna
     * @return bool false bila draf tidak ada.
     *
     * @throws PengaturanBerubah Draf berubah di tempat lain sejak $versi.
     */
    public static function hapus(int $id, string $kunci, array $pengguna, ?string $versi = null): bool
    {
        self::kegiatan($id, $pengguna);
        self::periksaBidang($kunci);
        $repo = app(DrafSuntingRepository::class);
        $lama = $repo->temukan($id, (int) $pengguna['id'], $kunci);
        if ($lama === null) {
            return false;
        }
        self::pastikanVersi($lama, $versi);

        return $repo->hapus($id, (int) $pengguna['id'], $kunci);
    }

    /**
     * Terapkan draf ke data kegiatan lewat SuntingDokumen::simpan, lalu buang drafnya.
     *
     * @param  array{id?: int|null, name: string, role?: string, leadings?: list<string>}  $pengguna
     * @return array<string, mixed> Hasil SuntingDokumen::simpan.
     *
     * @throws ValidationException `bidang` (tanpa draf) atau aturan isian (kunci kolom data kegiatan).
     * @throws PengaturanBerubah Draf ($versiDraf) atau data kegiatan ($versi) berubah di tempat lain.
     * @throws AuthorizationException
     */
    public static function terapkan(int $id, string $kunci, array $pengguna, ?string $versi = null, ?string $versiDraf = null): array
    {
        $kegiatan = self::kegiatan($id, $pengguna);
        self::periksaBidang($kunci);
        $repo = app(DrafSuntingRepository::class);
        $draf = $repo->temukan($id, (int) $pengguna['id'], $kunci)
            ?? throw ValidationException::withMessages(['bidang' => 'Belum ada draf '.mb_lcfirst(SuntingDokumen::label($kunci)).' untuk diterapkan.']);
        self::pastikanVersi($draf, $versiDraf);

        $hasil = SuntingDokumen::simpan($id, $kegiatan, $kunci, $draf['teks'], $pengguna, $versi);
        $repo->hapus($id, (int) $pengguna['id'], $kunci);

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array<string, mixed>  $draf
     * @return array<string, mixed>
     */
    public static function untukJson(array $kegiatan, array $draf): array
    {
        $keadaan = $draf['teks_awal'] === RiwayatSunting::teks($kegiatan, $draf['bidang']) ? 'belum_diterapkan' : 'data_berubah';

        return $draf + [
            'label' => SuntingDokumen::label($draf['bidang']),
            'versi' => self::versi($draf),
            'keadaan' => $keadaan,
            'keterangan' => self::KEADAAN[$keadaan],
        ];
    }

    /**
     * @param  array<string, mixed>  $pengguna
     * @return array<string, mixed>
     */
    private static function kegiatan(int $id, array $pengguna): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($id) ?? throw new RuntimeException('Kegiatan tidak ditemukan.');
        if (! AksesKegiatan::bolehLihat($kegiatan, $pengguna)) {
            throw new AuthorizationException(AksesKegiatan::PESAN_TOLAK);
        }
        if (! isset($pengguna['id'])) {
            throw new AuthorizationException('Draf sunting disimpan per akun — masuk dengan akun terlebih dahulu.');
        }

        return $kegiatan;
    }

    private static function periksaBidang(string $kunci): void
    {
        if (! isset(SuntingDokumen::BIDANG[$kunci])) {
            throw ValidationException::withMessages(['bidang' => 'Teks ini tidak bisa disunting di pratinjau.']);
        }
    }

    /** @param  array<string, mixed>|null  $draf */
    private static function pastikanVersi(?array $draf, ?string $versi): void
    {
        if ($versi !== null && $versi !== self::versi($draf)) {
            throw new PengaturanBerubah(self::PESAN_BENTROK);
        }
    }
}
