<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\MasterKode;
use App\Support\Pengaturan\KontrolTahunAktif;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pengaturan › Tahun Anggaran (PRD F6, Fase 5 "Tahun Anggaran Aktif"): status tiap tahun (aktif / terkunci / tidak
 * aktif, SK RKTM, master kode) dan pemilihan tepat satu tahun aktif lewat konfirmasi. Tahun aktif berlaku di seluruh
 * aplikasi (StubData::tahunAktif); data tahun lain tidak dihapus. Penyembunyian kegiatan per tahun menunggu kolom
 * tahun anggaran di data kegiatan.
 */
#[Layout('layouts.app')]
class TahunAnggaran extends Component
{
    /** Tahun yang sedang dikonfirmasi untuk diaktifkan (dialog konfirmasi); null bila dialog tertutup. */
    #[Locked]
    public ?int $akanDiaktifkan = null;

    /**
     * Semua tahun beserta status master kodenya.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function daftar(): array
    {
        return array_map(fn (array $t) => $t + [
            'master_tersedia' => MasterKode::tersedia($t['tahun']),
            'jumlah_rktm' => count(MasterKode::kodeRktm($t['tahun'])),
        ], app(TahunAnggaranRepository::class)->semua());
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function aktif(): ?array
    {
        return collect($this->daftar)->firstWhere('is_active', true);
    }

    /** Kegiatan yang sedang tampil (milik tahun aktif). */
    #[Computed]
    public function jumlahKegiatan(): int
    {
        return count(app(KegiatanRepository::class)->semua());
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function calon(): ?array
    {
        return $this->akanDiaktifkan === null ? null : collect($this->daftar)->firstWhere('tahun', $this->akanDiaktifkan);
    }

    public function pilih(int $tahun): void
    {
        $dipilih = collect($this->daftar)->firstWhere('tahun', $tahun);

        if ($dipilih === null || $dipilih['is_active']) {
            return;
        }

        $this->akanDiaktifkan = $tahun;
    }

    public function batalPilih(): void
    {
        $this->akanDiaktifkan = null;
    }

    /** Aktifkan tahun yang dikonfirmasi lalu muat ulang halaman agar menu atas & seluruh layar memakai tahun baru. */
    public function aktifkan(): void
    {
        $calon = $this->calon;

        if ($calon === null || ! KontrolTahunAktif::aktifkan($calon['tahun'], StubData::currentUser())) {
            return;
        }

        $this->redirectRoute('pengaturan.tahun.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.pengaturan.tahun-anggaran')->title('Tahun Anggaran · Pengaturan');
    }
}
