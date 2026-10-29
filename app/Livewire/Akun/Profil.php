<?php

namespace App\Livewire\Akun;

use App\Support\Akun\DataAkun;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Halaman Akun saya (PRD §8 "Kelola Profil & Sandi"; `/akun`, rute `akun.profil`, semua pengguna): tab `?tab=` (TAB)
 * profil (nama, surel, jabatan, NIP, peran, leading tertaut, status), sandi (formulir ubah sandi + syarat), aktivitas
 * (riwayat masuk terbaru). Data dari DataAkun::untuk (akun yang sedang masuk; tiruan selama autentikasi belum nyata).
 */
#[Layout('layouts.app')]
class Profil extends Component
{
    public const TAB = ['profil' => 'Profil', 'sandi' => 'Sandi', 'aktivitas' => 'Aktivitas masuk'];

    /** Syarat sandi yang ditampilkan di formulir ubah sandi. */
    public const SYARAT_SANDI = ['Minimal 8 karakter', 'Memuat huruf dan angka', 'Berbeda dari sandi lama', 'Bukan nama atau surel Anda'];

    #[Url(except: 'profil')]
    public string $tab = 'profil';

    public function mount(): void
    {
        $this->tab = array_key_exists($this->tab, self::TAB) ? $this->tab : 'profil';
    }

    public function updatedTab(): void
    {
        $this->mount();
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function akun(): ?array
    {
        return DataAkun::untuk(StubData::currentUser());
    }

    public function render()
    {
        return view('livewire.akun.profil')->title('Akun saya');
    }
}
