<?php

namespace App\Support\Akun;

use App\Contracts\PenggunaRepository;
use App\Support\Pengaturan\AturanAkun;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Atur sandi akun dari konsol server (`php artisan sigenrabtor:atur-sandi`) — jalan masuk admin pertama di server
 * produksi tanpa mode demo (`SIRABTOR_MODE_DEMO=false`: akun tanpa sandi tidak bisa masuk dan belum ada admin yang bisa
 * mengatur ulang sandi lewat layar). `atur($surel, $sandi, $nama, $buatAdmin)`: akun dicari lewat `DataAkun::cariSurel`;
 * tidak ada → `RuntimeException` kecuali `$buatAdmin` (akun admin aktif baru: nama wajib berhuruf ≤ `AturanAkun::MAKS_NAMA`,
 * surel sah); sandi wajib memenuhi `PendaftaranAkun::syaratSandi` + ≤ `MAKS_SANDI` (galat berkunci `sandi`/`nama`/`surel`
 * → `ValidationException`); hash disimpan lewat `PenggunaRepository::gantiSandi` (cookie "ingat saya" lama ikut gugur).
 * Dicatat di log aplikasi (tanpa sandi), bukan riwayat pengaturan — tidak ada akun yang sedang masuk di konsol.
 */
final class SandiKonsol
{
    /**
     * @return array{akun: array{id: int, nama: string, surel: string, peran: string, aktif: bool}, dibuat: bool}
     *
     * @throws ValidationException
     * @throws RuntimeException Akun tidak ada (tanpa `$buatAdmin`) / gagal disimpan.
     */
    public static function atur(string $surel, string $sandi, ?string $nama = null, bool $buatAdmin = false): array
    {
        $surel = PeriksaMasuk::rapikanSurel($surel);
        if (($galat = PeriksaMasuk::galatSurel($surel)) !== null) {
            throw ValidationException::withMessages(['surel' => $galat]);
        }
        $repo = app(PenggunaRepository::class);
        $akun = DataAkun::cariSurel($surel);
        $dibuat = false;

        if ($akun === null) {
            if (! $buatAdmin) {
                throw new RuntimeException("Akun {$surel} tidak ada — tambahkan --buat-admin --nama=\"…\" untuk membuat akun admin baru.");
            }
            $nama = AturanAkun::rapikan((string) $nama);
            $galatNama = match (true) {
                $nama === '' => 'Isi nama admin dengan --nama="…".',
                mb_strlen($nama) > AturanAkun::MAKS_NAMA => 'Nama terlalu panjang (maksimal '.AturanAkun::MAKS_NAMA.' karakter).',
                preg_match('/\p{L}/u', $nama) !== 1 => 'Nama harus berisi huruf.',
                default => null,
            };
            if ($galatNama !== null) {
                throw ValidationException::withMessages(['nama' => $galatNama]);
            }
        }

        self::periksaSandi($sandi, (string) ($akun['name'] ?? $nama), $surel);

        if ($akun === null) {
            $id = $repo->simpan(['name' => $nama, 'email' => $surel, 'role' => 'admin', 'nip' => null, 'jabatan' => 'Admin Aplikasi', 'leadings' => [], 'is_active' => true]);
            $akun = $repo->temukan($id) ?? throw new RuntimeException('Akun admin gagal dibuat.');
            $dibuat = true;
        }
        $repo->gantiSandi((int) $akun['id'], Hash::make($sandi));
        Log::notice('Sandi akun diatur dari konsol server.', ['akun_id' => (int) $akun['id'], 'surel' => $surel, 'dibuat' => $dibuat]);

        return ['akun' => [
            'id' => (int) $akun['id'],
            'nama' => (string) $akun['name'],
            'surel' => (string) $akun['email'],
            'peran' => (string) $akun['role'],
            'aktif' => (bool) ($akun['is_active'] ?? true),
        ], 'dibuat' => $dibuat];
    }

    /** @throws ValidationException */
    private static function periksaSandi(string $sandi, string $nama, string $surel): void
    {
        if (mb_strlen($sandi) > PendaftaranAkun::MAKS_SANDI) {
            throw ValidationException::withMessages(['sandi' => 'Sandi terlalu panjang (maksimal '.PendaftaranAkun::MAKS_SANDI.' karakter).']);
        }
        $kurang = array_keys(array_filter(PendaftaranAkun::syaratSandi($sandi, $nama, $surel), fn (bool $ok) => ! $ok));
        if ($kurang !== []) {
            throw ValidationException::withMessages(['sandi' => PendaftaranAkun::PESAN_SANDI[$kurang[0]]]);
        }
    }
}
