<?php

namespace App\Livewire\Pemeriksaan;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Pemeriksaan\AntreanPemeriksaan;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pemeriksaan (PRD Fase 6 §16, F17–F18): daftar kegiatan tahun aktif per status alur mutu — tab di `?status=` (bawaan
 * Perlu diperiksa), cari nama di `?cari=`, saringan leading di `?leading=`. Panel periksa (catatan, setujui/kembalikan),
 * arsip final, dan buka kunci menyusul. Khusus admin.
 */
#[Layout('layouts.app')]
class Daftar extends Component
{
    #[Url(as: 'status', except: 'diperiksa')]
    public string $tab = 'diperiksa';

    #[Url(except: '')]
    public string $cari = '';

    #[Url(except: '')]
    public string $leading = '';

    public function mount(): void
    {
        $this->updatedTab();
    }

    public function updatedTab(): void
    {
        if (! array_key_exists($this->tab, AntreanPemeriksaan::TAB)) {
            $this->tab = 'diperiksa';
        }
    }

    /**
     * Semua baris kegiatan tahun aktif.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function semua(): array
    {
        $tahun = StubData::tahunAktif()['tahun'];

        return AntreanPemeriksaan::baris(array_values(array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => TahunKegiatan::dari($k) === $tahun)));
    }

    /** @return array<string, int> */
    public function jumlah(): array
    {
        return AntreanPemeriksaan::jumlah($this->semua);
    }

    /**
     * Baris tab aktif setelah cari & saringan leading.
     *
     * @return list<array<string, mixed>>
     */
    public function baris(): array
    {
        $cari = mb_strtolower(trim($this->cari));

        return array_values(array_filter(
            AntreanPemeriksaan::untukTab($this->semua, $this->tab),
            fn (array $b) => ($cari === '' || str_contains(mb_strtolower($b['nama'].' '.($b['pembuat'] ?? '')), $cari))
                && ($this->leading === '' || $b['leading'] === $this->leading),
        ));
    }

    public function hapusSaringan(): void
    {
        $this->reset('cari', 'leading');
    }

    public function render()
    {
        return view('livewire.pemeriksaan.daftar')->title('Pemeriksaan');
    }
}
