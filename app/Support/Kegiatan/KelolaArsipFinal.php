<?php

namespace App\Support\Kegiatan;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Unggah, ganti, dan hapus arsip final PDF bertanda tangan per kegiatan × sumber dana (PRD F18) — dipakai panel
 * `Kegiatan\PanelArsipFinal` dan endpoint `kegiatan.arsip.data` (baca), `kegiatan.arsip.unggah`, `kegiatan.arsip.hapus`:
 * - `alasan($kegiatan, $pengguna)` = mengapa arsip tidak bisa diubah: bukan cakupan akun (AksesKegiatan), tahun anggaran
 *   terkunci (KunciKegiatan), atau status selain Disetujui (ArsipFinal::bolehUnggah; selama Final arsip hanya dilihat —
 *   kunci dibuka admin dulu); null = boleh;
 * - sumber dana harus salah satu yang dipilih kegiatan (ArsipFinal::sumberWajib, galat `sumber`);
 * - berkas diperiksa ArsipFinal::galatUnggahan (aturan sama dengan pemeriksaan klien `galatArsip`, galat `berkas`);
 * - hasil memuat `kurang` (sumber dana yang arsipnya belum ada — syarat "Tandai final"); unggahan yang melengkapi arsip
 *   semua sumber dana langsung memfinalkan kegiatan (FinalOtomatis, `final`/`status` di hasil).
 */
final class KelolaArsipFinal
{
    public const PESAN_STATUS = 'Arsip final hanya bisa diunggah selama kegiatan berstatus Disetujui.';

    public const PESAN_FINAL = 'Arsip semua sumber dana lengkap — kegiatan kini Final dan datanya terkunci.';

    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id: int, role: string, leadings?: list<string>}  $pengguna
     */
    public static function alasan(array $kegiatan, array $pengguna): ?string
    {
        if (! AksesKegiatan::bolehLihat($kegiatan, $pengguna)) {
            return AksesKegiatan::PESAN_TOLAK;
        }
        if (($tahun = KunciKegiatan::tahunTerkunci($kegiatan)) !== null) {
            return KunciKegiatan::pesanTahun($tahun);
        }

        return ArsipFinal::bolehUnggah(self::status($kegiatan)) ? null : self::PESAN_STATUS;
    }

    /**
     * Arsip satu sumber dana untuk ditampilkan/dijawab endpoint (tanpa path penyimpanan).
     *
     * @param  array<string, mixed>  $arsip  Satu entri ArsipFinalRepository::untukKegiatan().
     * @return array{sumber: string, nama_berkas: string, ukuran: int, ukuran_teks: string, diunggah_oleh: string, diunggah_pada: string, tautan: string}
     */
    public static function tampil(int $kegiatanId, array $arsip): array
    {
        return [
            'sumber' => $arsip['sumber'],
            'nama_berkas' => $arsip['nama_berkas'],
            'ukuran' => (int) $arsip['ukuran'],
            'ukuran_teks' => ArsipFinal::ukuran((int) $arsip['ukuran']),
            'diunggah_oleh' => $arsip['diunggah_oleh'],
            'diunggah_pada' => $arsip['diunggah_pada'],
            'tautan' => route('kegiatan.arsip', ['kegiatan' => $kegiatanId, 'sumber' => $arsip['sumber']]),
        ];
    }

    /**
     * Data endpoint `kegiatan.arsip.data`.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id: int, role: string, leadings?: list<string>}  $pengguna
     * @return array<string, mixed>
     */
    public static function data(array $kegiatan, array $pengguna): array
    {
        $id = (int) $kegiatan['id'];
        $arsip = app(ArsipFinalRepository::class)->untukKegiatan($id);
        $berlaku = FinalOtomatis::berlaku($kegiatan, $arsip);
        $status = self::status($kegiatan);

        return [
            'kegiatan_id' => $id,
            'status' => $status->value,
            'label_status' => $status->label(),
            'boleh_unggah' => ($alasan = self::alasan($kegiatan, $pengguna)) === null,
            'alasan' => $alasan,
            'maks_kb' => ArsipFinal::MAKS_KB,
            'sumber' => array_map(fn (string $s) => ['sumber' => $s, 'arsip' => isset($arsip[$s]) ? self::tampil($id, $arsip[$s]) + ['berlaku' => isset($berlaku[$s])] : null], ArsipFinal::sumberWajib($kegiatan)),
            'kurang' => $kurang = ArsipFinal::kurang($kegiatan, $arsip),
            'lengkap' => $kurang === [],
            // Sumber dana yang arsipnya belum diunggah sesudah persetujuan terakhir: unggahan berikutnya yang melengkapinya
            // memfinalkan kegiatan otomatis.
            'kurang_final_otomatis' => FinalOtomatis::kurang($kegiatan, $arsip),
        ];
    }

    /**
     * @param  array{id: int, name: string, role: string, leadings?: list<string>}  $pengguna
     * @return array{arsip: array<string, mixed>, ganti: bool, kurang: list<string>, final: bool, status: string, pesan: string}
     *
     * @throws RuntimeException Kegiatan tidak ditemukan.
     * @throws AuthorizationException
     * @throws ValidationException Berkunci `sumber` atau `berkas`.
     */
    public static function unggah(int $id, string $sumber, mixed $berkas, array $pengguna): array
    {
        $kegiatan = self::pastikan($id, $sumber, $pengguna);
        if (! $berkas instanceof UploadedFile) {
            throw ValidationException::withMessages(['berkas' => 'Pilih berkas PDF hasil pindaian.']);
        }
        if (($galat = ArsipFinal::galatUnggahan($berkas)) !== null) {
            throw ValidationException::withMessages(['berkas' => $galat]);
        }

        $repo = app(ArsipFinalRepository::class);
        $ganti = isset($repo->untukKegiatan($id)[$sumber]);
        $arsip = $repo->simpan($id, $sumber, $berkas, $pengguna);
        $final = FinalOtomatis::jalankan($id, $pengguna) !== null;

        return [
            'arsip' => self::tampil($id, $arsip),
            'ganti' => $ganti,
            'kurang' => ArsipFinal::kurang($kegiatan, $repo->untukKegiatan($id)),
            'final' => $final,
            'status' => $final ? StatusKegiatan::Final->value : self::status($kegiatan)->value,
            'pesan' => 'Arsip final RAB '.$sumber.($ganti ? ' diganti.' : ' tersimpan.').($final ? ' '.self::PESAN_FINAL : ''),
        ];
    }

    /**
     * @param  array{id: int, role: string, leadings?: list<string>}  $pengguna
     * @return array{kurang: list<string>, pesan: string}
     *
     * @throws RuntimeException Kegiatan tidak ditemukan.
     * @throws AuthorizationException
     * @throws ValidationException Berkunci `sumber`.
     */
    public static function hapus(int $id, string $sumber, array $pengguna): array
    {
        $kegiatan = self::pastikan($id, $sumber, $pengguna);
        $repo = app(ArsipFinalRepository::class);
        if (! isset($repo->untukKegiatan($id)[$sumber])) {
            throw ValidationException::withMessages(['sumber' => "Belum ada arsip final RAB {$sumber}."]);
        }

        $repo->hapus($id, $sumber);

        return ['kurang' => ArsipFinal::kurang($kegiatan, $repo->untukKegiatan($id)), 'pesan' => 'Arsip final RAB '.$sumber.' dihapus.'];
    }

    /** @return array<string, mixed> */
    private static function pastikan(int $id, string $sumber, array $pengguna): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($id) ?? throw new RuntimeException('Kegiatan tidak ditemukan.');
        if (($alasan = self::alasan($kegiatan, $pengguna)) !== null) {
            throw new AuthorizationException($alasan);
        }
        if (! in_array($sumber, ArsipFinal::sumberWajib($kegiatan), true)) {
            throw ValidationException::withMessages(['sumber' => "Sumber dana {$sumber} tidak dipilih kegiatan ini — arsip final hanya untuk RAB sumber dana yang dipilih."]);
        }

        return $kegiatan;
    }

    /** @param  array<string, mixed>  $kegiatan */
    private static function status(array $kegiatan): StatusKegiatan
    {
        return StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
    }
}
