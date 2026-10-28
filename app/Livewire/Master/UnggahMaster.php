<?php

namespace App\Livewire\Master;

use App\Contracts\KegiatanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Impor\GagalBacaImpor;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Master\Impor\BerkasMaster;
use App\Support\Master\Impor\PratinjauMaster;
use App\Support\Master\Impor\ProsesBerkasMaster;
use App\Support\Master\JenisKode;
use App\Support\Stub\StubData;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Formulir unggah berkas master kode (PRD §14) untuk satu tahun anggaran: pilih cakupan (berkas master lengkap atau
 * satu jenis), pilih/seret berkas Excel, lalu sheet tiap jenis dikenali dari namanya (BerkasMaster). Berkas sementara
 * disimpan sampai dibatalkan. *Lanjut ke pratinjau* membaca isi tiap sheet (PratinjauMaster) dan membandingkannya dengan
 * master tahun ini per jenis (tab, saringan status). Batal kapan saja tanpa mengubah data (konfirmasi bila pratinjau berisi
 * perubahan; berkas sementara dibuang). Khusus admin; tahun terkunci tidak bisa diunggah.
 */
class UnggahMaster extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $tahun;

    public string $cakupan = BerkasMaster::SEMUA;

    /** @var TemporaryUploadedFile|null */
    public $berkas = null;

    /** @var array{nama: string, ukuran: int, lembar: list<string>}|null Berkas yang sudah terbaca. */
    #[Locked]
    public ?array $terpilih = null;

    #[Locked]
    public ?string $galat = null;

    /** 'unggah' | 'pratinjau' */
    #[Locked]
    public string $tahap = 'unggah';

    /** @var array<string, array<string, mixed>> Hasil PratinjauMaster::susun() per jenis. */
    #[Locked]
    public array $pratinjau = [];

    /** Jenis yang sedang dilihat di pratinjau. */
    public string $tabPratinjau = '';

    /** Saringan status baris pratinjau: 'semua' atau kunci PratinjauMaster::STATUS. */
    public string $saring = 'semua';

    /** Konfirmasi batal tampil (pratinjau berisi perubahan yang akan dibuang). */
    public bool $konfirmasiBatal = false;

    public function mount(int $tahun): void
    {
        $this->tahun = $tahun;
        $this->pastikanBoleh();
    }

    public function updatedCakupan(): void
    {
        if (! array_key_exists($this->cakupan, BerkasMaster::cakupan())) {
            $this->cakupan = BerkasMaster::SEMUA;
        }
        if ($this->terpilih) {
            $this->galat = ProsesBerkasMaster::galatCakupan($this->terpilih['lembar'], $this->cakupan);
        }
    }

    public function updatedBerkas(): void
    {
        $this->pastikanBoleh();
        $this->galat = null;
        $this->terpilih = null;

        try {
            $this->terpilih = ProsesBerkasMaster::bacaBerkas($this->berkas instanceof TemporaryUploadedFile ? $this->berkas : null);
        } catch (GagalBacaImpor $e) {
            $this->galat = $e->getMessage();
            $this->buangBerkas();

            return;
        }
        // Berkas yang terbaca tetap terpilih walau cakupannya belum cocok (mis. CSV pada "Berkas master lengkap").
        $this->updatedCakupan();
    }

    /** Baca isi berkas per jenis lalu tampilkan pratinjau perbandingannya dengan master tahun ini. */
    public function lanjut(): void
    {
        $this->pastikanBoleh();
        if (! $this->siapDiperiksa()) {
            return;
        }
        if (! $this->berkas instanceof TemporaryUploadedFile || ! is_file($this->berkas->getRealPath())) {
            $this->reset('terpilih');
            $this->galat = 'Berkas sementara sudah tidak ada. Pilih berkasnya lagi.';

            return;
        }

        try {
            $this->pratinjau = ProsesBerkasMaster::proses($this->berkas->getRealPath(), $this->terpilih['nama'], $this->cakupan, $this->tahun, StubData::currentUser())['pratinjau'];
        } catch (GagalBacaImpor $e) {
            $this->galat = $e->getMessage();

            return;
        }
        $this->tahap = 'pratinjau';
        $this->tabPratinjau = (string) array_key_first($this->pratinjau);
        $this->saring = 'semua';
    }

    /** Kembali ke pilihan berkas (berkas & cakupan tetap). */
    public function kembali(): void
    {
        $this->reset('pratinjau', 'tabPratinjau', 'saring', 'konfirmasiBatal');
        $this->tahap = 'unggah';
    }

    public function updatedTabPratinjau(): void
    {
        if (! isset($this->pratinjau[$this->tabPratinjau])) {
            $this->tabPratinjau = (string) array_key_first($this->pratinjau);
        }
        $this->saring = 'semua';
    }

    public function updatedSaring(): void
    {
        if ($this->saring !== 'semua' && ! array_key_exists($this->saring, PratinjauMaster::STATUS)) {
            $this->saring = 'semua';
        }
    }

    /**
     * Baris pratinjau jenis yang dilihat setelah saringan status.
     *
     * @return list<array<string, mixed>>
     */
    public function barisPratinjau(): array
    {
        $baris = $this->pratinjau[$this->tabPratinjau]['baris'] ?? [];

        return $this->saring === 'semua' ? $baris : array_values(array_filter($baris, fn (array $b) => $b['status'] === $this->saring));
    }

    /** Buang berkas terpilih dan mulai lagi (cakupan tetap). */
    public function gantiBerkas(): void
    {
        $this->buangBerkas();
        $this->reset('terpilih', 'galat', 'pratinjau', 'tabPratinjau', 'saring');
        $this->tahap = 'unggah';
    }

    /** Tutup panel (tombol ×/Tutup/Batalkan): bila pratinjau berisi perubahan, minta konfirmasi dulu. */
    public function tutup(): void
    {
        if ($this->tahap === 'pratinjau' && array_sum($this->ringkasanPerubahan()) > 0) {
            $this->konfirmasiBatal = true;

            return;
        }

        $this->batalkan();
    }

    /**
     * Batalkan unggahan tanpa mengubah data: berkas sementara dibuang, pratinjau dikosongkan, master tetap; halaman
     * diberi tahu (`dibatalkan` = ada berkas yang dibuang).
     */
    public function batalkan(): void
    {
        $adaBerkas = $this->terpilih !== null;
        $this->buangBerkas();
        $this->reset('terpilih', 'galat', 'pratinjau', 'tabPratinjau', 'saring', 'konfirmasiBatal');
        $this->tahap = 'unggah';
        $this->dispatch('unggah-master-ditutup', dibatalkan: $adaBerkas);
    }

    /**
     * Jumlah perubahan yang akan terjadi bila pratinjau disimpan, semua jenis: baru, berubah, hilang.
     *
     * @return array{baru: int, berubah: int, hilang: int}
     */
    public function ringkasanPerubahan(): array
    {
        $jumlah = ['baru' => 0, 'berubah' => 0, 'hilang' => 0];
        foreach ($this->pratinjau as $item) {
            foreach ($jumlah as $status => $n) {
                $jumlah[$status] = $n + ($item['ringkasan'][$status] ?? 0);
            }
        }

        return $jumlah;
    }

    /**
     * Sheet tiap jenis dalam cakupan untuk berkas terpilih.
     *
     * @return array<string, string|null>
     */
    public function sheetDikenali(): array
    {
        return $this->terpilih ? BerkasMaster::kenali($this->terpilih['lembar'], $this->cakupan) : [];
    }

    /** Berkas terpilih bisa diteruskan ke pratinjau: terbaca, tanpa galat, minimal satu sheet dikenali. */
    public function siapDiperiksa(): bool
    {
        return $this->terpilih !== null && $this->galat === null && array_filter($this->sheetDikenali()) !== [];
    }

    /**
     * Master tahun ini yang akan diganti per jenis dalam cakupan: jumlah kode yang ada & kegiatan pemakainya.
     *
     * @return array<string, array{kode: int, kegiatan: int}>
     */
    public function akanDiganti(): array
    {
        $kegiatan = array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => TahunKegiatan::dari($k) === $this->tahun);
        $hasil = [];
        foreach (BerkasMaster::jenisUntuk($this->cakupan) as $jenis) {
            $kode = count(JenisKode::baris($jenis, $this->tahun));
            if ($kode > 0) {
                $hasil[$jenis] = ['kode' => $kode, 'kegiatan' => array_sum(JenisKode::pemakaian($jenis, $kegiatan))];
            }
        }

        return $hasil;
    }

    private function pastikanBoleh(): void
    {
        $tahun = collect(app(TahunAnggaranRepository::class)->semua())->firstWhere('tahun', $this->tahun);
        abort_unless(StubData::currentUser()['role'] === 'admin' && $tahun !== null && ! $tahun['is_locked'], 403);
    }

    private function buangBerkas(): void
    {
        if ($this->berkas instanceof TemporaryUploadedFile) {
            $this->berkas->delete();
        }
        $this->berkas = null;
    }

    public function render()
    {
        return view('livewire.master.unggah-master');
    }
}
