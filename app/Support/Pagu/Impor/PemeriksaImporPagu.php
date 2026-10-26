<?php

namespace App\Support\Pagu\Impor;

use App\Enums\SumberDana;
use App\Support\Format;
use App\Support\Kegiatan\Impor\PemeriksaImporKegiatan;
use App\Support\Kegiatan\Impor\TemplateImporKegiatan;
use App\Support\Pagu\DataPagu;

/**
 * Pemeriksaan baris impor Data Pagu. Nama dibandingkan tanpa beda huruf besar/kecil & spasi ganda (DataPagu::kunci):
 * - sudah ada di Data Pagu dengan leading & pagu sama → dilewati;
 * - sudah ada tetapi leading/pagu berbeda → siap memperbarui data itu (perubahannya dijelaskan di peringatan);
 * - sama dengan baris lain di berkas → gagal;
 * - sama dengan nama kegiatan di Data Kegiatan → peringatan (kegiatan itu otomatis tertaut ke pagunya).
 */
final class PemeriksaImporPagu
{
    /**
     * @param  list<array{baris: int, nilai: array<string, mixed>}>  $barisMentah
     * @param  list<array<string, mixed>>  $dataPaguAda  Data pagu tahun aktif.
     * @param  list<string>  $namaKegiatan  Nama kegiatan yang sudah ada.
     * @param  list<array{kode: string, nama_unit: string}>  $leadings  Leading yang boleh dipakai.
     * @param  list<array{kode: string, nama_unit: string}>  $semuaLeading
     * @return list<array{baris: int, nama: string, leading: string|null, leading_label: string|null, pagu: array<string, int|null>, id: string|null, aksi: string, status: string, pesan: list<string>, peringatan: list<string>}>
     */
    public static function periksa(array $barisMentah, array $dataPaguAda, array $namaKegiatan, array $leadings, array $semuaLeading): array
    {
        $ada = [];
        foreach ($dataPaguAda as $p) {
            $ada[DataPagu::kunci($p['nama_kegiatan'])] = $p;
        }
        $kegiatan = array_flip(array_map(DataPagu::kunci(...), $namaKegiatan));
        $dalamBerkas = [];
        $hasil = [];

        foreach ($barisMentah as ['baris' => $nomor, 'nilai' => $nilai]) {
            $pesan = [];
            $peringatan = [];
            $nama = trim(preg_replace('/\s+/u', ' ', (string) ($nilai['nama_kegiatan'] ?? '')));

            if ($nama === '') {
                $pesan[] = 'Nama kegiatan wajib diisi.';
            } elseif (mb_strlen($nama) > PemeriksaImporKegiatan::MAKS_NAMA) {
                $pesan[] = 'Nama kegiatan lebih dari '.PemeriksaImporKegiatan::MAKS_NAMA.' karakter ('.mb_strlen($nama).').';
            }

            [$leading, $pesanLeading] = PemeriksaImporKegiatan::leading($nilai['leading'] ?? null, $leadings, $semuaLeading, null);
            if ($pesanLeading) {
                $pesan[] = $pesanLeading;
            }

            $pagu = array_fill_keys(SumberDana::urutan(), null);
            foreach (['pagu_dipa' => SumberDana::Dipa, 'pagu_komite' => SumberDana::Komite] as $kolom => $jenis) {
                [$nominal, $pesanPagu] = PemeriksaImporKegiatan::pagu($nilai[$kolom] ?? null, $jenis);
                if ($pesanPagu) {
                    $pesan[] = $pesanPagu;
                } else {
                    $pagu[$jenis->value] = $nominal;
                }
            }
            if ($pesan === [] && array_filter($pagu, fn ($v) => $v !== null) === []) {
                $pesan[] = 'Isi Pagu DIPA atau Pagu Komite.';
            }

            $kunci = DataPagu::kunci($nama);
            $lama = $ada[$kunci] ?? null;
            $status = $pesan === [] ? 'siap' : 'gagal';
            $aksi = $lama ? 'perbarui' : 'tambah';

            if ($nama !== '' && isset($dalamBerkas[$kunci])) {
                $status = 'gagal';
                $pesan[] = "Nama sama dengan baris {$dalamBerkas[$kunci]} di berkas ini.";
            } elseif ($status === 'siap' && $lama) {
                $perubahan = self::perubahan($lama, $leading['kode'] ?? null, $pagu);
                if ($perubahan === []) {
                    $status = 'dilewati';
                    $pesan[] = 'Sudah ada di Data Pagu dengan leading & pagu yang sama.';
                } else {
                    $peringatan[] = 'Nama sudah ada di Data Pagu — akan diperbarui: '.implode('; ', $perubahan).'.';
                }
            }
            if ($status !== 'gagal' && isset($kegiatan[$kunci])) {
                $peringatan[] = 'Sudah ada kegiatan bernama sama di Data Kegiatan; pagu ini tertaut ke kegiatan itu.';
            }

            if ($nama !== '') {
                $dalamBerkas[$kunci] ??= $nomor;
            }

            $hasil[] = [
                'baris' => $nomor,
                'nama' => $nama,
                'leading' => $leading['kode'] ?? null,
                'leading_label' => $leading ? TemplateImporKegiatan::labelLeading($leading) : null,
                'pagu' => $pagu,
                'id' => $lama['id'] ?? null,
                'aksi' => $aksi,
                'status' => $status,
                'pesan' => $pesan,
                'peringatan' => $status === 'gagal' ? [] : $peringatan,
            ];
        }

        return $hasil;
    }

    /**
     * "Pagu DIPA: Rp6.000.000 → Rp6.500.000" untuk tiap kolom yang berbeda.
     *
     * @param  array<string, mixed>  $lama
     * @param  array<string, int|null>  $pagu
     * @return list<string>
     */
    private static function perubahan(array $lama, ?string $leading, array $pagu): array
    {
        $teks = fn (?int $v) => $v === null ? '—' : Format::rupiah($v);
        $hasil = [];
        if ($leading !== null && $leading !== $lama['leading']) {
            $hasil[] = 'Leading: '.($lama['leading'] ?? '—')." → {$leading}";
        }
        foreach ($pagu as $sumber => $nominal) {
            if ($nominal !== ($lama['pagu'][$sumber] ?? null)) {
                $hasil[] = "Pagu {$sumber}: {$teks($lama['pagu'][$sumber] ?? null)} → {$teks($nominal)}";
            }
        }

        return $hasil;
    }
}
