<?php

namespace App\Support\Pengaturan;

use App\Contracts\PejabatRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\MasterKode;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Kelola pejabat penanda tangan per tahun anggaran di server (PRD F8, §12; ubah hanya admin, tahun terkunci hanya bisa
 * dilihat) — dipakai tab Pejabat Pengaturan › Profil & Pejabat dan endpoint `pengaturan.madrasah.pejabat`. Kunci peran:
 * `kepala`, `komite`, `leading:{kode}` (`judul()`). Isian dirapikan satu baris; jabatan & nama wajib ≤ 150, NIP lewat
 * Nip (wajib untuk Kepala), nama tanpa angka; disimpan tanpa pemisah; riwayat `ubah_pejabat` hanya bila berubah; temuan
 * antar-peran (ValidasiPejabat::bentrok) dikembalikan sebagai `peringatan` tanpa memblokir simpan. Salin dari tahun lain
 * hanya mengisi peran yang masih kosong (`salin`, riwayat `salin_pejabat`); blok "Mengetahui" per sumber dana
 * (`simpanMengetahui`, riwayat `ubah_mengetahui`).
 */
final class KelolaPejabat
{
    /** Tahun anggaran terkunci (pejabatnya hanya dilihat). */
    public static function terkunci(int $tahun): bool
    {
        return TahunDipilih::terkunci($tahun);
    }

    /**
     * [peran, kode leading|null] dari kunci; null bila tidak dikenal (leading harus leading aktif).
     *
     * @return array{0: string, 1: string|null}|null
     */
    public static function peran(string $kunci): ?array
    {
        if (in_array($kunci, ['kepala', 'komite'], true)) {
            return [$kunci, null];
        }
        $kode = str_starts_with($kunci, 'leading:') ? substr($kunci, 8) : null;

        return $kode !== null && MasterKode::cari(MasterKode::leadings(), $kode) !== null ? ['leading', $kode] : null;
    }

    public static function judul(string $kunci): string
    {
        $leading = str_starts_with($kunci, 'leading:') ? MasterKode::leading(substr($kunci, 8)) : null;

        return match ($kunci) {
            'kepala' => 'Kepala Madrasah',
            'komite' => 'Ketua Komite',
            default => $leading ? "Pejabat {$leading['kode']} {$leading['nama_unit']}" : 'Pejabat leading',
        };
    }

    /** @return array<string, list<mixed>> */
    public static function aturan(string $kunci): array
    {
        return [
            'jabatan' => ['required', 'string', 'max:150', fn (string $atribut, mixed $nilai, Closure $gagal) => preg_match('/\p{L}/u', (string) $nilai) ? null : $gagal('Jabatan harus berisi huruf.')],
            'nama' => ['required', 'string', 'max:150', fn (string $atribut, mixed $nilai, Closure $gagal) => match (true) {
                (bool) preg_match('/\d/u', (string) $nilai) => $gagal('Nama pejabat tidak boleh berisi angka — NIP diisi di kolom NIP.'),
                ! preg_match('/\p{L}/u', (string) $nilai) => $gagal('Nama pejabat harus berisi huruf.'),
                default => null,
            }],
            'nip' => [$kunci === 'kepala' ? 'required' : 'nullable', fn (string $atribut, mixed $nilai, Closure $gagal) => ($pesan = Nip::galat((string) $nilai)) ? $gagal($pesan) : null],
        ];
    }

    /** @return array<string, string> */
    public static function pesan(): array
    {
        return [
            'jabatan.required' => 'Jabatan tercetak wajib diisi.',
            'nama.required' => 'Nama pejabat wajib diisi.',
            'nip.required' => 'NIP Kepala Madrasah wajib diisi — tercetak di kolom "Menyetujui".',
            '*.max' => 'Terlalu panjang (maksimal :max karakter).',
        ];
    }

    /**
     * @param  array<string, mixed>  $isian  {jabatan, nama, nip}
     * @param  array{role: string}  $pengguna
     * @return array{pejabat: array{jabatan: string, nama: string, nip: string|null}, ringkasan: string|null, peringatan: list<string>}
     *
     * @throws ValidationException Berkunci jabatan/nama/nip, atau `kunci` bila peran tidak dikenal.
     * @throws AuthorizationException Bukan admin atau tahun terkunci.
     */
    public static function simpan(int $tahun, string $kunci, array $isian, array $pengguna): array
    {
        self::pastikanBoleh($tahun, $pengguna);
        [$peran, $leading] = self::peran($kunci) ?? throw ValidationException::withMessages(['kunci' => 'Peran pejabat tidak dikenal.']);

        $rapi = array_map(fn (string $k) => trim((string) preg_replace('/\s+/u', ' ', (string) ($isian[$k] ?? ''))), ['jabatan' => 'jabatan', 'nama' => 'nama', 'nip' => 'nip']);
        Validator::make($rapi, self::aturan($kunci), self::pesan())->validate();

        $repo = app(PejabatRepository::class);
        $semua = $repo->untukTahun($tahun);
        $lama = $peran === 'leading' ? $semua['leading'][$leading] : $semua[$peran];
        $baru = ['jabatan' => $rapi['jabatan'], 'nama' => $rapi['nama'], 'nip' => Nip::bersihkan($rapi['nip']) ?: null];
        $repo->simpan($tahun, $peran, $leading, $baru);

        $tampil = fn (array $p) => ['nip' => $p['nip'] ? Nip::format($p['nip']) : null] + $p;
        $ringkasan = RiwayatPengaturan::perubahan($tampil($lama), $tampil($baru), ['jabatan' => 'Jabatan', 'nama' => 'Nama', 'nip' => 'NIP']);
        if ($ringkasan !== null) {
            RiwayatPengaturan::catat('ubah_pejabat', self::judul($kunci)." TA {$tahun} — {$ringkasan}");
        }

        return ['pejabat' => $baru, 'ringkasan' => $ringkasan, 'peringatan' => ValidasiPejabat::bentrok($semua, $kunci, $baru)];
    }

    /**
     * Salin pejabat tahun $dari ke tahun $ke — hanya peran yang di tahun tujuan belum bernama.
     *
     * @param  array{role: string}  $pengguna
     * @return int Jumlah pejabat yang disalin.
     */
    public static function salin(int $dari, int $ke, array $pengguna): int
    {
        self::pastikanBoleh($ke, $pengguna);
        if ($dari === $ke || ! collect(app(TahunAnggaranRepository::class)->semua())->contains('tahun', $dari)) {
            throw ValidationException::withMessages(['dari' => 'Pilih tahun anggaran lain sebagai sumber salinan.']);
        }

        $repo = app(PejabatRepository::class);
        $sumber = $repo->untukTahun($dari);
        $tujuan = $repo->untukTahun($ke);
        $disalin = 0;
        foreach (['kepala', 'komite'] as $peran) {
            if ($sumber[$peran]['nama'] && ! $tujuan[$peran]['nama']) {
                $repo->simpan($ke, $peran, null, $sumber[$peran]);
                $disalin++;
            }
        }
        foreach ($sumber['leading'] as $kode => $pejabat) {
            if ($pejabat['nama'] && ! ($tujuan['leading'][$kode]['nama'] ?? null)) {
                $repo->simpan($ke, 'leading', $kode, $pejabat);
                $disalin++;
            }
        }
        if ($disalin > 0) {
            RiwayatPengaturan::catat('salin_pejabat', "{$disalin} pejabat TA {$dari} disalin ke TA {$ke} (yang sudah terisi tidak diubah).");
        }

        return $disalin;
    }

    /**
     * Tahun yang disarankan sebagai sumber salinan: tahun lain terbaru yang berpejabat, selama tahun $tahun masih kosong
     * & tidak terkunci.
     */
    public static function sumberSalin(int $tahun): ?int
    {
        $adaNama = fn (array $p) => filled($p['kepala']['nama']) || filled($p['komite']['nama']) || collect($p['leading'])->contains(fn (array $l) => filled($l['nama']));
        $repo = app(PejabatRepository::class);
        if (self::terkunci($tahun) || $adaNama($repo->untukTahun($tahun))) {
            return null;
        }

        return collect(app(TahunAnggaranRepository::class)->semua())->pluck('tahun')->first(fn (int $t) => $t !== $tahun && $adaNama($repo->untukTahun($t)));
    }

    /**
     * @param  array<string, mixed>  $aturan  Sumber dana → tampil.
     * @param  array{role: string}  $pengguna
     * @return array{aturan: array<string, bool>, ringkasan: string|null}
     */
    public static function simpanMengetahui(int $tahun, array $aturan, array $pengguna): array
    {
        self::pastikanBoleh($tahun, $pengguna);
        $repo = app(PejabatRepository::class);
        $lama = $repo->aturanMengetahui($tahun);
        $baru = array_map(fn (string $s) => (bool) ($aturan[$s] ?? false), array_combine(array_keys($lama), array_keys($lama)));
        $repo->simpanAturanMengetahui($tahun, $baru);
        $sesudah = $repo->aturanMengetahui($tahun);

        $ringkasan = RiwayatPengaturan::perubahan($lama, $sesudah, array_combine(array_keys($lama), array_map(fn (string $s) => "Mengetahui di dokumen {$s}", array_keys($lama))));
        if ($ringkasan !== null) {
            RiwayatPengaturan::catat('ubah_mengetahui', "TA {$tahun} — {$ringkasan}");
        }

        return ['aturan' => $sesudah, 'ringkasan' => $ringkasan];
    }

    /**
     * Data pejabat satu tahun untuk endpoint: terkunci, pejabat (NIP berformat), aturan "Mengetahui", saran salin, kurang,
     * dan temuan per peran (ValidasiPejabat::tahun).
     *
     * @return array<string, mixed>
     */
    public static function data(int $tahun): array
    {
        $repo = app(PejabatRepository::class);
        $pejabat = $repo->untukTahun($tahun);
        $tampil = fn (array $p) => ['jabatan' => $p['jabatan'], 'nama' => $p['nama'], 'nip' => $p['nip'], 'nip_format' => $p['nip'] ? Nip::format($p['nip']) : null];

        return [
            'tahun' => $tahun,
            'terkunci' => self::terkunci($tahun),
            'kepala' => $tampil($pejabat['kepala']),
            'komite' => $tampil($pejabat['komite']),
            'leading' => array_values(array_map(fn (string $kode, array $p) => ['kode' => $kode, 'nama_unit' => MasterKode::leading($kode)['nama_unit'] ?? $kode] + $tampil($p), array_keys($pejabat['leading']), $pejabat['leading'])),
            'mengetahui' => $repo->aturanMengetahui($tahun),
            'sumber_salin' => self::sumberSalin($tahun),
            'kurang' => array_values(array_filter([
                $pejabat['kepala']['nama'] && $pejabat['kepala']['nip'] ? null : 'kepala',
                $pejabat['komite']['nama'] ? null : 'komite',
                ...array_map(fn (string $kode) => $pejabat['leading'][$kode]['nama'] ? null : "leading:{$kode}", array_keys($pejabat['leading'])),
            ])),
            'temuan' => ValidasiPejabat::tahun($tahun),
        ];
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanBoleh(int $tahun, array $pengguna): void
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Pejabat penanda tangan diatur admin.');
        }
        if (self::terkunci($tahun)) {
            throw new AuthorizationException("Tahun Anggaran {$tahun} terkunci — pejabatnya hanya bisa dilihat.");
        }
    }
}
