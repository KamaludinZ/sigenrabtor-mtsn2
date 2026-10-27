<?php

namespace App\Support\Stub;

use App\Contracts\BukuDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\PembuatPdfBuku;
use App\Support\Dokumen\ProsesPdfBuku;
use App\Support\MasterKode;
use App\Support\Rab\AnggaranKegiatan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pembuatan PDF buku tiruan: setiap kali dipantau, maju satu tahap (ProsesPdfBuku::TAHAP). Saat menyusun dokumen,
 * kegiatan yang RAB sumber dananya belum berisi rincian membuat proses gagal (seperti mesin dokumen nanti). Pada tahap
 * akhir ditulis PDF pengganti satu halaman berisi daftar kegiatan, disimpan di folder senama berkas riwayat buku.
 */
class StubPembuatPdfBuku implements PembuatPdfBuku
{
    public function mulai(array $buku): array
    {
        return app(BukuDokumenRepository::class)->catat($buku + [
            'pdf' => ['status' => 'antre', 'tahap' => 'antre', 'persen' => 0, 'pesan' => null],
        ]);
    }

    public function pantau(string $id): array
    {
        $repo = app(BukuDokumenRepository::class);
        $buku = $repo->temukan($id) ?? throw new \InvalidArgumentException("Buku {$id} tidak ditemukan.");
        $pdf = $buku['pdf'] ?? null;
        if (! ProsesPdfBuku::berjalan($pdf)) {
            return $buku;
        }

        // Tahap "susun" selesai → periksa dokumen tiap kegiatan sebelum diubah ke PDF.
        if ($pdf['tahap'] === 'susun' && ($galat = $this->galatSusun($buku)) !== null) {
            return $repo->perbarui($id, ['pdf' => ['status' => 'gagal', 'pesan' => $galat] + $pdf]);
        }

        $berikutnya = ProsesPdfBuku::berikutnya($pdf['tahap']);
        if ($berikutnya === null) {
            $path = $this->tulisPdf($buku);

            return $repo->perbarui($id, ['file_pdf_path' => $path, 'ukuran' => Storage::disk('local')->size($path), 'pdf' => ['status' => 'selesai', 'tahap' => $pdf['tahap'], 'persen' => 100, 'pesan' => 'PDF siap diunduh.']]);
        }

        return $repo->perbarui($id, ['pdf' => ['status' => 'proses', 'tahap' => $berikutnya, 'persen' => ProsesPdfBuku::persen($berikutnya, 'proses'), 'pesan' => null]]);
    }

    /** Pesan gagal bila ada kegiatan yang hilang atau RAB sumber dananya kosong. */
    private function galatSusun(array $buku): ?string
    {
        $repo = app(KegiatanRepository::class);
        foreach ($buku['kegiatan'] as $k) {
            $data = $repo->temukan((int) $k['id']);
            if ($data === null) {
                return "Kegiatan “{$k['nama']}” tidak ditemukan lagi — susun ulang buku.";
            }
            $anggaran = AnggaranKegiatan::perSumber($data)[$buku['sumber']] ?? null;
            if ($anggaran === null || $anggaran->jumlahItem() === 0) {
                return "RAB {$buku['sumber']} kegiatan “{$k['nama']}” belum berisi rincian — lengkapi lalu coba lagi.";
            }
        }

        return null;
    }

    /** PDF pengganti (satu halaman teks) agar alur unduh bisa dicoba sebelum mesin dokumen tersedia. */
    private function tulisPdf(array $buku): string
    {
        $unit = MasterKode::leading($buku['leading'])['nama_unit'] ?? $buku['leading'];
        $baris = [
            "Buku Dokumen {$buku['leading']} {$unit} - {$buku['sumber']} - Tahun Anggaran {$buku['tahun']}",
            'Berkas pengganti tahap frontend: PDF sebenarnya dibuat mesin dokumen.',
            '',
            ...array_map(fn (array $k, int $i) => ($i + 1).'. '.$k['nama'].' (hal. '.($k['halaman'] ?? '...').')', $buku['kegiatan'], array_keys($buku['kegiatan'])),
        ];
        $path = Str::beforeLast(config('sigenrabtor.stub.buku_file'), '.json')."/{$buku['id']}.pdf";
        Storage::disk('local')->put($path, self::pdfTeks($baris));

        return $path;
    }

    /** @param  list<string>  $baris */
    public static function pdfTeks(array $baris): string
    {
        $aman = fn (string $t) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], (string) iconv('UTF-8', 'windows-1252//TRANSLIT', $t));
        $isi = 'BT /F1 11 Tf 56 790 Td 15 TL '.implode(' ', array_map(fn (string $b) => '('.$aman($b).') Tj T*', $baris)).' ET';
        $objek = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($isi)." >>\nstream\n{$isi}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];

        $pdf = "%PDF-1.4\n";
        $posisi = [];
        foreach ($objek as $i => $o) {
            $posisi[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$o}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objek) + 1)."\n0000000000 65535 f \n".implode('', array_map(fn (int $p) => sprintf("%010d 00000 n \n", $p), $posisi));

        return $pdf.'trailer'."\n<< /Size ".(count($objek) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
