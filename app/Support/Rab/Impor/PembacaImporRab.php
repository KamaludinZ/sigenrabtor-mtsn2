<?php

namespace App\Support\Rab\Impor;

use App\Support\Impor\GagalBacaImpor;
use App\Support\Impor\LembarExcel;

/**
 * Membaca tabel rincian anggaran dari berkas Excel/CSV apa adanya — tidak harus template aplikasi.
 *
 * Tabel dicari di 30 baris teratas dari judul kolom yang lazim di RAB madrasah ("No | Kode | Uraian | Vol | Satuan |
 * Harga Satuan (Rp) | Jumlah (Rp)", "KODE KEGIATAN", "JUMLAH SATUAN (Rp.)", …), sehingga tabel yang disalin dari
 * dokumen RAB lama bisa langsung diimpor. Pembacaan berhenti di baris "TOTAL KESELURUHAN"/"JUMLAH" yang nilainya
 * dipakai untuk mencocokkan total.
 */
final class PembacaImporRab
{
    public const MAKS_BARIS = 300;

    /** Kunci isian → judul kolom yang dikenali (dinormalkan saat dibandingkan). */
    public const JUDUL = [
        'no' => ['No', 'No.', 'Nomor', 'No Urut'],
        'kode' => ['Kode', 'Kode Akun', 'Kode Kegiatan', 'Kode Rekening', 'Kode MAK', 'MAK', 'Akun'],
        'uraian' => ['Uraian', 'Rincian', 'Uraian Kegiatan', 'Uraian Belanja', 'Rincian Belanja', 'Komponen', 'Nama Barang', 'Item'],
        'volume' => ['Vol', 'Vol.', 'Volume', 'Qty', 'Kuantitas', 'Banyaknya'],
        'satuan' => ['Satuan', 'Sat', 'Sat.'],
        'harga_satuan' => ['Harga Satuan', 'Harga Satuan (Rp)', 'Harga Satuan (Rp.)', 'Harga', 'Biaya Satuan', 'Jumlah Satuan', 'Jumlah Satuan (Rp.)', 'Harga (Rp)'],
        'jumlah' => ['Jumlah', 'Jumlah (Rp)', 'Jumlah (Rp.)', 'Jumlah / (Rp.)', 'Jumlah Biaya', 'Total', 'Total (Rp)', 'Sub Total', 'Subtotal'],
    ];

    /** Baris penutup tabel: "TOTAL", "TOTAL KESELURUHAN", "JUMLAH", "JUMLAH TOTAL". */
    private const POLA_TOTAL = '/^(total( keseluruhan)?|jumlah( total| keseluruhan)?|grand total)$/u';

    /**
     * @return array{baris: list<array{baris: int, nilai: array<string, string|int|float|null>}>, total_berkas: int|float|string|null, baris_total: int|null}
     *
     * @throws GagalBacaImpor
     */
    public static function baca(string $path, string $namaAsli): array
    {
        $lembar = LembarExcel::buka($path, $namaAsli, saran: 'Simpan tabel rincian sebagai .xlsx lalu unggah lagi.');
        $judul = LembarExcel::cariJudul($lembar, self::JUDUL, ['uraian']);

        if ($judul === null || count(array_intersect(['volume', 'harga_satuan', 'jumlah'], array_keys($judul['kolom']))) < 2) {
            throw new GagalBacaImpor('Tabel rincian tidak ditemukan. Pastikan ada baris judul kolom seperti "Uraian", "Vol", "Satuan", "Harga Satuan", dan "Jumlah".');
        }

        $baris = [];
        $total = null;
        $barisTotal = null;

        foreach ($lembar->getRowIterator($judul['baris'] + 1) as $row) {
            $nomor = $row->getRowIndex();
            $nilai = [];
            foreach ($judul['kolom'] as $kunci => $huruf) {
                $nilai[$kunci] = LembarExcel::nilaiSel($lembar, $huruf.$nomor);
            }

            if (array_filter($nilai, fn ($v) => $v !== null && $v !== '') === []) {
                continue;
            }

            // Baris penutup: teks TOTAL/JUMLAH di kolom No, Kode, atau Uraian tanpa volume.
            $label = LembarExcel::normalkanJudul((string) ($nilai['uraian'] ?: ($nilai['no'] ?? '') ?: ($nilai['kode'] ?? '')));
            if (preg_match(self::POLA_TOTAL, $label) && blank($nilai['volume'] ?? null)) {
                $total = $nilai['jumlah'] ?? null;
                $barisTotal = $nomor;
                break;
            }

            if (count($baris) >= self::MAKS_BARIS) {
                throw new GagalBacaImpor('Tabel berisi lebih dari '.self::MAKS_BARIS.' baris rincian. Bagi menjadi beberapa berkas.');
            }

            $baris[] = ['baris' => $nomor, 'nilai' => $nilai];
        }

        if ($baris === []) {
            throw new GagalBacaImpor('Tabel rincian ditemukan tetapi belum berisi item belanja.');
        }

        return ['baris' => $baris, 'total_berkas' => $total, 'baris_total' => $barisTotal];
    }
}
