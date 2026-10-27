<?php

namespace App\Support\Monitoring;

use App\Contracts\TahunAnggaranRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Support\Format;
use App\Support\MasterKode;
use App\Support\Stub\StubData;

/**
 * Saringan Monitoring Anggaran (PRD §11 "Saring & Ekspor"): tahun anggaran, leading, sumber dana, status dokumen, dan
 * rentang tanggal pelaksanaan — satu aturan perapian untuk halaman (/monitoring, parameter URL yang sama) dan berkas
 * ekspor sehingga keduanya selalu memuat kegiatan yang sama. Nilai kosong = tanpa saringan; tahun '' = tahun aktif.
 */
final readonly class SaringanMonitoring
{
    public const KUNCI = ['tahun', 'leading', 'sumber', 'status', 'dari', 'sampai'];

    public function __construct(
        public string $tahun = '',
        public string $leading = '',
        public string $sumber = '',
        public string $status = '',
        public string $dari = '',
        public string $sampai = '',
    ) {}

    /**
     * Rapikan nilai mentah (URL/isian): yang tidak dikenal dilepas, tahun aktif ditulis '' dan tanggal harus Y-m-d sah.
     *
     * @param  array<string, mixed>  $nilai
     */
    public static function dari(array $nilai): self
    {
        $teks = fn (string $kunci) => is_scalar($nilai[$kunci] ?? null) ? trim((string) $nilai[$kunci]) : '';
        [$tahun, $leading, $sumber, $status, $dari, $sampai] = array_map($teks, self::KUNCI);

        return new self(
            tahun: self::tahunLain($tahun) ? $tahun : '',
            leading: MasterKode::leading($leading) ? $leading : '',
            sumber: in_array($sumber, SumberDana::urutan(), true) ? $sumber : '',
            status: StatusKegiatan::tryFrom($status) ? $status : '',
            dari: self::tanggalSah($dari) ? $dari : '',
            sampai: self::tanggalSah($sampai) ? $sampai : '',
        );
    }

    /**
     * Tahun anggaran yang bisa dipantau (terbaru lebih dulu).
     *
     * @return list<array{tahun: int, is_active: bool, is_locked: bool}>
     */
    public static function daftarTahun(): array
    {
        return app(TahunAnggaranRepository::class)->semua();
    }

    /** Tahun yang dipantau: saringan tahun atau tahun aktif. */
    public function tahunDipantau(): int
    {
        return $this->tahun !== '' ? (int) $this->tahun : StubData::tahunAktif()['tahun'];
    }

    /** Rentang terbalik (tanggal akhir sebelum tanggal awal) tidak diterapkan sampai diperbaiki. */
    public function rentangTerbalik(): bool
    {
        return $this->dari !== '' && $this->sampai !== '' && $this->dari > $this->sampai;
    }

    /**
     * Argumen saringan RingkasanAnggaran::dari().
     *
     * @return array{tahun: int, leading: string, sumber: string, status: string, dari?: string, sampai?: string}
     */
    public function untukRingkasan(): array
    {
        $rentang = $this->rentangTerbalik() ? [] : ['dari' => $this->dari, 'sampai' => $this->sampai];

        return ['tahun' => $this->tahunDipantau(), 'leading' => $this->leading, 'sumber' => $this->sumber, 'status' => $this->status] + $rentang;
    }

    /**
     * Saringan aktif sebagai label (chip di halaman, keterangan di berkas ekspor): kunci → label; tanggal = rentang.
     *
     * @return array<string, string>
     */
    public function label(): array
    {
        $tanggal = fn (string $t) => Format::tanggal($t);

        return array_filter([
            'tahun' => $this->tahun !== '' ? "TA {$this->tahun}" : null,
            'leading' => MasterKode::leading($this->leading)['nama_unit'] ?? null,
            'sumber' => $this->sumber !== '' ? "Dana {$this->sumber}" : null,
            'status' => StatusKegiatan::tryFrom($this->status)?->label(),
            'tanggal' => match (true) {
                $this->rentangTerbalik() => null,
                $this->dari !== '' && $this->sampai !== '' => 'Pelaksanaan '.$tanggal($this->dari).' – '.$tanggal($this->sampai),
                $this->dari !== '' => 'Pelaksanaan sejak '.$tanggal($this->dari),
                $this->sampai !== '' => 'Pelaksanaan s.d. '.$tanggal($this->sampai),
                default => null,
            },
        ]);
    }

    /**
     * Parameter URL saringan yang terisi (untuk tautan ekspor).
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return array_filter(['tahun' => $this->tahun, 'leading' => $this->leading, 'sumber' => $this->sumber, 'status' => $this->status, 'dari' => $this->dari, 'sampai' => $this->sampai], fn (string $v) => $v !== '');
    }

    /** $tahun adalah tahun anggaran terdaftar selain tahun aktif. */
    private static function tahunLain(string $tahun): bool
    {
        return in_array((int) $tahun, array_column(self::daftarTahun(), 'tahun'), true) && (int) $tahun !== StubData::tahunAktif()['tahun'];
    }

    private static function tanggalSah(string $teks): bool
    {
        return $teks === '' || (preg_match('/^\d{4}-\d{2}-\d{2}$/', $teks) && checkdate((int) substr($teks, 5, 2), (int) substr($teks, 8, 2), (int) substr($teks, 0, 4)));
    }
}
