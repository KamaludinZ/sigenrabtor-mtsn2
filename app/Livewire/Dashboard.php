<?php

namespace App\Livewire;

use App\Support\Akun\BerandaAkun;
use App\Support\Akun\PanduanLangkah;
use App\Support\Monitoring\RingkasanAnggaran;
use App\Support\Monitoring\RingkasanDashboard;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dashboard ringkas di halaman utama (PRD F26, §11 "Dashboard Ringkas"): bagian pribadi akun yang masuk (BerandaAkun:
 * perlu tindakan, terakhir diubah, aktivitas, pintasan), panduan langkah (PanduanLangkah: persiapan admin / tahapan kegiatan
 * leading — kegiatan yang dipandu dipilih lewat `$kegiatanPanduan`), kartu ringkasan anggaran, pemakaian pagu DIPA vs Komite, status
 * dokumen, dan kegiatan per leading. Admin melihat seluruh leading; akun leading hanya leading-nya
 * (RingkasanDashboard, sama dengan endpoint dashboard.data).
 */
#[Layout('layouts.app')]
class Dashboard extends Component
{
    /** Kegiatan yang dipandu di panduan tahapan leading (null = yang paling perlu perhatian). */
    public ?int $kegiatanPanduan = null;

    /**
     * Leading yang terlihat: null = semua (admin).
     *
     * @return list<string>|null
     */
    #[Computed]
    public function cakupan(): ?array
    {
        return RingkasanDashboard::cakupan(StubData::currentUser());
    }

    #[Computed]
    public function ringkasan(): RingkasanAnggaran
    {
        return RingkasanDashboard::ringkasan(StubData::currentUser());
    }

    /** Bagian pribadi: tindakan, kegiatan terakhir diubah, aktivitas, pintasan (BerandaAkun). @return array<string, mixed> */
    #[Computed]
    public function beranda(): array
    {
        return BerandaAkun::untuk(StubData::currentUser());
    }

    /**
     * Panduan langkah: admin = persiapan tahun anggaran, leading = tahapan kegiatan (PanduanLangkah).
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function panduan(): array
    {
        $pengguna = StubData::currentUser();

        return $pengguna['role'] === 'admin' ? PanduanLangkah::admin() : PanduanLangkah::leading($pengguna, $this->kegiatanPanduan);
    }

    public function namaCakupan(): string
    {
        return RingkasanDashboard::namaCakupan($this->cakupan);
    }

    public function render()
    {
        return view('livewire.dashboard')->title('Dashboard');
    }
}
