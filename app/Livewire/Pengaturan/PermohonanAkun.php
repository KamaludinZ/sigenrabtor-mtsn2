<?php

namespace App\Livewire\Pengaturan;

use App\Support\Akun\KeputusanPendaftaran;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * Panel permohonan pendaftaran akun di tab Akun Pengaturan › Leading & User (anak `LeadingUser`; data KeputusanPendaftaran):
 * permohonan menunggu (terlama dulu) dengan Setujui (`setujui($id)` → akun leading dibuat) dan Tolak beralasan
 * (`mulaiTolak($id)` → `$alasan` → `tolak()`), serta riwayat keputusan terakhir. Setiap keputusan men-dispatch
 * `permohonan-akun-diputuskan` agar tabel akun induk dimuat ulang.
 */
class PermohonanAkun extends Component
{
    #[Locked]
    public ?string $tolakId = null;

    public string $alasan = '';

    /** @return array{menunggu: list<array<string, mixed>>, diputuskan: list<array<string, mixed>>, jumlah_menunggu: int} */
    #[Computed]
    public function daftar(): array
    {
        return KeputusanPendaftaran::daftar(StubData::currentUser());
    }

    public function setujui(string $id): void
    {
        $this->jalankan(fn () => KeputusanPendaftaran::setujui($id, StubData::currentUser()));
    }

    public function mulaiTolak(string $id): void
    {
        $this->resetErrorBag();
        $this->tolakId = $id;
        $this->alasan = '';
    }

    public function batalTolak(): void
    {
        $this->resetErrorBag();
        $this->reset('tolakId', 'alasan');
    }

    public function tolak(): void
    {
        if ($this->tolakId !== null && $this->jalankan(fn () => KeputusanPendaftaran::tolak((string) $this->tolakId, $this->alasan, StubData::currentUser()))) {
            $this->batalTolak();
        }
    }

    private function jalankan(callable $aksi): bool
    {
        $this->resetErrorBag();
        try {
            $hasil = $aksi();
        } catch (ValidationException $e) {
            if (isset($e->errors()['alasan'])) {
                $this->addError('alasan', $e->errors()['alasan'][0]);
            } else {
                $this->dispatch('notify', message: 'Akun belum bisa dibuat: '.collect($e->errors())->flatten()->first(), type: 'error');
            }

            return false;
        } catch (AuthorizationException|PengaturanBerubah|RuntimeException $e) {
            unset($this->daftar);
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return false;
        }
        unset($this->daftar);
        $this->dispatch('notify', message: $hasil['pesan'], type: 'success');
        $this->dispatch('permohonan-akun-diputuskan');

        return true;
    }

    public function render()
    {
        return view('livewire.pengaturan.permohonan-akun');
    }
}
