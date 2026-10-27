<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\ArsipFinal;
use App\Support\Stub\StubData;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Panel arsip final di Data Kegiatan (PRD F18): selama Disetujui, PDF bertanda tangan/pindaian diunggah per sumber dana
 * (langsung tersimpan begitu selesai terunggah; bisa diganti atau dihapus); selama Final hanya bisa dilihat. Setiap
 * perubahan men-dispatch `arsip-final-berubah` agar panel status menghitung ulang syarat "Tandai final".
 */
class PanelArsipFinal extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $kegiatanId;

    /** Berkas yang baru dipilih, berkunci sumber dana. */
    public array $berkas = [];

    /** Sumber dana yang arsipnya menunggu konfirmasi hapus. */
    public ?string $konfirmasiHapus = null;

    /** @return array<string, mixed> */
    #[Computed]
    public function kegiatan(): array
    {
        return app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? [];
    }

    #[Computed]
    public function status(): StatusKegiatan
    {
        return StatusKegiatan::tryFrom((string) ($this->kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
    }

    /** @return array<string, array<string, mixed>> */
    #[Computed]
    public function arsip(): array
    {
        return app(ArsipFinalRepository::class)->untukKegiatan($this->kegiatanId);
    }

    /** @return list<string> */
    #[Computed]
    public function sumber(): array
    {
        return ArsipFinal::sumberWajib($this->kegiatan);
    }

    /** Berkas selesai terunggah (sementara): periksa lalu simpan sebagai arsip sumber dana itu. */
    public function updatedBerkas(mixed $nilai, string $sumber): void
    {
        $this->resetErrorBag("berkas.{$sumber}");
        if (! in_array($sumber, $this->sumber, true) || ! ArsipFinal::bolehUnggah($this->status)) {
            $this->buangBerkas($sumber);
            $this->dispatch('notify', message: 'Arsip final hanya bisa diunggah selama kegiatan berstatus Disetujui.', type: 'error');

            return;
        }

        try {
            $this->validate(
                ["berkas.{$sumber}" => ['required', 'file', 'mimes:pdf', 'max:'.ArsipFinal::MAKS_KB]],
                [
                    "berkas.{$sumber}.mimes" => 'Arsip final harus berupa berkas PDF.',
                    "berkas.{$sumber}.max" => 'Ukuran arsip maksimal '.(ArsipFinal::MAKS_KB / 1024).' MB — perkecil resolusi pindaian.',
                ],
            );
            if (! ArsipFinal::pdfSah($this->berkas[$sumber])) {
                throw ValidationException::withMessages(["berkas.{$sumber}" => 'Berkas ini bukan PDF yang sah — simpan ulang hasil pindaian sebagai PDF.']);
            }
        } catch (ValidationException $e) {
            $this->buangBerkas($sumber);

            throw $e;
        }

        $ganti = isset($this->arsip[$sumber]);
        app(ArsipFinalRepository::class)->simpan($this->kegiatanId, $sumber, $this->berkas[$sumber], StubData::currentUser());
        $this->buangBerkas($sumber);
        $this->selesai(($ganti ? 'Arsip final RAB '.$sumber.' diganti.' : 'Arsip final RAB '.$sumber.' tersimpan.'));
    }

    public function hapus(string $sumber): void
    {
        $this->konfirmasiHapus = null;
        if (! isset($this->arsip[$sumber]) || ! ArsipFinal::bolehUnggah($this->status)) {
            return;
        }

        app(ArsipFinalRepository::class)->hapus($this->kegiatanId, $sumber);
        $this->selesai('Arsip final RAB '.$sumber.' dihapus.', 'info');
    }

    private function selesai(string $pesan, string $jenis = 'success'): void
    {
        unset($this->arsip);
        $this->dispatch('arsip-final-berubah');
        $this->dispatch('notify', message: $pesan, type: $jenis);
    }

    private function buangBerkas(string $sumber): void
    {
        if (isset($this->berkas[$sumber]) && is_object($this->berkas[$sumber])) {
            $this->berkas[$sumber]->delete();
        }
        unset($this->berkas[$sumber]);
    }

    public function render()
    {
        return view('livewire.kegiatan.panel-arsip-final');
    }
}
