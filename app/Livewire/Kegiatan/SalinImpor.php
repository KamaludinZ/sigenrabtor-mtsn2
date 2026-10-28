<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\Duplikat\DaftarSumberDuplikat;
use App\Support\Kegiatan\Duplikat\DuplikatKegiatan;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Duplikat & Impor Cascade Perkin (PRD §19, F22): dua cara membuat draf kegiatan tahun aktif secara massal — tab
 * `?tab=duplikat` (salin kegiatan tahun lalu; tahun sumber `?dari=`, cari & saringan leading, pilihan kegiatan) dan
 * `?tab=perkin` (impor dari sheet cascade Perkin). Duplikat lewat dialog konfirmasi berisi rencana tiap kegiatan
 * (DuplikatKegiatan::rencana) lalu DuplikatKegiatan::jalankan(); unggah Perkin menyusul.
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

    /** Dialog konfirmasi duplikat terbuka. */
    public bool $dialogDuplikat = false;

    /**
     * Hasil duplikat terakhir: draf baru beserta catatannya.
     *
     * @var list<array{id: int, nama: string, catatan: list<array{jenis: string, teks: string}>}>
     */
    #[Locked]
    public array $hasilDuplikat = [];

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
        return DaftarSumberDuplikat::tahun($this->tahunSumber ?: null);
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
        return DaftarSumberDuplikat::saring($this->semuaSumber, $this->cari, $this->leading);
    }

    public function pilihSemua(): void
    {
        $bisa = array_column(array_filter($this->sumberTampil(), fn (array $b) => $b['sudah_ada'] === null), 'id');
        $this->pilihan = array_values(array_unique([...$this->pilihan, ...$bisa]));
    }

    public function kosongkanPilihan(): void
    {
        $this->pilihan = [];
    }

    public function bukaDuplikat(): void
    {
        $this->rapikan();
        $this->dialogDuplikat = $this->pilihan !== [];
    }

    public function batalDuplikat(): void
    {
        $this->dialogDuplikat = false;
    }

    /**
     * Rencana salinan kegiatan terpilih untuk dialog konfirmasi.
     *
     * @return list<array{id: int, nama: string, catatan: list<array{jenis: string, teks: string}>}>
     */
    public function rencanaDuplikat(): array
    {
        $perId = array_column(app(KegiatanRepository::class)->semua(), null, 'id');

        return array_values(array_map(fn (int $id) => ['id' => $id, 'nama' => (string) ($perId[$id]['nama_kegiatan'] ?? '')] + ['catatan' => DuplikatKegiatan::rencana($perId[$id])['catatan']], array_filter($this->pilihan, fn (int $id) => isset($perId[$id]))));
    }

    /** Salin kegiatan terpilih menjadi draf tahun aktif. */
    public function duplikat(): void
    {
        $this->rapikan();
        $this->dialogDuplikat = false;
        if ($this->pilihan === []) {
            return;
        }

        $perId = array_column(app(KegiatanRepository::class)->semua(), null, 'id');
        $pengguna = StubData::currentUser();
        $this->hasilDuplikat = array_values(array_map(fn (int $id) => DuplikatKegiatan::jalankan($perId[$id], $pengguna), $this->pilihan));
        $this->pilihan = [];
        unset($this->semuaSumber);

        $jumlah = count($this->hasilDuplikat);
        $this->dispatch('notify', message: "{$jumlah} kegiatan diduplikat menjadi draf TA ".StubData::tahunAktif()['tahun'].'.', type: 'success');
    }

    public function tutupHasil(): void
    {
        $this->hasilDuplikat = [];
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
        // Kegiatan yang sudah ada di tahun aktif (nama sama) tidak bisa dipilih.
        $ada = array_column(array_filter($this->semuaSumber, fn (array $b) => $b['sudah_ada'] === null), 'id');
        $this->pilihan = array_values(array_filter(array_map('intval', $this->pilihan), fn (int $id) => in_array($id, $ada, true)));
    }

    public function render()
    {
        return view('livewire.kegiatan.salin-impor')->title('Duplikat & Impor');
    }
}
