<?php

namespace App\Support\Akun;

use App\Contracts\PermohonanAkunRepository;
use App\Support\MasterKode;
use App\Support\Pengaturan\AturanAkun;
use App\Support\Pengaturan\Nip;
use App\Support\Teks;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Pendaftaran akun mandiri (PRD §8 "Daftar & Masuk": akun dibuat/diaktifkan admin): calon pengguna mengirim permohonan,
 * admin yang mengaktifkannya. Satu-satunya aturan formulir pendaftaran:
 * - `ISIAN` = kunci formulir; `rapikan()` (nama/jabatan satu baris, surel huruf kecil, NIP tanpa pemisah, leading tanpa
 *   dobel urut daftar leading, catatan `Teks::rapikan`, sandi apa adanya);
 * - `syaratSandi($sandi, $nama, $surel)` = `SYARAT_SANDI` terpenuhi/tidak (kembaran klien `syaratSandi` di
 *   resources/js/sandi.js; kasus bersama `tests/fixtures/syarat-sandi.json`);
 * - `galat($isian)` = pesan per kunci (surel belum terdaftar sebagai akun dan belum ada permohonan menunggu, leading aktif,
 *   NIP lewat `Nip::galat`, sandi memenuhi syarat & diulang sama, pernyataan dicentang);
 * - `ajukan($isian)` → permohonan `PermohonanAkunRepository::ajukan` (sandi disimpan sebagai hash bcrypt) → `tampil()`;
 * - `tampil()` = permohonan tanpa hash sandi + label status & nama unit leading; `menunggu($surel)`.
 */
final class PendaftaranAkun
{
    public const ISIAN = ['nama', 'surel', 'jabatan', 'nip', 'leadings', 'catatan', 'sandi', 'sandi_ulang', 'setuju'];

    public const SYARAT_SANDI = ['panjang' => 'Minimal 8 karakter', 'campur' => 'Memuat huruf dan angka', 'pribadi' => 'Tidak memuat nama atau surel Anda'];

    /** Pesan galat per syarat sandi yang belum terpenuhi (yang pertama ditampilkan). */
    public const PESAN_SANDI = ['panjang' => 'Sandi minimal 8 karakter.', 'campur' => 'Sandi harus memuat huruf dan angka.', 'pribadi' => 'Sandi tidak boleh memuat nama atau surel Anda.'];

    public const MIN_SANDI = 8;

    public const MAKS_SANDI = 64;

    /** Bagian nama/surel sepanjang ini atau lebih tidak boleh ada di dalam sandi. */
    public const MIN_BAGIAN_PRIBADI = 4;

    public const MAKS_CATATAN = 300;

    public const STATUS = ['menunggu' => 'Menunggu persetujuan admin', 'disetujui' => 'Disetujui — akun sudah dibuat', 'ditolak' => 'Ditolak'];

    /**
     * @param  array<string, mixed>  $isian
     * @return array{nama: string, surel: string, jabatan: string, nip: string, leadings: list<string>, catatan: string, sandi: string, sandi_ulang: string, setuju: bool}
     */
    public static function rapikan(array $isian): array
    {
        $urutan = array_column(MasterKode::semuaLeading(), 'kode');
        $dipilih = array_values(array_unique(array_map('strval', (array) ($isian['leadings'] ?? []))));
        $dikenal = array_values(array_intersect($urutan, $dipilih));

        return [
            'nama' => AturanAkun::rapikan((string) ($isian['nama'] ?? '')),
            'surel' => mb_strtolower(trim((string) ($isian['surel'] ?? ''))),
            'jabatan' => AturanAkun::rapikan((string) ($isian['jabatan'] ?? '')),
            'nip' => Nip::bersihkan((string) ($isian['nip'] ?? '')),
            // Urut seperti daftar leading; kode tak dikenal tetap di akhir agar ditolak galat().
            'leadings' => [...$dikenal, ...array_values(array_diff($dipilih, $dikenal))],
            'catatan' => Teks::rapikan((string) ($isian['catatan'] ?? '')),
            'sandi' => (string) ($isian['sandi'] ?? ''),
            'sandi_ulang' => (string) ($isian['sandi_ulang'] ?? ''),
            'setuju' => filter_var($isian['setuju'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /** @return array{panjang: bool, campur: bool, pribadi: bool} */
    public static function syaratSandi(string $sandi, string $nama, string $surel): array
    {
        $kecil = mb_strtolower($sandi);
        $lokal = explode('@', mb_strtolower(trim($surel)))[0];
        $bagian = array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($nama).' '.$lokal) ?: [],
            fn (string $b) => mb_strlen($b) >= self::MIN_BAGIAN_PRIBADI,
        );

        return [
            'panjang' => mb_strlen($sandi) >= self::MIN_SANDI,
            'campur' => preg_match('/\p{L}/u', $sandi) === 1 && preg_match('/[0-9]/', $sandi) === 1,
            'pribadi' => array_filter($bagian, fn (string $b) => str_contains($kecil, $b)) === [],
        ];
    }

    /**
     * @param  array<string, mixed>  $isian  Sudah dirapikan.
     * @return array<string, string>
     */
    public static function galat(array $isian): array
    {
        $aktif = array_column(MasterKode::leadings(), 'kode');
        $syarat = self::syaratSandi($isian['sandi'], $isian['nama'], $isian['surel']);
        $kurang = array_keys(array_filter($syarat, fn (bool $ok) => ! $ok));

        return array_filter([
            'nama' => match (true) {
                $isian['nama'] === '' => 'Nama lengkap wajib diisi, beserta gelar seperti tercetak di dokumen.',
                mb_strlen($isian['nama']) > AturanAkun::MAKS_NAMA => 'Terlalu panjang (maksimal '.AturanAkun::MAKS_NAMA.' karakter).',
                preg_match('/\p{L}/u', $isian['nama']) !== 1 => 'Nama harus berisi huruf.',
                default => null,
            },
            'surel' => match (true) {
                $isian['surel'] === '' => 'Surel wajib diisi — dipakai untuk masuk.',
                mb_strlen($isian['surel']) > 255 || filter_var($isian['surel'], FILTER_VALIDATE_EMAIL) === false => 'Surel tidak sah, mis. nama@madrasah.sch.id.',
                DataAkun::cariSurel($isian['surel']) !== null => 'Surel ini sudah terdaftar — masuk dengan akun itu, atau hubungi admin bila lupa sandi.',
                self::menunggu($isian['surel']) !== null => 'Permohonan dengan surel ini sedang menunggu persetujuan admin.',
                default => null,
            },
            'jabatan' => mb_strlen($isian['jabatan']) > AturanAkun::MAKS_JABATAN ? 'Terlalu panjang (maksimal '.AturanAkun::MAKS_JABATAN.' karakter).' : null,
            'nip' => $isian['nip'] === '' ? null : Nip::galat($isian['nip']),
            'leadings' => match (true) {
                $isian['leadings'] === [] => 'Pilih minimal satu leading yang Anda tangani.',
                array_diff($isian['leadings'], $aktif) !== [] => 'Leading yang dipilih tidak dikenal atau nonaktif.',
                default => null,
            },
            'catatan' => mb_strlen($isian['catatan']) > self::MAKS_CATATAN ? 'Terlalu panjang (maksimal '.self::MAKS_CATATAN.' karakter).' : null,
            'sandi' => match (true) {
                $isian['sandi'] === '' => 'Buat sandi untuk akun Anda.',
                mb_strlen($isian['sandi']) > self::MAKS_SANDI => 'Terlalu panjang (maksimal '.self::MAKS_SANDI.' karakter).',
                $kurang !== [] => self::PESAN_SANDI[$kurang[0]],
                default => null,
            },
            'sandi_ulang' => match (true) {
                $isian['sandi_ulang'] === '' => 'Ulangi sandi yang sama.',
                $isian['sandi_ulang'] !== $isian['sandi'] => 'Sandi ulang tidak sama.',
                default => null,
            },
            'setuju' => $isian['setuju'] ? null : 'Centang pernyataan ini untuk mengirim permohonan.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $isian
     * @return array<string, mixed>
     *
     * @throws ValidationException
     * @throws \RuntimeException Permohonan gagal ditulis.
     */
    public static function ajukan(array $isian): array
    {
        $isian = self::rapikan($isian);
        if (($galat = self::galat($isian)) !== []) {
            throw ValidationException::withMessages($galat);
        }

        return self::tampil(app(PermohonanAkunRepository::class)->ajukan([
            'nama' => $isian['nama'],
            'surel' => $isian['surel'],
            'jabatan' => $isian['jabatan'] !== '' ? $isian['jabatan'] : null,
            'nip' => $isian['nip'] !== '' ? $isian['nip'] : null,
            'leadings' => $isian['leadings'],
            'catatan' => $isian['catatan'] !== '' ? $isian['catatan'] : null,
            'sandi_hash' => Hash::make($isian['sandi']),
        ]));
    }

    /**
     * @param  array<string, mixed>  $permohonan
     * @return array<string, mixed>
     */
    public static function tampil(array $permohonan): array
    {
        unset($permohonan['sandi_hash']);

        return $permohonan + [
            'label_status' => self::STATUS[$permohonan['status'] ?? ''] ?? (string) ($permohonan['status'] ?? ''),
            'nip_format' => filled($permohonan['nip'] ?? null) ? Nip::format((string) $permohonan['nip']) : null,
            'leading' => array_map(fn (string $kode) => ['kode' => $kode, 'nama_unit' => MasterKode::leading($kode)['nama_unit'] ?? ''], $permohonan['leadings'] ?? []),
        ];
    }

    /** Permohonan menunggu bersurel $surel (tanpa beda huruf besar), atau null. @return array<string, mixed>|null */
    public static function menunggu(string $surel): ?array
    {
        $surel = mb_strtolower(trim($surel));

        return collect(app(PermohonanAkunRepository::class)->semua())
            ->first(fn (array $p) => ($p['status'] ?? '') === 'menunggu' && mb_strtolower((string) $p['surel']) === $surel);
    }
}
