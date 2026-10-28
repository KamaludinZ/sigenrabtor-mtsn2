<?php

namespace App\Support\Kegiatan\Impor;

use App\Contracts\ImporKegiatanRepository;
use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Kegiatan\ValidasiKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Validasi data impor massal & penandaan galat (PRD §19 F22): data leading, master kode, atau kegiatan bisa berubah
 * sejak pratinjau, jadi baris siap diperiksa ulang dengan aturan terkini sebelum/sambil disimpan —
 * - `periksaBaris($data, &$namaAda, $pengguna)`: nama sudah ada (pembanding PemeriksaImporKegiatan::kunciNama; nama baris
 *   yang lolos ikut dicatat agar baris kembar berikutnya dilewati) → `dilewati`; ValidasiKegiatan::galat terhadap tahun
 *   aktif (leading cakupan akun, master kode ValidasiKodeMaster, tanggal) → `gagal` beserta pesannya; null = tetap siap.
 *   Dipakai simpan per baris tab impor (`ImporPerkin`, `ImporKegiatan`);
 * - `namaAda($hanyaTahunAktif)`: kunci nama kegiatan yang sudah ada (cascade Perkin: tahun aktif saja; Impor Excel: semua);
 * - `periksaUlang($imporId, $pengguna)`: semua baris siap yang belum menjadi kegiatan pada impor berjalan (pengimpor/admin)
 *   diperiksa lalu yang berubah ditandai di ImporKegiatanRepository::tandaiBaris → {impor, berubah, siap}.
 */
final class ValidasiImporMassal
{
    /** @return array<string, true> Kunci nama kegiatan yang sudah ada. */
    public static function namaAda(bool $hanyaTahunAktif = true): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $ada = [];
        foreach (app(KegiatanRepository::class)->semua() as $k) {
            if (! $hanyaTahunAktif || TahunKegiatan::dari($k) === $tahun) {
                $ada[PemeriksaImporKegiatan::kunciNama((string) ($k['nama_kegiatan'] ?? ''))] = true;
            }
        }

        return $ada;
    }

    /**
     * @param  array<string, mixed>  $data  Data draf kegiatan baris impor.
     * @param  array<string, true>  $namaAda
     * @param  array{id: int, role: string, leadings?: list<string>}|null  $pengguna  null = akun yang sedang masuk.
     * @return array{status: string, pesan: list<string>}|null
     */
    public static function periksaBaris(array $data, array &$namaAda, ?array $pengguna = null, string $keteranganAda = 'Kegiatan dengan nama ini sudah ada di TA {tahun}.'): ?array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $kunci = PemeriksaImporKegiatan::kunciNama((string) ($data['nama_kegiatan'] ?? ''));
        if (isset($namaAda[$kunci])) {
            return ['status' => 'dilewati', 'pesan' => [str_replace('{tahun}', (string) $tahun, $keteranganAda)]];
        }
        if (($galat = ValidasiKegiatan::galat($data + ['tahun_anggaran' => $tahun], pengguna: $pengguna)) !== []) {
            return ['status' => 'gagal', 'pesan' => $galat];
        }
        $namaAda[$kunci] = true;

        return null;
    }

    /**
     * @param  array{id: int, role: string, leadings?: list<string>}  $pengguna
     * @return array{impor: array<string, mixed>, berubah: list<array{nomor_baris: int, status: string, pesan: list<string>}>, siap: int}
     *
     * @throws RuntimeException Impor tidak ditemukan.
     * @throws AuthorizationException Bukan pengimpor/admin.
     * @throws ValidationException Impor sudah selesai/dibatalkan (galat `impor`).
     */
    public static function periksaUlang(string $imporId, array $pengguna): array
    {
        $repo = app(ImporKegiatanRepository::class);
        $impor = $repo->temukan($imporId) ?? throw new RuntimeException('Impor tidak ditemukan.');
        if ($pengguna['role'] !== 'admin' && $impor['user_id'] !== $pengguna['id']) {
            throw new AuthorizationException(AksesKegiatan::PESAN_TOLAK);
        }
        if ($impor['status'] !== 'berjalan') {
            throw ValidationException::withMessages(['impor' => 'Impor ini sudah '.($impor['status'] === 'selesai' ? 'selesai' : 'dibatalkan').' — unggah ulang berkasnya untuk memeriksa lagi.']);
        }

        $namaAda = self::namaAda($impor['jenis'] === 'perkin');
        $berubah = [];
        $siap = 0;
        foreach ($repo->baris($imporId) as $b) {
            if ($b['status'] !== 'siap' || $b['kegiatan_id'] !== null) {
                continue;
            }
            $hasil = self::periksaBaris($b['data'], $namaAda, $pengguna);
            if ($hasil === null) {
                $siap++;

                continue;
            }
            $repo->tandaiBaris($imporId, $b['nomor_baris'], $hasil['status'], $hasil['pesan']);
            $berubah[] = ['nomor_baris' => $b['nomor_baris']] + $hasil;
        }

        return ['impor' => $repo->temukan($imporId), 'berubah' => $berubah, 'siap' => $siap];
    }
}
