<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Support\MasterKode;
use App\Support\Pengaturan\KelolaTahunAnggaran;
use App\Support\Pengaturan\KontrolTahunAktif;
use App\Support\Stub\StubData;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pengaturan › Tahun Anggaran (PRD F6, Fase 5 "Tahun Anggaran Aktif"): status tiap tahun (aktif / terkunci / tidak
 * aktif, SK RKTM, master kode) dan pemilihan tepat satu tahun aktif lewat konfirmasi. Tahun aktif berlaku di seluruh
 * aplikasi (StubData::tahunAktif); data tahun lain tidak dihapus. Tambah tahun, kunci/buka kunci, dan hapus tahun yang
 * tidak dipakai lewat KelolaTahunAnggaran.
 */
#[Layout('layouts.app')]
class TahunAnggaran extends Component
{
    /** Tahun yang sedang dikonfirmasi untuk diaktifkan (dialog konfirmasi); null bila dialog tertutup. */
    #[Locked]
    public ?int $akanDiaktifkan = null;

    /** Isian "Tambah tahun anggaran". */
    public string $tahunBaru = '';

    /** Tahun yang sedang dikonfirmasi untuk dihapus (konfirmasi di barisnya). */
    #[Locked]
    public ?int $akanDihapus = null;

    /**
     * Semua tahun beserta status master kodenya.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function daftar(): array
    {
        return array_map(fn (array $t) => $t + [
            'master_tersedia' => $t['pemakaian']['master_kode'],
            'jumlah_rktm' => count(MasterKode::kodeRktm($t['tahun'])),
        ], KelolaTahunAnggaran::daftar());
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

    /** Tambah tahun baru lewat KelolaTahunAnggaran (tidak aktif, tidak terkunci). */
    public function tambahTahun(): void
    {
        $this->resetErrorBag('tahunBaru');
        try {
            $tahun = KelolaTahunAnggaran::tambah($this->tahunBaru, StubData::currentUser());
        } catch (ValidationException $e) {
            $this->addError('tahunBaru', $e->errors()['tahun'][0]);

            return;
        }

        $this->reset('tahunBaru');
        unset($this->daftar, $this->aktif);
        $this->dispatch('notify', message: "Tahun Anggaran {$tahun} ditambahkan. Aktifkan bila sudah siap dikerjakan.", type: 'success');
    }

    /** Kunci tahun yang terbuka, atau buka kunci tahun yang terkunci. */
    public function ubahKunci(int $tahun): void
    {
        $data = collect($this->daftar)->firstWhere('tahun', $tahun);
        if ($data === null) {
            return;
        }

        try {
            KelolaTahunAnggaran::kunci($tahun, ! $data['is_locked'], StubData::currentUser());
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: $e->errors()['tahun'][0], type: 'error');

            return;
        }

        unset($this->daftar, $this->aktif);
        $this->dispatch('notify', message: "Tahun Anggaran {$tahun} ".($data['is_locked'] ? 'dibuka kuncinya — isiannya bisa diubah lagi.' : 'dikunci — isiannya hanya bisa dilihat.'), type: 'success');
    }

    public function mintaHapus(int $tahun): void
    {
        $this->akanDihapus = collect($this->daftar)->contains(fn (array $t) => $t['tahun'] === $tahun && $t['boleh_dihapus']) ? $tahun : null;
    }

    public function batalHapus(): void
    {
        $this->akanDihapus = null;
    }

    public function hapusTahun(): void
    {
        if ($this->akanDihapus === null) {
            return;
        }
        $tahun = $this->akanDihapus;
        $this->akanDihapus = null;

        try {
            KelolaTahunAnggaran::hapus($tahun, StubData::currentUser());
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: $e->errors()['tahun'][0], type: 'error');

            return;
        }

        unset($this->daftar, $this->aktif);
        $this->dispatch('notify', message: "Tahun Anggaran {$tahun} dihapus.", type: 'info');
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
