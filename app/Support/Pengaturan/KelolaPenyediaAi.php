<?php

namespace App\Support\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Support\Kegiatan\KesehatanPenyediaAi;
use App\Support\Kegiatan\KlienLlmHttp;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Simpan konfigurasi penyedia AI di server (PRD §21, F24; khusus admin) — dipakai tab Penyedia halaman Pengaturan AI
 * (`simpanLokal`/`simpanCloud`/`simpanPilihan`) dan endpoint admin `pengaturan.ai.penyedia*`:
 * - `simpanLokal($isian)`: Ollama {base_url, model, batas_waktu, peran} lewat AturanPenyediaAi::lokal + terapkanPeran;
 * - `simpanCloud($penyedia, $isian, tetapSimpan:)`: Claude/OpenAI/Gemini {model, kunci?, hapus_kunci?, base_url?, peran}
 *   lewat AturanPenyediaAi::cloud — kunci dienkripsi (Crypt) + 4 karakter akhir, tidak pernah dicatat; awalan kunci tak
 *   lazim = `peringatan` (tidak tersimpan) sampai dikirim ulang dengan `tetapSimpan`;
 * - `pilih($aktif, $cadangan)`: AturanPenyediaAi::pilih (keduanya siap, cadangan butuh aktif & berbeda);
 * - setiap simpan: versi = `diperbarui_pada` saat formulir dibuka (`pastikanVersi()` → PengaturanBerubah), lalu
 *   `tulis()` (PengaturanAiRepository::simpan, RuntimeException naik; riwayat `ubah_penyedia_ai` hanya bila berubah —
 *   ringkasan "Label: lama → baru" + catatan kunci diisi/diganti/dihapus). Galat isian → ValidationException berkunci
 *   isian; bukan admin → AuthorizationException.
 * `data()` = bentuk JSON konfigurasi penyedia tanpa kunci API (beserta `kesehatan` failover KesehatanPenyediaAi).
 */
final class KelolaPenyediaAi
{
    public const PESAN_BENTROK = 'Pengaturan AI baru diubah di tempat lain — tampilan dimuat ulang, isian Anda tetap. Periksa lalu simpan lagi.';

    /** Label ringkasan riwayat per isian. */
    public const LABEL_LOKAL = ['base_url' => 'Alamat server', 'model' => 'Model', 'batas_waktu' => 'Batas waktu (detik)'];

    public const LABEL_CLOUD = ['model' => 'Model', 'base_url' => 'Alamat API'];

    /**
     * Konfigurasi penyedia untuk API: versi, pilihan aktif & cadangan, dan tiap penyedia (tanpa kunci API).
     *
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        $ai = app(PengaturanAiRepository::class)->ambil();

        return [
            'versi' => $ai['diperbarui_pada'],
            'provider_aktif' => $ai['provider_aktif'],
            'provider_cadangan' => $ai['provider_cadangan'],
            'nama_aktif' => PenyediaAi::nama($ai['provider_aktif']),
            'nama_cadangan' => PenyediaAi::nama($ai['provider_cadangan']),
            'penyedia' => array_map(function (string $kunci) use ($ai) {
                $info = PenyediaAi::DAFTAR[$kunci];
                $p = $ai['penyedia'][$kunci] ?? [];
                $siap = AturanPenyediaAi::siap($ai, $kunci);

                return [
                    'kunci' => $kunci,
                    'nama' => $info['nama'],
                    'jenis' => $info['jenis'],
                    'butuh_kunci' => $info['butuh_kunci'],
                    'base_url' => filled($p['base_url'] ?? null) ? (string) $p['base_url'] : null,
                    'alamat_bawaan' => $info['base_url'] ?? KlienLlmHttp::ALAMAT_RESMI[$kunci] ?? null,
                    'model' => filled($p['model'] ?? null) ? (string) $p['model'] : null,
                    'batas_waktu' => isset($p['batas_waktu']) ? (int) $p['batas_waktu'] : null,
                    'ada_kunci' => (bool) ($p['ada_kunci'] ?? false),
                    'kunci_akhir' => ($p['ada_kunci'] ?? false) ? ($p['kunci_akhir'] ?? null) : null,
                    'peran' => AturanPenyediaAi::peranDari($ai, $kunci),
                    'siap' => $siap['siap'],
                    'kurang' => $siap['kurang'],
                    // Kegagalan nyata terakhir & jeda failover (KesehatanPenyediaAi); null = sehat.
                    'kesehatan' => KesehatanPenyediaAi::status($kunci),
                ];
            }, array_keys(PenyediaAi::DAFTAR)),
        ];
    }

    /**
     * @param  array<string, mixed>  $isian  {base_url, model, batas_waktu, peran}
     * @param  array{role: string}  $pengguna
     * @return array{pesan: string, berubah: bool}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     * @throws PengaturanBerubah
     * @throws RuntimeException
     */
    public static function simpanLokal(array $isian, array $pengguna, ?string $versi, bool $periksaVersi = true): array
    {
        self::pastikanAdmin($pengguna);
        ['data' => $data, 'galat' => $galat] = AturanPenyediaAi::lokal($isian);
        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }

        $lama = app(PengaturanAiRepository::class)->ambil();
        if ($periksaVersi) {
            self::pastikanVersi($lama, $versi);
        }
        $baru = AturanPenyediaAi::terapkanPeran($lama, 'ollama', $data['peran']);
        $baru['penyedia']['ollama'] = ['base_url' => $data['base_url'], 'model' => $data['model'], 'batas_waktu' => $data['batas_waktu']] + ($lama['penyedia']['ollama'] ?? []);

        $ringkas = fn (array $p) => [
            'base_url' => $p['penyedia']['ollama']['base_url'] ?? '', 'model' => $p['penyedia']['ollama']['model'] ?? '', 'batas_waktu' => $p['penyedia']['ollama']['batas_waktu'] ?? null,
        ] + self::ringkasPeran($p);
        $berubah = self::tulis($lama, $baru, $ringkas, self::LABEL_LOKAL, 'Server sekolah (Ollama) — ');
        $naik = $data['peran'] === 'cadangan' && $baru['provider_aktif'] === 'ollama';

        return ['pesan' => $naik ? 'Konfigurasi server sekolah tersimpan — belum ada penyedia aktif, jadi server sekolah dipakai sebagai penyedia aktif.' : 'Konfigurasi server sekolah tersimpan.', 'berubah' => $berubah];
    }

    /**
     * @param  array<string, mixed>  $isian  {model, kunci?, hapus_kunci?, base_url?, peran}
     * @param  array{role: string}  $pengguna
     * @return array{tersimpan: bool, peringatan: list<string>, pesan: string, berubah: bool}
     *
     * @throws AuthorizationException
     * @throws ValidationException Juga `penyedia` bila bukan penyedia cloud.
     * @throws PengaturanBerubah
     * @throws RuntimeException
     */
    public static function simpanCloud(string $penyedia, array $isian, array $pengguna, ?string $versi, bool $periksaVersi = true, bool $tetapSimpan = false): array
    {
        self::pastikanAdmin($pengguna);
        if ((PenyediaAi::DAFTAR[$penyedia]['jenis'] ?? null) !== 'cloud') {
            throw ValidationException::withMessages(['penyedia' => 'Penyedia API cloud tidak dikenal.']);
        }

        $lama = app(PengaturanAiRepository::class)->ambil();
        $tersimpan = $lama['penyedia'][$penyedia] ?? [];
        ['data' => $data, 'galat' => $galat, 'peringatan' => $peringatan] = AturanPenyediaAi::cloud($penyedia, $isian, $tersimpan);
        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }
        if ($peringatan !== [] && ! $tetapSimpan) {
            return ['tersimpan' => false, 'peringatan' => $peringatan, 'pesan' => 'Periksa peringatan kunci API lalu simpan lagi bila sudah yakin.', 'berubah' => false];
        }
        if ($periksaVersi) {
            self::pastikanVersi($lama, $versi);
        }

        $konfigurasi = ['model' => $data['model'], 'base_url' => $data['base_url']] + $tersimpan;
        $catatanKunci = null;
        if ($data['kunci'] !== null) {
            $konfigurasi = ['ada_kunci' => true, 'kunci_akhir' => mb_substr($data['kunci'], -4), 'kunci_terenkripsi' => Crypt::encryptString($data['kunci'])] + $konfigurasi;
            $catatanKunci = 'Kunci API: '.(($tersimpan['ada_kunci'] ?? false) ? 'diganti' : 'diisi');
        } elseif ($data['hapus_kunci'] && ($tersimpan['ada_kunci'] ?? false)) {
            $konfigurasi = ['ada_kunci' => false, 'kunci_akhir' => null, 'kunci_terenkripsi' => null] + $konfigurasi;
            $catatanKunci = 'Kunci API: dihapus';
        }

        $baru = AturanPenyediaAi::terapkanPeran($lama, $penyedia, $data['peran']);
        $baru['penyedia'][$penyedia] = $konfigurasi;
        $ringkas = fn (array $p) => ['model' => $p['penyedia'][$penyedia]['model'] ?? '', 'base_url' => ($p['penyedia'][$penyedia]['base_url'] ?? '') ?: 'alamat resmi'] + self::ringkasPeran($p);
        $berubah = self::tulis($lama, $baru, $ringkas, self::LABEL_CLOUD, PenyediaAi::nama($penyedia).' — ', $catatanKunci);
        $naik = $data['peran'] === 'cadangan' && $baru['provider_aktif'] === $penyedia;

        return ['tersimpan' => true, 'peringatan' => [], 'pesan' => 'Konfigurasi '.PenyediaAi::nama($penyedia).' tersimpan'.($naik ? ' — belum ada penyedia aktif, jadi dipakai sebagai penyedia aktif.' : '.'), 'berubah' => $berubah];
    }

    /**
     * Penyedia aktif & cadangan ('' / null = tanpa).
     *
     * @param  array{role: string}  $pengguna
     * @return array{pesan: string, berubah: bool}
     *
     * @throws AuthorizationException
     * @throws ValidationException Berkunci `aktif`/`cadangan`.
     * @throws PengaturanBerubah
     * @throws RuntimeException
     */
    public static function pilih(?string $aktif, ?string $cadangan, array $pengguna, ?string $versi, bool $periksaVersi = true): array
    {
        self::pastikanAdmin($pengguna);
        $lama = app(PengaturanAiRepository::class)->ambil();
        ['pengaturan' => $baru, 'galat' => $galat] = AturanPenyediaAi::pilih($lama, $aktif, $cadangan);
        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }
        if ($periksaVersi) {
            self::pastikanVersi($lama, $versi);
        }

        $berubah = self::tulis($lama, $baru, fn (array $p) => self::ringkasPeran($p), [], '');

        return ['pesan' => $baru['provider_aktif'] === null
            ? 'Tanpa penyedia AI — tombol “Buat dengan AI” memakai saran contoh (tiruan).'
            : 'Penyedia AI tersimpan: '.PenyediaAi::nama($baru['provider_aktif']).($baru['provider_cadangan'] ? ', cadangan '.PenyediaAi::nama($baru['provider_cadangan']) : ', tanpa cadangan').'.', 'berubah' => $berubah];
    }

    /** @throws PengaturanBerubah Pengaturan berubah sejak versi $versi dimuat. */
    public static function pastikanVersi(array $lama, ?string $versi): void
    {
        if ($lama['diperbarui_pada'] !== $versi) {
            throw new PengaturanBerubah(self::PESAN_BENTROK);
        }
    }

    /**
     * Tulis pengaturan $baru lalu catat riwayat bila ringkasannya berubah; true = ada perubahan tercatat.
     *
     * @param  array<string, mixed>  $lama
     * @param  array<string, mixed>  $baru
     * @param  Closure(array<string, mixed>): array<string, mixed>  $ringkas
     * @param  array<string, string>  $label
     * @param  string|null  $tambahan  Catatan riwayat tambahan (mis. "Kunci API: diganti").
     *
     * @throws RuntimeException Penyimpanan gagal (riwayat tidak dicatat).
     */
    public static function tulis(array $lama, array $baru, Closure $ringkas, array $label, string $awalan, ?string $tambahan = null, string $aksi = 'ubah_penyedia_ai'): bool
    {
        unset($baru['diperbarui_pada']);
        $perubahan = RiwayatPengaturan::perubahan($ringkas($lama), $ringkas($baru), $label + ['aktif' => 'Penyedia aktif', 'cadangan' => 'Penyedia cadangan']);
        // Kunci API tidak pernah ikut dicatat; hanya keterangan diisi/diganti/dihapus.
        $perubahan = implode('; ', array_filter([$perubahan, $tambahan])) ?: null;
        app(PengaturanAiRepository::class)->simpan($baru);
        if ($perubahan !== null) {
            RiwayatPengaturan::catat($aksi, $awalan.$perubahan);
        }

        return $perubahan !== null;
    }

    /** @return array{aktif: string, cadangan: string} */
    public static function ringkasPeran(array $pengaturan): array
    {
        return ['aktif' => PenyediaAi::nama($pengaturan['provider_aktif']) ?? 'belum dipilih', 'cadangan' => PenyediaAi::nama($pengaturan['provider_cadangan']) ?? 'belum dipilih'];
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanAdmin(array $pengguna): void
    {
        if (($pengguna['role'] ?? null) !== 'admin') {
            throw new AuthorizationException('Hanya admin yang bisa mengubah Pengaturan AI.');
        }
    }
}
