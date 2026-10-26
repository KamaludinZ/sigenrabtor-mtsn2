<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\ProfilMadrasahRepository;
use App\Support\MasterKode;
use App\Support\Pengaturan\KesiapanPengaturan;
use App\Support\Pengaturan\RiwayatPengaturan;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Pengaturan › Profil & Pejabat (PRD Fase 6 §12, F30): identitas madrasah untuk kop dokumen (nama, alamat, telepon,
 * kota, visi, logo) dan pejabat penanda tangan yang berlaku per tahun anggaran (Kepala Madrasah, Ketua Komite sebagai
 * "Mengetahui", pejabat tiap leading). Tab di `?tab=`; pratinjau kop & blok tanda tangan memakai susunan yang sama
 * dengan dokumen (NilaiVariabel `kop`, lembar pengesahan).
 */
#[Layout('layouts.app')]
class ProfilMadrasah extends Component
{
    use WithFileUploads;

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

    /** Batas ukuran logo (KB) dan sisi terpendek minimal (piksel). */
    public const MAKS_LOGO_KB = 2048;

    public const MIN_SISI_LOGO = 200;

    #[Url(except: 'profil')]
    public string $tab = 'profil';

    /** Formulir identitas sedang dibuka. */
    public bool $ubahProfil = false;

    /**
     * Isian formulir identitas (kunci ISIAN_PROFIL); dirapikan saat disimpan.
     *
     * @var array<string, string>
     */
    public array $isian = [];

    /** Logo baru yang dipilih (belum disimpan). */
    public $logo = null;

    /** Konfirmasi hapus logo sedang ditampilkan. */
    public bool $konfirmasiHapusLogo = false;

    public function mount(): void
    {
        $this->rapikanTab();
    }

    public function updatedTab(): void
    {
        $this->rapikanTab();
    }

    public function bukaUbahProfil(): void
    {
        $this->isian = array_map(fn ($nilai) => (string) $nilai, array_intersect_key($this->profil, self::ISIAN_PROFIL));
        $this->resetValidation();
        $this->ubahProfil = true;
    }

    public function batalUbahProfil(): void
    {
        $this->reset('isian', 'ubahProfil');
        $this->resetValidation();
    }

    /** Rapikan spasi (visi boleh berganti baris), validasi, simpan, lalu catat ringkasan perubahannya di riwayat. */
    public function simpanProfil(ProfilMadrasahRepository $repo): void
    {
        $mentah = array_merge(array_fill_keys(array_keys(self::ISIAN_PROFIL), ''), array_intersect_key($this->isian, self::ISIAN_PROFIL));
        $this->isian = array_map(fn ($nilai) => self::rapikan((string) $nilai), $mentah);
        $this->isian['visi'] = self::rapikanParagraf((string) $mentah['visi']);
        $this->validate(array_intersect_key($this->rules(), array_flip(array_map(fn ($k) => "isian.{$k}", array_keys(self::ISIAN_PROFIL)))));

        $lama = $repo->ambil();
        $repo->simpan($this->isian);
        $baru = $repo->ambil();
        $ringkasan = RiwayatPengaturan::perubahan($lama, $baru, array_map(fn (array $isian) => $isian['label'], self::ISIAN_PROFIL));
        if ($ringkasan !== null) {
            RiwayatPengaturan::catat('ubah_profil', $ringkasan);
        }

        unset($this->profil);
        $this->reset('isian', 'ubahProfil');
        $this->dispatch('notify', message: $ringkasan === null ? 'Tidak ada yang berubah.' : 'Profil madrasah tersimpan dan langsung dipakai kop dokumen.', type: $ringkasan === null ? 'info' : 'success');
    }

    /** Logo baru diperiksa begitu dipilih. */
    public function updatedLogo(): void
    {
        $this->validateOnly('logo');
    }

    public function simpanLogo(ProfilMadrasahRepository $repo): void
    {
        $this->validateOnly('logo');
        $adaSebelumnya = $repo->ambil()['logo_path'] !== null;
        $repo->simpanLogo($this->logo);
        $nama = $this->logo->getClientOriginalName();
        $this->logo->delete();
        $this->reset('logo');

        RiwayatPengaturan::catat('unggah_logo', ($adaSebelumnya ? 'Logo kop diganti' : 'Logo kop diunggah')." ({$nama}).");
        unset($this->profil);
        $this->dispatch('notify', message: 'Logo kop tersimpan.', type: 'success');
    }

    public function batalLogo(): void
    {
        $this->logo?->delete();
        $this->reset('logo');
        $this->resetValidation('logo');
    }

    public function hapusLogo(ProfilMadrasahRepository $repo): void
    {
        if ($repo->ambil()['logo_path'] === null) {
            return;
        }

        $repo->hapusLogo();
        $this->konfirmasiHapusLogo = false;
        RiwayatPengaturan::catat('hapus_logo', 'Logo kop dihapus; dokumen dicetak tanpa logo sampai diunggah lagi.');
        unset($this->profil);
        $this->dispatch('notify', message: 'Logo kop dihapus.', type: 'info');
    }

    /** URL logo tersimpan (dengan penanda versi agar tembolok browser diperbarui), null bila belum ada. */
    public function urlLogo(): ?string
    {
        return $this->profil['logo_path'] ? route('madrasah.logo', ['v' => substr(md5((string) $this->profil['logo_path']), 0, 8)]) : null;
    }

    /** Logo baru yang lolos pemeriksaan dan bisa dipratinjau. */
    public function logoBisaDipratinjau(): bool
    {
        return $this->logo instanceof TemporaryUploadedFile && ! $this->getErrorBag()->has('logo');
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'isian.nama' => ['required', 'string', 'max:150'],
            'isian.nama_singkat' => ['required', 'string', 'max:60'],
            'isian.kota' => ['required', 'string', 'max:60'],
            'isian.alamat' => ['nullable', 'string', 'max:255'],
            'isian.telepon' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s.\/]+$/'],
            'isian.visi' => ['nullable', 'string', 'max:1000'],
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.self::MAKS_LOGO_KB, 'dimensions:min_width='.self::MIN_SISI_LOGO.',min_height='.self::MIN_SISI_LOGO],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'isian.nama.required' => 'Nama madrasah wajib diisi — tercetak di kop dan cover.',
            'isian.nama_singkat.required' => 'Nama singkat wajib diisi — dipakai sebutan sumber dana di dokumen.',
            'isian.kota.required' => 'Kota wajib diisi — dipakai di tempat & tanggal tanda tangan.',
            'isian.*.max' => 'Terlalu panjang (maksimal :max karakter).',
            'isian.telepon.regex' => 'Telepon hanya boleh berisi angka, spasi, dan tanda + ( ) - . /',
            'logo.required' => 'Pilih gambar logo.',
            'logo.image' => 'Logo harus berupa gambar PNG atau JPG.',
            'logo.mimes' => 'Logo harus berupa gambar PNG atau JPG.',
            'logo.max' => 'Ukuran logo maksimal '.(self::MAKS_LOGO_KB / 1024).' MB.',
            'logo.dimensions' => 'Logo terlalu kecil — minimal '.self::MIN_SISI_LOGO.' × '.self::MIN_SISI_LOGO.' piksel agar tajam saat dicetak.',
        ];
    }

    /** Satu baris tanpa spasi ganda. */
    private static function rapikan(string $teks): string
    {
        return trim(preg_replace('/\s+/u', ' ', $teks));
    }

    /** Paragraf (visi): spasi ganda dirapikan per baris, paling banyak satu baris kosong di antara paragraf. */
    private static function rapikanParagraf(string $teks): string
    {
        $baris = array_map(fn (string $b) => self::rapikan($b), explode("\n", str_replace("\r", '', $teks)));

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $baris)));
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
