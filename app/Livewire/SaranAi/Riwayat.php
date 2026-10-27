<?php

namespace App\Livewire\SaranAi;

use App\Support\Kegiatan\BagianAi;
use App\Support\Kegiatan\RiwayatSaranAi;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Halaman Riwayat Saran AI (PRD F25, `/riwayat-saran-ai`): setiap permintaan "Buat dengan AI" & usulan jadwal beserta
 * versinya, keputusan (diterima/diedit/ditolak/belum diputuskan), bahan yang terkirim, pengguna, penyedia, dan waktu.
 * Cakupan & saringan lewat RiwayatSaranAi::untuk() — saringan di URL `kegiatan`/`bagian`/`status`/`cari`.
 */
#[Layout('layouts.app')]
class Riwayat extends Component
{
    public const PER_HALAMAN = 20;

    #[Url(as: 'kegiatan', except: 0)]
    public int $kegiatanId = 0;

    #[Url(except: '')]
    public string $bagian = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $cari = '';

    public int $batas = self::PER_HALAMAN;

    public function mount(): void
    {
        $this->rapikan();
    }

    public function updated(): void
    {
        $this->rapikan();
        $this->batas = self::PER_HALAMAN;
        unset($this->permintaan);
    }

    public function tampilkanLebih(): void
    {
        $this->batas += self::PER_HALAMAN;
    }

    public function aturUlang(): void
    {
        $this->reset('kegiatanId', 'bagian', 'status', 'cari', 'batas');
        unset($this->permintaan);
    }

    /** @return list<array<string, mixed>> Permintaan tersaring, terbaru dulu. */
    #[Computed]
    public function permintaan(): array
    {
        return RiwayatSaranAi::untuk(StubData::currentUser(), ['kegiatan' => $this->kegiatanId, 'bagian' => $this->bagian, 'status' => $this->status, 'cari' => $this->cari]);
    }

    /** @return array<int, string> Kegiatan dalam cakupan akun yang punya riwayat (pilihan saringan). */
    public function pilihanKegiatan(): array
    {
        return array_column(RiwayatSaranAi::untuk(StubData::currentUser()), 'nama_kegiatan', 'kegiatan_id');
    }

    /** @return array<int, string> */
    public function namaPengguna(): array
    {
        return array_column(StubPengaturan::pengguna(), 'name', 'id');
    }

    private function rapikan(): void
    {
        if (! isset(BagianAi::BAGIAN[$this->bagian])) {
            $this->bagian = '';
        }
        if (! isset(RiwayatSaranAi::STATUS[$this->status])) {
            $this->status = '';
        }
        $this->kegiatanId = max(0, $this->kegiatanId);
    }

    public function render()
    {
        return view('livewire.saran-ai.riwayat')->title('Riwayat Saran AI');
    }
}
