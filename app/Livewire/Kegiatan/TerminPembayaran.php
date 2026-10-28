<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Enums\StatusKegiatan;
use App\Support\Format;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;
use App\Support\Rab\MekanismePembayaran;
use App\Support\Rab\SimpanTermin;
use App\Support\Rab\ValidasiTermin;
use App\Support\Stub\StubData;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Halaman Termin pembayaran satu RAB (per sumber dana): sekaligus atau bertahap, dengan termin bertanggal yang
 * jumlahnya harus sama dengan total RAB. Termin disunting di layar lalu disimpan sekaligus.
 */
class TerminPembayaran extends Component
{
    #[Locked]
    public int $kegiatanId;

    #[Locked]
    public string $sumber;

    /** @var list<array{key: string, tanggal: string, nominal: string}> Termin yang sedang disunting (nominal berformat "7.680.000"). */
    public array $baris = [];

    public bool $adaPerubahan = false;

    /** @var array{index: int, baris: array{key: string, tanggal: string, nominal: string}}|null Termin terakhir dihapus (untuk "Urungkan"). */
    #[Locked]
    public ?array $terminTerhapus = null;

    /** @var list<array{key: string, tanggal: string, nominal: string}>|null Termin sebelum beralih ke Sekaligus (untuk "Urungkan"). */
    #[Locked]
    public ?array $terminSebelumSekaligus = null;

    public function mount(int $kegiatanId, string $sumber): void
    {
        $this->kegiatanId = $kegiatanId;
        $this->sumber = $sumber;
        $this->muatTersimpan();
    }

    #[Computed]
    public function anggaran(): AnggaranSumber
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? abort(404, 'Kegiatan tidak ditemukan.');

        return AnggaranKegiatan::perSumber($kegiatan)[$this->sumber] ?? abort(404, 'Sumber dana tidak dipakai kegiatan ini.');
    }

    /** Mekanisme dari isian yang sedang disunting (belum tentu tersimpan) — untuk status & pratinjau dokumen. */
    #[Computed]
    public function mekanisme(): MekanismePembayaran
    {
        return new MekanismePembayaran($this->anggaran->total(), $this->terminDariBaris());
    }

    /**
     * Mekanisme pembayaran semua sumber dana kegiatan untuk grid jadwal; sumber yang dibuka memakai isian yang sedang disunting.
     *
     * @return array<string, MekanismePembayaran>
     */
    #[Computed]
    public function jadwal(): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? [];
        $jadwal = [];

        foreach (AnggaranKegiatan::untuk($kegiatan) as $anggaran) {
            $jadwal[$anggaran->sumber] = $anggaran->sumber === $this->sumber ? $this->mekanisme : MekanismePembayaran::dari($anggaran);
        }

        return $jadwal;
    }

    #[Computed]
    public function status(): StatusKegiatan
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($this->kegiatanId);

        return StatusKegiatan::tryFrom($kegiatan['status'] ?? '') ?? StatusKegiatan::Draf;
    }

    public function dapatDiedit(): bool
    {
        return $this->status->dapatDiedit();
    }

    public function updated(string $properti): void
    {
        if (! str_starts_with($properti, 'baris.')) {
            return;
        }

        $this->pastikanDapatDiedit();
        $this->tandaiBerubah();

        if (str_ends_with($properti, '.nominal')) {
            $index = (int) explode('.', $properti)[1];
            $nilai = Format::parseRupiah($this->baris[$index]['nominal'] ?? '');
            $this->baris[$index]['nominal'] = $nilai === null ? '' : Format::angka($nilai);
        }

        unset($this->mekanisme, $this->jadwal);
    }

    /** Termin baru diisi sisa yang belum tertutup termin lain (bila ada). */
    public function tambahTermin(): void
    {
        $this->pastikanDapatDiedit();

        if (count($this->baris) >= MekanismePembayaran::MAKS_TERMIN) {
            $this->dispatch('notify', message: 'Termin paling banyak '.MekanismePembayaran::MAKS_TERMIN.'.', type: 'error');

            return;
        }

        $sisa = $this->mekanisme->selisih();
        $this->baris[] = ['key' => (string) Str::uuid(), 'tanggal' => '', 'nominal' => $sisa > 0 ? Format::angka($sisa) : ''];
        $this->berubah();
    }

    public function hapusTermin(string $key): void
    {
        $this->pastikanDapatDiedit();
        $index = array_search($key, array_column($this->baris, 'key'), true);

        if ($index === false) {
            return;
        }

        $this->terminTerhapus = ['index' => $index, 'baris' => $this->baris[$index]];
        array_splice($this->baris, $index, 1);
        $this->resetValidation();
        $this->berubah(simpanUrungkan: true);
    }

    public function urungkanHapus(): void
    {
        $this->pastikanDapatDiedit();

        if ($this->terminTerhapus === null) {
            return;
        }

        array_splice($this->baris, min($this->terminTerhapus['index'], count($this->baris)), 0, [$this->terminTerhapus['baris']]);
        $this->berubah();
    }

    /** Urutkan termin menurut tanggal (yang belum bertanggal di akhir); nomor termin ikut urutan baru. */
    public function urutkanTanggal(): void
    {
        $this->pastikanDapatDiedit();
        $baris = $this->baris;
        usort($baris, fn ($a, $b) => [$a['tanggal'] === '', $a['tanggal']] <=> [$b['tanggal'] === '', $b['tanggal']]);
        $this->baris = $baris;
        $this->resetValidation();
        $this->berubah();
    }

    /** True bila ada tanggal yang lebih awal dari termin sebelumnya (tombol "Urutkan menurut tanggal" ditampilkan). */
    public function urutanTanggalAcak(): bool
    {
        $tanggal = array_values(array_filter(array_column($this->baris, 'tanggal')));
        $urut = $tanggal;
        sort($urut);

        return $tanggal !== $urut;
    }

    /** Pembayaran sekaligus: semua termin dilepas (tersimpan setelah "Simpan"); bisa diurungkan sebelum disimpan. */
    public function jadikanSekaligus(): void
    {
        $this->pastikanDapatDiedit();
        $sebelum = $this->baris;
        $this->baris = [];
        $this->resetValidation();
        $this->berubah();
        $this->terminSebelumSekaligus = $sebelum === [] ? null : $sebelum;
    }

    public function urungkanSekaligus(): void
    {
        $this->pastikanDapatDiedit();

        if ($this->terminSebelumSekaligus === null) {
            return;
        }

        $this->baris = $this->terminSebelumSekaligus;
        $this->berubah();
    }

    /** Bagi total RAB sama rata ke $jumlah termin (minimal 2); sisa pembulatan masuk termin terakhir. */
    public function bagiRata(?int $jumlah = null): void
    {
        $this->pastikanDapatDiedit();
        $jumlah = max(2, min(MekanismePembayaran::MAKS_TERMIN, $jumlah ?? count($this->baris)));
        $total = $this->anggaran->total();
        $bagian = intdiv($total, $jumlah);

        $baru = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $baru[] = [
                'key' => $this->baris[$i]['key'] ?? (string) Str::uuid(),
                'tanggal' => $this->baris[$i]['tanggal'] ?? '',
                'nominal' => Format::angka($i === $jumlah - 1 ? $total - $bagian * ($jumlah - 1) : $bagian),
            ];
        }

        $this->baris = $baru;
        $this->berubah();
    }

    /** Tutup selisih dengan menyesuaikan termin terakhir agar jumlahnya pas dengan total RAB. */
    public function isiSisa(): void
    {
        $this->pastikanDapatDiedit();
        $akhir = array_key_last($this->baris);

        if ($akhir === null) {
            return;
        }

        $nominal = (int) Format::parseRupiah($this->baris[$akhir]['nominal']) + $this->mekanisme->selisih();

        if ($nominal < 1) {
            $this->dispatch('notify', message: 'Termin terakhir akan menjadi nol atau minus; kurangi nominal termin lain lebih dulu.', type: 'error');

            return;
        }

        $this->baris[$akhir]['nominal'] = Format::angka($nominal);
        $this->berubah();
    }

    public function simpan(): void
    {
        $this->pastikanDapatDiedit();

        // Termin opsional: baris yang dibiarkan kosong sama sekali (tanpa tanggal & nominal) diabaikan, bukan galat.
        $terisi = array_values(array_filter($this->baris, fn ($b) => $b['tanggal'] !== '' || Format::parseRupiah($b['nominal']) !== null));
        if (count($terisi) < count($this->baris)) {
            $diabaikan = count($this->baris) - count($terisi);
            $this->baris = $terisi;
            unset($this->mekanisme, $this->jadwal);
            $this->dispatch('notify', message: "{$diabaikan} baris termin kosong diabaikan.", type: 'info');
        }

        $this->validate();

        $mekanisme = $this->mekanisme;

        // PRD aturan 6: jumlah semua termin wajib sama dengan total RAB sumber dana ini.
        if ($pesan = self::pesanTidakPas($mekanisme, $this->sumber)) {
            $this->addError('termin', $pesan);
            $this->dispatch('notify', message: 'Termin belum disimpan: '.$pesan, type: 'error');

            return;
        }

        // Server memeriksa ulang & menyimpan lewat SimpanTermin (status terkunci, aturan baris, jumlah = total RAB).
        $mekanisme = SimpanTermin::simpan($this->kegiatanId, $this->sumber, $this->baris)['mekanisme'];
        $this->adaPerubahan = false;
        $this->terminTerhapus = null;
        $this->terminSebelumSekaligus = null;
        $this->dispatch('termin-berubah', sumber: null);
        $this->dispatch('termin-disimpan', sumber: $this->sumber);

        $this->dispatch('notify', message: $mekanisme->bertermin()
            ? count($mekanisme->termin)." termin RAB {$this->sumber} disimpan; jumlahnya sama dengan total RAB."
            : "RAB {$this->sumber} dibayar sekaligus (tanpa termin).");
    }

    /** Alasan termin belum boleh disimpan/diajukan (SimpanTermin::pesanTidakPas). */
    public static function pesanTidakPas(MekanismePembayaran $mekanisme, string $sumber): ?string
    {
        return SimpanTermin::pesanTidakPas($mekanisme, $sumber);
    }

    public function batalkan(): void
    {
        $this->muatTersimpan();
        $this->resetValidation();
        $this->dispatch('termin-berubah', sumber: null);
    }

    /** Aturan & pesan bersama ValidasiTermin (juga dipakai SimpanTermin di server). */
    protected function rules(): array
    {
        return ValidasiTermin::aturan($this->baris, StubData::tahunAktif()['tahun']);
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return ValidasiTermin::pesan();
    }

    /**
     * @return list<array{tanggal: string|null, nominal: int}>
     */
    private function terminDariBaris(): array
    {
        return array_map(fn ($b) => ['tanggal' => $b['tanggal'] ?: null, 'nominal' => (int) Format::parseRupiah($b['nominal'])], $this->baris);
    }

    private function muatTersimpan(): void
    {
        $this->baris = array_map(fn ($t) => [
            'key' => (string) Str::uuid(),
            'tanggal' => (string) ($t['tanggal'] ?? ''),
            'nominal' => Format::angka((int) $t['nominal']),
        ], app(RabRepository::class)->termin($this->kegiatanId, $this->sumber));
        $this->adaPerubahan = false;
        $this->terminTerhapus = null;
        $this->terminSebelumSekaligus = null;
        unset($this->mekanisme, $this->jadwal);
    }

    private function berubah(bool $simpanUrungkan = false): void
    {
        $this->resetErrorBag('termin');

        if (! $simpanUrungkan) {
            $this->terminTerhapus = null;
            $this->terminSebelumSekaligus = null;
        }

        $this->tandaiBerubah();
        unset($this->mekanisme, $this->jadwal);
    }

    /**
     * Tandai ada perubahan belum disimpan dan kabari halaman RAB, agar pindah sumber/halaman meminta konfirmasi dulu.
     */
    private function tandaiBerubah(): void
    {
        if (! $this->adaPerubahan) {
            $this->dispatch('termin-berubah', sumber: $this->sumber);
        }

        $this->adaPerubahan = true;
    }

    private function pastikanDapatDiedit(): void
    {
        if (! $this->dapatDiedit()) {
            abort(403, 'Kegiatan berstatus '.$this->status->label().' sehingga termin pembayarannya terkunci.');
        }
    }

    public function render()
    {
        return view('livewire.kegiatan.termin-pembayaran');
    }
}
