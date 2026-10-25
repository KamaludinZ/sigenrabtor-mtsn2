<?php

namespace App\Support\Rab;

use App\Support\Format;

/**
 * Isi peringatan batas pagu satu RAB (PRD F12, aturan 5) untuk komponen <x-rab.peringatan-pagu>: judul, penjelasan
 * dengan angka & persen pemakaian, dan — bila melebihi — item terbesar sebagai petunjuk bagian yang bisa dikurangi.
 * RAB yang tepat sama dengan pagu, atau belum berisi rincian, tidak diberi peringatan.
 */
final readonly class PeringatanPagu
{
    public function __construct(
        public string $sumber,
        public HasilRab $hasil,
        public StatusPagu $status,
    ) {}

    public static function untuk(string $sumber, HasilRab $hasil): ?self
    {
        $status = $hasil->keteranganPagu();

        if ($status->status === 'sesuai' || $hasil->jumlahItem() === 0) {
            return null;
        }

        return new self($sumber, $hasil, $status);
    }

    /** 'bahaya' (melebihi), 'perhatian' (pagu belum diisi), atau 'info' (di bawah pagu). */
    public function nada(): string
    {
        return match ($this->status->status) {
            'melebihi' => 'bahaya',
            'tanpa_pagu' => 'perhatian',
            default => 'info',
        };
    }

    public function judul(): string
    {
        return match ($this->status->status) {
            'melebihi' => "RAB {$this->sumber} melebihi pagu ".Format::rupiah(-$this->status->selisih),
            'tanpa_pagu' => "Pagu {$this->sumber} belum diisi",
            default => "RAB {$this->sumber} di bawah pagu — sisa ".Format::rupiah($this->status->selisih),
        };
    }

    public function pesan(): string
    {
        $total = Format::rupiah($this->hasil->total);

        if ($this->status->status === 'tanpa_pagu') {
            return "Total RAB {$total} belum bisa dibandingkan dengan pagu. Isi pagu {$this->sumber} di Data Kegiatan sesuai Cascade Perkin.";
        }

        $dari = "Total RAB {$total} dari pagu ".Format::rupiah($this->hasil->pagu).' ('.Format::angka(TabelPagu::persen($this->hasil->total, $this->hasil->pagu)).'%).';

        return $this->status->melebihi()
            ? "{$dari} Kurangi volume atau harga satuan, atau hapus item, sampai total tidak melebihi pagu sebelum diajukan."
            : "{$dari} Pastikan tidak ada belanja yang terlewat; sisa pagu tidak ikut diajukan.";
    }

    /**
     * Item dengan jumlah terbesar (hanya bila melebihi pagu), urut menurun.
     *
     * @return list<array{uraian: string, jumlah: int}>
     */
    public function itemTerbesar(int $batas = 3): array
    {
        if (! $this->status->melebihi()) {
            return [];
        }

        $item = array_map(fn (array $i) => ['uraian' => (string) ($i['uraian'] ?? ''), 'jumlah' => (int) ($i['jumlah'] ?? 0)], $this->hasil->rincian);
        usort($item, fn ($a, $b) => $b['jumlah'] <=> $a['jumlah']);

        return array_slice($item, 0, $batas);
    }
}
