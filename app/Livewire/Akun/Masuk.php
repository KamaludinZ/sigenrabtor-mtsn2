<?php

namespace App\Livewire\Akun;

use App\Contracts\PenggunaRepository;
use App\Support\Akun\DataAkun;
use Livewire\Attributes\Layout;
use Livewire\Component;
use RuntimeException;

/**
 * Halaman Masuk (PRD §8 "Daftar & Masuk"; `/masuk`, rute `akun.masuk`, layout tamu): surel + sandi + ingat saya. Selama
 * autentikasi Laravel belum dipasang (mode demo), surel dicocokkan dengan akun PenggunaRepository: tidak terdaftar /
 * nonaktif / bukan akun contoh (DataAkun::PERSONA) ditolak dengan pesannya; akun contoh masuk sebagai peran demonya
 * (session `stub_role`, waktu masuk dicatat `catatMasuk`) — sandi belum diperiksa. `masukSebagai($id)` = pintasan akun
 * contoh. Akun baru hanya dibuat admin (tidak ada pendaftaran mandiri).
 */
#[Layout('layouts.tamu')]
class Masuk extends Component
{
    public string $surel = '';

    public string $sandi = '';

    public bool $ingat = false;

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return ['surel' => ['required', 'email', 'max:255'], 'sandi' => ['required', 'string', 'max:255']];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'surel.required' => 'Isi surel akun Anda.',
            'surel.email' => 'Surel tidak sah, mis. nama@madrasah.sch.id.',
            'sandi.required' => 'Isi sandi akun Anda.',
        ];
    }

    public function masuk()
    {
        $this->validate();
        $akun = DataAkun::cariSurel($this->surel);
        $peran = $akun !== null ? (DataAkun::PERSONA[(int) $akun['id']] ?? null) : null;
        $galat = match (true) {
            $akun === null => 'Surel ini belum terdaftar — akun dibuat oleh admin madrasah.',
            ! ($akun['is_active'] ?? true) => 'Akun ini nonaktif — hubungi admin untuk mengaktifkannya kembali.',
            $peran === null => 'Mode demo: hanya akun contoh di bawah yang bisa masuk sampai autentikasi diaktifkan.',
            default => null,
        };
        if ($galat !== null) {
            $this->addError('surel', $galat);
            $this->reset('sandi');

            return null;
        }

        return $this->masukkan((int) $akun['id'], $peran);
    }

    public function masukSebagai(int $id)
    {
        $peran = DataAkun::PERSONA[$id] ?? null;

        return $peran === null ? null : $this->masukkan($id, $peran);
    }

    private function masukkan(int $id, string $peran)
    {
        session(['stub_role' => $peran]);
        session()->regenerate();
        try {
            app(PenggunaRepository::class)->catatMasuk($id, request()->ip(), request()->userAgent());
        } catch (RuntimeException $e) {
            report($e);
        }
        session()->flash('notify', ['message' => 'Selamat datang kembali.', 'type' => 'success']);

        return $this->redirectIntended(route('dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.akun.masuk', ['persona' => DataAkun::persona()])->title('Masuk');
    }
}
