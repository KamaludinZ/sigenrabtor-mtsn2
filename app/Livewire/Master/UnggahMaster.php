<?php

namespace App\Livewire\Master;

use App\Contracts\KegiatanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Impor\GagalBacaImpor;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Master\Impor\BerkasMaster;
use App\Support\Master\JenisKode;
use App\Support\Stub\StubData;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Formulir unggah berkas master kode (PRD §14) untuk satu tahun anggaran: pilih cakupan (berkas master lengkap atau
 * satu jenis), pilih/seret berkas Excel, lalu sheet tiap jenis dikenali dari namanya (BerkasMaster). Berkas sementara
 * disimpan sampai dibatalkan atau diteruskan ke pratinjau. Khusus admin; tahun terkunci tidak bisa diunggah.
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
        if ($this->terpilih && $this->cakupan === BerkasMaster::SEMUA && $this->terpilih['lembar'] === ['CSV']) {
            $this->galat = 'Berkas CSV hanya untuk satu jenis kode. Pilih jenisnya, atau unggah berkas .xlsx berisi semua sheet.';
        } elseif ($this->terpilih) {
            $this->galat = null;
        }
    }

    public function updatedBerkas(): void
    {
        $this->pastikanBoleh();
        $this->galat = null;
        $this->terpilih = null;

        try {
            $this->validate(
                ['berkas' => ['required', 'file', 'max:'.BerkasMaster::MAKS_KB, 'extensions:xlsx,xls,csv']],
                [
                    'berkas.max' => 'Ukuran berkas maksimal '.(BerkasMaster::MAKS_KB / 1024).' MB.',
                    'berkas.extensions' => 'Pilih berkas Excel (.xlsx, .xls) atau CSV.',
                ],
            );
        } catch (ValidationException $e) {
            $this->galat = $e->validator->errors()->first('berkas');
            $this->buangBerkas();

            return;
        }

        $nama = $this->berkas->getClientOriginalName();
        try {
            $lembar = BerkasMaster::lembar($this->berkas->getRealPath(), $nama);
        } catch (GagalBacaImpor $e) {
            $this->galat = $e->getMessage();
            $this->buangBerkas();

            return;
        }

        $this->terpilih = ['nama' => $nama, 'ukuran' => (int) $this->berkas->getSize(), 'lembar' => $lembar];
        $this->updatedCakupan();
        if ($this->galat === null && array_filter($this->sheetDikenali()) === []) {
            $this->galat = 'Tidak ada sheet yang dikenali. Beri nama sheet Perkin (SK & IKSK), SNP (RKTM), EDM, dan 5 Budaya Mutu seperti template.';
        }
    }

    /** Buang berkas terpilih dan mulai lagi (cakupan tetap). */
    public function gantiBerkas(): void
    {
        $this->buangBerkas();
        $this->reset('terpilih', 'galat');
    }

    public function tutup(): void
    {
        $this->buangBerkas();
        $this->dispatch('unggah-master-ditutup');
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
