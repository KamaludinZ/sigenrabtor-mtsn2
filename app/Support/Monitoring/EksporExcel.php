<?php

namespace App\Support\Monitoring;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Format;
use App\Support\MasterKode;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor Monitoring Anggaran ke Excel (PRD §11 "Saring & Ekspor"): satu buku berisi lembar Ringkasan, Per Leading,
 * Rincian Kegiatan (satu baris per RAB), Kelompok Belanja, Kode Akun, dan Operator dari RingkasanAnggaran yang sama dengan layar
 * (saringan & urutan ikut). Angka uang ditulis sebagai bilangan berformat ribuan agar bisa dijumlah ulang di Excel;
 * selisih yang belum bisa dihitung (RAB tanpa pagu) dikosongkan dan dijelaskan di kolom Keterangan.
 */
final class EksporExcel
{
    public const LEMBAR = ['Ringkasan', 'Per Leading', 'Rincian Kegiatan', 'Kelompok Belanja', 'Kode Akun', 'Operator'];

    private const UANG = '#,##0;[Red]-#,##0';

    private const PERSEN = '0.0';

    /**
     * Buku ekspor dari parameter URL halaman (/monitoring): saringan lewat SaringanMonitoring, urutan rincian
     * `urut`/`arah`, urutan per leading `urut_leading`/`arah_leading`. Dipakai unduhan Excel maupun PDF.
     *
     * @param  array<string, mixed>  $query
     * @return array{0: Spreadsheet, 1: SaringanMonitoring}
     */
    public static function dariPermintaan(array $query): array
    {
        $saringan = SaringanMonitoring::dari($query);
        $ringkasan = RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), $saringan->untukRingkasan());
        $teks = fn (string $kunci, string $bawaan = '') => is_string($query[$kunci] ?? null) ? $query[$kunci] : $bawaan;

        return [self::buat($ringkasan, $saringan, StubPengaturan::pengguna(), $teks('urut', 'total'), $teks('arah') ?: null, $teks('urut_leading', 'kode'), $teks('arah_leading') ?: null), $saringan];
    }

    /** Nama berkas unduhan, mis. "monitoring-anggaran-TA2026-komite.xlsx"; $ekstensi "pdf" untuk ekspor PDF. */
    public static function namaBerkas(SaringanMonitoring $saringan, string $ekstensi = 'xlsx'): string
    {
        $bagian = ['monitoring-anggaran', 'TA'.$saringan->tahunDipantau()];
        if ($saringan->leading !== '') {
            $bagian[] = 'leading-'.$saringan->leading;
        }
        if ($saringan->sumber !== '') {
            $bagian[] = mb_strtolower($saringan->sumber);
        }
        if ($saringan->status !== '') {
            $bagian[] = $saringan->status;
        }

        return implode('-', $bagian).'.'.$ekstensi;
    }

    /**
     * @param  list<array{id: int, name: string, role: string, jabatan?: string}>  $pengguna  Akun untuk lembar Operator.
     */
    public static function buat(RingkasanAnggaran $ringkasan, SaringanMonitoring $saringan, array $pengguna, string $urut = 'total', ?string $arah = null, string $urutLeading = 'kode', ?string $arahLeading = null): Spreadsheet
    {
        $buku = new Spreadsheet;
        $buku->getProperties()
            ->setTitle('Monitoring Anggaran TA '.$saringan->tahunDipantau())
            ->setCreator(config('sigenrabtor.aplikasi.nama'));

        self::ringkasan($buku->getActiveSheet()->setTitle(self::LEMBAR[0]), $ringkasan, $saringan);
        self::perLeading($buku->createSheet()->setTitle(self::LEMBAR[1]), $ringkasan, $saringan, $urutLeading, $arahLeading);
        self::rincian($buku->createSheet()->setTitle(self::LEMBAR[2]), $ringkasan, $urut, $arah);
        $rekap = $ringkasan->perKelompok();
        self::kelompok($buku->createSheet()->setTitle(self::LEMBAR[3]), $ringkasan, $rekap);
        self::kodeAkun($buku->createSheet()->setTitle(self::LEMBAR[4]), $rekap);
        self::operator($buku->createSheet()->setTitle(self::LEMBAR[5]), $ringkasan, $pengguna);
        self::aturCetak($buku, $saringan);
        $buku->setActiveSheetIndex(0);

        return $buku;
    }

    private static function ringkasan(Worksheet $lembar, RingkasanAnggaran $ringkasan, SaringanMonitoring $saringan): void
    {
        $label = $saringan->label();
        unset($label['tahun']);
        $lembar->fromArray([
            ['Monitoring Anggaran — '.StubData::madrasah()['nama']],
            ['Tahun Anggaran '.$saringan->tahunDipantau()],
            ['Saringan: '.($label === [] ? 'semua kegiatan' : implode(' · ', $label))],
            ['Diunduh: '.now()->locale('id')->translatedFormat('j F Y H:i')],
        ], null, 'A1');
        $lembar->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $baris = 6;
        $kepala = ['Sumber dana', 'Pagu', 'Total RAB', 'Selisih', 'Pemakaian (%)', 'Jumlah RAB', 'RAB melebihi pagu', 'RAB belum berpagu'];
        $isi = [];
        foreach ($ringkasan->perSumber() as $sumber => $s) {
            if ($saringan->sumber !== '' && $sumber !== $saringan->sumber) {
                continue;
            }
            $sel = RingkasanAnggaran::sel($s);
            $isi[] = [$sumber, $s['pagu'], $s['total'], $sel['selisih'], $sel['persen'], $s['rab'], $s['melebihi'], $s['tanpa_pagu']];
        }
        $jumlah = RingkasanAnggaran::sel($ringkasan->total());
        $akhir = self::tabel($lembar, $baris, $kepala, $isi, ['Jumlah', $jumlah['pagu'], $jumlah['total'], $jumlah['selisih'], $jumlah['persen'], $jumlah['rab'], $jumlah['melebihi'], $jumlah['tanpa_pagu']]);
        self::format($lembar, ['B', 'C', 'D'], $baris + 1, $akhir, self::UANG);
        self::format($lembar, ['E'], $baris + 1, $akhir, self::PERSEN);
        $lembar->setCellValue('A'.($akhir + 1), 'Selisih & pemakaian kosong bila ada RAB yang pagunya belum diisi (tidak bisa dibandingkan).');
        $lembar->getStyle('A'.($akhir + 1))->getFont()->setItalic(true)->setSize(9);

        $baris = $akhir + 3;
        $statusIsi = array_map(fn (StatusKegiatan $s) => [$s->label(), $ringkasan->perStatus()[$s->value]], StatusKegiatan::cases());
        self::tabel($lembar, $baris, ['Status dokumen', 'Jumlah kegiatan'], $statusIsi, ['Jumlah', $ringkasan->jumlahKegiatan()]);

        self::lebar($lembar, ['A' => 30, 'B' => 19, 'C' => 16, 'D' => 16, 'E' => 14, 'F' => 12, 'G' => 18, 'H' => 18]);
    }

    private static function perLeading(Worksheet $lembar, RingkasanAnggaran $ringkasan, SaringanMonitoring $saringan, string $urut, ?string $arah): void
    {
        $sumber = $saringan->sumber === '' ? array_keys($ringkasan->perSumber()) : [$saringan->sumber];
        $kepala = ['Kode', 'Leading', 'Kegiatan'];
        foreach ($sumber as $s) {
            array_push($kepala, "Pagu {$s}", "Total RAB {$s}", "Selisih {$s}");
        }
        array_push($kepala, 'Pagu jumlah', 'Total RAB jumlah', 'Selisih jumlah', 'Keterangan');

        $baris = array_values(array_filter($ringkasan->perLeading(urut: $urut, arah: $arah), fn (array $b) => $saringan->leading === '' || $b['kode'] === $saringan->leading));
        $isi = array_map(function (array $b) use ($sumber) {
            $kolom = [$b['kode'] ?? '', $b['nama_unit'], $b['jumlah']];
            foreach ($sumber as $s) {
                array_push($kolom, $b['sumber'][$s]['pagu'], $b['sumber'][$s]['total'], $b['sumber'][$s]['selisih']);
            }
            array_push($kolom, $b['pagu'], $b['total'], $b['selisih'], self::keterangan($b));

            return $kolom;
        }, $baris);

        $jumlahSumber = array_map(fn (array $s) => RingkasanAnggaran::sel($s), $ringkasan->perSumber());
        $jumlah = RingkasanAnggaran::sel($ringkasan->total());
        $kaki = ['', 'Jumlah', $ringkasan->jumlahKegiatan()];
        foreach ($sumber as $s) {
            array_push($kaki, $jumlahSumber[$s]['pagu'], $jumlahSumber[$s]['total'], $jumlahSumber[$s]['selisih']);
        }
        array_push($kaki, $jumlah['pagu'], $jumlah['total'], $jumlah['selisih'], self::keterangan($jumlah));

        $akhir = self::tabel($lembar, 1, $kepala, $isi, $kaki, teks: [0]);
        $kolomUang = array_map(fn (int $i) => Coordinate::stringFromColumnIndex($i), range(4, 3 + count($sumber) * 3 + 3));
        self::format($lembar, $kolomUang, 2, $akhir, self::UANG);
        self::bungkus($lembar, [Coordinate::stringFromColumnIndex(count($kepala))], 2, $akhir);
        $lembar->freezePane('D2');
        self::lebar($lembar, ['A' => 8, 'B' => 24, 'C' => 10] + array_fill_keys($kolomUang, 15) + [Coordinate::stringFromColumnIndex(count($kepala)) => 34]);
    }

    private static function rincian(Worksheet $lembar, RingkasanAnggaran $ringkasan, string $urut, ?string $arah): void
    {
        $kepala = ['No', 'Nama kegiatan', 'Kode RKTM', 'Leading', 'Status', 'Sumber dana', 'Pagu', 'Total RAB', 'Selisih', 'Pemakaian (%)', 'Kondisi pagu', 'Jumlah item'];
        $isi = [];
        foreach ($ringkasan->rincianKegiatan($urut, $arah) as $i => $k) {
            $awal = [$i + 1, $k['nama'], $k['kode_rktm'] ?? '', $k['leading'] ? $k['leading'].' '.($k['leading_nama'] ?? '') : 'Leading belum dipilih', $k['status']->label()];
            if ($k['rab'] === []) {
                $isi[] = [...$awal, '', null, null, null, null, 'Sumber dana belum dipilih', null];
            }
            foreach ($k['rab'] as $r) {
                $isi[] = [...$awal, $r['sumber'], $r['pagu'], $r['total'], $r['status']->selisih, $r['persen'], $r['status']->teks(), $r['item']];
            }
        }
        $total = $ringkasan->total();
        $akhir = self::tabel($lembar, 1, $kepala, $isi ?: [['', 'Tidak ada kegiatan yang cocok dengan saringan.']], ['', 'Jumlah '.$ringkasan->jumlahKegiatan().' kegiatan', '', '', '', $total['rab'].' RAB', $total['pagu'], $total['total'], null, null, $total['melebihi'].' RAB melebihi pagunya', null], teks: [2]);
        self::format($lembar, ['G', 'H', 'I'], 2, $akhir, self::UANG);
        self::format($lembar, ['J'], 2, $akhir, self::PERSEN);
        self::bungkus($lembar, ['B', 'D', 'K'], 2, $akhir);
        $lembar->freezePane('C2');
        self::lebar($lembar, ['A' => 6, 'B' => 40, 'C' => 12, 'D' => 22, 'E' => 13, 'F' => 12, 'G' => 15, 'H' => 15, 'I' => 15, 'J' => 13, 'K' => 26, 'L' => 11]);
    }

    /**
     * @param  array<string, mixed>  $rekap  RingkasanAnggaran::perKelompok().
     */
    private static function kelompok(Worksheet $lembar, RingkasanAnggaran $ringkasan, array $rekap): void
    {
        $kepala = ['Kelompok belanja', ...$rekap['sumber'], 'Total', 'Persen (%)', 'Jumlah item', 'Jumlah kegiatan'];
        $isi = array_map(fn (array $k) => [$k['label'], ...array_values($k['perSumber']), $k['total'], $k['persen'], $k['jumlahItem'], $k['kegiatan']], $rekap['kelompok']);
        $akhir = self::tabel($lembar, 1, $kepala, $isi, ['Jumlah', ...array_values($rekap['totalPerSumber']), $rekap['total'], $rekap['total'] > 0 ? 100 : 0, $rekap['jumlahItem'], $ringkasan->jumlahKegiatan()]);
        $n = count($rekap['sumber']);
        $kolomUang = array_map(fn (int $i) => Coordinate::stringFromColumnIndex($i), range(2, 2 + $n));
        self::format($lembar, $kolomUang, 2, $akhir, self::UANG);
        self::format($lembar, [Coordinate::stringFromColumnIndex(3 + $n)], 2, $akhir, self::PERSEN);
        self::lebar($lembar, ['A' => 22] + array_fill_keys(array_map(fn (int $i) => Coordinate::stringFromColumnIndex($i), range(2, 3 + $n)), 16) + [Coordinate::stringFromColumnIndex(4 + $n) => 14, Coordinate::stringFromColumnIndex(5 + $n) => 16]);
    }

    /**
     * @param  array<string, mixed>  $rekap  RingkasanAnggaran::perKelompok().
     */
    private static function kodeAkun(Worksheet $lembar, array $rekap): void
    {
        $n = count($rekap['sumber']);
        $isi = array_map(fn (array $k) => [$k['kode'] === '' ? '—' : $k['kode'], $k['label'], ...array_values($k['perSumber']), $k['total']], $rekap['kode']);
        $akhir = self::tabel($lembar, 1, ['Kode akun', 'Uraian', ...$rekap['sumber'], 'Total'], $isi, ['', 'Jumlah', ...array_values($rekap['totalPerSumber']), $rekap['total']], teks: [0]);
        $kolomUang = array_map(fn (int $i) => Coordinate::stringFromColumnIndex($i), range(3, 3 + $n));
        self::format($lembar, $kolomUang, 2, $akhir, self::UANG);
        self::lebar($lembar, ['A' => 12, 'B' => 42] + array_fill_keys($kolomUang, 16));
    }

    /**
     * @param  list<array{id: int, name: string, role: string, jabatan?: string}>  $pengguna
     */
    private static function operator(Worksheet $lembar, RingkasanAnggaran $ringkasan, array $pengguna): void
    {
        $namaLeading = array_column(MasterKode::leadings(), 'nama_unit', 'kode');
        $status = StatusKegiatan::cases();
        $kepala = ['Operator', 'Peran', 'Leading', 'Kegiatan', ...array_map(fn (StatusKegiatan $s) => $s->label(), $status), 'Pagu', 'Total RAB', 'RAB melebihi pagu', 'RAB belum berpagu', 'Terakhir mengubah'];
        $isi = array_map(fn (array $o) => [
            $o['nama'],
            ['admin' => 'Admin', 'leading' => 'Leading'][$o['peran']] ?? '',
            implode(', ', array_map(fn ($k) => $k.' '.($namaLeading[$k] ?? ''), $o['leading'])),
            $o['jumlah'],
            ...array_map(fn (StatusKegiatan $s) => $o['perStatus'][$s->value], $status),
            $o['pagu'],
            $o['total'],
            $o['melebihi'],
            $o['tanpa_pagu'],
            $o['terakhir'] ? Format::tanggal($o['terakhir']) : '',
        ], $ringkasan->perOperator($pengguna));
        $total = $ringkasan->total();
        $akhir = self::tabel($lembar, 1, $kepala, $isi, ['Jumlah', '', '', $ringkasan->jumlahKegiatan(), ...array_values($ringkasan->perStatus()), $total['pagu'], $total['total'], $total['melebihi'], $total['tanpa_pagu'], '']);
        $kolomPagu = 5 + count($status);
        self::format($lembar, [Coordinate::stringFromColumnIndex($kolomPagu), Coordinate::stringFromColumnIndex($kolomPagu + 1)], 2, $akhir, self::UANG);
        self::lebar($lembar, ['A' => 26, 'B' => 10, 'C' => 30] + array_fill_keys(array_map(fn (int $i) => Coordinate::stringFromColumnIndex($i), range(4, $kolomPagu - 1)), 14) + [Coordinate::stringFromColumnIndex($kolomPagu) => 16, Coordinate::stringFromColumnIndex($kolomPagu + 1) => 16, Coordinate::stringFromColumnIndex($kolomPagu + 2) => 18, Coordinate::stringFromColumnIndex($kolomPagu + 3) => 18, Coordinate::stringFromColumnIndex($kolomPagu + 4) => 20]);
    }

    /**
     * Pengaturan cetak tiap lembar (juga dipakai konversi PDF): A4 lanskap, muat selebar satu halaman, baris judul tabel
     * diulang di tiap halaman, kepala halaman berjudul, dan kaki "Halaman x dari y".
     */
    private static function aturCetak(Spreadsheet $buku, SaringanMonitoring $saringan): void
    {
        $judul = 'Monitoring Anggaran TA '.$saringan->tahunDipantau().' — '.StubData::madrasah()['nama_singkat'];
        $diunduh = config('sigenrabtor.aplikasi.nama').' · diunduh '.now()->locale('id')->translatedFormat('j F Y H:i');

        foreach ($buku->getAllSheets() as $i => $lembar) {
            $lembar->getPageSetup()
                ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                ->setPaperSize(PageSetup::PAPERSIZE_A4)
                ->setFitToWidth(1)
                ->setFitToHeight(0);
            if ($i > 0) {
                $lembar->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
            }
            $lembar->getPageMargins()->setTop(0.6)->setBottom(0.6)->setLeft(0.4)->setRight(0.4)->setHeader(0.25)->setFooter(0.25);
            $lembar->getHeaderFooter()
                ->setOddHeader('&L&B'.$judul.'&R'.$lembar->getTitle())
                ->setOddFooter('&L'.$diunduh.'&RHalaman &P dari &N');
        }
    }

    /**
     * Tulis tabel (kepala hijau tebal, isi, baris jumlah tebal) mulai $baris; kembalikan nomor baris terakhir. Kolom $teks
     * (indeks dari 0) ditulis sebagai teks agar kode seperti "1.6", "1.10", atau "521211" tidak berubah menjadi angka.
     *
     * @param  list<string>  $kepala
     * @param  list<list<mixed>>  $isi
     * @param  list<mixed>  $kaki
     * @param  list<int>  $teks
     */
    private static function tabel(Worksheet $lembar, int $baris, array $kepala, array $isi, array $kaki, array $teks = []): int
    {
        $kolomAkhir = Coordinate::stringFromColumnIndex(count($kepala));
        $lembar->fromArray($kepala, null, "A{$baris}");
        $lembar->getStyle("A{$baris}:{$kolomAkhir}{$baris}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F6F50']],
        ]);
        // fromArray mengosongkan null secara bawaan; nol tetap ditulis 0.
        $lembar->fromArray([...$isi, $kaki], null, 'A'.($baris + 1), true);
        foreach ($isi as $i => $kolom) {
            foreach ($teks as $k) {
                if (($kolom[$k] ?? null) !== null && $kolom[$k] !== '') {
                    $lembar->setCellValueExplicit(Coordinate::stringFromColumnIndex($k + 1).($baris + 1 + $i), (string) $kolom[$k], DataType::TYPE_STRING);
                }
            }
        }
        $akhir = $baris + count($isi) + 1;

        // Kolom angka rata kanan (judul ikut), kolom teks rata kiri; semua berjarak satu indentasi agar isi kolom yang
        // bersebelahan tidak menempel. Judul kolom boleh terbungkus dua baris.
        foreach (array_keys($kepala) as $k) {
            $angka = ! in_array($k, $teks, true) && array_filter(array_column([...$isi, $kaki], $k), fn ($v) => is_string($v) && $v !== '') === [];
            $lembar->getStyle(Coordinate::stringFromColumnIndex($k + 1).$baris.':'.Coordinate::stringFromColumnIndex($k + 1).$akhir)->getAlignment()
                ->setHorizontal($angka ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT)
                ->setIndent(1)
                ->setVertical(Alignment::VERTICAL_TOP);
        }
        $lembar->getStyle("A{$baris}:{$kolomAkhir}{$baris}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_BOTTOM);
        $lembar->getStyle("A{$akhir}:{$kolomAkhir}{$akhir}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F2EF']],
        ]);

        return $akhir;
    }

    /**
     * Bungkus teks panjang (nama kegiatan, keterangan) agar tidak terpotong kolom sebelahnya saat dicetak.
     *
     * @param  list<string>  $kolom
     */
    private static function bungkus(Worksheet $lembar, array $kolom, int $dari, int $sampai): void
    {
        foreach ($kolom as $k) {
            $lembar->getStyle("{$k}{$dari}:{$k}{$sampai}")->getAlignment()->setWrapText(true);
        }
    }

    /**
     * @param  list<string>  $kolom
     */
    private static function format(Worksheet $lembar, array $kolom, int $dari, int $sampai, string $kode): void
    {
        foreach ($kolom as $k) {
            $lembar->getStyle("{$k}{$dari}:{$k}{$sampai}")->getNumberFormat()->setFormatCode($kode);
        }
    }

    /**
     * @param  array<string, int|float>  $lebar
     */
    private static function lebar(Worksheet $lembar, array $lebar): void
    {
        foreach ($lebar as $kolom => $nilai) {
            $lembar->getColumnDimension($kolom)->setWidth($nilai);
        }
    }

    /**
     * Keterangan sel rekap: RAB tanpa pagu (selisih tidak dihitung) dan RAB yang melebihi pagunya sendiri.
     *
     * @param  array{rab: int, melebihi: int, tanpa_pagu: int}  $sel
     */
    private static function keterangan(array $sel): string
    {
        return implode('; ', array_filter([
            $sel['rab'] === 0 ? 'Belum ada RAB' : null,
            $sel['tanpa_pagu'] > 0 ? "{$sel['tanpa_pagu']} RAB belum berpagu (selisih tidak dihitung)" : null,
            $sel['melebihi'] > 0 ? "{$sel['melebihi']} RAB melebihi pagunya" : null,
        ]));
    }
}
