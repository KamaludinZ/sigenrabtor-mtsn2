<?php

namespace App\Support\Kegiatan\Impor;

use App\Contracts\ImporKegiatanRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Impor\ImporBertahap;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Kegiatan\RiwayatStatus;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Batch insert draf kegiatan dari impor & pembaruan status drafnya (PRD §19 F22) — endpoint `kegiatan.impor_perkin.proses`
 * (`POST /kegiatan/impor-perkin/{impor}/proses?batas=`) dan `kegiatan.impor_perkin.batal`:
 * - `proses($imporId, $pengguna, $batas)`: impor berjalan milik pengimpor/admin, tahun aktif terbuka; sampai `$batas`
 *   (bawaan ImporBertahap::UKURAN_KELOMPOK, maks. MAKS_BATAS) baris siap berikutnya yang belum menjadi kegiatan diperiksa
 *   ulang ValidasiImporMassal::periksaBaris — tidak lagi sah → baris ditandai dilewati/gagal; sah → kegiatan Draf dibuat
 *   (data baris + `impor_id`, riwayat `dibuat` "Diimpor dari …") lalu baris ditandai dibuat. Begitu tidak ada lagi baris
 *   siap tersisa, impor diakhiri `selesai`. Dipanggil berulang sampai `selesai` → {impor, diproses, sisa, selesai};
 * - `batalkan($imporId, $pengguna)`: impor berjalan diakhiri `dibatalkan` (kegiatan yang sudah dibuat tetap ada).
 */
final class ProsesImporMassal
{
    public const MAKS_BATAS = 50;

    /**
     * @param  array{id: int, name: string, role: string, leadings?: list<string>}  $pengguna
     * @return array{impor: array<string, mixed>, diproses: list<array{nomor_baris: int, status: string, pesan: list<string>, kegiatan_id: int|null, tautan: string|null}>, sisa: int, selesai: bool}
     *
     * @throws RuntimeException Impor tidak ditemukan.
     * @throws AuthorizationException Bukan pengimpor/admin atau tahun aktif terkunci.
     * @throws ValidationException Impor tidak berjalan (galat `impor`).
     */
    public static function proses(string $imporId, array $pengguna, int $batas = ImporBertahap::UKURAN_KELOMPOK): array
    {
        $repo = app(ImporKegiatanRepository::class);
        $impor = self::berjalan($imporId, $pengguna);
        $tahun = StubData::tahunAktif()['tahun'];
        if (($terkunci = KunciKegiatan::tahunTerkunci(['tahun_anggaran' => $tahun])) !== null) {
            throw new AuthorizationException(KunciKegiatan::pesanTahun($terkunci));
        }

        $antrean = array_values(array_filter($repo->baris($imporId), fn (array $b) => $b['status'] === 'siap' && $b['kegiatan_id'] === null));
        $namaAda = ValidasiImporMassal::namaAda($impor['jenis'] === 'perkin');
        $sumber = $impor['jenis'] === 'perkin'
            ? "Diimpor dari cascade Perkin ({$impor['nama_berkas']}".($impor['lembar'] ? ", sheet {$impor['lembar']}" : '').').'
            : "Diimpor dari Excel ({$impor['nama_berkas']}).";
        $diproses = [];

        foreach (array_slice($antrean, 0, max(1, min(self::MAKS_BATAS, $batas))) as $b) {
            $periksa = ValidasiImporMassal::periksaBaris($b['data'], $namaAda, $pengguna, $impor['jenis'] === 'perkin' ? 'Kegiatan dengan nama ini sudah ada di TA {tahun}.' : 'Kegiatan dengan nama ini sudah ada di aplikasi.');
            if ($periksa !== null) {
                $repo->tandaiBaris($imporId, $b['nomor_baris'], $periksa['status'], $periksa['pesan']);
                $diproses[] = ['nomor_baris' => $b['nomor_baris']] + $periksa + ['kegiatan_id' => null, 'tautan' => null];

                continue;
            }
            $id = app(KegiatanRepository::class)->simpan($b['data'] + [
                'asal' => $impor['jenis'] === 'perkin' ? 'impor_perkin' : 'impor_excel',
                'impor_id' => $imporId,
                'user_id' => $pengguna['id'] ?? null,
                'riwayat_status' => [RiwayatStatus::entri('dibuat', StatusKegiatan::Draf, $pengguna, $sumber)],
            ]);
            $repo->tandaiDibuat($imporId, $b['nomor_baris'], $id);
            $diproses[] = ['nomor_baris' => $b['nomor_baris'], 'status' => 'dibuat', 'pesan' => ['Dibuat sebagai Draf.'], 'kegiatan_id' => $id, 'tautan' => route('kegiatan.edit', ['kegiatan' => $id])];
        }

        $sisa = count($antrean) - count($diproses);
        $impor = $sisa === 0 ? $repo->akhiri($imporId, 'selesai') : $repo->temukan($imporId);

        return ['impor' => $impor, 'diproses' => $diproses, 'sisa' => $sisa, 'selesai' => $sisa === 0];
    }

    /**
     * @param  array{id: int, role: string}  $pengguna
     * @return array<string, mixed> Impor yang dibatalkan.
     *
     * @throws RuntimeException
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public static function batalkan(string $imporId, array $pengguna): array
    {
        self::berjalan($imporId, $pengguna);

        return app(ImporKegiatanRepository::class)->akhiri($imporId, 'dibatalkan');
    }

    /** @return array<string, mixed> */
    private static function berjalan(string $imporId, array $pengguna): array
    {
        $impor = app(ImporKegiatanRepository::class)->temukan($imporId) ?? throw new RuntimeException('Impor tidak ditemukan.');
        if ($pengguna['role'] !== 'admin' && $impor['user_id'] !== $pengguna['id']) {
            throw new AuthorizationException(AksesKegiatan::PESAN_TOLAK);
        }
        if ($impor['status'] !== 'berjalan') {
            throw ValidationException::withMessages(['impor' => 'Impor ini sudah '.($impor['status'] === 'selesai' ? 'selesai' : 'dibatalkan').'.']);
        }

        return $impor;
    }
}
