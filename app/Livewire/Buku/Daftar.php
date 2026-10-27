<?php

namespace App\Livewire\Buku;

use App\Contracts\KegiatanRepository;
use App\Enums\SumberDana;
use App\Support\Dokumen\BukuDokumen;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Buku Dokumen (PRD §18, F19): pilih leading & sumber dana (`?leading=&sumber=`; kosong = buku pertama yang berisi
 * kegiatan) lalu lihat susunan bukunya — cover buku, daftar kegiatan urut kode RKTM dengan halaman awal, status & cap
 * DRAF tiap kegiatan — serta peta semua buku tahun aktif. Akun leading hanya melihat buku leading-nya. Pratinjau & gabung
 * PDF menyusul.
 */
#[Layout('layouts.app')]
class Daftar extends Component
{
    #[Url(except: '')]
    public string $leading = '';

    #[Url(except: '')]
    public string $sumber = '';

    public function mount(): void
    {
        $this->rapikan();
    }

    public function updatedLeading(): void
    {
        $this->rapikan();
    }

    public function updatedSumber(): void
    {
        $this->rapikan();
    }

    public function pilih(string $leading, string $sumber): void
    {
        $this->leading = $leading;
        $this->sumber = $sumber;
        $this->rapikan();
    }

    /** @return list<array<string, mixed>> Kegiatan tahun aktif. */
    #[Computed]
    public function kegiatan(): array
    {
        return BukuDokumen::tahunAktif(app(KegiatanRepository::class)->semua());
    }

    /** @return list<array{kode: string, nama_unit: string, is_active: bool}> */
    #[Computed]
    public function leadings(): array
    {
        return BukuDokumen::leadingUntuk(StubData::currentUser(), $this->kegiatan);
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function peta(): array
    {
        return BukuDokumen::peta($this->leadings, $this->kegiatan);
    }

    /**
     * Buku yang ditampilkan: pilihan di URL, atau buku pertama yang berisi kegiatan (lalu buku pertama).
     *
     * @return array{leading: string, sumber: string}|null
     */
    #[Computed]
    public function terpilih(): ?array
    {
        if ($this->peta === []) {
            return null;
        }

        $cocok = collect($this->peta)->filter(fn (array $b) => ($this->leading === '' || $b['leading'] === $this->leading) && ($this->sumber === '' || $b['sumber'] === $this->sumber));
        $buku = $cocok->firstWhere('jumlah', '>', 0) ?? $cocok->first() ?? $this->peta[0];

        return ['leading' => $buku['leading'], 'sumber' => $buku['sumber']];
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function isi(): ?array
    {
        return $this->terpilih ? BukuDokumen::isi($this->kegiatan, $this->terpilih['leading'], $this->terpilih['sumber']) : null;
    }

    private function rapikan(): void
    {
        if ($this->leading !== '' && ! in_array($this->leading, array_column($this->leadings, 'kode'), true)) {
            $this->leading = '';
        }
        if ($this->sumber !== '' && SumberDana::tryFrom($this->sumber) === null) {
            $this->sumber = '';
        }
        unset($this->terpilih, $this->isi);
    }

    public function render()
    {
        return view('livewire.buku.daftar')->title('Buku Dokumen');
    }
}
