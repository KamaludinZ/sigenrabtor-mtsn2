<?php

namespace App\Livewire\Kegiatan;

use App\Support\Kegiatan\FormSections;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Layar utama Data Kegiatan: satu form panjang berisi semua bagian sumber KAK/TOR & RAB.
 */
#[Layout('layouts.app')]
#[Title('Data Kegiatan')]
class Form extends Component
{
    public string $status = 'draf';

    /**
     * @return list<array{id: string, title: string, description: string, icon: string, fields: list<string>}>
     */
    #[Computed]
    public function sections(): array
    {
        return FormSections::all();
    }

    /**
     * @return array{id: int, tahun: int, is_locked: bool}
     */
    #[Computed]
    public function tahunAktif(): array
    {
        return StubData::tahunAktif();
    }

    public function saveDraft(): void
    {
        // Tahap frontend: belum ada penyimpanan; backend menggantinya dengan persistensi kegiatan.
        $this->dispatch('notify', message: 'Draf kegiatan tersimpan (mode data tiruan).');
    }

    public function render()
    {
        return view('livewire.kegiatan.form');
    }
}
