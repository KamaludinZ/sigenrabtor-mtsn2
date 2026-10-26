<?php

namespace App\Livewire\Pengaturan;

use App\Support\Pengaturan\BagianPengaturan;
use App\Support\Pengaturan\KesiapanPengaturan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ikhtisar Pengaturan admin (/pengaturan): peta bagian pengaturan per kelompok beserta statusnya, dan daftar kesiapan
 * tahun anggaran aktif — yang wajib siap sebelum leading mengisi kegiatan (PRD alur kerja inti langkah 1–2).
 */
#[Layout('layouts.app')]
class Ikhtisar extends Component
{
    #[Computed]
    public function kesiapan(): KesiapanPengaturan
    {
        return KesiapanPengaturan::tahunAktif();
    }

    /**
     * @return array<string, list<array<string, mixed>>> Kunci kelompok → bagiannya.
     */
    #[Computed]
    public function kelompok(): array
    {
        return collect(BagianPengaturan::semua())->groupBy('kelompok')->map->values()->all();
    }

    public function render()
    {
        return view('livewire.pengaturan.ikhtisar')->title('Pengaturan');
    }
}
