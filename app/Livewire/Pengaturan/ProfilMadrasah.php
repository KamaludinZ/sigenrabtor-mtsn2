<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\PejabatRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\MasterKode;
use App\Support\Pengaturan\KelolaPejabat;
use App\Support\Pengaturan\KelolaProfilMadrasah;
use App\Support\Pengaturan\KesiapanPengaturan;
use App\Support\Pengaturan\Nip;
use App\Support\Pengaturan\TahunDipilih;
use App\Support\Pengaturan\ValidasiPejabat;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
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

    /** Tab di dalam komponen ini; tab lain berupa halaman tersendiri (TAB_HALAMAN) atau "Segera". */
    public const TAB_TERSEDIA = ['profil', 'pejabat'];

    /** Tab yang berupa halaman tersendiri (nama rute). */
    public const TAB_HALAMAN = ['dasar_hukum' => 'pengaturan.madrasah.dasar_hukum'];

    /** Isian profil madrasah: label, tempat tercetak, dan wajib untuk kop (KelolaProfilMadrasah::ISIAN). */
    public const ISIAN_PROFIL = KelolaProfilMadrasah::ISIAN;

    /** Batas ukuran logo (KB) dan sisi terpendek minimal (piksel). */
    public const MAKS_LOGO_KB = KelolaProfilMadrasah::MAKS_LOGO_KB;

    public const MIN_SISI_LOGO = KelolaProfilMadrasah::MIN_SISI_LOGO;

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

    /** Tahun anggaran pejabat yang dilihat di tab Pejabat; '' = tahun aktif. */
    #[Url(as: 'ta', except: '')]
    public string $tahunPejabat = '';

    /**
     * Pengaturan blok "Mengetahui" tahun yang dilihat: sumber dana → tampil; tersimpan begitu dicentang.
     *
     * @var array<string, bool>
     */
    public array $mengetahui = [];

    /** Pejabat yang sedang disunting: "kepala", "komite", atau "leading:{kode}"; null = tidak ada. */
    public ?string $ubahPejabat = null;

    /**
     * Isian <x-pengaturan.input-pejabat> (jabatan, nama, nip); dirapikan saat disimpan.
     *
     * @var array{jabatan: string, nama: string, nip: string}
     */
    public array $isianPejabat = ['jabatan' => '', 'nama' => '', 'nip' => ''];

    public function mount(): void
    {
        $this->rapikanTab();
        $this->rapikanTahunPejabat();
        $this->mengetahui = app(PejabatRepository::class)->aturanMengetahui($this->tahunDilihat());
    }

    public function updatedTab(): void
    {
        $this->rapikanTab();
    }

    /** Ganti tahun yang dilihat: formulir yang terbuka ditutup (isiannya milik tahun lain). */
    public function updatedTahunPejabat(): void
    {
        $this->rapikanTahunPejabat();
        $this->batalUbahPejabat();
        unset($this->pejabat);
        $this->mengetahui = app(PejabatRepository::class)->aturanMengetahui($this->tahunDilihat());
    }

    /** Peran Ketua Komite menurut pengaturan blok "Mengetahui", mis. '"Mengetahui" di dokumen DIPA & Komite'. */
    public function keteranganMengetahui(): string
    {
        $tampil = array_keys(array_filter($this->mengetahui));

        return $tampil === [] ? 'Blok "Mengetahui" sedang tidak ditampilkan di dokumen' : '"Mengetahui" di dokumen '.implode(' & ', $tampil);
    }

    /** Centang blok "Mengetahui" langsung tersimpan untuk tahun yang dilihat (tahun terkunci dikembalikan). */
    public function updatedMengetahui(): void
    {
        $tahun = $this->tahunDilihat();
        try {
            $this->mengetahui = KelolaPejabat::simpanMengetahui($tahun, (array) $this->mengetahui, StubData::currentUser())['aturan'];
        } catch (AuthorizationException) {
            $this->mengetahui = app(PejabatRepository::class)->aturanMengetahui($tahun);

            return;
        }

        $tampil = array_keys(array_filter($this->mengetahui));
        $this->dispatch('notify', message: $tampil === [] ? 'Blok "Mengetahui" tidak ditampilkan di dokumen mana pun.' : 'Blok "Mengetahui" tampil di dokumen '.implode(' & ', $tampil).'.', type: 'success');
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

    /** Tahun pejabat yang sedang dilihat. */
    public function tahunDilihat(): int
    {
        return TahunDipilih::dari($this->tahunPejabat) ?? StubData::tahunAktif()['tahun'];
    }

    /**
     * Salin pejabat dari tahun $dari ke tahun yang dilihat — hanya peran yang di tahun ini belum bernama, sehingga isian
     * yang sudah ada tidak tertimpa.
     */
    public function salinPejabat(int $dari): void
    {
        $ke = $this->tahunDilihat();
        try {
            $disalin = KelolaPejabat::salin($dari, $ke, StubData::currentUser());
        } catch (AuthorizationException|ValidationException) {
            return;
        }

        unset($this->pejabat);
        $this->dispatch('notify', message: $disalin > 0 ? "{$disalin} pejabat disalin dari TA {$dari}. Periksa lalu ubah yang berganti." : "Tidak ada yang disalin — pejabat TA {$dari} kosong atau TA {$ke} sudah terisi.", type: $disalin > 0 ? 'success' : 'info');
    }

    /**
     * Sumber saran "Salin dari TA …": bila tahun yang dilihat (belum terkunci) belum punya satu pun nama pejabat, tahun lain
     * terbaru yang sudah punya; null bila tidak perlu/tidak ada.
     */
    public function sumberSalin(): ?int
    {
        return KelolaPejabat::sumberSalin($this->tahunDilihat());
    }

    /**
     * Pejabat semua tahun per peran untuk tabel perbandingan: baris kepala, komite, tiap leading; kolom tahun (terbaru
     * dulu); `berganti` = nama berbeda dengan tahun sebelumnya yang juga terisi.
     *
     * @return list<array{kunci: string, judul: string, tahun: array<int, array{nama: string|null, nip: string|null, berganti: bool}>}>
     */
    public function perbandinganTahun(): array
    {
        $tahun = array_column($this->daftarTahun(), 'tahun');
        $data = array_combine($tahun, array_map(fn (int $t) => app(PejabatRepository::class)->untukTahun($t), $tahun));
        $baris = [['kunci' => 'kepala', 'judul' => 'Kepala Madrasah'], ['kunci' => 'komite', 'judul' => 'Ketua Komite']];
        foreach (MasterKode::leadings() as $l) {
            $baris[] = ['kunci' => 'leading:'.$l['kode'], 'judul' => $l['kode'].' '.$l['nama_unit']];
        }

        return array_map(function (array $b) use ($tahun, $data) {
            $ambil = fn (int $t) => str_starts_with($b['kunci'], 'leading:') ? ($data[$t]['leading'][substr($b['kunci'], 8)] ?? null) : $data[$t][$b['kunci']];
            $kolom = [];
            foreach ($tahun as $i => $t) {
                $p = $ambil($t);
                $sebelum = isset($tahun[$i + 1]) ? $ambil($tahun[$i + 1]) : null;
                $kolom[$t] = ['nama' => $p['nama'] ?? null, 'nip' => $p['nip'] ?? null, 'berganti' => ($p['nama'] ?? null) && ($sebelum['nama'] ?? null) && $p['nama'] !== $sebelum['nama']];
            }

            return $b + ['tahun' => $kolom];
        }, $baris);
    }

    private function rapikanTahunPejabat(): void
    {
        $this->tahunPejabat = TahunDipilih::parameter($this->tahunPejabat);
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
    public function simpanProfil(): void
    {
        $this->isian = KelolaProfilMadrasah::rapikan($this->isian);
        $this->validate(array_intersect_key($this->rules(), array_flip(array_map(fn ($k) => "isian.{$k}", array_keys(self::ISIAN_PROFIL)))));

        try {
            $ringkasan = KelolaProfilMadrasah::simpan($this->isian, StubData::currentUser())['ringkasan'];
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
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

    public function simpanLogo(): void
    {
        $this->validateOnly('logo');

        try {
            KelolaProfilMadrasah::simpanLogo($this->logo, StubData::currentUser());
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }
        $this->logo->delete();
        $this->reset('logo');
        unset($this->profil);
        $this->dispatch('notify', message: 'Logo kop tersimpan.', type: 'success');
    }

    public function batalLogo(): void
    {
        $this->logo?->delete();
        $this->reset('logo');
        $this->resetValidation('logo');
    }

    public function hapusLogo(): void
    {
        try {
            if (! KelolaProfilMadrasah::hapusLogo(StubData::currentUser())) {
                return;
            }
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->konfirmasiHapusLogo = false;
        unset($this->profil);
        $this->dispatch('notify', message: 'Logo kop dihapus.', type: 'info');
    }

    /** Buka formulir satu pejabat tahun aktif (tahun terkunci hanya bisa dilihat). */
    public function bukaUbahPejabat(string $kunci): void
    {
        $pejabat = $this->pejabatUntuk($kunci);
        if ($pejabat === null || $this->tahunTerkunci()) {
            return;
        }

        $this->isianPejabat = ['jabatan' => $pejabat['jabatan'], 'nama' => (string) $pejabat['nama'], 'nip' => $pejabat['nip'] ? Nip::format($pejabat['nip']) : ''];
        $this->resetValidation();
        $this->ubahPejabat = $kunci;
    }

    public function batalUbahPejabat(): void
    {
        $this->reset('ubahPejabat', 'isianPejabat');
        $this->resetValidation();
    }

    /** Rapikan & periksa isian (NIP wajib untuk Kepala Madrasah), simpan untuk tahun aktif, catat perubahan di riwayat. */
    public function simpanPejabat(): void
    {
        $kunci = (string) $this->ubahPejabat;
        if ($this->pejabatUntuk($kunci) === null || $this->tahunTerkunci()) {
            return;
        }

        $this->isianPejabat = array_map(fn ($nilai) => self::rapikan((string) $nilai), array_merge(['jabatan' => '', 'nama' => '', 'nip' => ''], array_intersect_key($this->isianPejabat, ['jabatan' => 1, 'nama' => 1, 'nip' => 1])));
        // Aturan KelolaPejabat dengan nama properti formulir (isianPejabat.*).
        $this->validate(
            collect(KelolaPejabat::aturan($kunci))->mapWithKeys(fn ($aturan, string $k) => ["isianPejabat.{$k}" => $aturan])->all(),
            collect(KelolaPejabat::pesan())->mapWithKeys(fn (string $pesan, string $k) => ["isianPejabat.{$k}" => $pesan])->all(),
        );

        $tahun = $this->tahunDilihat();
        try {
            ['ringkasan' => $ringkasan, 'peringatan' => $peringatan] = KelolaPejabat::simpan($tahun, $kunci, $this->isianPejabat, StubData::currentUser());
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        unset($this->pejabat);
        $this->reset('ubahPejabat', 'isianPejabat');
        $this->dispatch('notify', message: $ringkasan === null ? 'Tidak ada yang berubah.' : $this->judulPejabat($kunci).' tersimpan untuk TA '.$tahun.'.', type: $ringkasan === null ? 'info' : 'success');
        if ($peringatan !== []) {
            $this->dispatch('notify', message: 'Periksa: '.implode(' ', $peringatan), type: 'info');
        }
    }

    /**
     * Temuan data pejabat tahun yang dilihat selain isian kosong (ditandai "Belum diisi" di tempatnya): NIP tersimpan tidak
     * sah dan bentrok antar-peran (ValidasiPejabat::tahun).
     *
     * @return list<string>
     */
    public function temuanPejabat(): array
    {
        $pesan = [];
        foreach (ValidasiPejabat::tahun($this->tahunDilihat()) as $temuan) {
            foreach ($temuan as $t) {
                if ($t['jenis'] !== 'kurang') {
                    $pesan[] = $t['pesan'];
                }
            }
        }

        return array_values(array_unique($pesan));
    }

    /** Tahun yang dilihat terkunci: pejabatnya hanya bisa dilihat. */
    public function tahunTerkunci(): bool
    {
        return KelolaPejabat::terkunci($this->tahunDilihat());
    }

    /** "Kepala Madrasah", "Ketua Komite", atau "Pejabat 1.6 Tim Penjamin Mutu". */
    public function judulPejabat(string $kunci): string
    {
        $baris = str_starts_with($kunci, 'leading:') ? collect($this->pejabat['leading'])->firstWhere('kode', '===', substr($kunci, 8)) : null;

        return match ($kunci) {
            'kepala' => 'Kepala Madrasah',
            'komite' => 'Ketua Komite',
            default => $baris ? "Pejabat {$baris['kode']} {$baris['nama_unit']}" : 'Pejabat leading',
        };
    }

    /**
     * Data pejabat untuk kunci formulir, atau null bila kunci tidak dikenal.
     *
     * @return array{jabatan: string, nama: string|null, nip: string|null}|null
     */
    private function pejabatUntuk(string $kunci): ?array
    {
        if (in_array($kunci, ['kepala', 'komite'], true)) {
            return $this->pejabat[$kunci];
        }

        $baris = str_starts_with($kunci, 'leading:') ? collect($this->pejabat['leading'])->firstWhere('kode', '===', substr($kunci, 8)) : null;

        return $baris ? ['jabatan' => $baris['jabatan'], 'nama' => $baris['nama'], 'nip' => $baris['nip']] : null;
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
            ...collect(KelolaProfilMadrasah::aturan())->mapWithKeys(fn (array $aturan, string $kunci) => [$kunci === 'logo' ? 'logo' : "isian.{$kunci}" => $aturan])->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            ...collect(KelolaProfilMadrasah::pesan())->mapWithKeys(fn (string $pesan, string $kunci) => [str_starts_with($kunci, 'logo.') ? $kunci : "isian.{$kunci}" => $pesan])->all(),
        ];
    }

    /** Satu baris tanpa spasi ganda. */
    private static function rapikan(string $teks): string
    {
        return trim(preg_replace('/\s+/u', ' ', $teks));
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
     * Pejabat penanda tangan tahun yang dilihat: pimpinan (kepala, komite) dan tiap leading beserta akun yang tertaut.
     *
     * @return array{kepala: array{jabatan: string, nama: string|null, nip: string|null}, komite: array{jabatan: string, nama: string|null, nip: string|null}, leading: list<array{kode: string, nama_unit: string, jabatan: string, nama: string|null, nip: string|null, akun: list<string>}>}
     */
    #[Computed]
    public function pejabat(): array
    {
        $semua = app(PejabatRepository::class)->untukTahun($this->tahunDilihat());
        $pejabat = ['kepala' => $semua['kepala'], 'komite' => $semua['komite']];
        $perLeading = $semua['leading'];
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
