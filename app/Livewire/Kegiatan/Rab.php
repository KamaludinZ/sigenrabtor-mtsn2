<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Forms\ItemBelanjaForm;
use App\Support\Dokumen\AngkaDokumen;
use App\Support\Format;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\MasterKode;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;
use App\Support\Rab\DampakTermin;
use App\Support\Rab\HasilRab;
use App\Support\Rab\KalkulatorRab;
use App\Support\Rab\KelompokRincian;
use App\Support\Rab\RekapKelompok;
use App\Support\Rab\RincianRab;
use App\Support\Rab\TampilanRab;
use App\Support\Stub\StubData;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
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

    /** Halaman di dalam RAB: rincian belanja atau rekap per kelompok (tersimpan di URL). */
    #[Url(as: 'halaman')]
    public string $halaman = 'rincian';

    public ItemBelanjaForm $item;

    public bool $formItemTerbuka = false;

    /** Panel impor rincian dari Excel untuk sumber dana yang sedang dibuka. */
    public bool $imporTerbuka = false;

    /** Sumber dana yang termin-nya sedang disunting dan belum disimpan (dikabarkan komponen Termin pembayaran). */
    #[Locked]
    public ?string $terminBelumDisimpan = null;

    /** @var array{sumber: string, halaman: string}|null Tujuan pindah yang menunggu konfirmasi karena termin belum disimpan. */
    #[Locked]
    public ?array $tujuanTertunda = null;

    /** Halaman yang diminta lewat $set saat termin belum disimpan (hanya dalam satu permintaan). */
    private ?string $halamanDitahan = null;

    /** Id item yang sedang diubah; null = form dalam mode tambah. */
    #[Locked]
    public ?string $itemDiedit = null;

    /** Sumber dana tempat form item dibuka: isian form tidak boleh tersimpan ke RAB sumber dana lain. */
    #[Locked]
    public ?string $sumberForm = null;

    /** @var array{index: int, item: array<string, mixed>, sumber: string}|null Item terakhir dihapus (untuk "Urungkan"). */
    #[Locked]
    public ?array $itemTerhapus = null;

    /** @var array{id: string, dari: string, ke: string, index: int, item: array<string, mixed>}|null Item terakhir dipindah ke RAB sumber lain (untuk "Urungkan"). */
    #[Locked]
    public ?array $itemDipindah = null;

    /** Tanda pagu & total RAB pada render terakhir (AnggaranKegiatan::tanda); berubah = disiarkan ke tab lain. */
    #[Locked]
    public string $tandaAnggaran = '';

    public function mount(KegiatanRepository $repo, int $kegiatan): void
    {
        $this->dataKegiatan = AksesKegiatan::pastikan($kegiatan);
        $this->kegiatanId = $kegiatan;

        // Sumber & halaman dari URL dinormalkan sesuai sumber dana kegiatan (lihat TampilanRab).
        $this->sumber = $this->tampilan->aktif ?? '';
        $this->updatedKelompok();
        $this->updatedHalaman();
        $this->tandaAnggaran = AnggaranKegiatan::tanda($this->anggaran);
    }

    /**
     * Setiap perubahan isi RAB (tambah, ubah, hapus, salin, pindah, impor, urungkan) mengubah tanda anggaran; perubahan itu
     * dikabarkan ke peramban (event "anggaran-berubah") lalu disiarkan ke tab lain yang membuka kegiatan yang sama.
     */
    public function rendering(): void
    {
        $tanda = AnggaranKegiatan::tanda($this->anggaran);

        if ($tanda !== $this->tandaAnggaran) {
            $this->tandaAnggaran = $tanda;
            $this->dispatch('anggaran-berubah', kegiatan: $this->kegiatanId);
        }
    }

    /**
     * Anggaran kegiatan ini berubah di tab lain (RAB atau pagu di Data Kegiatan): muat ulang data kegiatan & RAB.
     * Tanda diperbarui di sini sehingga pembaruan ini tidak disiarkan balik.
     */
    #[On('anggaran-diperbarui')]
    public function anggaranDiperbarui(int $kegiatan): void
    {
        if ($kegiatan !== $this->kegiatanId) {
            return;
        }

        $this->dataKegiatan = app(KegiatanRepository::class)->temukan($kegiatan) ?? $this->dataKegiatan;
        $this->segarkanRincian();
        $this->tandaAnggaran = AnggaranKegiatan::tanda($this->anggaran);
        $this->dispatch('notify', message: 'Anggaran diperbarui dari tab lain.', type: 'info');
    }

    /**
     * Logika render berdasarkan sumber dana: mode, urutan sumber, sumber aktif, halaman tersedia, RAB terlepas.
     */
    #[Computed]
    public function tampilan(): TampilanRab
    {
        return TampilanRab::tentukan($this->anggaran, $this->sumber, $this->halaman);
    }

    /**
     * Dari rekap: buka rincian dalam tampilan per kelompok belanja.
     */
    public function lihatKelompokDiRincian(): void
    {
        $this->halaman = 'rincian';
        $this->kelompok = 'kategori';
    }

    public function updatingHalaman(mixed $baru): void
    {
        if ($this->halaman === 'termin' && $baru !== 'termin' && $this->terminBelumDisimpan !== null) {
            $this->halamanDitahan = (string) $baru;
        }
    }

    public function updatedHalaman(): void
    {
        if ($this->halamanDitahan !== null) {
            $this->tujuanTertunda = ['sumber' => $this->sumber, 'halaman' => $this->halamanDitahan];
            $this->halaman = 'termin';

            return;
        }

        unset($this->tampilan);
        $this->halaman = $this->tampilan->halaman;
    }

    #[On('termin-berubah')]
    public function terminBerubah(?string $sumber = null): void
    {
        $this->terminBelumDisimpan = $sumber;

        if ($sumber === null) {
            $this->tujuanTertunda = null;
        }
    }

    /** Tinggalkan termin yang belum disimpan dan lanjutkan ke tujuan yang tadi ditahan. */
    public function tinggalkanTermin(): void
    {
        $tujuan = $this->tujuanTertunda;
        $this->tujuanTertunda = null;
        $this->terminBelumDisimpan = null;

        if ($tujuan === null) {
            return;
        }

        $this->halaman = $tujuan['halaman'];
        $tujuan['sumber'] !== $this->sumber ? $this->pilihSumber($tujuan['sumber']) : $this->updatedHalaman();
    }

    public function tetapDiTermin(): void
    {
        $this->tujuanTertunda = null;
    }

    /**
     * Anggaran tiap sumber dana kegiatan, termasuk RAB tersimpan milik sumber yang sudah dilepas (dipilih = false).
     *
     * @return list<AnggaranSumber>
     */
    #[Computed]
    public function anggaran(): array
    {
        return AnggaranKegiatan::untuk($this->dataKegiatan, sertakanTerlepas: true);
    }

    /**
     * Rekap per kelompok belanja & kode akun untuk semua sumber dana kegiatan.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function rekap(): array
    {
        $perSumber = array_map(fn (AnggaranSumber $anggaran) => $anggaran->hasil, AnggaranKegiatan::perSumber($this->dataKegiatan));

        return RekapKelompok::susun($perSumber) + ['hasil' => $perSumber];
    }

    public function pilihSumber(string $sumber): void
    {
        if ($this->halaman === 'termin' && $this->terminBelumDisimpan !== null && $sumber !== $this->sumber) {
            $this->tujuanTertunda = ['sumber' => $sumber, 'halaman' => 'termin'];

            return;
        }

        if (in_array($sumber, $this->daftarSumber(), true)) {
            $this->sumber = $sumber;
            // Dari Termin pembayaran tetap di halaman termin sumber yang dipilih; selain itu ke rincian belanjanya.
            $this->halaman = in_array($this->halaman, TampilanRab::HALAMAN_PER_SUMBER, true) ? $this->halaman : 'rincian';
            $this->lepasStatePerSumber();
        }
    }

    /**
     * Sumber diganti langsung (URL ?sumber= atau $set): normalkan ke sumber dana kegiatan dan lepaskan state RAB sebelumnya.
     */
    public function updatedSumber(): void
    {
        unset($this->tampilan);
        $this->sumber = $this->tampilan->aktif ?? '';
        $this->lepasStatePerSumber();
    }

    /**
     * Form item, urungkan hapus/pindah, dan panel impor milik satu RAB; ditutup saat RAB yang dibuka berganti
     * agar perubahan tidak pernah jatuh ke RAB sumber dana lain.
     */
    private function lepasStatePerSumber(): void
    {
        $this->itemTerhapus = null;
        $this->itemDipindah = null;
        $this->imporTerbuka = false;
        $this->tutupFormItem();
        $this->segarkanRincian();
    }

    public function bukaImpor(): void
    {
        $this->pastikanDapatDiedit();
        $this->tutupFormItem();
        $this->imporTerbuka = ! $this->imporTerbuka;
    }

    /**
     * Rincian hasil impor Excel sudah tersimpan: muat ulang tabel, total, terbilang, dan rekap.
     */
    /** Termin tersimpan: perbarui kartu sumber (jumlah termin & status pasnya). */
    #[On('termin-disimpan')]
    public function terminDisimpan(): void
    {
        $this->segarkanRincian();
    }

    #[On('rab-diimpor')]
    public function rabDiimpor(int $jumlah = 0, string $sumber = ''): void
    {
        $this->segarkanRincian();

        if ($jumlah > 0) {
            $this->dispatch('notify', message: "{$jumlah} item hasil impor ditambahkan ke RAB {$sumber}.", type: 'success');
        }
    }

    public function dapatDiedit(): bool
    {
        return $this->status()->dapatDiedit() && $this->tahunTerkunci === null;
    }

    /** Tahun anggaran kegiatan bila terkunci (KunciKegiatan) — isian hanya bisa dilihat. */
    #[Computed]
    public function tahunTerkunci(): ?int
    {
        return KunciKegiatan::tahunTerkunci($this->dataKegiatan);
    }

    public function bukaFormItem(): void
    {
        $this->pastikanDapatDiedit();
        $this->tutupFormItem();
        $this->formItemTerbuka = true;
        $this->sumberForm = $this->sumber;
        $this->dispatch('form-item-dibuka');
    }

    public function tutupFormItem(): void
    {
        $this->item->kosongkan();
        $this->resetValidation();
        $this->formItemTerbuka = false;
        $this->itemDiedit = null;
        $this->sumberForm = null;
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
        $this->sumberForm = $this->sumber;
        $this->dispatch('form-item-dibuka');
    }

    public function hapusItem(string $id): void
    {
        $this->pastikanDapatDiedit();
        $terhapus = RincianRab::hapus($this->kegiatanId, $this->sumber, $id);

        if ($terhapus === null) {
            return;
        }

        if ($this->itemDiedit === $id) {
            $this->tutupFormItem();
        }

        $this->itemTerhapus = Arr::except($terhapus, 'termin') + ['sumber' => $this->sumber];
        $this->itemDipindah = null;
        $this->segarkanRincian();
        $this->beritahuTermin($terhapus['termin']);
    }

    /**
     * Salin baris tepat di bawahnya lalu buka form ubah untuk salinannya (baris RAB yang mirip, mis. nasi kotak → kue kotak).
     */
    public function salinItem(string $id): void
    {
        $this->pastikanDapatDiedit();
        $repo = app(RabRepository::class);
        $rincian = $repo->rincian($this->kegiatanId, $this->sumber);
        $index = array_search($id, array_column($rincian, 'id'), true);

        if ($index === false) {
            $this->dispatch('notify', message: 'Item tidak ditemukan; mungkin sudah dihapus.', type: 'error');

            return;
        }

        $salinan = ['id' => (string) Str::uuid()] + Arr::except($rincian[$index], ['id']);
        $totalSebelum = DampakTermin::total($this->kegiatanId, $this->sumber);
        $repo->sisipkanItem($this->kegiatanId, $this->sumber, $index + 1, $salinan);
        $this->segarkanRincian();
        $this->beritahuTermin(DampakTermin::sesudahPerubahan($this->kegiatanId, $this->sumber, $totalSebelum));
        $this->ubahItem($salinan['id']);
        $this->dispatch('notify', message: "“{$salinan['uraian']}” disalin tepat di bawahnya — ubah seperlunya lalu simpan.", type: 'info');
    }

    /**
     * Naikkan (-1) atau turunkan (+1) urutan cetak satu baris.
     */
    public function geserItem(string $id, int $arah): void
    {
        $this->pastikanDapatDiedit();
        $repo = app(RabRepository::class);
        $rincian = $repo->rincian($this->kegiatanId, $this->sumber);
        $index = array_search($id, array_column($rincian, 'id'), true);
        $tujuan = $index === false ? -1 : $index + ($arah < 0 ? -1 : 1);

        if ($tujuan < 0 || $tujuan >= count($rincian)) {
            return;
        }

        $terhapus = $repo->hapusItem($this->kegiatanId, $this->sumber, $id);
        $repo->sisipkanItem($this->kegiatanId, $this->sumber, $tujuan, $terhapus['item']);
        $this->segarkanRincian();
    }

    /**
     * Pindahkan baris ke RAB sumber dana lain kegiatan ini (ditambahkan di akhir rinciannya); bisa diurungkan.
     */
    public function pindahSumberItem(string $id, string $tujuan): void
    {
        $this->pastikanDapatDiedit();

        if ($tujuan === $this->sumber || ! in_array($tujuan, $this->daftarSumber(), true)) {
            return;
        }

        $terhapus = RincianRab::hapus($this->kegiatanId, $this->sumber, $id);

        if ($terhapus === null) {
            return;
        }

        if ($this->itemDiedit === $id) {
            $this->tutupFormItem();
        }

        $totalTujuan = DampakTermin::total($this->kegiatanId, $tujuan);
        $idBaru = app(RabRepository::class)->tambahItem($this->kegiatanId, $tujuan, Arr::except($terhapus['item'], ['id']));
        $this->beritahuTermin($terhapus['termin']);
        $this->beritahuTermin(DampakTermin::sesudahPerubahan($this->kegiatanId, $tujuan, $totalTujuan));
        $this->itemDipindah = ['id' => $idBaru, 'dari' => $this->sumber, 'ke' => $tujuan, 'index' => $terhapus['index'], 'item' => $terhapus['item']];
        $this->itemTerhapus = null;
        $this->segarkanRincian();
    }

    public function urungkanPindahItem(): void
    {
        $this->pastikanDapatDiedit();
        $pindah = $this->itemDipindah;

        if ($pindah === null || $pindah['dari'] !== $this->sumber) {
            return;
        }

        $this->beritahuTermin(RincianRab::hapus($this->kegiatanId, $pindah['ke'], $pindah['id'])['termin'] ?? null);
        $this->beritahuTermin(RincianRab::kembalikan($this->kegiatanId, $pindah['dari'], $pindah['index'], $pindah['item']));
        $this->itemDipindah = null;
        $this->segarkanRincian();
    }

    /**
     * Handler seret-lepas antar kelompok (wire:sort): item dilepas di kelompok $kategori.
     */
    public function pindahKategoriItem(string $id, int $posisi, string $kategori): void
    {
        $this->ubahKategoriItem($id, $kategori);
    }

    public function ubahKategoriItem(string $id, string $kategori): void
    {
        $this->pastikanDapatDiedit();
        $item = collect($this->rincian)->firstWhere('id', $id);

        if ($item === null || ! in_array($kategori, ItemBelanjaForm::KATEGORI, true)) {
            $this->segarkanRincian();

            return;
        }

        if ($item['kategori'] !== $kategori) {
            $data = Arr::only($item, ['kode', 'uraian', 'kategori', 'volume', 'satuan', 'harga_satuan']);
            app(RabRepository::class)->ubahItem($this->kegiatanId, $this->sumber, $id, ['kategori' => $kategori] + $data);
            $this->dispatch('notify', message: "“{$item['uraian']}” dipindah ke kelompok ".ItemBelanjaForm::LABEL_KATEGORI[$kategori].'.');
        }

        // Selalu segarkan agar DOM yang digeser seret-lepas kembali sesuai data.
        $this->segarkanRincian();
    }

    public function urungkanHapusItem(): void
    {
        $this->pastikanDapatDiedit();

        if ($this->itemTerhapus === null || $this->itemTerhapus['sumber'] !== $this->sumber) {
            return;
        }

        $this->beritahuTermin(RincianRab::kembalikan($this->kegiatanId, $this->sumber, $this->itemTerhapus['index'], $this->itemTerhapus['item']));
        $this->itemTerhapus = null;
        $this->segarkanRincian();
    }

    /** Total RAB berubah sehingga termin tersimpan tidak lagi (atau kembali) pas: beri tahu (DampakTermin). */
    private function beritahuTermin(?array $dampak): void
    {
        if ($dampak !== null) {
            $this->dispatch('notify', message: $dampak['pesan'], type: $dampak['tipe']);
        }
    }

    private function segarkanRincian(): void
    {
        unset($this->hasil, $this->rincian, $this->total, $this->kelompokRincian, $this->anggaran, $this->angkaDokumen, $this->rekap, $this->tampilan);
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

        if (! $this->formItemTerbuka || $this->sumberForm !== $this->sumber) {
            $this->tutupFormItem();
            $this->dispatch('notify', message: "Form item ditutup karena RAB yang dibuka berganti; tidak ada yang disimpan ke RAB {$this->sumber}.", type: 'error');

            return;
        }

        $this->item->sinkronKategori();
        $this->item->validate();

        // Server memeriksa ulang & menyimpan lewat RincianRab (status terkunci, sumber dana, aturan item).
        $isian = $this->item->dataItem();
        $this->itemTerhapus = null;

        if ($this->itemDiedit !== null) {
            $hasil = RincianRab::ubah($this->kegiatanId, $this->sumber, $this->itemDiedit, $isian);
            $this->tutupFormItem();
            $this->segarkanRincian();
            $this->dispatch('notify', ...$hasil !== null
                ? ['message' => "Item diperbarui: {$hasil['item']['uraian']} (".Format::rupiah($hasil['jumlah']).').']
                : ['message' => "Item yang diubah sudah tidak ada di RAB {$this->sumber} (dihapus atau dipindah); perubahan tidak disimpan.", 'type' => 'error']);
            $this->beritahuTermin($hasil['termin'] ?? null);

            return;
        }

        $hasil = RincianRab::tambah($this->kegiatanId, $this->sumber, $isian);
        $this->segarkanRincian();

        $this->item->kosongkan();
        $this->formItemTerbuka = $lanjut;
        $this->sumberForm = $lanjut ? $this->sumber : null;

        $this->dispatch('notify', message: "Item ditambahkan: {$hasil['item']['uraian']} (".Format::rupiah($hasil['jumlah']).').');
        $this->beritahuTermin($hasil['termin']);

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
        return $this->tampilan->sumber;
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
     * Hasil hitung RAB sumber dana aktif (jumlah per baris, total, subtotal, status pagu).
     */
    #[Computed]
    public function hasil(): HasilRab
    {
        $rincian = $this->sumber === '' ? [] : app(RabRepository::class)->rincian($this->kegiatanId, $this->sumber);

        return KalkulatorRab::hitung($rincian, $this->pagu());
    }

    /**
     * Rincian belanja sumber dana aktif beserta jumlah hasil hitung.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function rincian(): array
    {
        return $this->hasil->rincian;
    }

    /**
     * @return list<array{kunci: string, label: string|null, item: list<array<string, mixed>>, subtotal: int, persen: float}>
     */
    #[Computed]
    public function kelompokRincian(): array
    {
        return KelompokRincian::kelompokkan($this->rincian, $this->kelompok, sertakanKosong: $this->dapatDiedit());
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
        return $this->hasil->total;
    }

    /**
     * Nominal & terbilang yang tercetak di KAK dan RAB sumber dana aktif — layar memakai objek yang sama.
     */
    #[Computed]
    public function angkaDokumen(): AngkaDokumen
    {
        return AngkaDokumen::dari($this->hasil, $this->sumber, StubData::tahunAktif()['tahun'], StubData::madrasah()['nama_singkat']);
    }

    /**
     * Isi header RAB sesuai format resmi madrasah.
     *
     * @return array{leading: string, program: string, kode_anggaran: string, tahun: int}
     */
    #[Computed]
    public function header(): array
    {
        $leading = MasterKode::leading($this->dataKegiatan['leading'] ?? null);

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
