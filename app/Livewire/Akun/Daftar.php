<?php

namespace App\Livewire\Akun;

use App\Support\Akun\PendaftaranAkun;
use App\Support\MasterKode;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * Halaman Daftar akun (PRD §8 "Daftar & Masuk"; `/daftar`, rute `akun.daftar`, layout tamu): formulir permohonan akun
 * — identitas (nama, jabatan, NIP), leading yang ditangani, surel & sandi (syarat per ketukan di klien lewat Alpine
 * `formDaftar`), catatan untuk admin, pernyataan. Kolom diperiksa saat blur (`updated` → `PendaftaranAkun::galat`, kolom
 * terkait ikut bila sudah terisi) dan sekali lagi saat dikirim (`kirim()` → `PendaftaranAkun::ajukan`); hasil terkunci
 * `$terkirim` = ringkasan permohonan yang menunggu persetujuan admin.
 */
#[Layout('layouts.tamu')]
class Daftar extends Component
{
    /** Kolom yang galatnya ikut diperiksa ulang saat kolom kunci berubah (hanya bila sudah terisi). */
    private const TERKAIT = ['nama' => ['sandi'], 'surel' => ['sandi'], 'sandi' => ['sandi_ulang']];

    /** @var array<string, mixed> */
    public array $isian = ['nama' => '', 'jabatan' => '', 'nip' => '', 'leadings' => [], 'surel' => '', 'sandi' => '', 'sandi_ulang' => '', 'catatan' => '', 'setuju' => false];

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $terkirim = null;

    public function updated(string $properti): void
    {
        $kunci = str_starts_with($properti, 'isian.') ? explode('.', $properti)[1] : null;
        if ($kunci === null) {
            return;
        }
        $galat = PendaftaranAkun::galat(PendaftaranAkun::rapikan($this->isian));
        foreach ([$kunci, ...(self::TERKAIT[$kunci] ?? [])] as $k) {
            if ($k !== $kunci && blank($this->isian[$k] ?? null)) {
                continue;
            }
            $this->resetErrorBag("isian.{$k}");
            if (isset($galat[$k])) {
                $this->addError("isian.{$k}", $galat[$k]);
            }
        }
    }

    public function kirim(): void
    {
        $this->resetErrorBag();
        try {
            $this->terkirim = PendaftaranAkun::ajukan($this->isian);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kunci => $pesan) {
                $this->addError("isian.{$kunci}", $pesan[0]);
            }
            $this->dispatch('galat-pendaftaran', kolom: (string) array_key_first($e->errors()));

            return;
        } catch (RuntimeException $e) {
            report($e);
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }
        $this->reset('isian');
    }

    public function render()
    {
        return view('livewire.akun.daftar', [
            'leading' => MasterKode::leadings(),
            'syarat' => PendaftaranAkun::SYARAT_SANDI,
        ])->title('Daftar akun');
    }
}
