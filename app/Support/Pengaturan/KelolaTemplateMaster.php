<?php

namespace App\Support\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Support\Dokumen\DaftarTemplate;
use App\Support\Dokumen\DaftarTemplateMaster;
use App\Support\Dokumen\PilihanTemplate;
use App\Support\Kegiatan\TahunKegiatan;
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
 *   `ubah_default_master`;
 * - `dampak($id)` — peringatan sebelum menonaktifkan: default yang berpindah (`pengganti`), kegiatan yang memilihnya
 *   (`dipilih`, tetap memakainya — dokumen lama utuh), yang memakainya sebagai default leading (`beralih` ke default
 *   pengganti/aturan berikutnya), yang memakainya lewat aturan Format Seragam (`aturan`, tetap sampai aturannya diganti),
 *   dan aturan pilihan template yang menunjuknya (`pilihan`);
 * - `ubahStatus($id, $aktif, $pengguna)` — nonaktif: tidak muncul di pilihan baru & tidak lagi default (pindah ke template
 *   aktif terbaru pemilik × jenis); aktif kembali: default bila pemilik × jenis belum punya; riwayat `ubah_status_master`.
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
     * Dampak menonaktifkan template (tahun template itu).
     *
     * @return array{default: bool, pengganti: string|null, dipilih: list<array{id: int, nama: string}>, beralih: list<array{id: int, nama: string}>, aturan: list<array{id: int, nama: string}>, pilihan: list<string>}
     *
     * @throws RuntimeException
     */
    public static function dampak(string $id): array
    {
        $repo = app(TemplateDokumenRepository::class);
        $master = $repo->temukan($id) ?? throw new RuntimeException(self::PESAN_TIDAK_ADA);
        $tahun = (int) $master['tahun'];
        $sama = fn (array $t) => $t['id'] !== $id && $t['jenis'] === $master['jenis'] && ($t['leading'] ?? null) === ($master['leading'] ?? null) && ($t['aktif'] ?? true);
        $pengganti = $master['is_default'] ? (collect($repo->semua($tahun))->filter($sama)->sortByDesc('diperbarui_pada')->first()['nama'] ?? null) : null;

        // Kegiatan pemakai: dipilih (tetap memakainya), lewat default leading (beralih), lewat aturan Format Seragam (tetap).
        $pemakai = ['kegiatan' => [], 'default_leading' => [], 'aturan' => []];
        foreach (app(KegiatanRepository::class)->semua() as $k) {
            $berlaku = TahunKegiatan::dari($k) === $tahun ? PilihanTemplate::untukKegiatan($k, $tahun) : null;
            if ($berlaku !== null && $berlaku['kak'] === $id) {
                $pemakai[isset($pemakai[$berlaku['asal']]) ? $berlaku['asal'] : 'aturan'][] = ['id' => (int) $k['id'], 'nama' => (string) ($k['nama_kegiatan'] ?? '')];
            }
        }
        $pilihan = array_map(fn (array $p) => $p['label_jenis'].($p['leading'] ? ' · leading '.$p['leading'] : ''), DaftarTemplate::pemakaian(PilihanTemplate::data($tahun))['kak'][$id] ?? []);

        return ['default' => (bool) $master['is_default'], 'pengganti' => $pengganti, 'dipilih' => $pemakai['kegiatan'], 'beralih' => $pemakai['default_leading'], 'aturan' => $pemakai['aturan'], 'pilihan' => array_values($pilihan)];
    }

    /**
     * @param  array{role: string}  $pengguna
     * @return array{master: array<string, mixed>, berubah: bool, pengganti: string|null}
     *
     * @throws AuthorizationException
     * @throws RuntimeException
     */
    public static function ubahStatus(string $id, bool $aktif, array $pengguna): array
    {
        $master = self::temukan($id, $pengguna);
        if (($master['aktif'] ?? true) === $aktif) {
            return ['master' => $master, 'berubah' => false, 'pengganti' => null];
        }
        $repo = app(TemplateDokumenRepository::class);
        $repo->ubahStatus($id, $aktif);
        $kini = $repo->temukan($id);
        $pengganti = null;
        if (! $aktif && $master['is_default']) {
            $pengganti = collect($repo->semua((int) $master['tahun']))->first(fn (array $t) => $t['jenis'] === $master['jenis'] && ($t['leading'] ?? null) === ($master['leading'] ?? null) && $t['is_default'])['nama'] ?? null;
        }
        RiwayatPengaturan::catat('ubah_status_master', "Master “{$master['nama']}” ({$master['jenis']}) ".($aktif ? 'diaktifkan kembali'.($kini['is_default'] ? ' dan menjadi default' : '') : 'dinonaktifkan'
            .($master['is_default'] ? ' — default '.($pengganti !== null ? "pindah ke “{$pengganti}”" : 'kosong') : '')).'.');

        return ['master' => $kini, 'berubah' => true, 'pengganti' => $pengganti];
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
