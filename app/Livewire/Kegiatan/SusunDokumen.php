<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Dokumen\StatusCetak;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanOtomatis;
use App\Support\Dokumen\SusunanRab;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tab ruang kerja "Susun KAK/TOR" (PRD F4 "Buat KAK/TOR Otomatis"): sembilan bagian KAK/TOR disusun otomatis dari data
 * kegiatan, master & pengaturan tahun aktif, dan RAB — tiap bagian dengan status, isian yang kurang, asal isinya, dan
 * cuplikan hasil susunannya (SusunanOtomatis) — beserta kepala RAB otomatis, master template yang dipakai, dan draf
 * dokumen tersimpan (tiruan StubData::drafDokumen sampai Simpan Draf tersedia). Sumber dana di `?sumber=`
 * ('' = sumber pertama).
 */
#[Layout('layouts.app')]
class SusunDokumen extends Component
{
    #[Locked]
    public int $kegiatanId;

    #[Url(except: '')]
    public string $sumber = '';

    public function mount(int $kegiatan): void
    {
        AksesKegiatan::pastikan($kegiatan);
        $this->kegiatanId = $kegiatan;
        $this->updatedSumber();
    }

    public function updatedSumber(): void
    {
        unset($this->kak, $this->rab, $this->susunan, $this->statusCetak);
        if ($this->sumber !== '' && ! in_array($this->sumber, $this->kak->daftarSumber, true)) {
            $this->sumber = '';
            unset($this->kak, $this->rab, $this->susunan, $this->statusCetak);
        }
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function dataKegiatan(): array
    {
        return app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? abort(404, 'Kegiatan tidak ditemukan.');
    }

    public function status(): StatusKegiatan
    {
        return StatusKegiatan::tryFrom($this->dataKegiatan['status'] ?? '') ?? StatusKegiatan::Draf;
    }

    #[Computed]
    public function kak(): SusunanKak
    {
        return SusunanKak::dari($this->dataKegiatan, $this->sumber ?: null);
    }

    #[Computed]
    public function rab(): SusunanRab
    {
        return SusunanRab::dari($this->dataKegiatan, $this->sumber ?: null);
    }

    #[Computed]
    public function susunan(): SusunanOtomatis
    {
        return SusunanOtomatis::dari($this->kak, $this->rab, $this->dataKegiatan, StubData::currentUser(), StubData::tahunAktif()['tahun']);
    }

    /** Keadaan cetak set dokumen sumber dana ini (cap DRAF + alasan). */
    #[Computed]
    public function statusCetak(): StatusCetak
    {
        return StatusCetak::dari($this->dataKegiatan, $this->sumber ?: null, $this->kak, $this->rab);
    }

    /** @return array{jenis: string, sumber: string|null, disimpan_pada: string, oleh: string}|null */
    public function draf(): ?array
    {
        return StubData::drafDokumen($this->kegiatanId);
    }

    public function render()
    {
        return view('livewire.kegiatan.susun-dokumen')->title('Susun KAK/TOR · '.($this->dataKegiatan['nama_kegiatan'] ?? 'Kegiatan'));
    }
}
