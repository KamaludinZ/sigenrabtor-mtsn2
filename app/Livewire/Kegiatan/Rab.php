<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Forms\ItemBelanjaForm;
use App\Support\Format;
use App\Support\Stub\StubData;
use App\Support\Stub\StubMaster;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Halaman RAB satu kegiatan: satu RAB per sumber dana (DIPA / Komite).
 */
#[Layout('layouts.app')]
class Rab extends Component
{
    #[Locked]
    public int $kegiatanId;

    /** @var array<string, mixed> */
    #[Locked]
    public array $dataKegiatan = [];

    /** Sumber dana yang sedang ditampilkan; tersimpan di URL (?sumber=Komite). */
    #[Url]
    public string $sumber = '';

    public ItemBelanjaForm $item;

    public bool $formItemTerbuka = false;

    public function mount(KegiatanRepository $repo, int $kegiatan): void
    {
        $this->dataKegiatan = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        $this->kegiatanId = $kegiatan;

        if (! in_array($this->sumber, $this->daftarSumber(), true)) {
            $this->sumber = $this->daftarSumber()[0] ?? '';
        }
    }

    public function pilihSumber(string $sumber): void
    {
        if (in_array($sumber, $this->daftarSumber(), true)) {
            $this->sumber = $sumber;
            $this->tutupFormItem();
        }
    }

    public function dapatDiedit(): bool
    {
        return $this->status()->dapatDiedit();
    }

    public function bukaFormItem(): void
    {
        $this->pastikanDapatDiedit();
        $this->formItemTerbuka = true;
        $this->dispatch('form-item-dibuka');
    }

    public function tutupFormItem(): void
    {
        $this->item->kosongkan();
        $this->resetValidation();
        $this->formItemTerbuka = false;
    }

    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'item.')) {
            return;
        }

        $this->pastikanDapatDiedit();
        $field = substr($property, 5);

        match ($field) {
            'uraian' => $this->item->sinkronKategori(),
            'kategori' => $this->item->kategori_manual = true,
            'harga_satuan' => $this->item->rapikanHarga(),
            default => null,
        };

        if ($field !== 'kategori_manual') {
            $this->item->validateOnly($field);
        }
    }

    /**
     * Simpan item baru ke RAB sumber dana aktif. $lanjut: form tetap terbuka untuk item berikutnya.
     */
    public function simpanItem(bool $lanjut = false): void
    {
        $this->pastikanDapatDiedit();
        $this->item->sinkronKategori();
        $this->item->validate();

        $data = $this->item->dataItem();
        app(RabRepository::class)->tambahItem($this->kegiatanId, $this->sumber, $data);
        unset($this->rincian, $this->total);

        $this->item->kosongkan();
        $this->formItemTerbuka = $lanjut;

        $this->dispatch('notify', message: "Item ditambahkan: {$data['uraian']} (".Format::rupiah((int) round($data['volume'] * $data['harga_satuan'])).').');

        if ($lanjut) {
            $this->dispatch('form-item-dibuka');
        }
    }

    private function pastikanDapatDiedit(): void
    {
        if (! $this->dapatDiedit()) {
            abort(403, 'RAB kegiatan berstatus '.$this->status()->label().' sehingga terkunci.');
        }
    }

    /**
     * @return list<string>
     */
    public function daftarSumber(): array
    {
        return array_column($this->dataKegiatan['sumber_dana'] ?? [], 'sumber');
    }

    public function pagu(): ?int
    {
        foreach ($this->dataKegiatan['sumber_dana'] ?? [] as $baris) {
            if ($baris['sumber'] === $this->sumber) {
                return $baris['pagu'] !== null ? (int) $baris['pagu'] : null;
            }
        }

        return null;
    }

    /**
     * Rincian belanja sumber dana aktif; jumlah = volume × harga satuan.
     *
     * @return list<array{id: string, kode: string, uraian: string, kategori: string, volume: float|int, satuan: string, harga_satuan: int, jumlah: int}>
     */
    #[Computed]
    public function rincian(): array
    {
        return array_map(
            fn (array $item) => $item + ['jumlah' => (int) round($item['volume'] * $item['harga_satuan'])],
            $this->sumber === '' ? [] : app(RabRepository::class)->rincian($this->kegiatanId, $this->sumber),
        );
    }

    #[Computed]
    public function total(): int
    {
        return array_sum(array_column($this->rincian, 'jumlah'));
    }

    /**
     * Isi header RAB sesuai format resmi madrasah.
     *
     * @return array{leading: string, program: string, kode_anggaran: string, tahun: int}
     */
    #[Computed]
    public function header(): array
    {
        $leading = StubMaster::cari(StubMaster::leadings(), $this->dataKegiatan['leading'] ?? null);

        return [
            'leading' => $leading['nama_unit'] ?? '—',
            'program' => $this->dataKegiatan['nama_kegiatan'] ?? '—',
            'kode_anggaran' => $this->dataKegiatan['kode_rktm'] ?? '—',
            'tahun' => StubData::tahunAktif()['tahun'],
        ];
    }

    public function status(): StatusKegiatan
    {
        return StatusKegiatan::tryFrom($this->dataKegiatan['status'] ?? '') ?? StatusKegiatan::Draf;
    }

    public function render()
    {
        return view('livewire.kegiatan.rab')
            ->title('RAB · '.($this->dataKegiatan['nama_kegiatan'] ?? 'Kegiatan'));
    }
}
