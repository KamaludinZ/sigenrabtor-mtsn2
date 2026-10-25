<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Halaman Matrik & Jadwal satu kegiatan (PRD F10): matrik tahapan (Persiapan, Pelaksanaan, Evaluasi & Pelaporan)
 * × bulan 1–12 untuk bagian 4 KAK, dan jadwal kegiatan 6 baris baku untuk bagian 7. Bulan pelaksanaan dari Data
 * Kegiatan ditandai sebagai acuan.
 */
#[Layout('layouts.app')]
class MatrikJadwal extends Component
{
    #[Locked]
    public int $kegiatanId;

    /** @var array<string, mixed> */
    #[Locked]
    public array $dataKegiatan = [];

    public function mount(KegiatanRepository $repo, int $kegiatan): void
    {
        $this->dataKegiatan = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        $this->kegiatanId = $kegiatan;
    }

    #[Computed]
    public function matrik(): MatrikTahapan
    {
        return MatrikTahapan::dari($this->dataKegiatan);
    }

    #[Computed]
    public function jadwal(): JadwalKegiatan
    {
        return JadwalKegiatan::dari($this->dataKegiatan);
    }

    #[Computed]
    public function pelaksanaan(): DataPelaksanaan
    {
        return DataPelaksanaan::dari($this->dataKegiatan);
    }

    public function tahun(): int
    {
        return StubData::tahunAktif()['tahun'];
    }

    public function status(): StatusKegiatan
    {
        return StatusKegiatan::tryFrom($this->dataKegiatan['status'] ?? '') ?? StatusKegiatan::Draf;
    }

    public function render()
    {
        return view('livewire.kegiatan.matrik-jadwal')
            ->title('Matrik & Jadwal · '.($this->dataKegiatan['nama_kegiatan'] ?? 'Kegiatan'));
    }
}
