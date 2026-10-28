<?php

namespace App\Support\Dokumen;

use App\Support\Format;
use App\Support\Rab\AnggaranSumber;
use App\Support\Stub\StubData;

/**
 * Struktur tabel rincian RAB dokumen (PRD 7B.3 "Tabel rincian" gaya Form RAB): kolom No, (Kode — hanya bila ada item
 * berkode), Uraian, Vol, Satuan, Harga Satuan, Jumlah; baris item berurutan cetak dengan nilai mentah (`nilai`, untuk
 * Excel) dan teks siap tampil (`teks`, untuk dokumen); TOTAL KESELURUHAN & terbilang dari AngkaDokumen. Satu-satunya
 * penyusun tabel RAB dokumen — dipakai <x-dokumen.tabel-rab> (pratinjau/cetak, lewat SusunanRab::tabel()) dan
 * EksporRabExcel sehingga isi & angkanya selalu sama. Lebar kolom dalam mm (`KOLOM`) mengikuti lebar isi halaman A4
 * (`LEBAR_ISI`); Uraian mengambil sisa lebar.
 */
final readonly class TabelRab
{
    /** Kolom → [judul, perataan kiri|tengah|kanan, lebar mm (null = sisa lebar)]. */
    public const KOLOM = [
        'no' => ['No', 'tengah', 10],
        'kode' => ['Kode', 'kiri', 19],
        'uraian' => ['Uraian', 'kiri', null],
        'volume' => ['Vol', 'kanan', 12],
        'satuan' => ['Satuan', 'kiri', 22],
        'harga' => ['Harga Satuan (Rp)', 'kanan', 26],
        'jumlah' => ['Jumlah (Rp)', 'kanan', 28],
    ];

    /** Lebar isi halaman A4 dokumen RAB (210 mm dikurangi margin kiri & kanan 12,7 mm), mm. */
    public const LEBAR_ISI = 184.6;

    public const LABEL_TOTAL = 'TOTAL KESELURUHAN';

    /**
     * @param  list<string>  $kolom  Kunci KOLOM yang tampil, urut.
     * @param  list<array{nomor: int, id: string|null, nilai: array<string, int|float|string>, teks: array<string, string>}>  $baris
     */
    public function __construct(
        public ?string $sumber,
        public array $kolom,
        public array $baris,
        public int $total,
        public string $totalTeks,
        public ?string $terbilang,
    ) {}

    /**
     * Tabel RAB satu sumber dana; null = belum ada sumber dana (tabel kosong). `$kode` memaksa kolom Kode tampil/tersembunyi.
     */
    public static function dari(?AnggaranSumber $anggaran, ?bool $kode = null): self
    {
        $rincian = array_values($anggaran?->hasil->rincian ?? []);
        $denganKode = $kode ?? collect($rincian)->contains(fn (array $item) => filled($item['kode'] ?? null));
        $kolom = array_values(array_filter(array_keys(self::KOLOM), fn (string $k) => $k !== 'kode' || $denganKode));

        $baris = [];
        foreach ($rincian as $i => $item) {
            $volume = is_numeric($item['volume'] ?? null) ? $item['volume'] + 0 : 0;
            $nilai = [
                'no' => $i + 1,
                'kode' => (string) ($item['kode'] ?? ''),
                'uraian' => (string) $item['uraian'],
                'volume' => $volume,
                'satuan' => (string) $item['satuan'],
                'harga' => (int) $item['harga_satuan'],
                'jumlah' => (int) $item['jumlah'],
            ];
            $teks = [
                'no' => ($i + 1).'.',
                'kode' => $nilai['kode'],
                'uraian' => $nilai['uraian'],
                'volume' => Format::angka($volume),
                'satuan' => $nilai['satuan'],
                'harga' => Format::angka($nilai['harga']),
                'jumlah' => Format::angka($nilai['jumlah']),
            ];
            $baris[] = [
                'nomor' => $i + 1,
                'id' => isset($item['id']) ? (string) $item['id'] : null,
                'nilai' => array_intersect_key($nilai, array_flip($kolom)),
                'teks' => array_intersect_key($teks, array_flip($kolom)),
            ];
        }

        $angka = $anggaran ? AngkaDokumen::dari($anggaran->hasil, $anggaran->sumber, StubData::tahunAktif()['tahun'], StubData::madrasah()['nama_singkat']) : null;

        return new self($anggaran?->sumber, $kolom, $baris, $anggaran?->total() ?? 0, $angka?->angkaTabel() ?? Format::angka(0), $angka?->terbilang());
    }

    public function kosong(): bool
    {
        return $this->baris === [];
    }

    /** @return list<string> Judul kolom yang tampil. */
    public function judul(): array
    {
        return array_map(fn (string $k) => self::KOLOM[$k][0], $this->kolom);
    }

    /**
     * Lebar tiap kolom yang tampil dalam persen lebar tabel (Uraian = null, mengambil sisa).
     *
     * @return array<string, float|null>
     */
    public function lebar(): array
    {
        return array_combine($this->kolom, array_map(fn (string $k) => self::KOLOM[$k][2] === null ? null : round(self::KOLOM[$k][2] / self::LEBAR_ISI * 100, 2), $this->kolom));
    }

    /** Perataan sel kolom: kiri|tengah|kanan. */
    public static function rata(string $kolom): string
    {
        return self::KOLOM[$kolom][1];
    }
}
