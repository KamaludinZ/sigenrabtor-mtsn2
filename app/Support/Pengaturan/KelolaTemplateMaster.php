<?php

namespace App\Support\Pengaturan;

use App\Contracts\TemplateDokumenRepository;
use App\Support\Dokumen\DaftarTemplateMaster;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Kelola template master yang sudah diunggah (PRD Fase 5 "Template KAK/TOR Variabel (Multi per Leading)": nama & catatan
 * versi yang jelas, satu default per pemilik × jenis; hanya admin, tahun terkunci hanya dilihat):
 * - `ubah($id, {nama, catatan}, $pengguna)` — nama wajib ≤ 100, catatan satu baris ≤ UnggahMasterTemplate::MAKS_CATATAN
 *   (kosong = tanpa catatan); tanpa perubahan tidak ditulis; riwayat `ubah_master` "Nama: lama → baru; Catatan versi: …";
 * - `jadikanDefault($id, $pengguna)` — template aktif menjadi default pemilik × jenis-nya (default lama dilepas); riwayat
 *   `ubah_default_master`.
 * Template tidak ada → RuntimeException `PESAN_TIDAK_ADA`; galat isian → ValidationException berkunci `nama`/`catatan`/
 * `default`.
 */
final class KelolaTemplateMaster
{
    public const PESAN_TIDAK_ADA = 'Template tidak ditemukan — mungkin sudah dihapus.';

    /**
     * @param  array{nama?: mixed, catatan?: mixed}  $isian
     * @param  array{role: string}  $pengguna
     * @return array{master: array<string, mixed>, ringkasan: string|null}
     *
     * @throws ValidationException
     * @throws AuthorizationException
     * @throws RuntimeException
     */
    public static function ubah(string $id, array $isian, array $pengguna): array
    {
        $master = self::temukan($id, $pengguna);
        $nama = trim((string) preg_replace('/\s+/u', ' ', (string) ($isian['nama'] ?? '')));
        $catatan = UnggahMasterTemplate::rapikanAtribut(['catatan' => $isian['catatan'] ?? null])['catatan'];
        $aturan = UnggahMasterTemplate::aturan();
        Validator::make(['nama' => $nama, 'catatan' => $catatan], ['nama' => $aturan['nama'], 'catatan' => $aturan['catatan']], UnggahMasterTemplate::pesan())->validate();

        $ringkasan = RiwayatPengaturan::perubahan(
            ['nama' => $master['nama'], 'catatan' => $master['catatan'] ?? null],
            ['nama' => $nama, 'catatan' => $catatan],
            ['nama' => 'Nama', 'catatan' => 'Catatan versi'],
        );
        if ($ringkasan === null) {
            return ['master' => $master, 'ringkasan' => null];
        }

        app(TemplateDokumenRepository::class)->ubah($id, $nama, $catatan);
        RiwayatPengaturan::catat('ubah_master', "Master “{$master['nama']}” ({$master['jenis']}) — {$ringkasan}.");

        return ['master' => app(TemplateDokumenRepository::class)->temukan($id), 'ringkasan' => $ringkasan];
    }

    /**
     * @param  array{role: string}  $pengguna
     * @return array{master: array<string, mixed>, sebelumnya: string|null, berubah: bool}
     *
     * @throws ValidationException Template nonaktif (`default`).
     * @throws AuthorizationException
     * @throws RuntimeException
     */
    public static function jadikanDefault(string $id, array $pengguna): array
    {
        $master = self::temukan($id, $pengguna);
        if ($master['is_default']) {
            return ['master' => $master, 'sebelumnya' => null, 'berubah' => false];
        }
        if (! ($master['aktif'] ?? true)) {
            throw ValidationException::withMessages(['default' => 'Template nonaktif tidak bisa menjadi default — aktifkan dulu.']);
        }

        $repo = app(TemplateDokumenRepository::class);
        $sebelumnya = collect($repo->semua($master['tahun']))->first(fn (array $t) => $t['jenis'] === $master['jenis'] && ($t['leading'] ?? null) === ($master['leading'] ?? null) && $t['is_default']);
        $repo->jadikanDefault($id);
        $pemilik = DaftarTemplateMaster::labelPemilik($master['leading'] ?? null);
        RiwayatPengaturan::catat('ubah_default_master', "Default {$master['jenis']} {$pemilik}: ".($sebelumnya['nama'] ?? '—')." → {$master['nama']}.");

        return ['master' => $repo->temukan($id), 'sebelumnya' => $sebelumnya['nama'] ?? null, 'berubah' => true];
    }

    /**
     * @param  array{role: string}  $pengguna
     * @return array<string, mixed>
     */
    private static function temukan(string $id, array $pengguna): array
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Template dokumen diatur admin.');
        }
        $master = app(TemplateDokumenRepository::class)->temukan($id) ?? throw new RuntimeException(self::PESAN_TIDAK_ADA);
        if (TahunDipilih::terkunci((int) $master['tahun'])) {
            throw new AuthorizationException("Tahun Anggaran {$master['tahun']} terkunci — templatenya hanya bisa dilihat.");
        }

        return $master;
    }
}
