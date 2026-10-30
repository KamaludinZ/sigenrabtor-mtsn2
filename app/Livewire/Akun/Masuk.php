<?php

namespace App\Livewire\Akun;

use App\Contracts\PenggunaRepository;
use App\Support\Akun\Autentikasi;
use App\Support\Akun\DataAkun;
use App\Support\Akun\PeriksaMasuk;
use App\Support\Akun\SesiAkun;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Halaman Masuk (PRD §8 "Daftar & Masuk"; `/masuk`, rute `akun.masuk`, layout tamu): surel + sandi + ingat saya; aturan
 * hanya lewat PeriksaMasuk. Surel diperiksa formatnya saat blur (`updatedSurel`), `?surel=` mengisinya saat dibuka
 * (mis. dari halaman Daftar). Surel & sandi diperiksa `Autentikasi::periksa` (akun tidak terdaftar / permohonan menunggu /
 * nonaktif ditolak, sandi dicocokkan dengan hash tersimpan; akun tanpa sandi hanya selama mode demo); berhasil →
 * `Autentikasi::masuk` (SesiAkun, catat masuk, cookie "ingat saya" bila `$ingat`). Percobaan gagal dibatasi per surel × IP
 * (`$peringatan` sisa percobaan, jeda → event `masuk-dijeda` {detik}). `masukSebagai($id)` = pintasan akun contoh
 * (`DataAkun::PERSONA`) hanya selama `Autentikasi::modeDemo()`. Selama sudah masuk, halaman menampilkan kartu `sudah-masuk` (lanjut atau keluar; bila
 * wajib masuk, middleware HanyaTamu sudah mengalihkannya). `?sesi=berakhir` → pemberitahuan `sesi-berakhir`, `?kembali=`
 * jalur aman (`PeriksaMasuk::jalurKembali`) menjadi tujuan sesudah masuk.
 */
#[Layout('layouts.tamu')]
class Masuk extends Component
{
    public string $surel = '';

    public string $sandi = '';

    public bool $ingat = false;

    #[Locked]
    public ?string $peringatan = null;

    /** Sesi berakhir saat aksi berjalan (`?sesi=berakhir`, dari resources/js/sesi.js). */
    #[Locked]
    public bool $sesiBerakhir = false;

    public function mount(): void
    {
        $surel = request()->query('surel');
        if (is_string($surel)) {
            $this->surel = mb_substr(PeriksaMasuk::rapikanSurel($surel), 0, 255);
        }
        $this->sesiBerakhir = request()->query('sesi') === 'berakhir';
        // `?kembali=` = halaman asal (hanya jalur di aplikasi ini) → tujuan sesudah masuk.
        if (($kembali = PeriksaMasuk::jalurKembali(request()->query('kembali'))) !== null) {
            session()->put('url.intended', url($kembali));
        }
    }

    public function updatedSurel(): void
    {
        $this->resetErrorBag('surel');
        $surel = PeriksaMasuk::rapikanSurel($this->surel);
        if ($surel !== '' && ($galat = PeriksaMasuk::galatSurel($surel)) !== null) {
            $this->addError('surel', $galat);
        }
    }

    public function masuk()
    {
        $this->resetErrorBag();
        $this->peringatan = null;
        $hasil = Autentikasi::periksa($this->surel, $this->sandi, request()->ip());
        if ($hasil['akun'] === null) {
            foreach ($hasil['galat'] as $kolom => $pesan) {
                $this->addError($kolom, $pesan);
            }
            $this->peringatan = $hasil['peringatan'];
            // Percobaan yang sudah diperiksa (bukan sekadar format salah): sandi dikosongkan.
            if (PeriksaMasuk::galatSurel(PeriksaMasuk::rapikanSurel($this->surel)) === null && $this->sandi !== '') {
                $this->reset('sandi');
            }
            if ($hasil['tunggu'] > 0) {
                $this->dispatch('masuk-dijeda', detik: $hasil['tunggu']);
            }

            return null;
        }

        return $this->masukkan($hasil['akun'], $this->ingat);
    }

    public function masukSebagai(int $id)
    {
        $akun = Autentikasi::modeDemo() && isset(DataAkun::PERSONA[$id]) ? app(PenggunaRepository::class)->temukan($id) : null;

        return $akun === null ? null : $this->masukkan(['role' => DataAkun::PERSONA[$id]] + $akun, false);
    }

    /** @param  array<string, mixed>  $akun */
    private function masukkan(array $akun, bool $ingat)
    {
        Autentikasi::masuk($akun, $ingat, request());
        $nama = (string) $akun['name'];
        session()->flash('notify', ['message' => 'Selamat datang kembali, '.$nama.(str_ends_with($nama, '.') ? '' : '.'), 'type' => 'success']);

        return $this->redirectIntended(route('dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.akun.masuk', [
            'persona' => Autentikasi::modeDemo() ? DataAkun::persona() : [],
            'sesi' => SesiAkun::sudahMasuk() ? SesiAkun::pengguna() : null,
        ])->title('Masuk');
    }
}
