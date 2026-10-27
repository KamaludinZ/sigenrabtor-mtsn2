<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\Duplikat\DuplikatKegiatan;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Duplikat & Impor Cascade Perkin (PRD §19, F22): dua cara membuat draf kegiatan tahun aktif secara massal — tab
 * `?tab=duplikat` (salin kegiatan tahun lalu; tahun sumber `?dari=`, cari & saringan leading, pilihan kegiatan) dan
 * `?tab=perkin` (impor dari sheet cascade Perkin). Proses salin & unggah menyusul.
 */
#[Layout('layouts.app')]
class SalinImpor extends Component
{
    public const TAB = ['duplikat' => 'Duplikat tahun lalu', 'perkin' => 'Impor cascade Perkin'];

    #[Url(except: 'duplikat')]
    public string $tab = 'duplikat';

    /** Tahun sumber duplikat (0 = tahun sumber terbaru). */
    #[Url(as: 'dari', except: 0)]
    public int $tahunSumber = 0;

    #[Url(except: '')]
    public string $cari = '';

    #[Url(except: '')]
    public string $leading = '';

    /** Id kegiatan sumber yang dipilih untuk diduplikat. */
    public array $pilihan = [];

    public function mount(): void
    {
        $this->rapikan();
    }

    public function updated(string $properti): void
    {
        // Pilihan selalu dirapikan ke kegiatan sumber yang memang boleh diduplikat akun ini.
        if (in_array($properti, ['tab', 'tahunSumber', 'leading'], true) || str_starts_with($properti, 'pilihan')) {
            $this->rapikan();
        }
    }

    /** Tahun sumber yang berlaku (pilihan URL atau tahun sumber terbaru); null bila tidak ada tahun lain. */
    public function tahunDipakai(): ?int
    {
        $tahun = DuplikatKegiatan::tahunSumber();

        return in_array($this->tahunSumber, $tahun, true) ? $this->tahunSumber : ($tahun[0] ?? null);
    }

    /** @return list<array<string, mixed>> Kegiatan tahun sumber dalam cakupan akun. */
    #[Computed]
    public function semuaSumber(): array
    {
        $tahun = $this->tahunDipakai();

        return $tahun === null ? [] : DuplikatKegiatan::sumber(app(KegiatanRepository::class)->semua(), $tahun, StubData::currentUser());
    }

    /** @return list<array<string, mixed>> Setelah cari & saringan leading. */
    public function sumberTampil(): array
    {
        $cari = mb_strtolower(trim($this->cari));

        return array_values(array_filter($this->semuaSumber, fn (array $b) => ($cari === '' || str_contains(mb_strtolower($b['nama']), $cari))
            && ($this->leading === '' || $b['leading'] === $this->leading)));
    }

    public function pilihSemua(): void
    {
        $this->pilihan = array_values(array_unique([...$this->pilihan, ...array_column($this->sumberTampil(), 'id')]));
    }

    public function kosongkanPilihan(): void
    {
        $this->pilihan = [];
    }

    private function rapikan(): void
    {
        if (! array_key_exists($this->tab, self::TAB)) {
            $this->tab = 'duplikat';
        }
        if ($this->tahunSumber !== 0 && ! in_array($this->tahunSumber, DuplikatKegiatan::tahunSumber(), true)) {
            $this->tahunSumber = 0;
        }
        unset($this->semuaSumber);
        $ada = array_column($this->semuaSumber, 'id');
        $this->pilihan = array_values(array_filter(array_map('intval', $this->pilihan), fn (int $id) => in_array($id, $ada, true)));
    }

    public function render()
    {
        return view('livewire.kegiatan.salin-impor')->title('Duplikat & Impor');
    }
}
