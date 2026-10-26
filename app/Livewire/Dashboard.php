<?php

namespace App\Livewire;

use App\Contracts\KegiatanRepository;
use App\Support\MasterKode;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dashboard ringkas di halaman utama (PRD F26, §11 "Dashboard Ringkas"): kartu ringkasan anggaran, pemakaian pagu DIPA
 * vs Komite, status dokumen, dan kegiatan per leading. Admin melihat seluruh leading; akun leading hanya leading-nya.
 */
#[Layout('layouts.app')]
class Dashboard extends Component
{
    /**
     * Leading yang terlihat: null = semua (admin).
     *
     * @return list<string>|null
     */
    #[Computed]
    public function cakupan(): ?array
    {
        $pengguna = StubData::currentUser();

        return $pengguna['role'] === 'admin' ? null : $pengguna['leadings'];
    }

    #[Computed]
    public function ringkasan(): RingkasanAnggaran
    {
        return RingkasanAnggaran::dari(app(KegiatanRepository::class)->semua(), ['tahun' => StubData::tahunAktif()['tahun'], 'leadings' => $this->cakupan]);
    }

    public function namaCakupan(): string
    {
        if ($this->cakupan === null) {
            return 'Semua leading';
        }

        return implode(', ', array_map(fn (string $kode) => $kode.' · '.(MasterKode::cari(MasterKode::leadings(), $kode)['nama_unit'] ?? $kode), $this->cakupan));
    }

    public function render()
    {
        return view('livewire.dashboard')->title('Dashboard');
    }
}
