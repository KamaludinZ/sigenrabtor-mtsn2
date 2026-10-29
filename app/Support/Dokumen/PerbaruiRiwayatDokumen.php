<?php

namespace App\Support\Dokumen;

use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatDokumenRepository;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Pembaruan dokumen Riwayat Dokumen tanpa mengubah catatan lama (catatan hanya-tambah):
 *
 * - `terbitkanUlang($id, $isian, $pengguna)` (PUT `dokumen.perbarui`) = catat VERSI BARU jenis × sumber dana × format yang
 *   sama dari data kegiatan terbaru (CatatRiwayatDokumen::catat: nama berkas, cap DRAF, ringkas & cuplikan isi saat ini).
 *   Isian {asal? cetak|unduh (bawaan cetak; arsip hanya lewat unggah arsip final), nomor_dokumen? (bawaan nomor versi
 *   sebelumnya), versi? (versi terbaru yang terakhir dilihat — berbeda → PengaturanBerubah)}. Butuh OtorisasiDokumen
 *   `buka_ulang`. Hasil {dokumen (baris + izin), sebelumnya {id, versi}, perbedaan (catatan lama vs data terbaru), pesan}.
 * - `beriNomor($id, $nomor, $pengguna)` (PATCH `dokumen.perbarui`) = isi nomor dokumen sekali pada catatan yang belum
 *   bernomor (OtorisasiDokumen::beriNomor); sudah bernomor → PengaturanBerubah. Nomor (`galatNomor`): wajib, ≤ MAKS_NOMOR,
 *   huruf/angka/spasi/. / - saja, dan tidak dipakai dokumen lain (kunci versi lain) di tahun anggaran yang sama.
 */
final class PerbaruiRiwayatDokumen
{
    public const ASAL = ['cetak', 'unduh'];

    public const MAKS_NOMOR = 100;

    /**
     * @param  array<string, mixed>  $isian
     * @param  array{id?: int|null, name: string, role: string, leadings?: list<string>}  $pengguna
     * @return array<string, mixed>
     *
     * @throws RuntimeException Catatan tidak ada.
     * @throws AuthorizationException
     * @throws ValidationException Berkunci `asal`, `nomor_dokumen`.
     * @throws PengaturanBerubah Versi terbaru sudah berganti.
     */
    public static function terbitkanUlang(string $id, array $isian, array $pengguna): array
    {
        $c = AksiRiwayatDokumen::temukan($id, $pengguna);
        $kegiatan = $c['tersedia'] ? app(KegiatanRepository::class)->temukan((int) $c['kegiatan_id']) : null;
        OtorisasiDokumen::pastikan('buka_ulang', $c, $kegiatan, $pengguna);

        $asal = $isian['asal'] ?? 'cetak';
        if (! in_array($asal, self::ASAL, true)) {
            throw ValidationException::withMessages(['asal' => 'Asal harus cetak atau unduh — arsip final dicatat lewat unggah arsip.']);
        }
        $rincian = AksiRiwayatDokumen::lihat($id, $pengguna);
        $terakhir = $rincian['versi'][0];
        if (isset($isian['versi']) && (int) $isian['versi'] !== (int) $terakhir['versi']) {
            throw new PengaturanBerubah('Dokumen ini sudah diperbarui menjadi v'.$terakhir['versi'].' di tempat lain — muat ulang lalu coba lagi.');
        }
        $nomor = array_key_exists('nomor_dokumen', $isian) ? self::rapikanNomor($isian['nomor_dokumen']) : $terakhir['nomor_dokumen'];
        if ($nomor !== null && ($galat = self::galatNomor($nomor, $c)) !== null) {
            throw ValidationException::withMessages(['nomor_dokumen' => $galat]);
        }

        $baru = CatatRiwayatDokumen::catat($kegiatan, $c['jenis'], $c['sumber'], $c['format'], $asal, $pengguna, null, $nomor);
        $tampil = collect(RiwayatDokumen::untuk($pengguna))->firstWhere('id', $baru['id']);

        return [
            'dokumen' => DataRiwayatDokumen::baris($tampil),
            'sebelumnya' => ['id' => $terakhir['id'], 'versi' => (int) $terakhir['versi']],
            'perbedaan' => $rincian['perbedaan'],
            'pesan' => $tampil['label_jenis'].' '.$tampil['label_format'].' diterbitkan ulang sebagai v'.$tampil['versi'].' dari data terbaru.',
        ];
    }

    /**
     * @param  array{id?: int|null, name: string, role: string, leadings?: list<string>}  $pengguna
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     * @throws AuthorizationException
     * @throws ValidationException Berkunci `nomor_dokumen`.
     * @throws PengaturanBerubah Catatan sudah bernomor.
     */
    public static function beriNomor(string $id, mixed $nomor, array $pengguna): array
    {
        $c = AksiRiwayatDokumen::temukan($id, $pengguna);
        if (($alasan = OtorisasiDokumen::beriNomor($c, $pengguna)) !== null) {
            throw new AuthorizationException($alasan);
        }
        if ($c['nomor_dokumen'] !== null) {
            throw new PengaturanBerubah("Dokumen ini sudah bernomor {$c['nomor_dokumen']} — nomor tidak bisa diganti; terbitkan ulang untuk versi bernomor lain.");
        }
        $nomor = self::rapikanNomor($nomor);
        if (($galat = self::galatNomor($nomor, $c)) !== null) {
            throw ValidationException::withMessages(['nomor_dokumen' => $galat]);
        }
        if (app(RiwayatDokumenRepository::class)->beriNomor($id, (string) $nomor) === null) {
            throw new PengaturanBerubah('Dokumen ini baru saja diberi nomor di tempat lain — muat ulang.');
        }
        $tampil = collect(RiwayatDokumen::untuk($pengguna))->firstWhere('id', $id);

        return ['dokumen' => DataRiwayatDokumen::baris($tampil), 'pesan' => "Nomor {$nomor} diberikan pada ".$tampil['label_jenis'].' v'.$tampil['versi'].'.'];
    }

    public static function rapikanNomor(mixed $nomor): ?string
    {
        $nomor = is_string($nomor) ? trim((string) preg_replace('/\s+/u', ' ', $nomor)) : '';

        return $nomor === '' ? null : $nomor;
    }

    /**
     * @param  array<string, mixed>  $catatan  Catatan yang diberi/membawa nomor.
     */
    public static function galatNomor(?string $nomor, array $catatan): ?string
    {
        if ($nomor === null) {
            return 'Nomor dokumen wajib diisi.';
        }
        if (mb_strlen($nomor) > self::MAKS_NOMOR) {
            return 'Nomor dokumen paling banyak '.self::MAKS_NOMOR.' karakter.';
        }
        if (! preg_match('#^[\pL\pN][\pL\pN ./\-]*$#u', $nomor)) {
            return 'Nomor dokumen hanya boleh berisi huruf, angka, spasi, titik, garis miring, dan tanda hubung.';
        }
        $kunci = RiwayatDokumen::kunciVersi($catatan);
        foreach (RiwayatDokumen::semua() as $lain) {
            if ((int) $lain['tahun_anggaran'] === (int) $catatan['tahun_anggaran'] && mb_strtolower((string) ($lain['nomor_dokumen'] ?? '')) === mb_strtolower($nomor) && RiwayatDokumen::kunciVersi($lain) !== $kunci) {
                return "Nomor {$nomor} sudah dipakai dokumen lain di TA {$catatan['tahun_anggaran']}.";
            }
        }

        return null;
    }
}
