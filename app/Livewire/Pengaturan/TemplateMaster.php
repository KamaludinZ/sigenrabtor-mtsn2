<?php

namespace App\Livewire\Pengaturan;

use App\Support\Dokumen\DaftarTemplateMaster;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pengaturan › Template Master (PRD Fase 5 "Template KAK/TOR Variabel (Multi per Leading)"; `/pengaturan/template/master`,
 * rute `pengaturan.template.master.index`, HanyaAdmin): halaman utama semua master .docx ber-variabel tahun aktif —
 * ringkasan, saringan di URL (`cari`, `leading` ''|umum|kode, `jenis`, `status` aktif|nonaktif), kelompok per pemilik
 * (umum lalu per leading) dengan default per jenis, status, catatan versi, variabel, berkas, dan pemakaian
 * (DaftarTemplateMaster). Unggah & pratinjau isi master tetap di Template Dokumen › KAK/TOR.
 */
#[Layout('layouts.app')]
class TemplateMaster extends Component
{
    #[Url(except: '')]
    public string $cari = '';

    #[Url(except: '')]
    public string $leading = '';

    #[Url(except: '')]
    public string $jenis = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->rapikan();
    }

    public function updated(string $properti): void
    {
        // Kata kunci tidak dirapikan di tempat (ketikan yang belum selesai tetap); saringan lain dikembalikan ke nilai sah.
        if ($properti !== 'cari') {
            $this->rapikan();
        }
    }

    public function hapusSaringan(): void
    {
        $this->reset('cari', 'leading', 'jenis', 'status');
    }

    /** @return array<string, mixed> DaftarTemplateMaster::data tahun aktif dengan saringan saat ini. */
    #[Computed]
    public function data(): array
    {
        return DaftarTemplateMaster::data((int) StubData::tahunAktif()['tahun'], ['cari' => $this->cari, 'leading' => $this->leading, 'jenis' => $this->jenis, 'status' => $this->status]);
    }

    private function rapikan(): void
    {
        $rapi = DaftarTemplateMaster::saringan(['leading' => $this->leading, 'jenis' => $this->jenis, 'status' => $this->status]);
        $this->leading = $rapi['leading'];
        $this->jenis = $rapi['jenis'];
        $this->status = $rapi['status'];
    }

    public function render()
    {
        return view('livewire.pengaturan.template-master')->title('Template Master · Pengaturan');
    }
}
