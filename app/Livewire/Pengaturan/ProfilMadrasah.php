<?php

namespace App\Livewire\Pengaturan;

use App\Support\MasterKode;
use App\Support\Pengaturan\KesiapanPengaturan;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pengaturan › Profil & Pejabat (PRD Fase 6 §12, F30): identitas madrasah untuk kop dokumen (nama, alamat, telepon,
 * kota, visi, logo) dan pejabat penanda tangan yang berlaku per tahun anggaran (Kepala Madrasah, Ketua Komite sebagai
 * "Mengetahui", pejabat tiap leading). Tab di `?tab=`; pratinjau kop & blok tanda tangan memakai susunan yang sama
 * dengan dokumen (NilaiVariabel `kop`, lembar pengesahan).
 */
#[Layout('layouts.app')]
class ProfilMadrasah extends Component
{
    public const TAB = ['profil' => 'Profil madrasah', 'pejabat' => 'Pejabat penanda tangan', 'dasar_hukum' => 'Dasar hukum & latar belakang'];

    /** Tab yang halamannya sudah dibuat; sisanya tampil "Segera". */
    public const TAB_TERSEDIA = ['profil', 'pejabat'];

    /**
     * Isian profil madrasah (kolom tabel profil_madrasah): label, tempat tercetak, dan wajib untuk kop.
     *
     * @var array<string, array{label: string, dipakai: string, wajib: bool}>
     */
    public const ISIAN_PROFIL = [
        'nama' => ['label' => 'Nama madrasah', 'dipakai' => 'Kop, cover, dan judul dokumen', 'wajib' => true],
        'nama_singkat' => ['label' => 'Nama singkat', 'dipakai' => 'Sebutan sumber dana ("DIPA MTsN 2 Kota Malang Tahun …") dan isi KAK', 'wajib' => true],
        'kota' => ['label' => 'Kota', 'dipakai' => 'Tempat & tanggal tanda tangan, cover', 'wajib' => true],
        'alamat' => ['label' => 'Alamat', 'dipakai' => 'Kop dokumen', 'wajib' => true],
        'telepon' => ['label' => 'Telepon', 'dipakai' => 'Kop dokumen', 'wajib' => true],
        'visi' => ['label' => 'Visi', 'dipakai' => 'Profil madrasah; dapat dirujuk di latar belakang KAK', 'wajib' => false],
    ];

    #[Url(except: 'profil')]
    public string $tab = 'profil';

    public function mount(): void
    {
        $this->rapikanTab();
    }

    public function updatedTab(): void
    {
        $this->rapikanTab();
    }

    /**
     * Profil madrasah dengan semua kolom (yang belum diisi = null).
     *
     * @return array{nama: string|null, nama_singkat: string|null, kota: string|null, alamat: string|null, telepon: string|null, visi: string|null, logo_path: string|null}
     */
    #[Computed]
    public function profil(): array
    {
        $profil = StubData::madrasah();

        return array_map(fn ($nilai) => blank($nilai) ? null : $nilai, array_merge(array_fill_keys([...array_keys(self::ISIAN_PROFIL), 'logo_path'], null), $profil));
    }

    /**
     * Pejabat penanda tangan tahun aktif: pimpinan (kepala, komite) dan tiap leading beserta akun yang tertaut.
     *
     * @return array{kepala: array{jabatan: string, nama: string|null, nip: string|null}, komite: array{jabatan: string, nama: string|null, nip: string|null}, leading: list<array{kode: string, nama_unit: string, jabatan: string, nama: string|null, nip: string|null, akun: list<string>}>}
     */
    #[Computed]
    public function pejabat(): array
    {
        $pejabat = StubData::pejabat();
        $perLeading = StubData::pejabatLeading();
        $akun = StubPengaturan::pengguna();

        return $pejabat + ['leading' => array_map(fn (array $l) => ['kode' => $l['kode'], 'nama_unit' => $l['nama_unit']] + ($perLeading[$l['kode']] ?? ['jabatan' => $l['jabatan_penanda_tangan'], 'nama' => null, 'nip' => null]) + [
            'akun' => array_values(array_column(array_filter($akun, fn (array $a) => in_array($l['kode'], $a['leadings'], true)), 'name')),
        ], MasterKode::leadings())];
    }

    /**
     * Isian yang belum lengkap per tab (untuk penanda di tab): profil = isian wajib kop + logo, pejabat = peran tanpa nama/NIP.
     *
     * @return array{profil: list<string>, pejabat: list<string>}
     */
    public function kurang(): array
    {
        $profil = $this->profil;
        $pejabat = $this->pejabat;

        return [
            'profil' => [
                ...array_keys(array_filter(self::ISIAN_PROFIL, fn (array $isian, string $kunci) => $isian['wajib'] && $profil[$kunci] === null, ARRAY_FILTER_USE_BOTH)),
                ...($profil['logo_path'] === null ? ['logo_path'] : []),
            ],
            'pejabat' => array_values(array_filter([
                $pejabat['kepala']['nama'] && $pejabat['kepala']['nip'] ? null : 'Kepala Madrasah',
                $pejabat['komite']['nama'] ? null : 'Ketua Komite',
                ...array_map(fn (array $l) => $l['nama'] ? null : $l['nama_unit'], $pejabat['leading']),
            ])),
        ];
    }

    /**
     * Butir kesiapan tahun aktif yang dikelola di halaman ini (profil, pejabat, dasar hukum).
     *
     * @return list<array{kunci: string, judul: string, siap: bool, wajib: bool, keterangan: string}>
     */
    public function kesiapan(): array
    {
        return array_values(array_filter(KesiapanPengaturan::tahunAktif()->butir, fn (array $b) => $b['bagian'] === 'madrasah'));
    }

    private function rapikanTab(): void
    {
        if (! in_array($this->tab, self::TAB_TERSEDIA, true)) {
            $this->tab = 'profil';
        }
    }

    public function render()
    {
        return view('livewire.pengaturan.profil-madrasah')->title('Profil & Pejabat');
    }
}
