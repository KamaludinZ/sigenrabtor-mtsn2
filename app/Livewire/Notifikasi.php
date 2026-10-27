<?php

namespace App\Livewire;

use App\Contracts\KegiatanRepository;
use App\Contracts\NotifikasiRepository;
use App\Support\Notifikasi\NotifikasiStatus;
use App\Support\Stub\StubData;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Lonceng notifikasi status dokumen di bilah atas (NotifikasiStatus): lencana jumlah belum dibaca, panel daftar
 * terbaru. Membuka panel menandai semuanya dibaca; yang baru saat dibuka tetap disorot selama halaman ini.
 * Diperbarui berkala (wire:poll) selama tab terlihat.
 */
class Notifikasi extends Component
{
    /** Penanda baca sebelum panel terakhir dibuka (penentu sorotan "baru"); null = belum dibuka di halaman ini. */
    public ?string $sorotSejak = null;

    public bool $pernahDibuka = false;

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function daftar(): array
    {
        $pengguna = StubData::currentUser();
        $penanda = $this->pernahDibuka ? $this->sorotSejak : app(NotifikasiRepository::class)->dibacaSampai($pengguna['id']);

        return NotifikasiStatus::untuk($pengguna, app(KegiatanRepository::class)->semua(), StubData::tahunAktif()['tahun'], $penanda);
    }

    /** Jumlah belum dibaca menurut penanda baca tersimpan. */
    public function jumlahBaru(): int
    {
        $penanda = app(NotifikasiRepository::class)->dibacaSampai(StubData::currentUser()['id']);

        return count(array_filter($this->daftar, fn (array $n) => $penanda === null || strtotime($n['pada']) > strtotime($penanda)));
    }

    /** Panel dibuka: simpan penanda lama untuk sorotan, lalu tandai semua dibaca. */
    public function buka(): void
    {
        $repo = app(NotifikasiRepository::class);
        $id = StubData::currentUser()['id'];

        $this->sorotSejak = $repo->dibacaSampai($id);
        $this->pernahDibuka = true;
        $repo->tandaiDibaca($id, now()->toIso8601String());
        unset($this->daftar);
    }

    /** @param  array<string, mixed>  $notifikasi */
    public function tautan(array $notifikasi): ?string
    {
        $rute = StubData::currentUser()['role'] === 'admin' ? 'pemeriksaan.periksa' : 'kegiatan.edit';

        return Route::has($rute) ? route($rute, ['kegiatan' => $notifikasi['kegiatan_id']]) : null;
    }

    public function render()
    {
        return view('livewire.notifikasi');
    }
}
