<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\DaftarIsiRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\NomorHalamanRepository;
use App\Enums\SumberDana;
use App\Support\Dokumen\DaftarIsi;
use App\Support\Dokumen\NomorHalaman;
use App\Support\MasterKode;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Tab Nomor Halaman di Pengaturan › Template Dokumen (PRD F20): format "Halaman {x} dari {y}" (preset atau sendiri),
 * posisi, nomor awal, sampul/daftar isi tanpa nomor, dan hitungan halaman tanpa nomor — dengan simulasi buku contoh
 * yang menunjukkan label tiap halaman dan halaman awal tiap kegiatan di daftar isi.
 */
class TemplateNomorHalaman extends Component
{
    /** Kunci pilihan "format lain" (diketik sendiri). */
    public const FORMAT_LAIN = 'lain';

    /** @var array<string, mixed> Isian formulir (format diambil dari pilihan di bawah). */
    public array $aturan = [];

    /** Format preset terpilih, atau FORMAT_LAIN. */
    public string $pilihanFormat = '';

    public string $formatLain = '';

    public function mount(): void
    {
        $this->muat();
    }

    public function simpan(NomorHalamanRepository $repo): void
    {
        if (($galat = NomorHalaman::galatFormat($this->format())) !== null) {
            $this->addError('formatLain', $galat);

            return;
        }

        $repo->simpan($this->tahun(), $this->hasil());
        $this->muat();
        $this->dispatch('notify', message: 'Aturan nomor halaman tersimpan.', type: 'success');
    }

    public function batalkan(): void
    {
        $this->muat();
        $this->resetValidation();
    }

    public function pakaiAturanAplikasi(): void
    {
        $this->isi(NomorHalaman::BAWAAN);
        $this->resetValidation();
    }

    /** Galat format sendiri diperiksa tiap ketukan (debounce) agar admin langsung tahu. */
    public function updatedFormatLain(): void
    {
        $this->resetValidation('formatLain');

        if ($this->pilihanFormat === self::FORMAT_LAIN && ($galat = NomorHalaman::galatFormat($this->formatLain)) !== null) {
            $this->addError('formatLain', $galat);
        }
    }

    public function updatedPilihanFormat(): void
    {
        $this->updatedFormatLain();
    }

    public function format(): string
    {
        return $this->pilihanFormat === self::FORMAT_LAIN ? trim($this->formatLain) : $this->pilihanFormat;
    }

    /**
     * Aturan dari isian (format tidak sah → pratinjau memakai format tersimpan sampai diperbaiki).
     *
     * @return array<string, mixed>
     */
    public function hasil(): array
    {
        return NomorHalaman::rapikan(['format' => $this->format()] + $this->aturan);
    }

    public function berubah(): bool
    {
        return $this->hasil() !== app(NomorHalamanRepository::class)->untuk($this->tahun()) || NomorHalaman::galatFormat($this->format()) !== null;
    }

    /**
     * Buku contoh untuk simulasi: buku dengan kegiatan terbanyak, urut sesuai daftar isinya.
     *
     * @return array{nama: string, kegiatan: list<string>}
     */
    #[Computed]
    public function bukuContoh(): array
    {
        $semua = app(KegiatanRepository::class)->semua();
        $jumlah = [];
        foreach ($semua as $k) {
            foreach (filled($k['leading'] ?? null) ? array_keys(AnggaranKegiatan::perSumber($k)) : [] as $s) {
                $jumlah["{$k['leading']}|{$s}"] = ($jumlah["{$k['leading']}|{$s}"] ?? 0) + 1;
            }
        }
        arsort($jumlah);
        [$leading, $sumber] = explode('|', array_key_first($jumlah) ?? MasterKode::leadings()[0]['kode'].'|'.SumberDana::urutan()[0]);
        $daftarIsi = app(DaftarIsiRepository::class);
        $konfigurasi = $daftarIsi->khusus($this->tahun())["{$leading}|{$sumber}"] ?? $daftarIsi->bawaan($this->tahun());

        return [
            'nama' => (MasterKode::cari(MasterKode::leadings(), $leading)['nama_unit'] ?? $leading).' · '.$sumber,
            'kegiatan' => array_column(array_slice(DaftarIsi::baris($semua, $leading, $sumber, $konfigurasi['urutan']), 0, 3), 'nama_kegiatan'),
        ];
    }

    public function tahun(): int
    {
        return StubData::tahunAktif()['tahun'];
    }

    public function render()
    {
        return view('livewire.pengaturan.template-nomor-halaman');
    }

    private function muat(): void
    {
        $this->isi(app(NomorHalamanRepository::class)->untuk($this->tahun()));
    }

    /**
     * @param  array<string, mixed>  $aturan
     */
    private function isi(array $aturan): void
    {
        $this->aturan = $aturan;
        $preset = array_key_exists($aturan['format'], NomorHalaman::PRESET);
        $this->pilihanFormat = $preset ? $aturan['format'] : self::FORMAT_LAIN;
        $this->formatLain = $preset ? '' : $aturan['format'];
    }
}
