<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Forms\ItemBelanjaForm;
use App\Support\Format;
use App\Support\Rab\KelompokRincian;
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

    /** Mode tampilan rincian: urutan cetak, per kelompok belanja, atau per kode akun (tersimpan di URL). */
    #[Url(as: 'tampil')]
    public string $kelompok = 'urutan';

    public ItemBelanjaForm $item;

    public bool $formItemTerbuka = false;

    /** Id item yang sedang diubah; null = form dalam mode tambah. */
    #[Locked]
    public ?string $itemDiedit = null;

    /** @var array{index: int, item: array<string, mixed>, sumber: string}|null Item terakhir dihapus (untuk "Urungkan"). */
    #[Locked]
    public ?array $itemTerhapus = null;

    public function mount(KegiatanRepository $repo, int $kegiatan): void
    {
        $this->dataKegiatan = $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        $this->kegiatanId = $kegiatan;

        if (! in_array($this->sumber, $this->daftarSumber(), true)) {
            $this->sumber = $this->daftarSumber()[0] ?? '';
        }

        $this->updatedKelompok();
    }

    public function pilihSumber(string $sumber): void
    {
        if (in_array($sumber, $this->daftarSumber(), true)) {
            $this->sumber = $sumber;
            $this->itemTerhapus = null;
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
        $this->tutupFormItem();
        $this->formItemTerbuka = true;
        $this->dispatch('form-item-dibuka');
    }

    public function tutupFormItem(): void
    {
        $this->item->kosongkan();
        $this->resetValidation();
        $this->formItemTerbuka = false;
        $this->itemDiedit = null;
    }

    /**
     * Buka form dalam mode ubah untuk item tertentu.
     */
    public function ubahItem(string $id): void
    {
        $this->pastikanDapatDiedit();
        $item = collect($this->rincian)->firstWhere('id', $id);

        if ($item === null) {
            $this->dispatch('notify', message: 'Item tidak ditemukan; mungkin sudah dihapus.', type: 'error');

            return;
        }

        $this->tutupFormItem();
        $this->item->isiDari($item);
        $this->itemDiedit = $id;
        $this->formItemTerbuka = true;
        $this->dispatch('form-item-dibuka');
    }

    public function hapusItem(string $id): void
    {
        $this->pastikanDapatDiedit();
        $terhapus = app(RabRepository::class)->hapusItem($this->kegiatanId, $this->sumber, $id);

        if ($terhapus === null) {
            return;
        }

        if ($this->itemDiedit === $id) {
            $this->tutupFormItem();
        }

        $this->itemTerhapus = $terhapus + ['sumber' => $this->sumber];
        $this->segarkanRincian();
    }

    public function urungkanHapusItem(): void
    {
        $this->pastikanDapatDiedit();

        if ($this->itemTerhapus === null || $this->itemTerhapus['sumber'] !== $this->sumber) {
            return;
        }

        app(RabRepository::class)->sisipkanItem($this->kegiatanId, $this->sumber, $this->itemTerhapus['index'], $this->itemTerhapus['item']);
        $this->itemTerhapus = null;
        $this->segarkanRincian();
    }

    private function segarkanRincian(): void
    {
        unset($this->rincian, $this->total, $this->kelompokRincian);
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
        $jumlah = Format::rupiah((int) round($data['volume'] * $data['harga_satuan']));
        $repo = app(RabRepository::class);
        $this->itemTerhapus = null;

        if ($this->itemDiedit !== null) {
            $repo->ubahItem($this->kegiatanId, $this->sumber, $this->itemDiedit, $data);
            $this->tutupFormItem();
            $this->segarkanRincian();
            $this->dispatch('notify', message: "Item diperbarui: {$data['uraian']} ({$jumlah}).");

            return;
        }

        $repo->tambahItem($this->kegiatanId, $this->sumber, $data);
        $this->segarkanRincian();

        $this->item->kosongkan();
        $this->formItemTerbuka = $lanjut;

        $this->dispatch('notify', message: "Item ditambahkan: {$data['uraian']} ({$jumlah}).");

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

    /**
     * @return list<array{kunci: string, label: string|null, item: list<array<string, mixed>>, subtotal: int, persen: float}>
     */
    #[Computed]
    public function kelompokRincian(): array
    {
        return KelompokRincian::kelompokkan($this->rincian, $this->kelompok);
    }

    public function updatedKelompok(): void
    {
        if (! isset(KelompokRincian::MODE[$this->kelompok])) {
            $this->kelompok = 'urutan';
        }
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
