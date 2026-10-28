<?php

namespace App\Support\Pengaturan;

use App\Contracts\ProfilMadrasahRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Kelola profil madrasah di server (PRD Fase 6 §12, tabel profil_madrasah; ubah hanya admin) — dipakai Pengaturan ›
 * Profil & Pejabat dan endpoint `madrasah.profil`: isian `ISIAN` (label, tempat tercetak, wajib kop), perapian
 * (`rapikan()`: satu baris tanpa spasi ganda, visi boleh berparagraf), aturan & pesan (`aturan()`/`pesan()`), simpan
 * (riwayat `ubah_profil` hanya bila ada perubahan), logo PNG/JPG ≤ MAKS_LOGO_KB minimal MIN_SISI_LOGO piksel
 * (riwayat `unggah_logo`/`hapus_logo`), dan `data()` = profil + tautan logo + isian kop yang masih kosong.
 */
final class KelolaProfilMadrasah
{
    /**
     * Isian profil madrasah (kolom tabel profil_madrasah): label, tempat tercetak, dan wajib untuk kop.
     *
     * @var array<string, array{label: string, dipakai: string, wajib: bool}>
     */
    public const ISIAN = [
        'nama' => ['label' => 'Nama madrasah', 'dipakai' => 'Kop, cover, dan judul dokumen', 'wajib' => true],
        'nama_singkat' => ['label' => 'Nama singkat', 'dipakai' => 'Sebutan sumber dana ("DIPA MTsN 2 Kota Malang Tahun …") dan isi KAK', 'wajib' => true],
        'kota' => ['label' => 'Kota', 'dipakai' => 'Tempat & tanggal tanda tangan, cover', 'wajib' => true],
        'alamat' => ['label' => 'Alamat', 'dipakai' => 'Kop dokumen', 'wajib' => true],
        'telepon' => ['label' => 'Telepon', 'dipakai' => 'Kop dokumen', 'wajib' => true],
        'visi' => ['label' => 'Visi', 'dipakai' => 'Profil madrasah; dapat dirujuk di latar belakang KAK', 'wajib' => false],
    ];

    /** Batas ukuran logo (KB) dan sisi terpendek minimal (piksel). */
    public const MAKS_LOGO_KB = 2048;

    public const MIN_SISI_LOGO = 200;

    /** @return array<string, list<string>> */
    public static function aturan(): array
    {
        return [
            'nama' => ['required', 'string', 'max:150'],
            'nama_singkat' => ['required', 'string', 'max:60'],
            'kota' => ['required', 'string', 'max:60'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s.\/]+$/'],
            'visi' => ['nullable', 'string', 'max:1000'],
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.self::MAKS_LOGO_KB, 'dimensions:min_width='.self::MIN_SISI_LOGO.',min_height='.self::MIN_SISI_LOGO],
        ];
    }

    /** @return array<string, string> */
    public static function pesan(): array
    {
        return [
            'nama.required' => 'Nama madrasah wajib diisi — tercetak di kop dan cover.',
            'nama_singkat.required' => 'Nama singkat wajib diisi — dipakai sebutan sumber dana di dokumen.',
            'kota.required' => 'Kota wajib diisi — dipakai di tempat & tanggal tanda tangan.',
            '*.max' => 'Terlalu panjang (maksimal :max karakter).',
            'telepon.regex' => 'Telepon hanya boleh berisi angka, spasi, dan tanda + ( ) - . /',
            'logo.required' => 'Pilih gambar logo.',
            'logo.image' => 'Logo harus berupa gambar PNG atau JPG.',
            'logo.mimes' => 'Logo harus berupa gambar PNG atau JPG.',
            'logo.max' => 'Ukuran logo maksimal '.(self::MAKS_LOGO_KB / 1024).' MB.',
            'logo.dimensions' => 'Logo terlalu kecil — minimal '.self::MIN_SISI_LOGO.' × '.self::MIN_SISI_LOGO.' piksel agar tajam saat dicetak.',
        ];
    }

    /**
     * Semua isian ISIAN dirapikan: satu baris tanpa spasi ganda; visi boleh berparagraf (maks. satu baris kosong).
     *
     * @param  array<string, mixed>  $isian
     * @return array<string, string>
     */
    public static function rapikan(array $isian): array
    {
        $hasil = [];
        foreach (array_keys(self::ISIAN) as $kunci) {
            $teks = (string) ($isian[$kunci] ?? '');
            $hasil[$kunci] = $kunci === 'visi' ? self::paragraf($teks) : self::satuBaris($teks);
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $isian
     * @param  array{role: string}  $pengguna
     * @return array{profil: array<string, mixed>, ringkasan: string|null} ringkasan null = tidak ada yang berubah.
     *
     * @throws ValidationException Berkunci nama isian.
     */
    public static function simpan(array $isian, array $pengguna): array
    {
        self::pastikanAdmin($pengguna);
        $rapi = self::rapikan($isian);
        Validator::make($rapi, array_intersect_key(self::aturan(), self::ISIAN), self::pesan())->validate();

        $repo = app(ProfilMadrasahRepository::class);
        $lama = $repo->ambil();
        $repo->simpan($rapi);
        $baru = $repo->ambil();
        $ringkasan = RiwayatPengaturan::perubahan($lama, $baru, array_map(fn (array $i) => $i['label'], self::ISIAN));
        if ($ringkasan !== null) {
            RiwayatPengaturan::catat('ubah_profil', $ringkasan);
        }

        return ['profil' => $baru, 'ringkasan' => $ringkasan];
    }

    /**
     * @param  array{role: string}  $pengguna
     *
     * @throws ValidationException Berkunci `logo`.
     */
    public static function simpanLogo(?UploadedFile $logo, array $pengguna): void
    {
        self::pastikanAdmin($pengguna);
        Validator::make(['logo' => $logo], ['logo' => self::aturan()['logo']], self::pesan())->validate();

        $repo = app(ProfilMadrasahRepository::class);
        $adaSebelumnya = $repo->ambil()['logo_path'] !== null;
        $repo->simpanLogo($logo);
        RiwayatPengaturan::catat('unggah_logo', ($adaSebelumnya ? 'Logo kop diganti' : 'Logo kop diunggah')." ({$logo->getClientOriginalName()}).");
    }

    /**
     * @param  array{role: string}  $pengguna
     * @return bool false bila belum ada logo.
     */
    public static function hapusLogo(array $pengguna): bool
    {
        self::pastikanAdmin($pengguna);
        $repo = app(ProfilMadrasahRepository::class);
        if ($repo->ambil()['logo_path'] === null) {
            return false;
        }

        $repo->hapusLogo();
        RiwayatPengaturan::catat('hapus_logo', 'Logo kop dihapus; dokumen dicetak tanpa logo sampai diunggah lagi.');

        return true;
    }

    /**
     * Profil untuk endpoint: isian, tautan logo (null bila belum ada), waktu perubahan, dan isian kop yang masih kosong.
     *
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        $profil = app(ProfilMadrasahRepository::class)->ambil();

        return [
            ...array_intersect_key($profil, self::ISIAN),
            'logo' => $profil['logo_path'] ? route('madrasah.logo', ['v' => $profil['diperbarui_pada']]) : null,
            'diperbarui_pada' => $profil['diperbarui_pada'],
            'kurang' => [
                ...array_keys(array_filter(self::ISIAN, fn (array $i, string $kunci) => $i['wajib'] && $profil[$kunci] === null, ARRAY_FILTER_USE_BOTH)),
                ...($profil['logo_path'] === null ? ['logo'] : []),
            ],
        ];
    }

    private static function satuBaris(string $teks): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $teks));
    }

    private static function paragraf(string $teks): string
    {
        $baris = array_map(self::satuBaris(...), explode("\n", str_replace("\r", '', $teks)));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $baris)));
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanAdmin(array $pengguna): void
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Profil madrasah diatur admin.');
        }
    }
}
