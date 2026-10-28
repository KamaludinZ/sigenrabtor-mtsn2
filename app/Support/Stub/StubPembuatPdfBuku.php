<?php

namespace App\Support\Stub;

use App\Contracts\BukuDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\PembuatPdfBuku;
use App\Support\Dokumen\BukuDokumen;
use App\Support\Dokumen\GabungPdfBuku;
use App\Support\Dokumen\GagalGabungPdf;
use App\Support\Dokumen\PdfSederhana;
use App\Support\Dokumen\ProsesPdfBuku;
use App\Support\Rab\AnggaranKegiatan;
use Illuminate\Support\Str;

/**
 * Pembuatan PDF buku tiruan: setiap kali dipantau, maju satu tahap (ProsesPdfBuku::TAHAP). Saat menyusun dokumen,
 * kegiatan yang RAB sumber dananya belum berisi rincian membuat proses gagal (seperti mesin dokumen nanti). Pada tahap
 * akhir PDF buku disusun GabungPdfBuku dari halaman pengganti per bagian (jumlah halaman = susunan buku), disimpan di
 * folder senama berkas riwayat buku; penggabungan gagal → status gagal dengan pesannya.
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
            try {
                $hasil = $this->tulisPdf($buku);
            } catch (GagalGabungPdf $e) {
                return $repo->perbarui($id, ['pdf' => ['status' => 'gagal', 'pesan' => $e->getMessage()] + $pdf]);
            }

            return $repo->perbarui($id, ['file_pdf_path' => $hasil['path'], 'ukuran' => $hasil['ukuran'], 'halaman' => $hasil['halaman'], 'pdf' => ['status' => 'selesai', 'tahap' => $pdf['tahap'], 'persen' => 100, 'pesan' => 'PDF siap diunduh.']]);
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

    /**
     * PDF buku: bagian buku (cover, daftar isi, cover/KAK-TOR/RAB tiap kegiatan) berhalaman pengganti digabung lewat
     * GabungPdfBuku (PenggabungPdf) — jumlah halamannya sama dengan susunan buku.
     *
     * @return array{path: string, halaman: int, ukuran: int, bagian: int}
     */
    private function tulisPdf(array $buku): array
    {
        $path = Str::beforeLast(config('sigenrabtor.stub.buku_file'), '.json')."/{$buku['id']}.pdf";

        return GabungPdfBuku::buat($buku, BukuDokumen::tahunAktif(app(KegiatanRepository::class)->semua()), $path);
    }

    /** @param  list<string>  $baris */
    public static function pdfTeks(array $baris): string
    {
        return PdfSederhana::tulis([$baris]);
    }
}
