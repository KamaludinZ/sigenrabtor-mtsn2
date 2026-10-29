<?php

namespace App\Livewire\Akun;

use App\Contracts\PenggunaRepository;
use App\Support\Akun\DataAkun;
use App\Support\Akun\PeriksaMasuk;
use App\Support\Akun\SesiAkun;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * Halaman Masuk (PRD §8 "Daftar & Masuk"; `/masuk`, rute `akun.masuk`, layout tamu): surel + sandi + ingat saya; aturan
 * hanya lewat PeriksaMasuk. Surel diperiksa formatnya saat blur (`updatedSurel`), `?surel=` mengisinya saat dibuka
 * (mis. dari halaman Daftar). Selama autentikasi Laravel belum dipasang (mode demo), surel dicocokkan dengan akun
 * PenggunaRepository (`PeriksaMasuk::periksa`: tidak terdaftar / permohonan menunggu / nonaktif ditolak); akun aktif masuk
 * lewat state tiruan `SesiAkun::masuk` (waktu masuk dicatat `catatMasuk`) — sandi belum diperiksa. Percobaan gagal dibatasi
 * per surel × IP (`$peringatan` sisa percobaan, jeda → event `masuk-dijeda` {detik}). `masukSebagai($id)` = pintasan akun
 * contoh (`DataAkun::PERSONA`). Selama sudah masuk, halaman menampilkan kartu `sudah-masuk` (lanjut atau keluar; bila
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
        $surel = PeriksaMasuk::rapikanSurel($this->surel);
        $galat = array_filter([
            'surel' => PeriksaMasuk::galatSurel($surel),
            'sandi' => match (true) {
                $this->sandi === '' => 'Isi sandi akun Anda.',
                mb_strlen($this->sandi) > 255 => 'Sandi terlalu panjang.',
                default => null,
            },
        ]);
        if ($galat !== []) {
            foreach ($galat as $kolom => $pesan) {
                $this->addError($kolom, $pesan);
            }

            return null;
        }

        $ip = request()->ip();
        if (($detik = PeriksaMasuk::tunggu($surel, $ip)) > 0) {
            return $this->tolak(PeriksaMasuk::pesanTunggu($detik), $detik);
        }
        $hasil = PeriksaMasuk::periksa($surel);
        if ($hasil['galat'] !== null) {
            $sisa = PeriksaMasuk::gagal($surel, $ip);
            $this->peringatan = PeriksaMasuk::pesanSisa($sisa);

            return $this->tolak($hasil['galat'], $sisa === 0 ? PeriksaMasuk::tunggu($surel, $ip) : 0);
        }
        PeriksaMasuk::berhasil($surel, $ip);

        return $this->masukkan($hasil['akun']);
    }

    public function masukSebagai(int $id)
    {
        $akun = isset(DataAkun::PERSONA[$id]) ? app(PenggunaRepository::class)->temukan($id) : null;

        return $akun === null ? null : $this->masukkan(['role' => DataAkun::PERSONA[$id]] + $akun);
    }

    private function tolak(string $pesan, int $jeda): null
    {
        $this->addError('surel', $pesan);
        $this->reset('sandi');
        if ($jeda > 0) {
            $this->dispatch('masuk-dijeda', detik: $jeda);
        }

        return null;
    }

    /** @param  array<string, mixed>  $akun */
    private function masukkan(array $akun)
    {
        SesiAkun::masuk(['id' => (int) $akun['id'], 'role' => (string) $akun['role']]);
        try {
            app(PenggunaRepository::class)->catatMasuk((int) $akun['id'], request()->ip(), request()->userAgent());
        } catch (RuntimeException $e) {
            report($e);
        }
        $nama = (string) $akun['name'];
        session()->flash('notify', ['message' => 'Selamat datang kembali, '.$nama.(str_ends_with($nama, '.') ? '' : '.'), 'type' => 'success']);

        return $this->redirectIntended(route('dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.akun.masuk', [
            'persona' => DataAkun::persona(),
            'sesi' => SesiAkun::sudahMasuk() ? SesiAkun::pengguna() : null,
        ])->title('Masuk');
    }
}
