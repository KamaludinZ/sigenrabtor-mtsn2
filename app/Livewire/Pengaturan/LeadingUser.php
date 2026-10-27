<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\LeadingRepository;
use App\Contracts\PenggunaRepository;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pengaturan › Leading & User (PRD Fase 6 §15, F29–F30): tab Leading (kode, nama unit, jabatan penanda tangan, status,
 * akun tertaut, kegiatan tahun aktif) dan tab Akun (peran admin/leading, tautan leading, status, terakhir masuk). Tab di
 * `?tab=`. Tambah/ubah/nonaktifkan menyusul (tombolnya "Segera").
 */
#[Layout('layouts.app')]
class LeadingUser extends Component
{
    public const TAB = ['leading' => 'Leading', 'akun' => 'Akun'];

    /** Hak tiap peran (PRD "Pengguna dan Peran"). */
    public const PERAN = [
        'admin' => ['label' => 'Admin', 'hak' => 'Kelola user, tahun anggaran, master data, template, penanda tangan, dan pengaturan AI; melihat & memeriksa semua kegiatan; membuka kunci dokumen final.'],
        'leading' => ['label' => 'Leading', 'hak' => 'Mengisi KAK/TOR, RAB, matrik, jadwal, dan konteks kegiatan leading-nya; memakai bantuan AI; mencetak; mengunggah arsip final.'],
    ];

    #[Url(except: 'leading')]
    public string $tab = 'leading';

    public function mount(): void
    {
        $this->updatedTab();
    }

    public function updatedTab(): void
    {
        if (! array_key_exists($this->tab, self::TAB)) {
            $this->tab = 'leading';
        }
    }

    /**
     * @return list<array{kode: string, nama_unit: string, jabatan_penanda_tangan: string, urutan: int, is_active: bool}>
     */
    #[Computed]
    public function leading(): array
    {
        return app(LeadingRepository::class)->semua();
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function akun(): array
    {
        return app(PenggunaRepository::class)->semua();
    }

    /**
     * Akun tertaut per kode leading (aktif lebih dulu).
     *
     * @return array<string, list<array{id: int, name: string, is_active: bool}>>
     */
    public function akunPerLeading(): array
    {
        $hasil = [];
        foreach ($this->akun as $a) {
            foreach ($a['leadings'] as $kode) {
                $hasil[$kode][] = ['id' => $a['id'], 'name' => $a['name'], 'is_active' => $a['is_active']];
            }
        }

        return array_map(fn (array $daftar) => collect($daftar)->sortByDesc('is_active')->values()->all(), $hasil);
    }

    /**
     * Jumlah kegiatan tahun aktif per kode leading.
     *
     * @return array<string, int>
     */
    public function kegiatanPerLeading(): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $kode = array_filter(array_map(fn (array $k) => TahunKegiatan::dari($k) === $tahun ? ($k['leading'] ?? null) : null, app(KegiatanRepository::class)->semua()));

        return array_count_values(array_map('strval', $kode));
    }

    /**
     * Ringkasan untuk tab & kartu: leading aktif/nonaktif, leading aktif tanpa akun aktif, akun per peran & status.
     *
     * @return array{leading_aktif: int, leading_nonaktif: int, tanpa_akun: list<string>, akun_aktif: int, akun_nonaktif: int, per_peran: array<string, int>}
     */
    public function ringkasan(): array
    {
        $aktifPerLeading = array_map(fn (array $daftar) => count(array_filter($daftar, fn (array $a) => $a['is_active'])), $this->akunPerLeading());
        $leadingAktif = array_filter($this->leading, fn (array $l) => $l['is_active']);
        $akunAktif = array_filter($this->akun, fn (array $a) => $a['is_active']);

        return [
            'leading_aktif' => count($leadingAktif),
            'leading_nonaktif' => count($this->leading) - count($leadingAktif),
            'tanpa_akun' => array_values(array_map(fn (array $l) => $l['kode'], array_filter($leadingAktif, fn (array $l) => ($aktifPerLeading[$l['kode']] ?? 0) === 0))),
            'akun_aktif' => count($akunAktif),
            'akun_nonaktif' => count($this->akun) - count($akunAktif),
            'per_peran' => array_merge(['admin' => 0, 'leading' => 0], array_count_values(array_column($akunAktif, 'role'))),
        ];
    }

    public function render()
    {
        return view('livewire.pengaturan.leading-user')->title('Leading & User');
    }
}
