<?php

namespace App\Support\Pengaturan;

use App\Contracts\LeadingRepository;
use App\Contracts\PenggunaRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Tautan akun ke leading (PRD F29 "tautkan akun leading ke satu atau lebih leading") — satu aturan untuk:
 * - cakupan data: `bolehAkses($pengguna, $kodeLeading)` = admin, atau akun leading yang tertaut ke leading itu (dipakai
 *   unduh buku, daftar leading buku, riwayat saran AI, duplikat kegiatan);
 * - perubahan tautan (khusus admin, `$versi` = KelolaLeading::sidik → PengaturanBerubah bila usang): `atur($akunId,
 *   $kode)` mengganti seluruh tautan satu akun (hanya akun berperan leading; tautan baru wajib ke leading aktif, tautan
 *   lama ke leading nonaktif boleh tetap; urut daftar leading tanpa dobel; boleh kosong — `tanpa_tautan`), `tautkan()`/
 *   `lepas()` satu leading (dipakai panel tautan tab Leading lewat KelolaLeading). Riwayat `ubah_tautan_leading`.
 * - bacaan: `untukAkun($id)` (tautan + nama unit & status), `calon($kode)` (akun leading yang belum tertaut, aktif dulu).
 */
final class TautanAkunLeading
{
    /** @param  array{role: string, leadings?: list<string>}  $pengguna */
    public static function bolehAkses(array $pengguna, ?string $kodeLeading): bool
    {
        return $pengguna['role'] === 'admin' || ($kodeLeading !== null && in_array($kodeLeading, $pengguna['leadings'] ?? [], true));
    }

    /**
     * Ganti seluruh tautan akun $akunId menjadi $kode.
     *
     * @param  list<string>  $kode
     * @param  array{role: string}  $pengguna
     * @return array{akun: array<string, mixed>, ditambah: list<string>, dilepas: list<string>, tanpa_tautan: bool, keterangan: string|null} keterangan null = tidak berubah.
     *
     * @throws ValidationException Berkunci `akun` atau `leadings`.
     * @throws AuthorizationException
     * @throws PengaturanBerubah
     */
    public static function atur(int $akunId, array $kode, array $pengguna, ?string $versi = null): array
    {
        self::pastikanBoleh($pengguna, $versi);

        return self::ganti($akunId, $kode, null);
    }

    /**
     * Inti atur(): $keterangan = teks riwayat pengganti (tautkan/lepas satu leading memakai kalimatnya sendiri).
     *
     * @param  list<string>  $kode
     * @return array{akun: array<string, mixed>, ditambah: list<string>, dilepas: list<string>, tanpa_tautan: bool, keterangan: string|null}
     */
    private static function ganti(int $akunId, array $kode, ?string $keterangan): array
    {
        $repo = app(PenggunaRepository::class);
        $akun = $repo->temukan($akunId);
        if ($akun === null || $akun['role'] !== 'leading') {
            throw ValidationException::withMessages(['akun' => $akun === null ? "Akun {$akunId} tidak ditemukan." : 'Akun admin tidak bertaut ke leading.']);
        }

        $leading = collect(app(LeadingRepository::class)->semua());
        $baru = array_values(array_intersect($leading->pluck('kode')->all(), array_unique(array_map('strval', $kode))));
        if (($asing = array_diff(array_map('strval', $kode), $baru)) !== []) {
            throw ValidationException::withMessages(['leadings' => 'Leading '.implode(', ', $asing).' tidak dikenal.']);
        }
        $ditambah = array_values(array_diff($baru, $akun['leadings']));
        $nonaktif = array_values(array_filter($ditambah, fn (string $k) => ! $leading->firstWhere('kode', '===', $k)['is_active']));
        if ($nonaktif !== []) {
            throw ValidationException::withMessages(['leadings' => 'Leading '.implode(', ', $nonaktif).' nonaktif — tidak bisa diberi tautan baru.']);
        }
        $dilepas = array_values(array_diff($akun['leadings'], $baru));
        if ($ditambah === [] && $dilepas === []) {
            return ['akun' => $akun, 'ditambah' => [], 'dilepas' => [], 'tanpa_tautan' => $akun['leadings'] === [] && $akun['is_active'], 'keterangan' => null];
        }

        $repo->simpan(['leadings' => $baru], $akunId);
        $label = fn (array $daftar) => implode(', ', array_map(fn (string $k) => trim($k.' '.($leading->firstWhere('kode', '===', $k)['nama_unit'] ?? '')), $daftar));
        $keterangan ??= "Tautan akun {$akun['name']} — ".implode('; ', array_filter([$ditambah !== [] ? 'ditautkan ke '.$label($ditambah) : null, $dilepas !== [] ? 'dilepas dari '.$label($dilepas) : null]));
        RiwayatPengaturan::catat('ubah_tautan_leading', $keterangan);

        return ['akun' => $repo->temukan($akunId), 'ditambah' => $ditambah, 'dilepas' => $dilepas, 'tanpa_tautan' => $baru === [] && $akun['is_active'], 'keterangan' => $keterangan];
    }

    /**
     * Tautkan akun $akunId ke leading aktif $kode.
     *
     * @param  array{role: string}  $pengguna
     * @return string Keterangan ("Akun … ditautkan ke kode nama").
     *
     * @throws ValidationException Berkunci `akun`.
     */
    public static function tautkan(string $kode, int $akunId, array $pengguna, ?string $versi = null): string
    {
        self::pastikanBoleh($pengguna, $versi);
        $leading = collect(app(LeadingRepository::class)->semua())->firstWhere('kode', '===', $kode);
        $akun = app(PenggunaRepository::class)->temukan($akunId);
        if ($leading === null || $akun === null || $akun['role'] !== 'leading' || in_array($kode, $akun['leadings'], true)) {
            throw ValidationException::withMessages(['akun' => 'Pilih akun leading yang akan ditautkan.']);
        }
        if (! $leading['is_active']) {
            throw ValidationException::withMessages(['akun' => 'Leading nonaktif tidak bisa diberi tautan baru.']);
        }

        $keterangan = "Akun {$akun['name']} ditautkan ke {$kode} {$leading['nama_unit']}";
        self::ganti($akunId, [...$akun['leadings'], $kode], $keterangan);

        return $keterangan;
    }

    /**
     * Lepas tautan akun $akunId dari leading $kode.
     *
     * @param  array{role: string}  $pengguna
     * @return array{keterangan: string, tanpa_tautan: bool}
     *
     * @throws ValidationException Berkunci `akun`.
     */
    public static function lepas(string $kode, int $akunId, array $pengguna, ?string $versi = null): array
    {
        self::pastikanBoleh($pengguna, $versi);
        $akun = app(PenggunaRepository::class)->temukan($akunId);
        if ($akun === null || ! in_array($kode, $akun['leadings'], true)) {
            throw ValidationException::withMessages(['akun' => 'Akun ini tidak tertaut ke leading tersebut.']);
        }
        $nama = collect(app(LeadingRepository::class)->semua())->firstWhere('kode', '===', $kode)['nama_unit'] ?? '';
        $keterangan = trim("Akun {$akun['name']} dilepas dari {$kode} {$nama}");
        $hasil = self::ganti($akunId, array_values(array_diff($akun['leadings'], [$kode])), $keterangan);

        return ['keterangan' => $keterangan, 'tanpa_tautan' => $hasil['tanpa_tautan']];
    }

    /**
     * Tautan akun $id beserta nama unit & status leadingnya (urut daftar leading).
     *
     * @return list<array{kode: string, nama_unit: string, is_active: bool}>
     */
    public static function untukAkun(int $id): array
    {
        $akun = app(PenggunaRepository::class)->temukan($id);
        if ($akun === null) {
            return [];
        }

        return array_values(array_map(
            fn (array $l) => ['kode' => $l['kode'], 'nama_unit' => $l['nama_unit'], 'is_active' => $l['is_active']],
            array_filter(app(LeadingRepository::class)->semua(), fn (array $l) => in_array($l['kode'], $akun['leadings'], true)),
        ));
    }

    /**
     * Akun berperan leading yang belum tertaut ke $kode (aktif lebih dulu, lalu nama).
     *
     * @return list<array<string, mixed>>
     */
    public static function calon(string $kode): array
    {
        return collect(app(PenggunaRepository::class)->semua())
            ->filter(fn (array $a) => $a['role'] === 'leading' && ! in_array($kode, $a['leadings'], true))
            ->sortBy([['is_active', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanBoleh(array $pengguna, ?string $versi): void
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Tautan akun ke leading diatur admin.');
        }
        if ($versi !== null && $versi !== KelolaLeading::sidik()) {
            throw new PengaturanBerubah('Data leading & akun baru saja diubah di tempat lain.');
        }
    }
}
