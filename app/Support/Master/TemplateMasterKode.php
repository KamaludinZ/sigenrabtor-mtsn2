<?php

namespace App\Support\Master;

use App\Support\Master\Impor\BerkasMaster;
use App\Support\MasterKode;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template Excel master kode (PRD §14, F7 "Unduh template"): sheet & judul kolom yang langsung dikenali unggahan
 * (BerkasMaster::LEMBAR, KolomMaster/Pemeriksa*):
 * - Petunjuk — cara mengisi, format kode, dan langkah unggah;
 * - Perkin — SK, IKSK, & Rencana Aksi bertag (Kode SK | Uraian SK | Kode IKSK | Uraian IKSK | Kode RKTM | Unit/Tim
 *   Penerima Mandat | Rencana Aksi | Anggaran DIPA | Anggaran Komite | Jan … Des);
 * - SNP — RKTM (Kode | SNP | Kegiatan | Sub Kegiatan);
 * - EDM — Kode | Komponen | Uraian | Kode RKTM (relasi EDM ↔ RKTM, beberapa kode dipisah koma);
 * - 5 Budaya Mutu — No | Budaya Mutu.
 * `isi` true = berisi master tahun itu (unggah ulang tanpa perubahan → semua "tetap"); kode ditulis sebagai teks.
 * Cakupan satu jenis hanya memuat sheet jenis itu (SK/IKSK/Rencana Aksi → Perkin).
 */
final class TemplateMasterKode
{
    public const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Sheet → judul kolom & lebar. */
    public const SHEET = [
        'Perkin' => [['Kode SK', 12], ['Uraian SK', 40], ['Kode IKSK', 16], ['Uraian IKSK', 40], ['Kode RKTM', 11], ['Unit/Tim Penerima Mandat', 20],
            ['Rencana Aksi', 44], ['Anggaran DIPA', 15], ['Anggaran Komite', 15]],
        'SNP' => [['Kode', 10], ['SNP', 36], ['Kegiatan', 40], ['Sub Kegiatan', 50]],
        'EDM' => [['Kode', 8], ['Komponen', 22], ['Uraian', 70], ['Kode RKTM', 30]],
        '5 Budaya Mutu' => [['No', 6], ['Budaya Mutu', 40]],
    ];

    /** Jenis → sheet. */
    private const SHEET_JENIS = ['sk' => 'Perkin', 'iksk' => 'Perkin', 'rencana_aksi' => 'Perkin', 'rktm' => 'SNP', 'edm' => 'EDM', 'budaya' => '5 Budaya Mutu'];

    /**
     * @throws InvalidArgumentException Cakupan tidak dikenal.
     */
    public static function buat(int $tahun, string $cakupan = BerkasMaster::SEMUA, bool $isi = false, ?int $dari = null): Spreadsheet
    {
        // Titik awal tahun baru: isi master tahun $dari (kode RKTM dll. biasanya berubah — sunting lalu unggah ke $tahun).
        $sumber = $isi ? ($dari ?? $tahun) : $tahun;
        if (! array_key_exists($cakupan, BerkasMaster::cakupan())) {
            throw new InvalidArgumentException("Cakupan template [{$cakupan}] tidak dikenal.");
        }
        $sheet = array_values(array_unique(array_map(fn (string $j) => self::SHEET_JENIS[$j], BerkasMaster::jenisUntuk($cakupan))));

        $buku = new Spreadsheet;
        $buku->getProperties()->setCreator('SIRABTOR')->setTitle("Master kode TA {$tahun}")->setDescription('Template master kode — unggah di Master Kode.');
        self::petunjuk($buku->getActiveSheet(), $tahun, $sheet, $isi && $dari !== null && $dari !== $tahun ? $dari : null);
        foreach ($sheet as $nama) {
            $lembar = $buku->createSheet()->setTitle($nama);
            self::judul($lembar, $nama);
            if ($isi) {
                self::tulis($lembar, match ($nama) {
                    'Perkin' => self::barisPerkin($sumber),
                    'SNP' => array_map(fn (array $r) => [$r['kode'], $r['snp'], $r['kegiatan'], $r['sub_kegiatan']], MasterKode::kodeRktm($sumber)),
                    'EDM' => self::barisEdm($sumber),
                    '5 Budaya Mutu' => array_map(fn (array $b) => [$b['nomor'], $b['nama']], MasterKode::budayaMutu($sumber)),
                }, $nama);
            }
        }
        $buku->setActiveSheetIndex(count($sheet) > 0 ? 1 : 0);

        return $buku;
    }

    public static function namaBerkas(int $tahun, string $cakupan = BerkasMaster::SEMUA, bool $isi = false, ?int $dari = null): string
    {
        $jenis = $cakupan === BerkasMaster::SEMUA ? 'Kode' : str_replace([' ', '/'], '_', JenisKode::DAFTAR[$cakupan]['label']);

        return ($isi ? 'Master_' : 'Template_Master_').$jenis."_TA{$tahun}".($isi && $dari !== null && $dari !== $tahun ? "_dari_TA{$dari}" : '').'.xlsx';
    }

    /**
     * Baris sheet Perkin dari master: per SK → rencana aksi tanpa IKSK lebih dulu (agar tidak mewarisi IKSK baris di
     * atasnya), lalu per IKSK → rencana aksinya (IKSK tanpa rencana aksi = satu baris kode); rencana aksi tanpa SK di
     * paling atas. Kode & uraian SK/IKSK ditulis di setiap baris.
     *
     * @return list<list<mixed>>
     */
    public static function barisPerkin(int $tahun): array
    {
        $sk = array_column(MasterKode::kodeSk($tahun), 'uraian', 'kode');
        $iksk = MasterKode::kodeIksk(null, $tahun);
        $uraianIksk = array_column($iksk, 'uraian', 'kode');
        $ra = MasterKode::rencanaAksi($tahun);
        $tertulis = [];

        $baris = function (?string $kodeSk, ?string $kodeIksk, ?array $r) use ($sk, $uraianIksk): array {
            $bulan = array_fill(0, 12, '');
            foreach ((array) ($r['bulan'] ?? []) as $n) {
                $bulan[(int) $n - 1] = 'X';
            }

            return [
                $kodeSk ?? '', $kodeSk !== null ? ($sk[$kodeSk] ?? '') : '', $kodeIksk ?? '', $kodeIksk !== null ? ($uraianIksk[$kodeIksk] ?? '') : '',
                $r['kode_rktm'] ?? '', $r['unit'] ?? '',
                $r['uraian'] ?? '', $r !== null ? (int) $r['pagu_dipa'] : '', $r !== null ? (int) $r['pagu_komite'] : '', ...$bulan,
            ];
        };

        $hasil = [];
        foreach ($ra as $i => $r) {
            if (($r['kode_sk'] ?? null) === null && ($r['kode_iksk'] ?? null) === null) {
                $hasil[] = $baris(null, null, $r);
                $tertulis[$i] = true;
            }
        }
        $skUrut = array_keys($sk);
        foreach ($ra as $r) { // SK rencana aksi yang tidak ada di master tetap ikut.
            if (($r['kode_sk'] ?? null) !== null && ! in_array($r['kode_sk'], $skUrut, true)) {
                $skUrut[] = $r['kode_sk'];
            }
        }
        foreach ($skUrut as $kodeSk) {
            $adaBaris = false;
            foreach ($ra as $i => $r) {
                if (! isset($tertulis[$i]) && ($r['kode_sk'] ?? null) === $kodeSk && ($r['kode_iksk'] ?? null) === null) {
                    $hasil[] = $baris($kodeSk, null, $r);
                    $tertulis[$i] = $adaBaris = true;
                }
            }
            $ikskSk = array_column(array_filter($iksk, fn (array $x) => $x['kode_sk'] === $kodeSk), 'kode');
            foreach ($ra as $r) {
                if (($r['kode_sk'] ?? null) === $kodeSk && ($r['kode_iksk'] ?? null) !== null && ! in_array($r['kode_iksk'], $ikskSk, true)) {
                    $ikskSk[] = $r['kode_iksk'];
                }
            }
            foreach ($ikskSk as $kodeIksk) {
                $adaRa = false;
                foreach ($ra as $i => $r) {
                    if (! isset($tertulis[$i]) && ($r['kode_iksk'] ?? null) === $kodeIksk && ($r['kode_sk'] ?? null) === $kodeSk) {
                        $hasil[] = $baris($kodeSk, $kodeIksk, $r);
                        $tertulis[$i] = $adaRa = true;
                    }
                }
                if (! $adaRa && isset($uraianIksk[$kodeIksk])) {
                    $hasil[] = $baris($kodeSk, $kodeIksk, null);
                }
                $adaBaris = true;
            }
            if (! $adaBaris && isset($sk[$kodeSk])) {
                $hasil[] = $baris($kodeSk, null, null);
            }
        }
        // IKSK master yang SK induknya tidak ada di master SK.
        foreach ($iksk as $x) {
            if (! isset($sk[$x['kode_sk']])) {
                $hasil[] = [$x['kode_sk'], '', $x['kode'], $x['uraian'], '', '', '', '', '', ...array_fill(0, 12, '')];
            }
        }
        foreach ($ra as $i => $r) { // Sisa (mis. IKSK tanpa SK).
            if (! isset($tertulis[$i])) {
                $hasil[] = $baris($r['kode_sk'] ?? null, $r['kode_iksk'] ?? null, $r);
            }
        }

        return $hasil;
    }

    /**
     * Baris sheet EDM dari master: kode, nama komponen, uraian, kode RKTM terkait (relasi EDM ↔ RKTM).
     *
     * @return list<list<mixed>>
     */
    public static function barisEdm(int $tahun): array
    {
        $komponen = MasterKode::komponenEdm($tahun);
        $relasi = [];
        foreach (MasterKode::relasiEdmRktm($tahun) as $r) {
            $relasi[$r['kode_edm']][] = $r['kode_rktm'];
        }

        return array_map(fn (array $e) => [$e['kode'], $komponen[$e['komponen']] ?? '', $e['uraian'], implode(', ', $relasi[$e['kode']] ?? [])], MasterKode::kodeEdm($tahun));
    }

    private static function judul(Worksheet $lembar, string $nama): void
    {
        $kolom = self::SHEET[$nama];
        if ($nama === 'Perkin') {
            $kolom = [...$kolom, ...array_map(fn (string $b) => [$b, 5], self::BULAN)];
        }
        foreach ($kolom as $i => [$judul, $lebar]) {
            $huruf = Coordinate::stringFromColumnIndex($i + 1);
            $lembar->setCellValue("{$huruf}1", $judul);
            $lembar->getColumnDimension($huruf)->setWidth($lebar);
            if (str_starts_with($judul, 'Kode') || $judul === 'No') {
                $lembar->getStyle("{$huruf}:{$huruf}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
            if (str_starts_with($judul, 'Anggaran')) {
                $lembar->getStyle("{$huruf}:{$huruf}")->getNumberFormat()->setFormatCode('#,##0');
            }
        }
        $akhir = Coordinate::stringFromColumnIndex(count($kolom));
        $gaya = $lembar->getStyle("A1:{$akhir}1");
        $gaya->getFont()->setBold(true);
        $gaya->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E7F0EC');
        $gaya->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $lembar->freezePane('A2');
        $lembar->setAutoFilter("A1:{$akhir}1");
    }

    /**
     * @param  list<list<mixed>>  $baris
     */
    private static function tulis(Worksheet $lembar, array $baris, string $nama): void
    {
        $judul = array_column(self::SHEET[$nama], 0);
        foreach ($baris as $b => $isi) {
            foreach ($isi as $k => $nilai) {
                if ($nilai === '' || $nilai === null) {
                    continue;
                }
                $alamat = Coordinate::stringFromColumnIndex($k + 1).($b + 2);
                $teks = str_starts_with($judul[$k] ?? '', 'Kode') || is_string($nilai);
                if ($teks && ! ($nama === '5 Budaya Mutu' && $k === 0)) {
                    $lembar->setCellValueExplicit($alamat, (string) $nilai, DataType::TYPE_STRING);
                } else {
                    $lembar->setCellValue($alamat, $nilai);
                }
            }
        }
        $lembar->setAutoFilter($lembar->calculateWorksheetDimension());
    }

    /** @param  list<string>  $sheet */
    private static function petunjuk(Worksheet $lembar, int $tahun, array $sheet, ?int $dari = null): void
    {
        $lembar->setTitle('Petunjuk');
        $teks = [
            ["Template master kode — Tahun Anggaran {$tahun}", true],
            ...($dari !== null ? [["Berisi master TA {$dari} sebagai titik awal: kode RKTM, SK/IKSK, dan rencana aksi biasanya berubah tiap tahun — tambah, ubah, atau hapus barisnya sesuai TA {$tahun}. Kode yang berganti nomor dengan nama sub kegiatan sama dikenali otomatis.", false]] : []),
            ['Isi sheet di berkas ini lalu unggah di menu Master Kode › Unggah Excel. Isi berkas dipratinjau dulu dan baru menjadi master setelah disimpan.', false],
            ['Jangan mengganti nama sheet maupun judul kolom di baris pertama. Sheet yang tidak diperlukan boleh dibiarkan kosong atau dihapus.', false],
            ['', false],
        ];
        $per = [
            'Perkin' => [
                'Perkin — SK, IKSK, dan Rencana Aksi (cascade Perjanjian Kinerja).',
                '• Kode SK: SK.10 atau SK.1.2133 · Kode IKSK: IKSK 10.2 atau IKSK.1.2133.1 (boleh berakhiran huruf, mis. IKSK.4422.1.1.a).',
                '• Satu baris per rencana aksi. Kode SK/IKSK yang dikosongkan mengikuti baris di atasnya; SK baru memulai kelompok IKSK baru.',
                '• Baris tanpa Rencana Aksi hanya mendaftarkan SK/IKSK. Kode RKTM = kode sub kegiatan (n.n.n) — dipakai untuk tag EDM otomatis.',
                '• Unit/Tim Penerima Mandat dicocokkan dengan leading (nama unit atau jabatan). Anggaran ditulis angka rupiah tanpa titik.',
                '• Kolom bulan Jan–Des: tandai X pada bulan pelaksanaan.',
            ],
            'SNP' => [
                'SNP — RKTM: Kode sub kegiatan n.n.n (format sel Teks), Standar (SNP), Kegiatan, dan Sub Kegiatan.',
                '• Kode RKTM boleh berbeda dari tahun lalu: daftar baru diterima apa adanya. Kode yang berganti nomor dengan nama sub kegiatan sama dikenali otomatis (kegiatan pemakainya disarankan memakai kode baru).',
                '• SNP & Kegiatan boleh dikosongkan: SNP mengikuti baris judul standar ("PENGEMBANGAN STANDAR ISI") di atasnya, Kegiatan mengikuti kode n.n yang sama atau master tahun lalu. Daftar RKTM per leading (No | kode sub kegiatan | Penanggung Jawab | Program/Kegiatan) juga diterima.',
            ],
            'EDM' => ['EDM — Kode indikator (A1, B3, …), nama Komponen, Uraian indikator, dan Kode RKTM yang direkomendasikan (pisahkan dengan koma).'],
            '5 Budaya Mutu' => ['5 Budaya Mutu — No (1–5) dan nama butir budaya mutu.'],
        ];
        foreach ($sheet as $nama) {
            foreach ($per[$nama] as $i => $baris) {
                $teks[] = [$baris, $i === 0];
            }
            $teks[] = ['', false];
        }
        foreach ($teks as $i => [$isi, $tebal]) {
            $lembar->setCellValue('A'.($i + 1), $isi);
            if ($tebal) {
                $lembar->getStyle('A'.($i + 1))->getFont()->setBold(true);
            }
        }
        $lembar->getStyle('A1')->getFont()->setSize(14);
        $lembar->getColumnDimension('A')->setWidth(130);
    }
}
