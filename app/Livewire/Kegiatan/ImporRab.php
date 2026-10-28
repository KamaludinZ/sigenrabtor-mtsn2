<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Forms\ItemBelanjaForm;
use App\Livewire\Impor\ImporBertahap;
use App\Support\Format;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\Impor\PembacaImporRab;
use App\Support\Rab\Impor\PemeriksaImporRab;
use App\Support\Rab\StatusPagu;
use Livewire\Attributes\Locked;

/**
 * Impor tabel rincian anggaran (Excel/CSV, tidak harus template) ke RAB satu sumber dana kegiatan.
 * Baris disesuaikan otomatis ke format rincian aplikasi (lihat PemeriksaImporRab) lalu ditambahkan ke tabel RAB.
 */
class ImporRab extends ImporBertahap
{
    #[Locked]
    public int $kegiatanId;

    #[Locked]
    public string $sumber;

    /** Total di baris "TOTAL"/"JUMLAH" berkas (bila ada) untuk dicocokkan dengan total impor. */
    #[Locked]
    public ?int $totalBerkas = null;

    public function mount(int $kegiatanId, string $sumber): void
    {
        $this->kegiatanId = $kegiatanId;
        $this->sumber = $sumber;
        $this->pastikanBolehImpor();
    }

    public function kolom(): array
    {
        return ['uraian' => 'Uraian', 'kode' => 'Kode', 'volume' => 'Vol', 'harga' => 'Harga satuan', 'jumlah' => 'Jumlah', 'kelompok' => 'Kelompok'];
    }

    protected function periksaBerkas(string $path, string $namaAsli): array
    {
        $tabel = PembacaImporRab::baca($path, $namaAsli);
        $this->totalBerkas = is_numeric($tabel['total_berkas']) ? (int) $tabel['total_berkas']
            : Format::parseRupiah(preg_replace('/,(-|00)?\s*$/', '', (string) $tabel['total_berkas']));

        $diperiksa = PemeriksaImporRab::periksa($tabel['baris'], app(RabRepository::class)->rincian($this->kegiatanId, $this->sumber));

        return array_map(fn (array $b) => [
            'baris' => $b['baris'],
            'tampil' => [
                'uraian' => $b['tampil_uraian'],
                'kode' => $b['item']['kode'] ?? '' ?: '—',
                'volume' => $b['item'] ? ItemBelanjaForm::teksVolume($b['item']['volume']).' '.$b['item']['satuan'] : '—',
                'harga' => $b['item'] ? Format::rupiah($b['item']['harga_satuan']) : '—',
                'jumlah' => $b['jumlah'] !== null ? Format::rupiah($b['jumlah']) : '—',
                'kelompok' => $b['item'] ? ItemBelanjaForm::LABEL_KATEGORI[$b['item']['kategori']] : '—',
            ],
            'status' => $b['status'],
            'pesan' => $b['pesan'],
            'peringatan' => $b['peringatan'],
            'data' => ($b['item'] ?? []) + ['jumlah' => $b['jumlah']],
        ], $diperiksa);
    }

    protected function simpanBaris(array $data): array
    {
        unset($data['jumlah']);
        app(RabRepository::class)->tambahItem($this->kegiatanId, $this->sumber, $data);

        return ['status' => 'berhasil', 'pesan' => 'Ditambahkan ke RAB '.$this->sumber.'.'];
    }

    protected function labelBaris(array $baris): string
    {
        return $baris['tampil']['uraian'];
    }

    protected function selesaiDiimpor(int $berhasil): void
    {
        $this->dispatch('rab-diimpor', jumlah: $berhasil, sumber: $this->sumber);
    }

    protected function namaLaporan(): string
    {
        return 'laporan-impor-rab-'.mb_strtolower($this->sumber);
    }

    /** RAB hanya boleh diubah selama kegiatan berstatus Draf/Dikembalikan. */
    protected function pastikanBolehImpor(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? abort(404, 'Kegiatan tidak ditemukan.');
        $status = StatusKegiatan::tryFrom($kegiatan['status'] ?? '') ?? StatusKegiatan::Draf;

        if (! $status->dapatDiedit()) {
            abort(403, 'Kegiatan berstatus '.$status->label().' sehingga RAB-nya terkunci.');
        }
        if (($tahun = KunciKegiatan::tahunTerkunci($kegiatan)) !== null) {
            abort(403, KunciKegiatan::pesanTahun($tahun));
        }
    }

    /**
     * Ringkasan total pratinjau: total item siap, cocok tidaknya dengan baris TOTAL berkas, dan status pagu sesudah impor.
     *
     * @return array{siap: int, dilewati: int, berkas: int|null, cocok: bool|null, sesudah: int, status: StatusPagu}
     */
    public function ringkasanTotal(): array
    {
        $jumlah = fn (string $status) => array_sum(array_map(fn ($b) => (int) ($b['data']['jumlah'] ?? 0), array_filter($this->baris, fn ($b) => $b['status'] === $status)));
        $siap = $jumlah('siap');
        $dilewati = $jumlah('dilewati');
        $anggaran = AnggaranKegiatan::perSumber(app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? [])[$this->sumber] ?? null;
        $sesudah = ($anggaran?->total() ?? 0) + $siap;

        return [
            'siap' => $siap,
            'dilewati' => $dilewati,
            'berkas' => $this->totalBerkas,
            'cocok' => $this->totalBerkas === null ? null : $this->totalBerkas === $siap + $dilewati,
            'sesudah' => $sesudah,
            'status' => StatusPagu::hitung($sesudah, $anggaran?->pagu()),
        ];
    }

    public function render()
    {
        return view('livewire.impor.bertahap', [
            'judul' => 'Impor rincian RAB '.$this->sumber.' dari Excel',
            'keterangan' => 'Unggah tabel rincian (No, Kode, Uraian, Vol, Satuan, Harga Satuan, Jumlah) — boleh disalin dari RAB lama. '
                .'Format disesuaikan otomatis; item yang sudah ada dilewati.',
            'tautanTemplate' => route('kegiatan.rab.template'),
            'satuan' => 'item',
            'ringkasanTotal' => $this->tahap === 'periksa' ? $this->ringkasanTotal() : null,
        ]);
    }
}
