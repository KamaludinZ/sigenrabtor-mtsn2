<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Support\MasterKode;
use App\Support\Pengaturan\KelolaTahunAnggaran;
use App\Support\Pengaturan\KontrolTahunAktif;
use App\Support\Pengaturan\SalinPengaturanTahun;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pengaturan › Tahun Anggaran (PRD F6, Fase 5 "Tahun Anggaran Aktif"): status tiap tahun (aktif / terkunci / tidak
 * aktif, SK RKTM, master kode) dan pemilihan tepat satu tahun aktif lewat konfirmasi. Tahun aktif berlaku di seluruh
 * aplikasi (StubData::tahunAktif); data tahun lain tidak dihapus. Tambah tahun, kunci/buka kunci, dan hapus tahun yang
 * tidak dipakai lewat KelolaTahunAnggaran; salin master & pengaturan dari tahun lain lewat SalinPengaturanTahun (dialog
 * per tahun yang tidak terkunci: tahun sumber, bagian yang disalin, lalu hasil per bagian).
 */
#[Layout('layouts.app')]
class TahunAnggaran extends Component
{
    /** Tahun yang sedang dikonfirmasi untuk diaktifkan (dialog konfirmasi); null bila dialog tertutup. */
    #[Locked]
    public ?int $akanDiaktifkan = null;

    /** Kunci tahun yang sebelumnya aktif sekaligus (bawaan: ya bila beralih ke tahun yang lebih baru). */
    public bool $kunciSebelumnya = false;

    /** Isian "Tambah tahun anggaran". */
    public string $tahunBaru = '';

    /** Tahun yang sedang dikonfirmasi untuk dihapus (konfirmasi di barisnya). */
    #[Locked]
    public ?int $akanDihapus = null;

    /** Tahun tujuan dialog "Salin pengaturan"; null bila dialog tertutup. */
    #[Locked]
    public ?int $salinKe = null;

    /** Tahun sumber salinan (null = belum ada tahun lain berisi pengaturan; teks hanya sesaat dari pilihan, dirapikan updatedSalinDari). */
    public int|string|null $salinDari = null;

    /** Bagian SalinPengaturanTahun::BAGIAN yang dicentang. */
    public array $salinBagian = [];

    /** Hasil salin terakhir (SalinPengaturanTahun::salin) untuk ditampilkan di dialog. */
    #[Locked]
    public ?array $hasilSalin = null;

    /**
     * Semua tahun beserta status master kodenya.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function daftar(): array
    {
        return array_map(fn (array $t) => $t + [
            'master_tersedia' => $t['pemakaian']['master_kode'],
            'jumlah_rktm' => count(MasterKode::kodeRktm($t['tahun'])),
        ], KelolaTahunAnggaran::daftar());
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function aktif(): ?array
    {
        return collect($this->daftar)->firstWhere('is_active', true);
    }

    /** Kegiatan yang sedang tampil (milik tahun aktif). */
    #[Computed]
    public function jumlahKegiatan(): int
    {
        return count(app(KegiatanRepository::class)->semua());
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function calon(): ?array
    {
        return $this->akanDiaktifkan === null ? null : collect($this->daftar)->firstWhere('tahun', $this->akanDiaktifkan);
    }

    public function pilih(int $tahun): void
    {
        $dipilih = collect($this->daftar)->firstWhere('tahun', $tahun);

        if ($dipilih === null || $dipilih['is_active']) {
            return;
        }

        $this->akanDiaktifkan = $tahun;
        $aktif = $this->aktif;
        $this->kunciSebelumnya = $aktif !== null && ! $aktif['is_locked'] && $tahun > $aktif['tahun'];
    }

    public function batalPilih(): void
    {
        $this->akanDiaktifkan = null;
    }

    /** Tambah tahun baru lewat KelolaTahunAnggaran (tidak aktif, tidak terkunci). */
    public function tambahTahun(): void
    {
        $this->resetErrorBag('tahunBaru');
        try {
            $tahun = KelolaTahunAnggaran::tambah($this->tahunBaru, StubData::currentUser());
        } catch (ValidationException $e) {
            $this->addError('tahunBaru', $e->errors()['tahun'][0]);

            return;
        }

        $this->reset('tahunBaru');
        unset($this->daftar, $this->aktif);
        $sumber = SalinPengaturanTahun::sumber($tahun);
        $this->dispatch('notify', message: "Tahun Anggaran {$tahun} ditambahkan. ".($sumber !== null ? "Salin master & pengaturan TA {$sumber} lewat tombol Salin pengaturan, lalu aktifkan bila sudah siap." : 'Aktifkan bila sudah siap dikerjakan.'), type: 'success');
    }

    /** Kunci tahun yang terbuka, atau buka kunci tahun yang terkunci. */
    public function ubahKunci(int $tahun): void
    {
        $data = collect($this->daftar)->firstWhere('tahun', $tahun);
        if ($data === null) {
            return;
        }

        try {
            KelolaTahunAnggaran::kunci($tahun, ! $data['is_locked'], StubData::currentUser());
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: $e->errors()['tahun'][0], type: 'error');

            return;
        }

        unset($this->daftar, $this->aktif);
        $this->dispatch('notify', message: "Tahun Anggaran {$tahun} ".($data['is_locked'] ? 'dibuka kuncinya — isiannya bisa diubah lagi.' : 'dikunci — isiannya hanya bisa dilihat.'), type: 'success');
    }

    public function mintaHapus(int $tahun): void
    {
        $this->akanDihapus = collect($this->daftar)->contains(fn (array $t) => $t['tahun'] === $tahun && $t['boleh_dihapus']) ? $tahun : null;
    }

    public function batalHapus(): void
    {
        $this->akanDihapus = null;
    }

    public function hapusTahun(): void
    {
        if ($this->akanDihapus === null) {
            return;
        }
        $tahun = $this->akanDihapus;
        $this->akanDihapus = null;

        try {
            KelolaTahunAnggaran::hapus($tahun, StubData::currentUser());
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: $e->errors()['tahun'][0], type: 'error');

            return;
        }

        unset($this->daftar, $this->aktif);
        $this->dispatch('notify', message: "Tahun Anggaran {$tahun} dihapus.", type: 'info');
    }

    /** Aktifkan tahun yang dikonfirmasi lalu muat ulang halaman agar menu atas & seluruh layar memakai tahun baru. */
    public function aktifkan(): void
    {
        $calon = $this->calon;

        if ($calon === null || ! KontrolTahunAktif::aktifkan($calon['tahun'], StubData::currentUser(), $this->kunciSebelumnya)) {
            return;
        }

        $this->redirectRoute('pengaturan.tahun.index', navigate: true);
    }

    /** Rencana salin tahun sumber → tujuan dialog (keadaan tiap bagian); null bila belum ada sumber. */
    #[Computed]
    public function rencanaSalin(): ?array
    {
        if ($this->salinKe === null || $this->salinDari === null) {
            return null;
        }

        try {
            return SalinPengaturanTahun::rencana((int) $this->salinDari, $this->salinKe);
        } catch (ValidationException) {
            return null;
        }
    }

    /** Buka dialog salin untuk tahun $tahun yang tidak terkunci; sumber bawaan = tahun berisi terdekat sebelumnya. */
    public function bukaSalin(int $tahun): void
    {
        $data = collect($this->daftar)->firstWhere('tahun', $tahun);
        if ($data === null || $data['is_locked']) {
            return;
        }

        $this->salinKe = $tahun;
        $this->salinDari = SalinPengaturanTahun::sumber($tahun);
        $this->hasilSalin = null;
        $this->pilihSemuaBagian();
    }

    public function updatedSalinDari(mixed $nilai): void
    {
        $tahun = filter_var($nilai, FILTER_VALIDATE_INT);
        $this->salinDari = $tahun !== false && $tahun !== $this->salinKe && collect($this->daftar)->contains('tahun', $tahun) ? $tahun : null;
        $this->hasilSalin = null;
        $this->pilihSemuaBagian();
    }

    public function tutupSalin(): void
    {
        $this->reset('salinKe', 'salinDari', 'salinBagian', 'hasilSalin');
    }

    public function salinPengaturan(): void
    {
        if ($this->salinKe === null || $this->salinDari === null) {
            return;
        }
        $bagian = array_values(array_intersect(array_keys(SalinPengaturanTahun::BAGIAN), array_map('strval', $this->salinBagian)));
        if ($bagian === []) {
            $this->dispatch('notify', message: 'Centang minimal satu bagian yang akan disalin.', type: 'error');

            return;
        }

        try {
            $this->hasilSalin = SalinPengaturanTahun::salin((int) $this->salinDari, $this->salinKe, $bagian, StubData::currentUser());
        } catch (AuthorizationException|ValidationException $e) {
            $this->dispatch('notify', message: $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage(), type: 'error');

            return;
        }

        unset($this->daftar, $this->aktif, $this->rencanaSalin);
        $this->pilihSemuaBagian();
        $gagal = collect($this->hasilSalin['hasil'])->contains('status', 'gagal');
        $this->dispatch('notify', message: $this->hasilSalin['pesan'], type: $gagal ? 'error' : ($this->hasilSalin['disalin'] > 0 ? 'success' : 'info'));
    }

    /** Centang semua bagian yang bisa disalin dari sumber terpilih. */
    private function pilihSemuaBagian(): void
    {
        unset($this->rencanaSalin);
        $this->salinBagian = array_column(array_filter($this->rencanaSalin['bagian'] ?? [], fn (array $b) => $b['bisa']), 'kunci');
    }

    public function render()
    {
        return view('livewire.pengaturan.tahun-anggaran')->title('Tahun Anggaran · Pengaturan');
    }
}
