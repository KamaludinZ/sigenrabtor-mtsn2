<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Dokumen\AngkaDokumen;
use App\Support\Dokumen\ChecklistIsian;
use App\Support\Dokumen\CoverDokumen;
use App\Support\Dokumen\KaidahDokumen;
use App\Support\Dokumen\StatusCetak;
use App\Support\Dokumen\StatusDraf;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Dokumen\TahunDokumen;
use App\Support\Dokumen\TanggalDokumen;
use App\Support\Dokumen\TeksCover;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tab ruang kerja "Pratinjau Dokumen" (PRD F15 & §17): satu set dokumen per sumber dana seperti hasil cetak — cover dari
 * template cover default tahun aktif, KAK/TOR sembilan bagian, dan RAB — berdampingan dengan panel kaidah dokumen
 * otomatis (KaidahDokumen). Sumber dana di `?sumber=` ('' = sumber pertama). Cetak & unduh menyusul.
 */
#[Layout('layouts.app')]
class PratinjauDokumen extends Component
{
    #[Locked]
    public int $kegiatanId;

    #[Url(except: '')]
    public string $sumber = '';

    public function mount(KegiatanRepository $repo, int $kegiatan): void
    {
        AksesKegiatan::pastikan($kegiatan);
        $this->kegiatanId = $kegiatan;
        $this->updatedSumber();
    }

    public function updatedSumber(): void
    {
        unset($this->kak, $this->rab, $this->kaidah, $this->angka, $this->tanggal, $this->tahunCetak, $this->cover, $this->statusCetak);
        if ($this->sumber !== '' && ! in_array($this->sumber, $this->kak->daftarSumber, true)) {
            $this->sumber = '';
            unset($this->kak, $this->rab, $this->kaidah, $this->angka, $this->tanggal, $this->tahunCetak, $this->cover, $this->statusCetak);
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

    /** Angka dokumen (nominal & terbilang tiap tempat cetak) RAB sumber dana yang ditampilkan; null tanpa sumber dana. */
    #[Computed]
    public function angka(): ?AngkaDokumen
    {
        $anggaran = $this->rab->anggaran;

        return $anggaran ? AngkaDokumen::dari($anggaran->hasil, $anggaran->sumber, StubData::tahunAktif()['tahun'], StubData::madrasah()['nama_singkat']) : null;
    }

    /** Keputusan cetak set dokumen (cover + KAK/TOR + RAB): cap DRAF, alasan, dan saran menghilangkannya. */
    #[Computed]
    public function statusCetak(): StatusCetak
    {
        return StatusCetak::dari($this->dataKegiatan, $this->sumber ?: null, $this->kak, $this->rab);
    }

    /** Cap DRAF set dokumen (cover + KAK/TOR + RAB) beserta alasannya. */
    public function statusDraf(): StatusDraf
    {
        return $this->statusCetak->set;
    }

    /** @return list<array<string, mixed>> Checklist isian wajib per tempat mengisinya (ChecklistIsian). */
    #[Computed]
    public function checklist(): array
    {
        return ChecklistIsian::untuk($this->dataKegiatan);
    }

    /** @return list<array<string, mixed>> Tempat tahun tercetak/terketik (TahunDokumen) dokumen sumber dana yang ditampilkan. */
    #[Computed]
    public function tahunCetak(): array
    {
        return TahunDokumen::dari($this->dataKegiatan, $this->kak, $this->rab, $this->cover);
    }

    /** @return list<array<string, mixed>> Tempat tanggal tercetak (TanggalDokumen) dokumen sumber dana yang ditampilkan. */
    #[Computed]
    public function tanggal(): array
    {
        return TanggalDokumen::dari($this->dataKegiatan, $this->kak->sumber);
    }

    /** @return list<array{kunci: string, nomor: int, judul: string, status: string, pesan: string}> */
    #[Computed]
    public function kaidah(): array
    {
        return KaidahDokumen::periksa($this->dataKegiatan, $this->kak, $this->rab, $this->cover);
    }

    /**
     * Cover default tahun aktif (gambar + kotak teks); tanpa template: bingkai polos dengan susunan teks bawaan.
     *
     * @return array{gambar: string|null, teks: list<array<string, mixed>>, nilai: array<string, string>}
     */
    #[Computed]
    public function cover(): array
    {
        return CoverDokumen::desain() + ['nilai' => TeksCover::nilai($this->dataKegiatan, $this->kak->sumber)];
    }

    public function render()
    {
        return view('livewire.kegiatan.pratinjau-dokumen')->title('Pratinjau Dokumen · '.($this->dataKegiatan['nama_kegiatan'] ?? 'Kegiatan'));
    }
}
