<?php

namespace App\Livewire\Akun;

use App\Contracts\PenggunaRepository;
use App\Support\Akun\DataAkun;
use App\Support\Akun\GantiSandi;
use App\Support\Akun\ProfilAkun;
use App\Support\Akun\UbahProfil;
use App\Support\MasterKode;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Halaman Akun saya (PRD §8 "Kelola Profil & Sandi"; `/akun`, rute `akun.profil`, semua pengguna): tab `?tab=` (TAB)
 * profil (nama, surel, jabatan, NIP, peran, leading tertaut, status + ProfilAkun: tercetak di dokumen, kegiatan & dokumen
 * buatan akun, kuota AI hari ini, sesi), sandi (ganti sandi `gantiSandi()` lewat GantiSandi; syarat per ketukan Alpine
 * `formSandi`; `statusSandi`), aktivitas
 * (riwayat masuk terbaru). Data dari DataAkun::untuk (akun yang sedang masuk; tiruan selama autentikasi belum nyata).
 * Ubah profil (nama & jabatan) di tempat: `bukaUbah()` → `$isian` (diperiksa saat blur) + pratinjau tanda tangan di klien
 * → `simpanProfil()` lewat UbahProfil (versi `$versiProfil` terkunci; bentrok → `$bentrok` data terbaru, isian tetap)
 * → event `profil-diperbarui` {nama, jabatan} (nama di bilah atas ikut berganti); `batalUbah()`/Esc.
 */
#[Layout('layouts.app')]
class Profil extends Component
{
    public const TAB = ['profil' => 'Profil', 'sandi' => 'Sandi', 'aktivitas' => 'Aktivitas masuk'];

    #[Url(except: 'profil')]
    public string $tab = 'profil';

    public bool $ubah = false;

    /** @var array{nama: string, jabatan: string} */
    public array $isian = ['nama' => '', 'jabatan' => ''];

    #[Locked]
    public ?string $versiProfil = null;

    /** @var array{sandi_lama: string, sandi_baru: string, sandi_ulang: string} */
    public array $isianSandi = ['sandi_lama' => '', 'sandi_baru' => '', 'sandi_ulang' => ''];

    /** Data tersimpan terbaru saat simpan bentrok. @var array{nama: string, jabatan: string}|null */
    #[Locked]
    public ?array $bentrok = null;

    public function mount(): void
    {
        $this->tab = array_key_exists($this->tab, self::TAB) ? $this->tab : 'profil';
    }

    public function updatedTab(): void
    {
        $this->mount();
        $this->batalUbah();
    }

    public function bukaUbah(): void
    {
        $akun = $this->akunTersimpan();
        if ($akun === null) {
            return;
        }
        $this->resetErrorBag();
        $this->isian = ['nama' => (string) $akun['name'], 'jabatan' => (string) ($akun['jabatan'] ?? '')];
        $this->versiProfil = UbahProfil::versi($akun);
        $this->bentrok = null;
        $this->ubah = true;
    }

    public function batalUbah(): void
    {
        $this->resetErrorBag();
        $this->reset('ubah', 'isian', 'versiProfil', 'bentrok');
    }

    /** $kunci null = seluruh isian terkirim sekaligus (beberapa kolom berubah sebelum satu permintaan). */
    public function updatedIsian(mixed $nilai, ?string $kunci = null): void
    {
        $galat = UbahProfil::galat(UbahProfil::rapikan($this->isian));
        foreach ($kunci === null ? array_keys(UbahProfil::ISIAN) : [$kunci] as $k) {
            $this->resetErrorBag("isian.{$k}");
            if (isset($galat[$k])) {
                $this->addError("isian.{$k}", $galat[$k]);
            }
        }
    }

    public function pakaiTerbaru(): void
    {
        if ($this->bentrok !== null) {
            $this->isian = $this->bentrok;
            $this->bentrok = null;
        }
    }

    public function simpanProfil(): void
    {
        $this->resetErrorBag();
        try {
            $hasil = UbahProfil::simpan(StubData::currentUser(), $this->isian, $this->versiProfil);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kunci => $pesan) {
                $this->addError("isian.{$kunci}", $pesan[0]);
            }

            return;
        } catch (PengaturanBerubah $e) {
            $akun = $this->akunTersimpan();
            $this->bentrok = $akun === null ? null : ['nama' => (string) $akun['name'], 'jabatan' => (string) ($akun['jabatan'] ?? '')];
            $this->versiProfil = $akun === null ? null : UbahProfil::versi($akun);
            $this->dispatch('notify', message: $e->getMessage(), type: 'warning');

            return;
        } catch (RuntimeException $e) {
            report($e);
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }
        $this->batalUbah();
        unset($this->akun, $this->rincian);
        $this->dispatch('notify', message: $hasil['ringkasan'] === null ? 'Tidak ada perubahan profil.' : 'Profil disimpan.', type: $hasil['ringkasan'] === null ? 'info' : 'success');
        $this->dispatch('profil-diperbarui', nama: (string) $hasil['akun']['name'], jabatan: (string) ($hasil['akun']['jabatan'] ?? ''));
    }

    /** Ada/tidaknya sandi tersimpan & waktu terakhir diganti (GantiSandi). @return array{ada: bool, diganti_pada: string|null} */
    #[Computed]
    public function statusSandi(): array
    {
        return GantiSandi::status((int) StubData::currentUser()['id']);
    }

    public function gantiSandi(): void
    {
        $this->resetErrorBag();
        try {
            GantiSandi::simpan(StubData::currentUser(), $this->isianSandi);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kunci => $pesan) {
                $this->addError("isianSandi.{$kunci}", $pesan[0]);
            }

            return;
        } catch (RuntimeException $e) {
            report($e);
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }
        $this->reset('isianSandi');
        unset($this->statusSandi);
        $this->dispatch('sandi-diganti');
        $this->dispatch('notify', message: 'Sandi diganti. Gunakan sandi baru saat masuk berikutnya.', type: 'success');
    }

    /** Saran jabatan: jabatan penanda tangan leading yang tertaut + jabatan tersimpan. @return list<string> */
    public function saranJabatan(): array
    {
        $akun = $this->akun;
        $saran = array_map(fn (array $l) => (string) (MasterKode::leading($l['kode'])['jabatan_penanda_tangan'] ?? ''), $akun['leading'] ?? []);

        return array_values(array_unique(array_filter([...$saran, (string) ($akun['jabatan'] ?? '')])));
    }

    /** @return array<string, mixed>|null */
    private function akunTersimpan(): ?array
    {
        return app(PenggunaRepository::class)->temukan((int) StubData::currentUser()['id']);
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function akun(): ?array
    {
        return DataAkun::untuk(StubData::currentUser());
    }

    /** Kegiatan & dokumen buatan akun, penanda tangan tercetak, kuota AI, sesi (ProfilAkun). @return array<string, mixed> */
    #[Computed]
    public function rincian(): array
    {
        return ProfilAkun::untuk(StubData::currentUser());
    }

    public function render()
    {
        return view('livewire.akun.profil')->title('Akun saya');
    }
}
