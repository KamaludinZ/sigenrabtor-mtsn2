<?php

namespace App\Support\Kegiatan\Impor;

use App\Support\Impor\GagalBacaImpor;
use App\Support\Impor\LembarExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Membaca sheet cascade Perkin (PRD §19) menjadi baris kegiatan mentah: Kode SK → Kode IKSK → Nama Kegiatan, Leading,
 * Pagu DIPA/Komite, Bulan Pelaksanaan. Sheet dipilih otomatis: sheet bernama Perkin/Cascade yang memuat kolom Nama
 * Kegiatan, selain itu sheet lain yang memuatnya. Sel SK & IKSK yang digabung (cascade) berlaku untuk baris di bawahnya
 * sampai kode berikutnya; baris tanpa nama kegiatan hanya membawa kode untuk baris di bawahnya.
 */
final class PembacaPerkin
{
    private const CARI_JUDUL_DI = 15;

    /** Kunci isian → judul kolom yang dikenali (dinormalkan LembarExcel::normalkanJudul). */
    public const JUDUL = [
        'kode_sk' => ['kode sk', 'sk', 'kode sasaran kegiatan', 'kode sasaran'],
        'kode_iksk' => ['kode iksk', 'iksk', 'kode indikator', 'kode indikator kinerja'],
        'nama_kegiatan' => ['nama kegiatan', 'kegiatan', 'uraian kegiatan', 'rencana kegiatan', 'program kegiatan'],
        'leading' => ['leading', 'kode leading', 'leading sektor', 'penanggung jawab'],
        'pagu_dipa' => ['pagu dipa', 'dipa', 'anggaran dipa'],
        'pagu_komite' => ['pagu komite', 'komite', 'anggaran komite'],
        'bulan' => ['bulan pelaksanaan', 'bulan', 'waktu pelaksanaan'],
    ];

    /** Label kolom opsional untuk pesan "kolom tidak ditemukan". */
    public const LABEL = [
        'kode_sk' => 'Kode SK', 'kode_iksk' => 'Kode IKSK', 'leading' => 'Leading', 'pagu_dipa' => 'Pagu DIPA', 'pagu_komite' => 'Pagu Komite', 'bulan' => 'Bulan Pelaksanaan',
    ];

    /**
     * Baris kegiatan beserta nama sheet yang dibaca dan kunci isian yang ditemukan di judul kolomnya.
     *
     * @return array{lembar: string, kolom: list<string>, baris: list<array{baris: int, nilai: array<string, string|int|float|null>, ikut: array{kode_sk: bool, kode_iksk: bool}}>}
     *
     * @throws GagalBacaImpor
     */
    public static function baca(string $path, string $namaAsli): array
    {
        $pertama = LembarExcel::buka($path, $namaAsli, null, 'Gunakan berkas master .xlsx yang memuat sheet cascade Perkin.');
        [$lembar, $judul] = self::cariLembar($pertama->getParent()?->getAllSheets() ?? [$pertama]);

        $hasil = [];
        $sk = null;
        $iksk = null;
        for ($nomor = $judul['baris'] + 1; $nomor <= $lembar->getHighestDataRow(); $nomor++) {
            $nilai = [];
            foreach ($judul['kolom'] as $kunci => $huruf) {
                $nilai[$kunci] = LembarExcel::nilaiSel($lembar, $huruf.$nomor);
            }

            // Cascade: kode SK baru membuka kelompok IKSK baru; sel kosong mengikuti kode di atasnya.
            if (filled($nilai['kode_sk'] ?? null)) {
                $iksk = (string) $nilai['kode_sk'] !== (string) $sk ? null : $iksk;
                $sk = $nilai['kode_sk'];
            }
            if (filled($nilai['kode_iksk'] ?? null)) {
                $iksk = $nilai['kode_iksk'];
            }

            $isi = array_filter(array_diff_key($nilai, ['kode_sk' => true, 'kode_iksk' => true]), fn ($v) => $v !== null && $v !== '');
            if ($isi === []) {
                continue;
            }

            if (count($hasil) >= TemplateImporKegiatan::MAKS_BARIS) {
                throw new GagalBacaImpor('Sheet '.$lembar->getTitle().' berisi lebih dari '.TemplateImporKegiatan::MAKS_BARIS.' baris kegiatan. Bagi menjadi beberapa berkas.');
            }

            $hasil[] = [
                'baris' => $nomor,
                'nilai' => ['kode_sk' => $sk, 'kode_iksk' => $iksk] + $nilai,
                // Kode yang tidak tertulis di baris ini (diambil dari sel gabung/baris di atasnya).
                'ikut' => ['kode_sk' => $sk !== null && blank($nilai['kode_sk'] ?? null), 'kode_iksk' => $iksk !== null && blank($nilai['kode_iksk'] ?? null)],
            ];
        }

        if ($hasil === []) {
            throw new GagalBacaImpor('Sheet '.$lembar->getTitle().' tidak berisi baris kegiatan di bawah judul kolomnya.');
        }

        return ['lembar' => $lembar->getTitle(), 'kolom' => array_keys($judul['kolom']), 'baris' => $hasil];
    }

    /**
     * Sheet cascade beserta judul kolomnya: sheet bernama Perkin/Cascade didahulukan, lalu sheet lain.
     *
     * @param  list<Worksheet>  $semua
     * @return array{0: Worksheet, 1: array{baris: int, kolom: array<string, string>}}
     *
     * @throws GagalBacaImpor
     */
    private static function cariLembar(array $semua): array
    {
        $bernama = array_values(array_filter($semua, fn (Worksheet $l) => preg_match('/perkin|cascade|kaskade/i', $l->getTitle()) === 1));
        $lain = array_values(array_filter($semua, fn (Worksheet $l) => ! in_array($l, $bernama, true)));

        foreach ([...$bernama, ...$lain] as $lembar) {
            $judul = LembarExcel::cariJudul($lembar, self::JUDUL, ['nama_kegiatan'], self::CARI_JUDUL_DI);
            if ($judul === null) {
                continue;
            }
            if (! isset($judul['kolom']['kode_sk']) && ! isset($judul['kolom']['kode_iksk'])) {
                throw new GagalBacaImpor('Sheet '.$lembar->getTitle().' memuat kolom "Nama Kegiatan" tetapi tidak ada kolom "Kode SK" atau "Kode IKSK". Berkas tanpa cascade Perkin diimpor dari Data Kegiatan › Impor dari Excel.');
            }

            return [$lembar, $judul];
        }

        $nama = implode(', ', array_map(fn (Worksheet $l) => $l->getTitle(), $semua));
        if ($bernama !== []) {
            throw new GagalBacaImpor('Sheet '.$bernama[0]->getTitle().' tidak memuat kolom "Nama Kegiatan" — sheet Perkin master kode hanya berisi SK & IKSK. Unggah sheet cascade Perkin yang memuat kegiatan, leading, pagu, dan bulan pelaksanaan.');
        }

        throw new GagalBacaImpor("Sheet cascade Perkin tidak ditemukan. Sheet di berkas ini: {$nama}. Pastikan ada sheet berjudul kolom \"Kode SK\", \"Kode IKSK\", dan \"Nama Kegiatan\".");
    }
}
