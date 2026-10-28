<?php

namespace App\Support\Pemeriksaan;

use App\Contracts\CatatanPemeriksaanRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Catatan pemeriksaan admin (PRD §16 F17, tabel PRD `pemeriksaan_dokumen`) — dipakai panel keputusan
 * `Pemeriksaan\Periksa` dan endpoint admin `pemeriksaan.catatan` (baca) & `pemeriksaan.catatan.simpan` (simpan draf):
 * - draf (CatatanPemeriksaanRepository): catatan yang sedang ditulis sebelum keputusan, satu per kegiatan, `versi()` =
 *   waktu simpan bermikrodetik; dibuang KeputusanPemeriksaan::putuskan begitu keputusan tersimpan;
 * - `riwayat($kegiatan)`: catatan keputusan yang sudah dikirim (entri riwayat_status aksi KeputusanPemeriksaan::AKSI oleh
 *   admin, terbaru dulu; keputusan sama dengan view `pemeriksaan_dokumen`);
 * - `simpan($id, $catatan, $pengguna, $versi)`: hanya admin, hanya selama ada keputusan yang tersedia (`galat()`: status
 *   & tahun anggaran terkunci), dirapikan RiwayatStatus::rapikanCatatan & ≤ MAKS_CATATAN (galat `catatan`), kosong =
 *   draf dihapus, teks sama tidak ditulis ulang, `$versi` usang (draf diubah admin lain) → PengaturanBerubah.
 */
final class CatatanPemeriksaan
{
    /** Aksi keputusan di riwayat → keputusan pemeriksaan (= view pemeriksaan_dokumen). */
    public const KEPUTUSAN = ['setujui' => 'disetujui', 'kembalikan' => 'dikembalikan', 'buka_kunci' => 'dibuka'];

    /** @param  array{diperbarui_pada?: string}|null  $draf */
    public static function versi(?array $draf): string
    {
        return $draf['diperbarui_pada'] ?? '';
    }

    /** @return array{catatan: string, user_id: int|null, oleh: string, diperbarui_pada: string}|null */
    public static function draf(int $kegiatanId): ?array
    {
        return app(CatatanPemeriksaanRepository::class)->draf($kegiatanId);
    }

    /**
     * @param  array<string, mixed>  $kegiatan
     * @return list<array{aksi: string, keputusan: string, label: string, catatan: string|null, oleh: string, pada: string}>
     */
    public static function riwayat(array $kegiatan): array
    {
        $keputusan = array_filter((array) ($kegiatan['riwayat_status'] ?? []), fn (array $e) => isset(self::KEPUTUSAN[$e['aksi'] ?? '']) && ($e['peran'] ?? null) === 'admin');

        return array_values(array_reverse(array_map(fn (array $e) => [
            'aksi' => $e['aksi'],
            'keputusan' => self::KEPUTUSAN[$e['aksi']],
            'label' => StatusKegiatan::labelRiwayat($e['aksi']),
            'catatan' => ($e['catatan'] ?? '') === '' ? null : $e['catatan'],
            'oleh' => $e['oleh'],
            'pada' => $e['pada'],
        ], $keputusan)));
    }

    /**
     * Alasan catatan tidak bisa disimpan untuk kegiatan ini; null = boleh.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{role: string}  $pengguna
     */
    public static function galat(array $kegiatan, array $pengguna): ?string
    {
        if (KeputusanPemeriksaan::tersedia($kegiatan, $pengguna) === []) {
            $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;

            return "Kegiatan berstatus {$status->label()} tidak sedang menunggu keputusan pemeriksaan — catatan tidak disimpan.";
        }
        if (($tahun = KunciKegiatan::tahunTerkunci($kegiatan)) !== null) {
            return KunciKegiatan::pesanTahun($tahun);
        }

        return null;
    }

    /**
     * Data catatan satu kegiatan untuk endpoint `pemeriksaan.catatan`.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{role: string}  $pengguna
     * @return array<string, mixed>
     */
    public static function untuk(array $kegiatan, array $pengguna): array
    {
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
        $draf = self::draf((int) $kegiatan['id']);
        $tersedia = KeputusanPemeriksaan::tersedia($kegiatan, $pengguna);

        return [
            'kegiatan_id' => (int) $kegiatan['id'],
            'status' => $status->value,
            'label_status' => $status->label(),
            'dapat_dicatat' => ($alasan = self::galat($kegiatan, $pengguna)) === null,
            'alasan' => $alasan,
            'keputusan_tersedia' => array_map(fn (string $aksi) => ['aksi' => $aksi, 'label' => $tersedia[$aksi]['label'], 'catatan_wajib' => isset(RiwayatStatus::CATATAN_WAJIB[$aksi])], array_keys($tersedia)),
            'batas' => RiwayatStatus::MAKS_CATATAN,
            'draf' => $draf,
            'versi' => self::versi($draf),
            'riwayat' => self::riwayat($kegiatan),
        ];
    }

    /**
     * @param  array{id: int, name: string, role: string}  $pengguna
     * @return array{draf: array<string, mixed>|null, berubah: bool, versi: string, pesan: string}
     *
     * @throws AuthorizationException Bukan admin.
     * @throws RuntimeException Kegiatan tidak ditemukan.
     * @throws PengaturanBerubah Draf diubah di tempat lain sejak $versi.
     * @throws ValidationException Berkunci `catatan`.
     */
    public static function simpan(int $id, ?string $catatan, array $pengguna, ?string $versi = null): array
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Catatan pemeriksaan hanya untuk admin.');
        }
        $kegiatan = app(KegiatanRepository::class)->temukan($id) ?? throw new RuntimeException('Kegiatan tidak ditemukan.');
        $repo = app(CatatanPemeriksaanRepository::class);
        $draf = $repo->draf($id);
        if ($versi !== null && $versi !== self::versi($draf)) {
            throw new PengaturanBerubah($draf === null
                ? 'Draf catatan kegiatan ini baru saja dikosongkan di tempat lain (mis. keputusan sudah dikirim). Periksa lagi sebelum menyimpan.'
                : "Draf catatan kegiatan ini baru saja diubah {$draf['oleh']} di tempat lain. Catatan terbaru sudah dimuat — gabungkan bila perlu lalu simpan lagi.");
        }
        if (($alasan = self::galat($kegiatan, $pengguna)) !== null) {
            throw ValidationException::withMessages(['catatan' => $alasan]);
        }

        $catatan = RiwayatStatus::rapikanCatatan((string) $catatan);
        // Aksi kosong = catatan tidak wajib; hanya batas panjang yang berlaku untuk draf.
        Validator::make(['catatan' => $catatan], ...RiwayatStatus::validasiCatatan('', 'catatan'))->validate();

        if ($catatan === '') {
            $draf === null || $repo->hapus($id);

            return ['draf' => null, 'berubah' => $draf !== null, 'versi' => '', 'pesan' => 'Draf catatan dikosongkan.'];
        }
        if ($draf !== null && $draf['catatan'] === $catatan) {
            return ['draf' => $draf, 'berubah' => false, 'versi' => self::versi($draf), 'pesan' => 'Draf catatan tidak berubah.'];
        }

        $baru = $repo->simpan($id, $catatan, $pengguna);

        return ['draf' => $baru, 'berubah' => true, 'versi' => self::versi($baru), 'pesan' => 'Draf catatan tersimpan.'];
    }

    /** Buang draf kegiatan — dipanggil setelah keputusan tersimpan (catatannya kini di riwayat). */
    public static function buang(int $id): void
    {
        app(CatatanPemeriksaanRepository::class)->hapus($id);
    }
}
