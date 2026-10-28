<?php

namespace App\Support\Pengaturan;

use App\Contracts\DaftarModelAi;
use App\Contracts\PengaturanAiRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Model & parameter jawaban AI di server (PRD F24 "Model, Kunci API, Prompt per Bagian"; khusus admin) — dipakai tab
 * Model & parameter halaman Pengaturan AI (`pakaiModel`, `simpanParameter`) dan endpoint admin `pengaturan.ai.model*` /
 * `pengaturan.ai.parameter*`:
 * - `dataParameter()`: parameter tersimpan + bawaan, preset suhu, pilihan panjang & jumlah versi;
 * - `daftar($penyedia)`: model terpasang/tersedia dari DaftarModelAi (gagal → `galat`, daftar kosong = ketik nama model);
 * - `pakai($penyedia, $model, daftar:)`: model harus ada di daftar penyedia bila daftarnya tidak kosong (selain itu cukup
 *   berpola AturanPenyediaAi::POLA_MODEL); riwayat `ubah_model_ai`;
 * - `simpanParameter($isian)`: ParameterAi::rapikan (suhu 0–1, MAKS_TOKEN, JUMLAH_VERSI); riwayat `ubah_model_ai`.
 * Versi & penulisan lewat KelolaPenyediaAi::pastikanVersi()/tulis(). Galat → ValidationException berkunci isian.
 */
final class KelolaModelAi
{
    public const LABEL_PARAMETER = ['suhu' => 'Kreativitas', 'maks_token' => 'Panjang jawaban', 'jumlah_versi' => 'Jumlah versi'];

    /**
     * @return array{penyedia: string, nama: string, model: string|null, daftar: list<array<string, mixed>>, galat: string|null}
     */
    public static function daftar(string $penyedia): array
    {
        $konfigurasi = app(PengaturanAiRepository::class)->ambil()['penyedia'][$penyedia] ?? [];
        try {
            $daftar = app(DaftarModelAi::class)->daftar($penyedia, $konfigurasi);
            $galat = null;
        } catch (RuntimeException $e) {
            [$daftar, $galat] = [[], $e->getMessage()];
        }

        return ['penyedia' => $penyedia, 'nama' => PenyediaAi::nama($penyedia), 'model' => filled($konfigurasi['model'] ?? null) ? (string) $konfigurasi['model'] : null, 'daftar' => $daftar, 'galat' => $galat];
    }

    /**
     * Parameter jawaban tersimpan beserta pilihan & bawaannya.
     *
     * @return array<string, mixed>
     */
    public static function dataParameter(): array
    {
        $ai = app(PengaturanAiRepository::class)->ambil();

        return [
            'versi' => $ai['diperbarui_pada'],
            'parameter' => ParameterAi::dari($ai),
            'bawaan' => ParameterAi::BAWAAN,
            'preset_suhu' => array_map(fn (string $k, array $p) => ['kunci' => $k, 'suhu' => $p[0], 'label' => $p[1], 'keterangan' => $p[2]], array_keys(ParameterAi::PRESET_SUHU), ParameterAi::PRESET_SUHU),
            'maks_token' => array_map(fn (int $t, string $label) => ['nilai' => $t, 'label' => $label], array_keys(ParameterAi::MAKS_TOKEN), ParameterAi::MAKS_TOKEN),
            'jumlah_versi' => ParameterAi::JUMLAH_VERSI,
        ];
    }

    /**
     * @param  array{role: string}  $pengguna
     * @param  list<string>|null  $daftar  Nama model yang boleh dipakai; null = dimuat dari DaftarModelAi.
     * @return array{pesan: string, berubah: bool}
     *
     * @throws AuthorizationException
     * @throws ValidationException Berkunci `model`/`penyedia`.
     * @throws PengaturanBerubah
     * @throws RuntimeException
     */
    public static function pakai(string $penyedia, mixed $model, array $pengguna, ?string $versi, bool $periksaVersi = true, ?array $daftar = null): array
    {
        self::pastikanAdmin($pengguna);
        if (! isset(PenyediaAi::DAFTAR[$penyedia])) {
            throw ValidationException::withMessages(['penyedia' => 'Penyedia AI tidak dikenal.']);
        }
        $model = is_string($model) ? trim($model) : '';
        if ($daftar === null) {
            $dimuat = self::daftar($penyedia);
            if ($dimuat['galat'] !== null) {
                throw ValidationException::withMessages(['model' => $dimuat['galat']]);
            }
            $daftar = array_column($dimuat['daftar'], 'nama');
        }
        $galat = match (true) {
            $model === '' => 'Pilih atau tulis nama model.',
            $daftar !== [] && ! in_array($model, $daftar, true) => "Model {$model} tidak ada di daftar model ".PenyediaAi::nama($penyedia).'.',
            mb_strlen($model) > 100 || ! preg_match(AturanPenyediaAi::POLA_MODEL, $model) => 'Nama model hanya huruf, angka, titik, titik dua, garis miring, dan tanda hubung.',
            default => null,
        };
        if ($galat !== null) {
            throw ValidationException::withMessages(['model' => $galat]);
        }

        $lama = app(PengaturanAiRepository::class)->ambil();
        if ($periksaVersi) {
            KelolaPenyediaAi::pastikanVersi($lama, $versi);
        }
        $baru = $lama;
        $baru['penyedia'][$penyedia] = ['model' => $model] + ($lama['penyedia'][$penyedia] ?? []);
        $ringkas = fn (array $p) => ['model' => ($p['penyedia'][$penyedia]['model'] ?? '') ?: 'belum diisi'];
        $berubah = KelolaPenyediaAi::tulis($lama, $baru, $ringkas, ['model' => 'Model'], PenyediaAi::nama($penyedia).' — ', null, 'ubah_model_ai');

        return ['pesan' => "Model {$model} dipakai untuk ".PenyediaAi::nama($penyedia).'.', 'berubah' => $berubah];
    }

    /**
     * @param  array<string, mixed>  $isian  {suhu, maks_token, jumlah_versi}
     * @param  array{role: string}  $pengguna
     * @return array{pesan: string, berubah: bool, parameter: array{suhu: float, maks_token: int, jumlah_versi: int}}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws PengaturanBerubah
     * @throws RuntimeException
     */
    public static function simpanParameter(array $isian, array $pengguna, ?string $versi, bool $periksaVersi = true): array
    {
        self::pastikanAdmin($pengguna);
        ['data' => $data, 'galat' => $galat] = ParameterAi::rapikan($isian);
        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        $lama = app(PengaturanAiRepository::class)->ambil();
        if ($periksaVersi) {
            KelolaPenyediaAi::pastikanVersi($lama, $versi);
        }
        $ringkas = fn (array $p) => [
            'suhu' => number_format(ParameterAi::dari($p)['suhu'], 1, ',', ''), 'maks_token' => ParameterAi::MAKS_TOKEN[ParameterAi::dari($p)['maks_token']] ?? ParameterAi::dari($p)['maks_token'],
            'jumlah_versi' => ParameterAi::dari($p)['jumlah_versi'].' versi',
        ];
        $berubah = KelolaPenyediaAi::tulis($lama, ['parameter' => $data] + $lama, $ringkas, self::LABEL_PARAMETER, 'Parameter jawaban — ', null, 'ubah_model_ai');

        return ['pesan' => 'Parameter jawaban AI tersimpan.', 'berubah' => $berubah, 'parameter' => $data];
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanAdmin(array $pengguna): void
    {
        if (($pengguna['role'] ?? null) !== 'admin') {
            throw new AuthorizationException('Hanya admin yang bisa mengubah Pengaturan AI.');
        }
    }
}
