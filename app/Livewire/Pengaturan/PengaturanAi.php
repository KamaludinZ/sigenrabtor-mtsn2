<?php

namespace App\Livewire\Pengaturan;

use App\Support\Kegiatan\BatasAi;
use App\Support\Kegiatan\RiwayatSaranAi;
use App\Support\Pengaturan\PenyediaAi;
use App\Support\Pengaturan\PromptAi;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Pengaturan › Pengaturan AI (PRD §21, F24; `/pengaturan/ai`, rute `pengaturan.ai.index`): tab `?tab=penyedia` (penyedia
 * aktif & cadangan, model, alamat, kunci API, perbandingan server sekolah vs API cloud, data yang dikirim), `prompt`
 * (prompt per bagian & gaya bahasa baku — PromptAi), dan `pemakaian` (batas harian BatasAi, pemakaian hari ini per akun,
 * ringkasan riwayat saran AI). Halaman dasar: penyuntingan menyusul.
 */
#[Layout('layouts.app')]
class PengaturanAi extends Component
{
    public const TAB = ['penyedia' => 'Penyedia', 'prompt' => 'Prompt per bagian', 'pemakaian' => 'Batas pemakaian'];

    #[Url(except: 'penyedia')]
    public string $tab = 'penyedia';

    public function mount(): void
    {
        $this->rapikanTab();
    }

    public function updatedTab(): void
    {
        $this->rapikanTab();
    }

    /**
     * Pengaturan tersimpan dengan nama penyedia terbaca.
     *
     * @return array<string, mixed>
     */
    public function pengaturan(): array
    {
        $ai = StubPengaturan::pengaturanAi();

        return $ai + ['nama_aktif' => PenyediaAi::nama($ai['provider_aktif']), 'nama_cadangan' => PenyediaAi::nama($ai['provider_cadangan'])];
    }

    /**
     * Pemakaian AI hari ini per akun aktif (terbanyak dulu).
     *
     * @return list<array{id: int, nama: string, peran: string, terpakai: int, sisa: int}>
     */
    public function pemakaianHariIni(): array
    {
        $baris = array_map(fn (array $p) => [
            'id' => (int) $p['id'], 'nama' => $p['name'], 'peran' => $p['role'],
            'terpakai' => BatasAi::terpakai($p), 'sisa' => max(0, BatasAi::batas() - BatasAi::terpakai($p)),
        ], array_values(array_filter(StubPengaturan::pengguna(), fn (array $p) => $p['is_active'])));
        usort($baris, fn (array $a, array $b) => [$b['terpakai'], $a['nama']] <=> [$a['terpakai'], $b['nama']]);

        return $baris;
    }

    /** @return list<array<string, mixed>> */
    public function prompt(): array
    {
        return PromptAi::semua();
    }

    /** @return array{permintaan: int, versi: int, status: array<string, int>, dipakai: int} */
    public function ringkasanSaran(): array
    {
        return RiwayatSaranAi::ringkasan(RiwayatSaranAi::untuk(StubData::currentUser()));
    }

    private function rapikanTab(): void
    {
        if (! array_key_exists($this->tab, self::TAB)) {
            $this->tab = 'penyedia';
        }
    }

    public function render()
    {
        return view('livewire.pengaturan.pengaturan-ai')->title('Pengaturan AI');
    }
}
