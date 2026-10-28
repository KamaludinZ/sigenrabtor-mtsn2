<?php

namespace App\Repositories;

use App\Contracts\TahunAnggaranRepository;
use App\Models\TahunAnggaran;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Tahun anggaran di PostgreSQL (tabel tahun_anggaran), padanan StubTahunAnggaranRepository dengan bentuk data yang sama
 * (terbaru dulu; tanggal SK Y-m-d; diaktifkan_pada ISO-8601 zona aplikasi). `aktifkan()` dalam satu transaksi:
 * tahun lain dinonaktifkan lebih dulu sehingga indeks "tepat satu aktif" tidak pernah dilanggar. Tahun yang belum dibuat
 * → InvalidArgumentException; penyimpanan yang ditolak database (mis. SK tanpa tanggal) → QueryException (RuntimeException).
 */
class EloquentTahunAnggaranRepository implements TahunAnggaranRepository
{
    public function semua(): array
    {
        return TahunAnggaran::query()->orderByDesc('tahun')->get()->map($this->bentuk(...))->all();
    }

    public function aktif(): ?array
    {
        $aktif = TahunAnggaran::query()->where('is_active', true)->first();

        return $aktif ? $this->bentuk($aktif) : null;
    }

    public function aktifkan(int $tahun, string $oleh): void
    {
        DB::transaction(function () use ($tahun, $oleh) {
            $dipilih = TahunAnggaran::query()->where('tahun', $tahun)->lockForUpdate()->first()
                ?? throw new InvalidArgumentException("Tahun anggaran {$tahun} belum dibuat.");

            TahunAnggaran::query()->where('tahun', '<>', $tahun)->where('is_active', true)->update(['is_active' => false]);
            $dipilih->update(['is_active' => true, 'diaktifkan_oleh' => $oleh, 'diaktifkan_pada' => now()]);
        });
    }

    public function simpanSk(int $tahun, ?string $nomor, ?string $tanggal): void
    {
        $baris = TahunAnggaran::query()->where('tahun', $tahun)->first()
            ?? throw new InvalidArgumentException("Tahun anggaran {$tahun} belum dibuat.");

        $baris->update(['no_sk_rktm' => $nomor, 'tanggal_sk_rktm' => $tanggal]);
    }

    /**
     * @return array{id: int, tahun: int, is_active: bool, is_locked: bool, no_sk_rktm: string|null, tanggal_sk_rktm: string|null, diaktifkan_oleh: string|null, diaktifkan_pada: string|null}
     */
    private function bentuk(TahunAnggaran $t): array
    {
        return [
            'id' => (int) $t->id,
            'tahun' => $t->tahun,
            'is_active' => $t->is_active,
            'is_locked' => $t->is_locked,
            'no_sk_rktm' => $t->no_sk_rktm,
            'tanggal_sk_rktm' => $t->tanggal_sk_rktm?->toDateString(),
            'diaktifkan_oleh' => $t->diaktifkan_oleh,
            'diaktifkan_pada' => $t->diaktifkan_pada?->setTimezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
