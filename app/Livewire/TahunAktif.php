<?php

namespace App\Livewire;

use App\Support\Pengaturan\KontrolTahunAktif;
use App\Support\Stub\StubData;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Kontrol tahun anggaran aktif di bilah atas (KontrolTahunAktif): lencana tahun aktif yang membuka panel — apa saja yang
 * mengikuti tahun itu beserta kesiapannya — dan, untuk admin, ganti tahun aktif lewat konfirmasi di tempat (lalu
 * halaman yang sama dimuat ulang dengan tahun baru) serta tautan Pengaturan › Tahun Anggaran.
 */
class TahunAktif extends Component
{
    /** Halaman tempat kontrol dibuka; dimuat ulang setelah tahun diganti. */
    #[Locked]
    public string $kembali = '';

    /** Tahun yang menunggu konfirmasi untuk diaktifkan. */
    public ?int $akanDiaktifkan = null;

    public function mount(): void
    {
        $this->kembali = url()->full();
    }

    public function admin(): bool
    {
        return StubData::currentUser()['role'] === 'admin';
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function pilihan(): array
    {
        return KontrolTahunAktif::pilihan();
    }

    /** @return array<string, mixed>|null */
    public function aktif(): ?array
    {
        return collect($this->pilihan)->firstWhere('is_active', true);
    }

    public function pilih(int $tahun): void
    {
        $dipilih = collect($this->pilihan)->firstWhere('tahun', $tahun);
        $this->akanDiaktifkan = $this->admin() && $dipilih && ! $dipilih['is_active'] ? $tahun : null;
    }

    public function batal(): void
    {
        $this->akanDiaktifkan = null;
    }

    public function aktifkan(): void
    {
        if (! $this->admin() || $this->akanDiaktifkan === null || ! KontrolTahunAktif::aktifkan($this->akanDiaktifkan, StubData::currentUser())) {
            $this->akanDiaktifkan = null;

            return;
        }

        $this->redirect($this->kembali ?: route('dashboard'), navigate: true);
    }

    public function kelola(): ?string
    {
        return $this->admin() && Route::has('pengaturan.tahun.index') ? route('pengaturan.tahun.index') : null;
    }

    public function render()
    {
        return view('livewire.tahun-aktif');
    }
}
