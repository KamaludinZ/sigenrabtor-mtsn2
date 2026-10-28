<?php

namespace App\Support\Kegiatan;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Pemeriksaan\KeputusanPemeriksaan;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Mengunci & membuka data final (PRD F18, aturan 9, N7) — dipakai panel status Data Kegiatan (`Form::ubahStatus`
 * finalkan/buka_kunci) dan endpoint `kegiatan.kunci` (baca), `kegiatan.kunci.pasang`, `kegiatan.kunci.buka`:
 * - kunci (`finalkan`): kegiatan Disetujui yang boleh dibuka akun itu (AksesKegiatan, aksi StatusKegiatan::aksiUntuk),
 *   tahun anggaran terbuka, dan arsip final semua sumber dana wajib sudah ada (ArsipFinal::kurang → galat `arsip`) →
 *   Final + entri riwayat `finalkan` lewat AlurStatus::terapkan (jejak kunci KunciDokumen: siapa & kapan; dokumen
 *   tersimpan disamakan). Unggahan arsip yang melengkapi
 *   menguncinya otomatis (FinalOtomatis);
 * - buka (`buka_kunci`): hanya admin, alasan wajib — lewat KeputusanPemeriksaan::putuskan (catatan & `sidik`
 *   perbaikan, status kembali Dikembalikan; arsip lama tetap tersimpan);
 * - `$versi` (= KeputusanPemeriksaan::versi saat ditampilkan) usang → PengaturanBerubah;
 * - keduanya tercatat di log RiwayatKunci (`kunci_final`, `buka_kunci`).
 */
final class KunciFinal
{
    public const AKSI = ['finalkan', 'buka_kunci'];

    public const PESAN_KUNCI = 'Kegiatan dikunci final — data tidak bisa diubah sampai admin membuka kunci.';

    /** @param  array<string, mixed>  $kegiatan */
    public static function versi(array $kegiatan): string
    {
        return KeputusanPemeriksaan::versi($kegiatan);
    }

    /**
     * Alasan kegiatan tidak bisa dikunci final oleh $pengguna; null = bisa.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id: int, role: string, leadings?: list<string>}  $pengguna
     */
    public static function alasanKunci(array $kegiatan, array $pengguna): ?string
    {
        return self::penghalangKunci($kegiatan, $pengguna)[1] ?? null;
    }

    /**
     * Alasan kunci kegiatan tidak bisa dibuka oleh $pengguna; null = bisa.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{role: string}  $pengguna
     */
    public static function alasanBuka(array $kegiatan, array $pengguna): ?string
    {
        $status = self::status($kegiatan);

        return match (true) {
            $pengguna['role'] !== 'admin' => 'Hanya admin yang dapat membuka kunci kegiatan final.',
            $status !== StatusKegiatan::Final => "Kegiatan berstatus {$status->label()} — tidak sedang terkunci final.",
            ($tahun = KunciKegiatan::tahunTerkunci($kegiatan)) !== null => KunciKegiatan::pesanTahun($tahun),
            default => null,
        };
    }

    /**
     * Data endpoint `kegiatan.kunci`.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id: int, role: string, leadings?: list<string>}  $pengguna
     * @return array<string, mixed>
     */
    public static function data(array $kegiatan, array $pengguna): array
    {
        $status = self::status($kegiatan);
        $jejak = KunciDokumen::dari($kegiatan);

        return [
            'kegiatan_id' => (int) $kegiatan['id'],
            'status' => $status->value,
            'label_status' => $status->label(),
            'terkunci' => $status === StatusKegiatan::Final,
            'jejak' => $jejak === null ? null : ['pada' => $jejak->pada, 'oleh' => $jejak->oleh, 'kalimat' => $jejak->kalimat()],
            'kunci' => ['tersedia' => ($alasan = self::alasanKunci($kegiatan, $pengguna)) === null, 'alasan' => $alasan, 'arsip_kurang' => self::arsipKurang($kegiatan)],
            'buka' => ['tersedia' => ($alasan = self::alasanBuka($kegiatan, $pengguna)) === null, 'alasan' => $alasan, 'alasan_wajib' => true, 'maks_alasan' => RiwayatStatus::MAKS_CATATAN],
            'versi' => self::versi($kegiatan),
        ];
    }

    /**
     * @param  array{id: int, name: string, role: string, leadings?: list<string>}  $pengguna
     * @return array{kegiatan: array<string, mixed>, entri: array<string, mixed>, pesan: string, versi: string}
     *
     * @throws RuntimeException Kegiatan tidak ditemukan.
     * @throws PengaturanBerubah
     * @throws AuthorizationException
     * @throws ValidationException Berkunci `arsip` (arsip final belum lengkap).
     */
    public static function kunci(int $id, array $pengguna, ?string $versi = null): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($id) ?? throw new RuntimeException('Kegiatan tidak ditemukan.');
        self::pastikanVersi($kegiatan, $versi);
        if (($penghalang = self::penghalangKunci($kegiatan, $pengguna)) !== null) {
            throw $penghalang[0] === 'arsip' ? ValidationException::withMessages(['arsip' => $penghalang[1]]) : new AuthorizationException($penghalang[1]);
        }

        $hasil = AlurStatus::terapkan($kegiatan, 'finalkan', StatusKegiatan::Final, $pengguna);
        RiwayatKunci::catat($id, 'kunci_final', '', $pengguna, $hasil['entri']['pada']);

        return $hasil + ['pesan' => self::PESAN_KUNCI, 'versi' => self::versi($hasil['kegiatan'])];
    }

    /**
     * @param  array{id: int, name: string, role: string}  $pengguna
     * @return array{kegiatan: array<string, mixed>, entri: array<string, mixed>, pesan: string, versi: string}
     *
     * @throws RuntimeException Kegiatan tidak ditemukan.
     * @throws PengaturanBerubah
     * @throws AuthorizationException
     * @throws ValidationException Berkunci `catatan` (alasan wajib, ≤ RiwayatStatus::MAKS_CATATAN).
     */
    public static function buka(int $id, ?string $alasan, array $pengguna, ?string $versi = null): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($id) ?? throw new RuntimeException('Kegiatan tidak ditemukan.');
        self::pastikanVersi($kegiatan, $versi);
        if (($tolak = self::alasanBuka($kegiatan, $pengguna)) !== null) {
            throw new AuthorizationException($tolak);
        }

        return KeputusanPemeriksaan::putuskan($id, 'buka_kunci', $alasan, $pengguna);
    }

    /**
     * Penghalang pertama mengunci: [jenis akses|status|tahun|arsip, pesan]; null = bisa.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id: int, role: string, leadings?: list<string>}  $pengguna
     * @return array{0: string, 1: string}|null
     */
    private static function penghalangKunci(array $kegiatan, array $pengguna): ?array
    {
        $status = self::status($kegiatan);

        return match (true) {
            ! AksesKegiatan::bolehLihat($kegiatan, $pengguna) => ['akses', AksesKegiatan::PESAN_TOLAK],
            ! isset($status->aksiUntuk($pengguna['role'])['finalkan']) => ['status', "Kegiatan berstatus {$status->label()} — hanya kegiatan Disetujui yang bisa dikunci final."],
            ($tahun = KunciKegiatan::tahunTerkunci($kegiatan)) !== null => ['tahun', KunciKegiatan::pesanTahun($tahun)],
            ($kurang = self::arsipKurang($kegiatan)) !== [] => ['arsip', ArsipFinal::pesanKurang($kurang)],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $kegiatan
     * @return list<string>
     */
    private static function arsipKurang(array $kegiatan): array
    {
        return ArsipFinal::kurang($kegiatan, app(ArsipFinalRepository::class)->untukKegiatan((int) ($kegiatan['id'] ?? 0)));
    }

    /** @param  array<string, mixed>  $kegiatan */
    private static function pastikanVersi(array $kegiatan, ?string $versi): void
    {
        if ($versi !== null && $versi !== self::versi($kegiatan)) {
            throw new PengaturanBerubah('Kegiatan ini baru saja berubah di tempat lain. Muat ulang lalu periksa lagi sebelum mengunci atau membuka kunci.');
        }
    }

    /** @param  array<string, mixed>  $kegiatan */
    private static function status(array $kegiatan): StatusKegiatan
    {
        return StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
    }
}
