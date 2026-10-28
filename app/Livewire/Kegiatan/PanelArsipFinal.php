<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\ArsipFinal;
use App\Support\Kegiatan\KelolaArsipFinal;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Panel arsip final di Data Kegiatan (PRD F18): selama Disetujui, PDF bertanda tangan/pindaian diunggah per sumber dana
 * (diperiksa dulu di klien oleh Alpine `unggahArsip`, lalu langsung tersimpan begitu selesai terunggah; bisa diganti atau
 * dihapus — aturan lewat KelolaArsipFinal, sama dengan endpoint `kegiatan.arsip.unggah`/`hapus`); selama Final hanya bisa
 * dilihat. Setiap perubahan men-dispatch `arsip-final-berubah` agar panel status
 * menghitung ulang syarat "Tandai final".
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

    /**
     * Berkas selesai terunggah (sementara): periksa lalu simpan sebagai arsip sumber dana itu (KelolaArsipFinal — aturan
     * berkas sama dengan pemeriksaan klien `galatArsip` agar pesannya sama walau klien dilewati).
     */
    public function updatedBerkas(mixed $nilai, string $sumber): void
    {
        $this->resetErrorBag("berkas.{$sumber}");
        try {
            $hasil = KelolaArsipFinal::unggah($this->kegiatanId, $sumber, $this->berkas[$sumber] ?? null, StubData::currentUser());
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        } catch (ValidationException $e) {
            $this->addError("berkas.{$sumber}", collect($e->errors())->flatten()->first());

            return;
        } finally {
            $this->buangBerkas($sumber);
        }

        $this->selesai($hasil['pesan']);
    }

    public function hapus(string $sumber): void
    {
        $this->konfirmasiHapus = null;
        try {
            $hasil = KelolaArsipFinal::hapus($this->kegiatanId, $sumber, StubData::currentUser());
        } catch (AuthorizationException|ValidationException) {
            return;
        }

        $this->selesai($hasil['pesan'], 'info');
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
