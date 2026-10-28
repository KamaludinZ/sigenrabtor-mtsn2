<?php

namespace App\Support\Pengaturan;

use App\Contracts\PromptAiRepository;
use App\Support\Kegiatan\BagianAi;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Template prompt AI di server (PRD F24 "Prompt per Bagian … termasuk gaya bahasa baku"; khusus admin) — dipakai tab
 * Prompt halaman Pengaturan AI (`simpanGaya`, `simpanPromptBagian`) dan endpoint admin `pengaturan.ai.prompt*`:
 * - `data()`: gaya bahasa baku + tiap bagian BagianAi (instruksi, gaya khusus, aktif, bawaan, instruksi bawaan), penanda;
 * - `simpanGaya($gaya, bawaan:)`: 1–PromptAi::MAKS_GAYA karakter satu baris (sama dengan bawaan = tidak disimpan);
 * - `simpanBagian($bagian, $isian, bawaan:)`: {instruksi, gaya_bahasa (null = gaya baku), aktif} lewat PromptAi::galat;
 *   sama dengan bawaan = baris bagian dibuang;
 * - versi = `diperbarui_pada` PromptAiRepository (`pastikanVersi()` → PengaturanBerubah), riwayat `ubah_prompt_ai` hanya
 *   bila berubah; gagal simpan → RuntimeException. Galat → ValidationException berkunci `gaya_bahasa`/`instruksi`.
 */
final class KelolaPromptAi
{
    public const PESAN_BENTROK = 'Prompt AI baru diubah di tempat lain — tampilan dimuat ulang, isian Anda tetap. Periksa lalu simpan lagi.';

    /** @return array<string, mixed> */
    public static function data(): array
    {
        $gaya = PromptAi::gayaBahasa();

        return [
            'versi' => app(PromptAiRepository::class)->ambil()['diperbarui_pada'],
            'gaya_bahasa' => ['teks' => $gaya, 'bawaan' => $gaya === PromptAi::GAYA_BAHASA, 'teks_bawaan' => PromptAi::GAYA_BAHASA, 'maks' => PromptAi::MAKS_GAYA],
            'batas_aman' => PromptAi::BATAS_AMAN,
            'penanda' => array_map(fn (string $p, string $k) => ['penanda' => $p, 'keterangan' => $k], array_keys(PromptAi::PENANDA), PromptAi::PENANDA),
            'panjang_instruksi' => PromptAi::PANJANG_INSTRUKSI,
            'bagian' => array_map(fn (array $t) => $t + ['instruksi_bawaan' => PromptAi::BAWAAN[$t['bagian']]], PromptAi::semua()),
        ];
    }

    /**
     * @param  array{role: string}  $pengguna
     * @return array{pesan: string, berubah: bool}
     *
     * @throws AuthorizationException
     * @throws ValidationException Berkunci `gaya_bahasa`.
     * @throws PengaturanBerubah
     * @throws RuntimeException
     */
    public static function simpanGaya(mixed $gaya, array $pengguna, ?string $versi, bool $periksaVersi = true, bool $bawaan = false): array
    {
        self::pastikanAdmin($pengguna);
        $gaya = $bawaan ? PromptAi::GAYA_BAHASA : trim((string) preg_replace('/\s+/u', ' ', is_string($gaya) ? $gaya : ''));
        if ($gaya === '' || mb_strlen($gaya) > PromptAi::MAKS_GAYA) {
            throw ValidationException::withMessages(['gaya_bahasa' => 'Gaya bahasa 1–'.PromptAi::MAKS_GAYA.' karakter.']);
        }

        $lama = PromptAi::gayaBahasa();
        self::tulis(fn (array $d) => ['gaya_bahasa' => $gaya === PromptAi::GAYA_BAHASA ? null : $gaya] + $d, $lama !== $gaya ? 'Gaya bahasa baku diubah' : null, $versi, $periksaVersi);

        return ['pesan' => 'Gaya bahasa baku tersimpan.', 'berubah' => $lama !== $gaya];
    }

    /**
     * @param  array<string, mixed>  $isian  {instruksi, gaya_bahasa: string|null, aktif}
     * @param  array{role: string}  $pengguna
     * @return array{pesan: string, berubah: bool}
     *
     * @throws AuthorizationException
     * @throws ValidationException Berkunci `bagian`/`instruksi`/`gaya_bahasa`.
     * @throws PengaturanBerubah
     * @throws RuntimeException
     */
    public static function simpanBagian(string $bagian, array $isian, array $pengguna, ?string $versi, bool $periksaVersi = true, bool $bawaan = false): array
    {
        self::pastikanAdmin($pengguna);
        if (! isset(BagianAi::BAGIAN[$bagian])) {
            throw ValidationException::withMessages(['bagian' => 'Bagian AI tidak dikenal.']);
        }
        if ($bawaan) {
            $isian = ['instruksi' => PromptAi::BAWAAN[$bagian], 'gaya_bahasa' => null, 'aktif' => true];
        }

        $instruksi = trim((string) preg_replace('/[ \t]+/u', ' ', is_string($isian['instruksi'] ?? null) ? $isian['instruksi'] : ''));
        $gaya = is_string($isian['gaya_bahasa'] ?? null) ? trim((string) preg_replace('/\s+/u', ' ', $isian['gaya_bahasa'])) : null;
        $galat = PromptAi::galat($instruksi, $gaya);
        if ($gaya === '') {
            $galat['gaya_bahasa'] = 'Tulis gaya bahasa khusus, atau matikan pilihan gaya khusus.';
        }
        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        $lama = PromptAi::untuk($bagian);
        $aktif = filter_var($isian['aktif'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $baru = ['instruksi' => $instruksi, 'gaya_bahasa' => $gaya, 'aktif' => $aktif];
        $ringkas = fn (array $t) => ['instruksi' => $t['instruksi'] === PromptAi::BAWAAN[$bagian] ? 'bawaan' : 'diubah', 'gaya' => $t['gaya_bahasa'] ?? 'gaya baku', 'aktif' => $t['aktif'] ? 'aktif' : 'nonaktif'];
        $perubahan = RiwayatPengaturan::perubahan($ringkas($lama), $ringkas($baru), ['instruksi' => 'Instruksi', 'gaya' => 'Gaya bahasa', 'aktif' => 'Status']);
        if ($perubahan === null && $lama['instruksi'] !== $instruksi) {
            $perubahan = 'Instruksi diubah';
        }

        $samaBawaan = $instruksi === PromptAi::BAWAAN[$bagian] && $gaya === null && $aktif;
        self::tulis(function (array $d) use ($bagian, $baru, $samaBawaan) {
            if ($samaBawaan) {
                unset($d['bagian'][$bagian]);
            } else {
                $d['bagian'][$bagian] = $baru;
            }

            return $d;
        }, $perubahan !== null ? BagianAi::BAGIAN[$bagian]['judul'].' — '.$perubahan : null, $versi, $periksaVersi);

        return ['pesan' => 'Prompt '.mb_strtolower(BagianAi::BAGIAN[$bagian]['judul']).' tersimpan'.($aktif ? '.' : ' — tombol “Buat dengan AI” bagian ini dinonaktifkan.'), 'berubah' => $perubahan !== null];
    }

    /**
     * @param  Closure(array<string, mixed>): array<string, mixed>  $ubah
     *
     * @throws PengaturanBerubah
     * @throws RuntimeException
     */
    private static function tulis(Closure $ubah, ?string $keterangan, ?string $versi, bool $periksaVersi): void
    {
        $repo = app(PromptAiRepository::class);
        $lama = $repo->ambil();
        if ($periksaVersi && $lama['diperbarui_pada'] !== $versi) {
            throw new PengaturanBerubah(self::PESAN_BENTROK);
        }
        $repo->simpan(array_diff_key($ubah($lama), ['diperbarui_pada' => 1]));
        if ($keterangan !== null) {
            RiwayatPengaturan::catat('ubah_prompt_ai', $keterangan);
        }
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanAdmin(array $pengguna): void
    {
        if (($pengguna['role'] ?? null) !== 'admin') {
            throw new AuthorizationException('Hanya admin yang bisa mengubah Pengaturan AI.');
        }
    }
}
