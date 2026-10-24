<?php

namespace App\Livewire\Kegiatan;

use App\Livewire\Forms\KegiatanForm;
use App\Support\Kegiatan\FormSections;
use App\Support\Stub\StubData;
use Illuminate\Validation\ValidationException;
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
    public KegiatanForm $form;

    public string $status = 'draf';

    public function mount(): void
    {
        $this->form->tanggal_dokumen = now()->toDateString();
    }

    /**
     * Validasi per isian begitu nilainya berubah, plus aturan turunan (nama cover).
     */
    public function updated(string $property): void
    {
        match ($property) {
            'form.nama_kegiatan' => $this->form->syncNamaCover(),
            'form.nama_kegiatan_cover' => $this->form->setNamaCoverManual(),
            default => null,
        };

        if (str_starts_with($property, 'form.') && $property !== 'form.cover_manual') {
            $field = substr($property, 5);
            $this->form->rapikanUraian($field);
            $this->form->validateOnly($field);
        }

        // Tanggal pelaporan bergantung pada tanggal dokumen; validasi ulang bila sudah terisi.
        if ($property === 'form.tanggal_dokumen' && $this->form->tanggal_laporan) {
            $this->form->validateOnly('tanggal_laporan');
        }
    }

    public function resetNamaCover(): void
    {
        $this->form->resetNamaCover();
        $this->form->validateOnly('nama_kegiatan_cover');
    }

    public function tambahPanitia(): void
    {
        if (! $this->form->tambahPanitia()) {
            $this->dispatch('notify', message: 'Susunan tim sudah mencapai batas '.KegiatanForm::MAKS_PANITIA.' orang.', type: 'error');

            return;
        }

        $this->dispatch('panitia-ditambah');
    }

    public function hapusPanitia(int $index): void
    {
        $this->form->hapusPanitia($index);
        $this->resetErrorPanitia();
    }

    public function geserPanitia(int $index, int $arah): void
    {
        $this->form->geserPanitia($index, $arah > 0 ? 1 : -1);
        $this->resetErrorPanitia();
    }

    public function isiSusunanBaku(): void
    {
        $this->form->isiSusunanBaku();
        $this->dispatch('panitia-ditambah', posisi: 'awal');
    }

    /**
     * Pesan error baris panitia terikat ke indeks; buang setelah urutan berubah agar tidak salah tempat.
     */
    private function resetErrorPanitia(): void
    {
        $keys = array_filter(
            array_keys($this->getErrorBag()->messages()),
            fn (string $key) => str_starts_with($key, 'form.panitia.'),
        );

        if ($keys !== []) {
            $this->resetErrorBag($keys);
        }
    }

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
        try {
            $this->form->validate();
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: 'Periksa kembali isian yang ditandai merah.', type: 'error');

            throw $e;
        }

        // Tahap frontend: belum ada penyimpanan; backend menggantinya dengan persistensi kegiatan.
        $this->dispatch('notify', message: 'Draf kegiatan tersimpan (mode data tiruan).');
    }

    public function render()
    {
        return view('livewire.kegiatan.form');
    }
}
