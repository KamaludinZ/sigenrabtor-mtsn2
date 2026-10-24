<?php

namespace App\Livewire\Kegiatan;

use App\Livewire\Forms\KegiatanForm;
use App\Support\Kegiatan\FormSections;
use App\Support\Stub\StubData;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
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

    /** @var array{index: int, anggota: array{key: string, nama: string, jabatan: string, peran: string}}|null Untuk "Urungkan" hapus anggota. */
    #[Locked]
    public ?array $panitiaTerhapus = null;

    /** Isian yang ikut divalidasi ulang ketika isian kunci berubah. */
    private const ISIAN_TERKAIT = [
        'tanggal_dokumen' => ['tanggal_laporan'],
        'tanggal_mulai' => ['lama_hari', 'tanggal_laporan'],
        'lama_hari' => ['tanggal_laporan'],
        'hari_kerja' => ['lama_hari', 'tanggal_laporan'],
    ];

    /**
     * Validasi per isian begitu nilainya berubah, plus aturan turunan (nama cover, jadwal).
     */
    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'form.') || $property === 'form.cover_manual') {
            return;
        }

        $field = substr($property, 5);

        match ($field) {
            'nama_kegiatan' => $this->form->syncNamaCover(),
            'nama_kegiatan_cover' => $this->form->setNamaCoverManual(),
            'tanggal_mulai', 'lama_hari', 'hari_kerja' => $this->form->sinkronJadwal(),
            'tanggal_laporan' => $this->form->setLaporanManual(),
            default => null,
        };

        $this->form->rapikanUraian($field);

        // Isian terkait divalidasi lebih dulu (errornya disimpan), lalu isian utama dengan alur Livewire biasa.
        $this->validasiIsianTerkait(self::ISIAN_TERKAIT[$field] ?? []);
        $this->form->validateOnly($field);
    }

    /**
     * @param  list<string>  $fields
     */
    private function validasiIsianTerkait(array $fields): void
    {
        foreach ($fields as $field) {
            try {
                $this->form->validateOnly($field);
            } catch (ValidationException $e) {
                $this->setErrorBag($e->validator->errors());
            }
        }
    }

    public function toggleBulan(int $bulan): void
    {
        $this->form->toggleBulan($bulan);
        $this->form->validateOnly('bulan_pelaksanaan');
    }

    public function sesuaikanBulan(): void
    {
        $this->form->sesuaikanBulanDenganTanggal();
        $this->form->validateOnly('bulan_pelaksanaan');
    }

    public function resetNamaCover(): void
    {
        $this->form->resetNamaCover();
        $this->form->validateOnly('nama_kegiatan_cover');
    }

    public function tambahPanitia(): void
    {
        $this->panitiaTerhapus = null;

        if (! $this->form->tambahPanitia()) {
            $this->dispatch('notify', message: 'Susunan tim sudah mencapai batas '.KegiatanForm::MAKS_PANITIA.' orang.', type: 'error');

            return;
        }

        $this->dispatch('panitia-ditambah');
    }

    public function hapusPanitia(int $index): void
    {
        if (! isset($this->form->panitia[$index])) {
            return;
        }

        $this->panitiaTerhapus = ['index' => $index, 'anggota' => $this->form->panitia[$index]];
        $this->form->hapusPanitia($index);
        $this->resetErrorPanitia();
    }

    public function urungkanHapusPanitia(): void
    {
        if ($this->panitiaTerhapus === null) {
            return;
        }

        $this->form->sisipkanPanitia($this->panitiaTerhapus['index'], $this->panitiaTerhapus['anggota']);
        $this->panitiaTerhapus = null;
        $this->resetErrorPanitia();
    }

    public function geserPanitia(int $index, int $arah): void
    {
        $this->panitiaTerhapus = null;
        $this->form->geserPanitia($index, $arah > 0 ? 1 : -1);
        $this->resetErrorPanitia();
    }

    /**
     * Handler seret-lepas (wire:sort): $key anggota yang dipindah, $posisi indeks tujuannya.
     */
    public function pindahkanPanitia(string $key, int $posisi): void
    {
        $this->panitiaTerhapus = null;
        $this->form->pindahkanPanitia($key, $posisi);
        $this->resetErrorPanitia();
    }

    public function urutkanPanitiaSesuaiPeran(): void
    {
        $this->panitiaTerhapus = null;
        $this->form->urutkanSesuaiPeran();
        $this->resetErrorPanitia();
        $this->dispatch('notify', message: 'Susunan tim diurutkan sesuai peran.', type: 'info');
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
