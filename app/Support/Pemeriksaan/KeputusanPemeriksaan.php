<?php

namespace App\Support\Pemeriksaan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Kegiatan\PerbaikanKegiatan;
use App\Support\Kegiatan\RiwayatKunci;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\BatasPagu;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Keputusan pemeriksaan admin atas status dokumen kegiatan (PRD §16, F17–F18) — Setujui, Kembalikan, dan Buka kunci —
 * dipakai panel keputusan `Pemeriksaan\Periksa` dan endpoint admin `pemeriksaan.putuskan`:
 * - `tersedia($kegiatan, $pengguna)` = aksi AKSI yang dibuka StatusKegiatan::aksiUntuk(peran) dari status saat ini;
 * - `periksa($kegiatan, $aksi, $catatan, $pengguna)`: hanya admin, aksi tersedia (galat `keputusan`), catatan
 *   RiwayatStatus::validasiCatatan (wajib untuk kembalikan & buka kunci, ≤ MAKS_CATATAN; galat `catatan`), Setujui
 *   ditahan selama RAB melebihi pagu (BatasPagu), tahun anggaran terkunci ditolak (KunciKegiatan) → catatan rapi;
 * - `putuskan($id, $aksi, $catatan, $pengguna, $versi)`: `$versi` (= `versi()` saat ditampilkan) yang usang →
 *   PengaturanBerubah; status + entri RiwayatStatus::entri (beserta `sidik` PerbaikanKegiatan untuk kembalikan & buka
 *   kunci) disimpan bersama lewat KegiatanRepository, lalu draf catatan kegiatan itu dibuang (CatatanPemeriksaan); buka
 *   kunci tercatat di log RiwayatKunci beserta alasannya.
 */
final class KeputusanPemeriksaan
{
    public const AKSI = ['kembalikan', 'setujui', 'buka_kunci'];

    /** Pesan berhasil per aksi. */
    public const PESAN = [
        'setujui' => 'Kegiatan disetujui — leading dapat mencetak dan menandatangani dokumennya.',
        'kembalikan' => 'Kegiatan dikembalikan ke leading beserta catatan perbaikannya.',
        'buka_kunci' => 'Kunci dibuka — leading dapat memperbaiki data lalu mengajukan ulang.',
    ];

    /**
     * Sidik status, riwayat, dan versi data kegiatan — berubah bila kegiatan diubah di tempat lain.
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function versi(array $kegiatan): string
    {
        return md5((string) json_encode([$kegiatan['status'] ?? null, $kegiatan['riwayat_status'] ?? [], $kegiatan['diperbarui_pada'] ?? null]));
    }

    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array{role: string}  $pengguna
     * @return array<string, array{label: string, dari: list<StatusKegiatan>, ke: StatusKegiatan, peran: list<string>, gaya: string, ikon: string, konfirmasi: string}>
     */
    public static function tersedia(array $kegiatan, array $pengguna): array
    {
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;

        return array_intersect_key($status->aksiUntuk($pengguna['role']), array_flip(self::AKSI));
    }

    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array{role: string}  $pengguna
     * @return string Catatan yang dirapikan.
     *
     * @throws AuthorizationException Bukan admin.
     * @throws ValidationException Berkunci `keputusan` atau `catatan`.
     */
    public static function periksa(array $kegiatan, string $aksi, ?string $catatan, array $pengguna): string
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Keputusan pemeriksaan hanya untuk admin.');
        }
        if (! isset(self::tersedia($kegiatan, $pengguna)[$aksi])) {
            throw ValidationException::withMessages(['keputusan' => 'Keputusan ini tidak tersedia untuk status kegiatan saat ini.']);
        }

        $catatan = RiwayatStatus::rapikanCatatan((string) $catatan);
        Validator::make(['catatan' => $catatan], ...RiwayatStatus::validasiCatatan($aksi, 'catatan'))->validate();

        if ($aksi === 'setujui' && ($terlampaui = BatasPagu::terlampaui(AnggaranKegiatan::untuk($kegiatan))) !== []) {
            throw ValidationException::withMessages(['keputusan' => implode(' ', $terlampaui)]);
        }
        if (($tahun = KunciKegiatan::tahunTerkunci($kegiatan)) !== null) {
            throw ValidationException::withMessages(['keputusan' => KunciKegiatan::pesanTahun($tahun)]);
        }

        return $catatan;
    }

    /**
     * @param  array{id: int, name: string, role: string}  $pengguna
     * @return array{kegiatan: array<string, mixed>, entri: array<string, mixed>, pesan: string, versi: string}
     *
     * @throws RuntimeException Kegiatan tidak ditemukan.
     * @throws PengaturanBerubah Kegiatan berubah di tempat lain sejak $versi.
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public static function putuskan(int $id, string $aksi, ?string $catatan, array $pengguna, ?string $versi = null): array
    {
        $repo = app(KegiatanRepository::class);
        $kegiatan = $repo->temukan($id) ?? throw new RuntimeException('Kegiatan tidak ditemukan.');
        if ($versi !== null && $versi !== self::versi($kegiatan)) {
            throw new PengaturanBerubah('Kegiatan ini baru saja berubah di tempat lain (mis. pengajuan ditarik). Tampilan sudah dimuat ulang — periksa lagi sebelum memutuskan.');
        }

        $catatan = self::periksa($kegiatan, $aksi, $catatan, $pengguna);
        $ke = self::tersedia($kegiatan, $pengguna)[$aksi]['ke'];
        $kegiatan['status'] = $ke->value;
        $entri = RiwayatStatus::entri($aksi, $ke, $pengguna, $catatan);
        if (in_array($aksi, PerbaikanKegiatan::AKSI, true)) {
            // Titik awal perbaikan: bagian yang berubah sesudahnya ditandai untuk leading & pemeriksa berikutnya.
            $entri['sidik'] = PerbaikanKegiatan::sidik($kegiatan);
        }
        $kegiatan['riwayat_status'] = [...array_values((array) ($kegiatan['riwayat_status'] ?? [])), $entri];
        $repo->simpan($kegiatan, $id);
        CatatanPemeriksaan::buang($id);
        if ($aksi === 'buka_kunci') {
            RiwayatKunci::catat($id, 'buka_kunci', $catatan, $pengguna, $entri['pada']);
        }
        $tersimpan = $repo->temukan($id);

        return ['kegiatan' => $tersimpan, 'entri' => $entri, 'pesan' => self::PESAN[$aksi], 'versi' => self::versi($tersimpan)];
    }
}
