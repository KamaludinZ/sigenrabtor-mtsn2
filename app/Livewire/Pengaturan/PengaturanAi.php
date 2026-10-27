<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Support\Kegiatan\BatasAi;
use App\Support\Kegiatan\RiwayatSaranAi;
use App\Support\Pengaturan\AturanPenyediaAi;
use App\Support\Pengaturan\PenyediaAi;
use App\Support\Pengaturan\PromptAi;
use App\Support\Pengaturan\RiwayatPengaturan;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Pengaturan › Pengaturan AI (PRD §21, F24; `/pengaturan/ai`, rute `pengaturan.ai.index`): tab `?tab=penyedia` (penyedia
 * aktif & cadangan, model, alamat, kunci API, perbandingan server sekolah vs API cloud, data yang dikirim), `prompt`
 * (prompt per bagian & gaya bahasa baku — PromptAi), dan `pemakaian` (batas harian BatasAi, pemakaian hari ini per akun,
 * ringkasan riwayat saran AI). Konfigurasi server sekolah (Ollama) lewat formulir bukaLokal() → simpanLokal()
 * (AturanPenyediaAi, PengaturanAiRepository, riwayat `ubah_penyedia_ai`); penyedia cloud & prompt menyusul.
 */
#[Layout('layouts.app')]
class PengaturanAi extends Component
{
    public const TAB = ['penyedia' => 'Penyedia', 'prompt' => 'Prompt per bagian', 'pemakaian' => 'Batas pemakaian'];

    #[Url(except: 'penyedia')]
    public string $tab = 'penyedia';

    /** Formulir konfigurasi server sekolah (Ollama) terbuka. */
    public bool $lokalTerbuka = false;

    /** @var array{base_url: string, model: string, batas_waktu: int|string|null, peran: string} */
    public array $isianLokal = ['base_url' => '', 'model' => '', 'batas_waktu' => 60, 'peran' => 'tidak'];

    /** `diperbarui_pada` pengaturan saat formulir dibuka; pembanding perubahan dari tab/admin lain. */
    #[Locked]
    public ?string $versi = null;

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

    /** Buka formulir server sekolah berisi konfigurasi tersimpan. */
    public function bukaLokal(): void
    {
        $ai = app(PengaturanAiRepository::class)->ambil();
        $lokal = $ai['penyedia']['ollama'] ?? [];
        $this->isianLokal = [
            'base_url' => (string) ($lokal['base_url'] ?? ''),
            'model' => (string) ($lokal['model'] ?? ''),
            'batas_waktu' => (int) ($lokal['batas_waktu'] ?? 60),
            'peran' => AturanPenyediaAi::peranDari($ai, 'ollama'),
        ];
        $this->versi = $ai['diperbarui_pada'];
        $this->resetErrorBag();
        $this->lokalTerbuka = true;
    }

    public function batalLokal(): void
    {
        $this->lokalTerbuka = false;
        $this->resetErrorBag();
    }

    /**
     * Simpan konfigurasi server sekolah beserta perannya (aktif/cadangan/tidak dipakai). Perubahan dari tab/admin lain
     * sejak formulir dibuka membatalkan simpan tanpa membuang isian; kegagalan penyimpanan dilaporkan tanpa menutup.
     */
    public function simpanLokal(): void
    {
        $this->resetErrorBag();
        ['data' => $data, 'galat' => $galat] = AturanPenyediaAi::lokal($this->isianLokal);
        if ($galat !== []) {
            foreach ($galat as $kolom => $pesan) {
                $this->addError("isianLokal.{$kolom}", $pesan);
            }

            return;
        }

        $repo = app(PengaturanAiRepository::class);
        $lama = $repo->ambil();
        if ($lama['diperbarui_pada'] !== $this->versi) {
            $this->versi = $lama['diperbarui_pada'];
            $this->dispatch('notify', message: 'Pengaturan AI baru diubah di tempat lain — tampilan dimuat ulang, isian Anda tetap. Periksa lalu simpan lagi.', type: 'error');

            return;
        }

        $baru = AturanPenyediaAi::terapkanPeran($lama, 'ollama', $data['peran']);
        $baru['penyedia']['ollama'] = ['base_url' => $data['base_url'], 'model' => $data['model'], 'batas_waktu' => $data['batas_waktu']] + ($lama['penyedia']['ollama'] ?? []);
        unset($baru['diperbarui_pada']);

        $ringkas = fn (array $p) => [
            'base_url' => $p['penyedia']['ollama']['base_url'] ?? '', 'model' => $p['penyedia']['ollama']['model'] ?? '', 'batas_waktu' => $p['penyedia']['ollama']['batas_waktu'] ?? null,
            'aktif' => PenyediaAi::nama($p['provider_aktif']) ?? 'belum dipilih', 'cadangan' => PenyediaAi::nama($p['provider_cadangan']) ?? 'belum dipilih',
        ];
        $perubahan = RiwayatPengaturan::perubahan($ringkas($lama), $ringkas($baru), ['base_url' => 'Alamat server', 'model' => 'Model', 'batas_waktu' => 'Batas waktu (detik)', 'aktif' => 'Penyedia aktif', 'cadangan' => 'Penyedia cadangan']);

        try {
            $repo->simpan($baru);
        } catch (RuntimeException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        if ($perubahan !== null) {
            RiwayatPengaturan::catat('ubah_penyedia_ai', 'Server sekolah (Ollama) — '.$perubahan);
        }
        $this->lokalTerbuka = false;
        $naik = $data['peran'] === 'cadangan' && $baru['provider_aktif'] === 'ollama';
        $this->dispatch('notify', message: $naik ? 'Konfigurasi server sekolah tersimpan — belum ada penyedia aktif, jadi server sekolah dipakai sebagai penyedia aktif.' : 'Konfigurasi server sekolah tersimpan.', type: 'success');
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
