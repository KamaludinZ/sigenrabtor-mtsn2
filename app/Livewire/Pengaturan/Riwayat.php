<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\RiwayatPengaturanRepository;
use App\Support\Pengaturan\BagianPengaturan;
use App\Support\Pengaturan\RiwayatPengaturan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pengaturan › Riwayat Perubahan (PRD N7): tabel siapa mengubah pengaturan apa dan kapan, dengan saringan bagian,
 * pengguna, dan kata kunci (tersimpan di URL). Riwayat hanya bisa dibaca.
 */
#[Layout('layouts.app')]
class Riwayat extends Component
{
    public const PER_HALAMAN = 20;

    #[Url(except: '')]
    public string $bagian = '';

    #[Url(except: '')]
    public string $pengguna = '';

    #[Url(except: '')]
    public string $cari = '';

    #[Url(except: 1)]
    public int $halaman = 1;

    /** Saringan berubah → kembali ke halaman 1. */
    public function updated(string $properti): void
    {
        if (in_array($properti, ['bagian', 'pengguna', 'cari'], true)) {
            $this->halaman = 1;
        }
    }

    public function hapusSaringan(): void
    {
        $this->reset('bagian', 'pengguna', 'cari', 'halaman');
    }

    public function ke(int $halaman): void
    {
        $this->halaman = max(1, min($halaman, $this->jumlahHalaman()));
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function semua(): array
    {
        return app(RiwayatPengaturanRepository::class)->semua();
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function terfilter(): array
    {
        $cari = mb_strtolower(trim($this->cari));

        return array_values(array_filter($this->semua, fn (array $r) => ($this->bagian === '' || (RiwayatPengaturan::AKSI[$r['aksi']][1] ?? null) === $this->bagian)
            && ($this->pengguna === '' || $r['pengguna'] === $this->pengguna)
            && ($cari === '' || str_contains(mb_strtolower($r['keterangan'].' '.(RiwayatPengaturan::AKSI[$r['aksi']][0] ?? $r['aksi'])), $cari))));
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function halamanIni(): array
    {
        return array_slice($this->terfilter, ($this->halaman - 1) * self::PER_HALAMAN, self::PER_HALAMAN);
    }

    public function jumlahHalaman(): int
    {
        return max(1, (int) ceil(count($this->terfilter) / self::PER_HALAMAN));
    }

    /**
     * Bagian yang pernah tercatat (untuk pilihan saringan).
     *
     * @return array<string, string> kunci → judul
     */
    public function pilihanBagian(): array
    {
        $ada = array_unique(array_filter(array_map(fn ($r) => RiwayatPengaturan::AKSI[$r['aksi']][1] ?? null, $this->semua)));

        return collect(BagianPengaturan::semua())->whereIn('kunci', $ada)->pluck('judul', 'kunci')->all();
    }

    /**
     * @return list<string>
     */
    public function pilihanPengguna(): array
    {
        return array_values(array_unique(array_column($this->semua, 'pengguna')));
    }

    public function render()
    {
        return view('livewire.pengaturan.riwayat')->title('Riwayat Perubahan · Pengaturan');
    }
}
