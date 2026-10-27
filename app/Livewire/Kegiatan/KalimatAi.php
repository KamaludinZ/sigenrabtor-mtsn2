<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Forms\KegiatanForm;
use App\Support\Dokumen\SusunanKak;
use App\Support\Kegiatan\BagianAi;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\KonteksAi;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Halaman Kalimat KAK satu kegiatan (PRD §20, F23; tab ruang kerja "Kalimat KAK"): enam bagian yang bisa dibantu AI
 * (BagianAi::BAGIAN — nama cover, latar belakang, tujuan, sasaran, pelaksanaan, jadwal) dalam satu formulir: isian teks,
 * bahan dari data kegiatan untuk AI, tempat tercetak, dan pratinjau bagian KAK. Kolom yang disunting hanya
 * BagianAi::KOLOM (+ cover_manual) dan disimpan dengan menggabungkan record tersimpan; jadwal disunting di Matrik & Jadwal.
 * Tombol "Buat dengan AI" menyusul.
 */
#[Layout('layouts.app')]
class KalimatAi extends Component
{
    #[Locked]
    public int $kegiatanId;

    /** @var array<string, mixed> */
    #[Locked]
    public array $dataKegiatan = [];

    public KegiatanForm $form;

    public bool $adaPerubahan = false;

    /** @var array<string, string> Teks tersimpan ketika halaman dimuat/terakhir disimpan (pembanding perubahan & bentrok). */
    #[Locked]
    public array $teksDimuat = [];

    /** @var array<string, array{0: string, 1: string}>|null Kolom yang disimpan pihak lain sejak dimuat → [dimuat, tersimpan]. */
    #[Locked]
    public ?array $bentrok = null;

    public function mount(KegiatanRepository $repo, int $kegiatan): void
    {
        $this->kegiatanId = $kegiatan;
        $this->muatDari($repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.'));
    }

    public function status(): StatusKegiatan
    {
        return StatusKegiatan::tryFrom($this->dataKegiatan['status'] ?? '') ?? StatusKegiatan::Draf;
    }

    public function dapatDiedit(): bool
    {
        return $this->status()->dapatDiedit();
    }

    public function updating(string $property): void
    {
        if (str_starts_with($property, 'form.')) {
            $this->pastikanDapatDiedit();
            abort_unless(in_array(substr($property, 5), [...BagianAi::KOLOM, 'cover_manual'], true), 403, 'Isian ini diubah di halaman Data Kegiatan.');
        }
    }

    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'form.') || $property === 'form.cover_manual') {
            return;
        }

        $field = substr($property, 5);
        if ($field === 'nama_kegiatan_cover') {
            $this->form->setNamaCoverManual();
        }
        $this->form->rapikanUraian($field);
        $this->adaPerubahan = $this->isianBerubah() !== [];
        $this->form->validateOnly($field);
    }

    public function resetNamaCover(): void
    {
        $this->pastikanDapatDiedit();
        $this->form->resetNamaCover();
        $this->adaPerubahan = $this->isianBerubah() !== [];
        $this->form->validateOnly('nama_kegiatan_cover');
    }

    /**
     * Teks yang sedang disunting (kolom BagianAi::KOLOM + cover_manual).
     *
     * @return array<string, mixed>
     */
    public function teksSekarang(): array
    {
        return array_intersect_key($this->form->dataUntukDisimpan(), array_flip([...BagianAi::KOLOM, 'cover_manual']));
    }

    /**
     * Kolom yang berbeda dari versi tersimpan.
     *
     * @return list<string>
     */
    public function isianBerubah(): array
    {
        $sekarang = self::baku($this->teksSekarang());

        return array_values(array_filter(BagianAi::KOLOM, fn (string $k) => ($this->teksDimuat[$k] ?? '') !== $sekarang[$k]));
    }

    public function simpan(bool $timpa = false): void
    {
        $this->pastikanDapatDiedit();

        try {
            foreach (BagianAi::KOLOM as $field) {
                $this->form->validateOnly($field);
            }
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: 'Periksa kembali isian yang ditandai merah.', type: 'error');

            throw $e;
        }

        $repo = app(KegiatanRepository::class);
        $tersimpan = $repo->temukan($this->kegiatanId) ?? $this->dataKegiatan;
        $teksTersimpan = self::baku($tersimpan);

        $bentrok = array_filter(BagianAi::KOLOM, fn (string $k) => ($this->teksDimuat[$k] ?? '') !== $teksTersimpan[$k]);
        if (! $timpa && $bentrok !== []) {
            $this->bentrok = array_combine(array_values($bentrok), array_map(fn (string $k) => [$this->teksDimuat[$k] ?? '', $teksTersimpan[$k]], array_values($bentrok)));
            $this->dispatch('notify', message: 'Kalimat kegiatan ini baru diubah di tempat lain — pilih muat versi terbaru atau timpa.', type: 'error');

            return;
        }

        $berubah = $this->isianBerubah();
        if ($berubah !== [] || $timpa) {
            $repo->simpan(array_merge($tersimpan, $this->teksSekarang()), $this->kegiatanId);
        }
        $this->muatDari($repo->temukan($this->kegiatanId) ?? $tersimpan);

        $this->dispatch('notify', ...($berubah !== [] || $timpa
            ? ['message' => 'Kalimat KAK tersimpan.', 'type' => 'success']
            : ['message' => 'Tidak ada perubahan kalimat untuk disimpan.', 'type' => 'info']));
    }

    /** Buang suntingan yang belum disimpan. */
    public function batalkanPerubahan(): void
    {
        $this->muatDari(app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? $this->dataKegiatan);
        $this->dispatch('notify', message: 'Perubahan kalimat dibatalkan.', type: 'info');
    }

    /** Saat bentrok: ambil kalimat yang disimpan pihak lain (suntingan di layar ini dibuang). */
    public function muatVersiTerbaru(): void
    {
        $this->muatDari(app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? $this->dataKegiatan);
        $this->dispatch('notify', message: 'Kalimat terbaru dimuat.', type: 'info');
    }

    /** Data kegiatan dengan teks yang sedang disunting (bahan AI & pratinjau mengikuti layar). */
    public function kegiatanSekarang(): array
    {
        return array_merge($this->dataKegiatan, $this->teksSekarang());
    }

    #[Computed]
    public function jadwal(): JadwalKegiatan
    {
        return app(MatrikJadwalRepository::class)->jadwal($this->kegiatanId);
    }

    /** Susunan KAK dari teks di layar untuk pratinjau bagian (cover & nomor bagian BagianAi::BAGIAN). */
    public function susunan(): SusunanKak
    {
        $repo = app(MatrikJadwalRepository::class);

        return SusunanKak::dari($this->kegiatanSekarang(), null, $repo->matrik($this->kegiatanId), $this->jadwal);
    }

    /**
     * Ringkasan panel samping: kelengkapan bagian, aspek konteks, penyedia AI.
     *
     * @return array{terisi: array<string, bool>, aspek: array<string, bool>, konteks: bool, penyedia: string|null, batas: int}
     */
    public function ringkasan(): array
    {
        $kegiatan = $this->kegiatanSekarang();
        $konteks = trim((string) ($kegiatan['konteks'] ?? ''));

        return [
            'terisi' => array_map(fn (string $b) => BagianAi::terisi($b, $kegiatan, $this->jadwal), array_combine(array_keys(BagianAi::BAGIAN), array_keys(BagianAi::BAGIAN))),
            'aspek' => KonteksAi::aspekTerisi($konteks),
            'konteks' => $konteks !== '',
            'penyedia' => StubPengaturan::pengaturanAi()['provider_aktif'],
            'batas' => StubPengaturan::pengaturanAi()['batas_per_user_per_hari'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private static function baku(array $data): array
    {
        return array_combine(BagianAi::KOLOM, array_map(fn (string $k) => trim((string) ($data[$k] ?? '')), BagianAi::KOLOM));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function muatDari(array $data): void
    {
        $this->dataKegiatan = $data;
        $this->form->isiDari($data);
        $this->teksDimuat = self::baku($data);
        $this->adaPerubahan = false;
        $this->bentrok = null;
        $this->resetValidation();
        unset($this->jadwal);
    }

    private function pastikanDapatDiedit(): void
    {
        if (! $this->dapatDiedit()) {
            abort(403, 'Kegiatan berstatus '.$this->status()->label().' sehingga kalimatnya terkunci.');
        }
    }

    public function render()
    {
        return view('livewire.kegiatan.kalimat-ai', ['tahunAktif' => StubData::tahunAktif()['tahun']])
            ->title('Kalimat KAK · '.($this->dataKegiatan['nama_kegiatan'] ?? 'Kegiatan'));
    }
}
