<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Pengaturan\KesiapanPengaturan;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pengaturan › Profil & Pejabat › Dasar hukum & latar belakang (PRD Fase 6 §13, F9): daftar dasar hukum per tahun
 * anggaran (tercetak bernomor di bagian 2 KAK) dan paragraf latar belakang baku (bagian 1) beserta kalimat penutup yang
 * menyisipkan nama kegiatan. Tahun yang dilihat di `?ta=` ('' = tahun aktif); pratinjau memakai susunan yang sama dengan
 * dokumen (SusunanKak, KalimatKak).
 */
#[Layout('layouts.app')]
class DasarHukum extends Component
{
    #[Url(as: 'ta', except: '')]
    public string $tahun = '';

    public function mount(): void
    {
        $this->rapikanTahun();
    }

    public function updatedTahun(): void
    {
        $this->rapikanTahun();
        unset($this->dasarHukum, $this->latarBelakang);
    }

    /**
     * Tahun anggaran terdaftar (terbaru lebih dulu).
     *
     * @return list<array{tahun: int, is_active: bool, is_locked: bool}>
     */
    public function daftarTahun(): array
    {
        return app(TahunAnggaranRepository::class)->semua();
    }

    public function tahunDilihat(): int
    {
        return $this->tahun !== '' ? (int) $this->tahun : StubData::tahunAktif()['tahun'];
    }

    public function tahunTerkunci(): bool
    {
        return (bool) (collect($this->daftarTahun())->firstWhere('tahun', $this->tahunDilihat())['is_locked'] ?? false);
    }

    /**
     * Butir dasar hukum tahun yang dilihat, urut cetak.
     *
     * @return list<string>
     */
    #[Computed]
    public function dasarHukum(): array
    {
        return StubData::dasarHukum($this->tahunDilihat());
    }

    /** Paragraf latar belakang baku tahun yang dilihat (tahun anggaran sudah tersisip). */
    #[Computed]
    public function latarBelakang(): string
    {
        return StubData::latarBelakangBaku($this->tahunDilihat());
    }

    /**
     * Kalimat penutup latar belakang per kegiatan, dipecah di tempat sisip nama kegiatan.
     *
     * @return array{sebelum: string, sesudah: string}
     */
    public function kalimatSisip(): array
    {
        $penanda = '§NAMA§';
        [$sebelum, $sesudah] = explode($penanda, KalimatKak::latarBelakangKegiatan($penanda), 2);

        return ['sebelum' => $sebelum, 'sesudah' => $sesudah];
    }

    /** Nama contoh kegiatan untuk pratinjau (kegiatan terbaru), atau contoh baku bila belum ada. */
    public function contohKegiatan(): string
    {
        $kegiatan = collect(app(KegiatanRepository::class)->semua())->first(fn (array $k) => filled($k['nama_kegiatan'] ?? null));

        return $kegiatan['nama_kegiatan'] ?? 'Workshop Penyusunan Kurikulum Madrasah';
    }

    /**
     * Butir kesiapan tahun aktif untuk dasar hukum & latar belakang.
     *
     * @return array{kunci: string, judul: string, siap: bool, wajib: bool, keterangan: string}|null
     */
    public function kesiapan(): ?array
    {
        return collect(KesiapanPengaturan::tahunAktif()->butir)->firstWhere('kunci', 'dasar_hukum');
    }

    private function rapikanTahun(): void
    {
        $terdaftar = in_array((int) $this->tahun, array_column($this->daftarTahun(), 'tahun'), true);
        if (! $terdaftar || (int) $this->tahun === StubData::tahunAktif()['tahun']) {
            $this->tahun = '';
        }
    }

    public function render()
    {
        return view('livewire.pengaturan.dasar-hukum')->title('Dasar Hukum & Latar Belakang');
    }
}
