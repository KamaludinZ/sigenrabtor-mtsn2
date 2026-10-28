<?php

namespace App\Support\Dokumen;

use App\Contracts\PenggabungPdf;
use App\Support\MasterKode;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Logika penggabungan PDF buku per leading × sumber dana (PRD §18 F19 "Gabung PDF"): urutan bagian buku diturunkan dari
 * simulasi nomor halaman BukuDokumen::isi (cover buku, daftar isi, lalu tiap kegiatan: cover, KAK/TOR, RAB — bagian =
 * halaman berurutan berjenis & berkegiatan sama, beserta label halaman awal–akhirnya), PDF tiap bagian digabung lewat
 * kontrak PenggabungPdf, lalu jumlah halaman hasilnya harus sama dengan susunan (agar nomor halaman daftar isi benar).
 * Selama mesin dokumen belum menyusun KAK/TOR & RAB sebagai PDF, `buat()` memakai halaman pengganti PdfSederhana per
 * bagian (StubPembuatPdfBuku). Kegagalan → GagalGabungPdf berpesan siap tampil.
 */
final class GabungPdfBuku
{
    public const BAGIAN = [
        'cover_buku' => 'Cover buku',
        'daftar_isi' => 'Daftar isi',
        'cover_kegiatan' => 'Cover kegiatan',
        'kak' => 'KAK/TOR',
        'rab' => 'RAB',
    ];

    /**
     * Bagian buku berurutan dari simulasi halaman.
     *
     * @param  array{halaman: list<array{jenis: string, kegiatan: int|null, label: string|null}>}  $simulasi
     * @param  list<array{id: int, nama_kegiatan: string}>  $baris  Baris daftar isi (urutan = indeks kegiatan simulasi).
     * @return list<array{jenis: string, judul: string, kegiatan_id: int|null, nama_kegiatan: string|null, halaman: int, label_awal: string|null, label_akhir: string|null}>
     */
    public static function bagian(array $simulasi, array $baris): array
    {
        $bagian = [];
        foreach ($simulasi['halaman'] as $h) {
            $akhir = array_key_last($bagian);
            if ($akhir !== null && $bagian[$akhir]['jenis'] === $h['jenis'] && $bagian[$akhir]['indeks'] === $h['kegiatan']) {
                $bagian[$akhir]['halaman']++;
                $bagian[$akhir]['label_akhir'] = $h['label'] ?? $bagian[$akhir]['label_akhir'];

                continue;
            }
            $kegiatan = $h['kegiatan'] === null ? null : ($baris[$h['kegiatan']] ?? null);
            $bagian[] = [
                'jenis' => $h['jenis'],
                'indeks' => $h['kegiatan'],
                'judul' => self::BAGIAN[$h['jenis']] ?? $h['jenis'],
                'kegiatan_id' => $kegiatan['id'] ?? null,
                'nama_kegiatan' => $kegiatan['nama_kegiatan'] ?? null,
                'halaman' => 1,
                'label_awal' => $h['label'],
                'label_akhir' => $h['label'],
            ];
        }

        return array_map(fn (array $b) => array_diff_key($b, ['indeks' => true]), $bagian);
    }

    /**
     * Gabungkan PDF bagian (path lokal, berurutan) ke $tujuan; $harapan = jumlah halaman menurut susunan buku.
     *
     * @param  list<string>  $berkas
     * @return array{halaman: int, ukuran: int}
     *
     * @throws GagalGabungPdf
     */
    public static function gabung(array $berkas, string $tujuan, ?int $harapan = null): array
    {
        foreach (array_values($berkas) as $i => $b) {
            $kepala = is_file($b) ? (string) file_get_contents($b, false, null, 0, 5) : '';
            if ($kepala !== '%PDF-') {
                throw new GagalGabungPdf('Bagian buku ke-'.($i + 1).' bukan PDF yang sah — susun ulang buku.');
            }
        }

        $penggabung = app(PenggabungPdf::class);
        $penggabung->gabung(array_values($berkas), $tujuan);
        $halaman = $penggabung->jumlahHalaman($tujuan);
        if ($harapan !== null && $halaman !== $harapan) {
            throw new GagalGabungPdf("PDF buku berisi {$halaman} halaman, seharusnya {$harapan} sesuai susunan buku — susun ulang buku.");
        }

        return ['halaman' => $halaman, 'ukuran' => (int) filesize($tujuan)];
    }

    /**
     * Halaman pengganti satu bagian (baris teks per halaman) sampai mesin dokumen menyusun PDF aslinya.
     *
     * @param  array{judul: string, nama_kegiatan: string|null, halaman: int, label_awal: string|null}  $bagian
     * @param  array{leading: string, sumber: string, tahun: int}  $buku
     */
    public static function pengganti(array $bagian, array $buku): string
    {
        $unit = MasterKode::leading($buku['leading'])['nama_unit'] ?? $buku['leading'];

        return PdfSederhana::tulis(array_map(fn (int $i) => [
            "Buku Dokumen {$unit} - {$buku['sumber']} - Tahun Anggaran {$buku['tahun']}",
            $bagian['judul'].($bagian['nama_kegiatan'] ? ': '.$bagian['nama_kegiatan'] : '').' (halaman '.($i + 1).' dari '.$bagian['halaman'].')',
            'Berkas pengganti: isi dokumen dibuat mesin dokumen.',
        ], range(0, $bagian['halaman'] - 1)));
    }

    /**
     * Susun PDF buku dari kegiatan tahun aktif: bagian dari BukuDokumen::isi → PDF tiap bagian (pengganti) → digabung &
     * dicek jumlah halamannya → disimpan di disk local $path. Berkas kerja sementara selalu dihapus.
     *
     * @param  array{leading: string, sumber: string, tahun: int}  $buku
     * @param  list<array<string, mixed>>  $kegiatan  Kegiatan tahun aktif.
     * @return array{path: string, halaman: int, ukuran: int, bagian: int}
     *
     * @throws GagalGabungPdf
     */
    public static function buat(array $buku, array $kegiatan, string $path): array
    {
        $isi = BukuDokumen::isi($kegiatan, $buku['leading'], $buku['sumber']);
        $bagian = self::bagian($isi['simulasi'], $isi['baris']);
        $kerja = storage_path('framework/cache/buku-'.Str::random(16));
        File::ensureDirectoryExists($kerja);

        try {
            $berkas = [];
            foreach ($bagian as $i => $b) {
                $berkas[] = $nama = sprintf('%s/%03d-%s.pdf', $kerja, $i + 1, $b['jenis']);
                file_put_contents($nama, self::pengganti($b, $buku));
            }
            $hasil = self::gabung($berkas, $kerja.'/buku.pdf', count($isi['simulasi']['halaman']));
            Storage::disk('local')->put($path, (string) file_get_contents($kerja.'/buku.pdf'));

            return ['path' => $path, 'halaman' => $hasil['halaman'], 'ukuran' => $hasil['ukuran'], 'bagian' => count($bagian)];
        } finally {
            File::deleteDirectory($kerja);
        }
    }
}
