<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Dokumen\CoverDokumen;
use App\Support\Dokumen\JenisDokumen;
use App\Support\Dokumen\PemeriksaanCetak;
use App\Support\Dokumen\StatusCetak;
use App\Support\Dokumen\StatusDraf;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanOtomatis;
use App\Support\Dokumen\SusunanRab;
use App\Support\Dokumen\TeksCover;
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
 * ('' = sumber pertama); jenis dokumen di `?jenis=` (JenisDokumen, pilihan <x-dokumen.pilih-jenis>) menentukan bagian yang
 * tampil (cover/KAK-TOR/RAB), master template, cap DRAF, dan pemeriksaan cetak (PemeriksaanCetak: nama berkas & butir cek).
 */
#[Layout('layouts.app')]
class SusunDokumen extends Component
{
    #[Locked]
    public int $kegiatanId;

    #[Url(except: '')]
    public string $sumber = '';

    #[Url(except: JenisDokumen::BAWAAN)]
    public string $jenis = JenisDokumen::BAWAAN;

    public function mount(int $kegiatan): void
    {
        AksesKegiatan::pastikan($kegiatan);
        $this->kegiatanId = $kegiatan;
        $this->updatedSumber();
    }

    public function updatedSumber(): void
    {
        $this->lupakan();
        if ($this->sumber !== '' && ! in_array($this->sumber, $this->kak->daftarSumber, true)) {
            $this->sumber = '';
            $this->lupakan();
        }
        $this->updatedJenis();
    }

    /** Jenis tak dikenal → bawaan; jenis per sumber dana pada kegiatan tanpa sumber dana → KAK/TOR. */
    public function updatedJenis(): void
    {
        $jenis = JenisDokumen::rapikan($this->jenis);
        $this->jenis = JenisDokumen::perluSumber($jenis) && $this->kak->sumber === null ? JenisDokumen::TANPA_SUMBER : $jenis;
        unset($this->pemeriksaan, $this->pilihanJenis);
    }

    private function lupakan(): void
    {
        unset($this->kak, $this->rab, $this->susunan, $this->statusCetak, $this->pemeriksaan, $this->pilihanJenis, $this->cover);
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
        return SusunanOtomatis::dari($this->kak, $this->rab, $this->dataKegiatan, StubData::currentUser());
    }

    /** @return list<array<string, mixed>> Pilihan jenis dokumen (JenisDokumen::pilihan). */
    #[Computed]
    public function pilihanJenis(): array
    {
        return JenisDokumen::pilihan($this->statusCetak, $this->kak->sumber, StubData::tahunAktif()['tahun']);
    }

    /** @return list<string> Bagian dokumen jenis terpilih (cover, kak, rab). */
    public function isi(): array
    {
        return JenisDokumen::ISI[$this->jenis];
    }

    /** Cap DRAF dokumen jenis terpilih. */
    public function statusDraf(): StatusDraf
    {
        return JenisDokumen::statusDraf($this->jenis, $this->statusCetak);
    }

    /** @return array<string, mixed> Pemeriksaan cetak jenis terpilih (nama berkas, butir aturan 8, siap). */
    #[Computed]
    public function pemeriksaan(): array
    {
        return PemeriksaanCetak::periksa($this->dataKegiatan, $this->jenis, $this->kak->sumber);
    }

    /** @return array<string, array<string, mixed>|null> Master template per bagian jenis terpilih. */
    public function template(): array
    {
        return JenisDokumen::template($this->jenis, StubData::tahunAktif()['tahun']);
    }

    /**
     * Cover kegiatan (set lengkap): desain default tahun aktif berisi kotak teks kegiatan.
     *
     * @return array{gambar: string|null, teks: list<array<string, mixed>>, nilai: array<string, string>}
     */
    #[Computed]
    public function cover(): array
    {
        return CoverDokumen::desain() + ['nilai' => TeksCover::nilai($this->dataKegiatan, $this->kak->sumber)];
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
