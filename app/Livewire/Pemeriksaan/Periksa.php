<?php

namespace App\Livewire\Pemeriksaan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanRab;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Pemeriksaan\AntreanPemeriksaan;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Detail dokumen satu kegiatan untuk pemeriksaan admin (PRD §16, F17): pratinjau KAK/TOR atau RAB per sumber dana
 * (`?dokumen=kak|rab`, `?sumber=`), kelengkapan tiap dokumen, ringkasan anggaran & termin, riwayat status, dan posisi di
 * antrean Perlu diperiksa. Catatan & keputusan (setujui/kembalikan) menyusul. Khusus admin.
 */
#[Layout('layouts.app')]
class Periksa extends Component
{
    public const DOKUMEN = ['kak' => 'KAK/TOR', 'rab' => 'RAB'];

    #[Locked]
    public int $kegiatanId;

    #[Url(except: 'kak')]
    public string $dokumen = 'kak';

    /** '' = sumber dana pertama kegiatan. */
    #[Url(except: '')]
    public string $sumber = '';

    public function mount(KegiatanRepository $repo, int $kegiatan): void
    {
        $repo->temukan($kegiatan) ?? abort(404, 'Kegiatan tidak ditemukan.');
        $this->kegiatanId = $kegiatan;
        $this->rapikanPilihan();
    }

    public function updated(): void
    {
        $this->rapikanPilihan();
    }

    /** Tampilkan satu dokumen (kak/rab) sumber dana tertentu — dari panel kelengkapan. */
    public function tampilkan(string $dokumen, string $sumber): void
    {
        $this->dokumen = $dokumen;
        $this->sumber = $sumber;
        $this->rapikanPilihan();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function dataKegiatan(): array
    {
        return app(KegiatanRepository::class)->temukan($this->kegiatanId) ?? abort(404, 'Kegiatan tidak ditemukan.');
    }

    /** @return array<string, mixed> Baris antrean kegiatan ini (pembuat, waktu pengajuan, anggaran, jumlah kurang). */
    #[Computed]
    public function ringkasan(): array
    {
        return AntreanPemeriksaan::baris([$this->dataKegiatan])[0];
    }

    #[Computed]
    public function kak(): SusunanKak
    {
        return SusunanKak::dari($this->dataKegiatan, $this->sumber ?: null);
    }

    #[Computed]
    public function rab(): SusunanRab
    {
        return SusunanRab::dari($this->dataKegiatan, $this->sumber ?: null);
    }

    /**
     * Kelengkapan setiap dokumen (sumber × KAK/TOR, RAB) untuk panel samping sekaligus navigasi dokumen.
     *
     * @return list<array{sumber: string, kak: int, rab: int}>
     */
    #[Computed]
    public function kelengkapan(): array
    {
        return array_map(fn (string $sumber) => [
            'sumber' => $sumber,
            'kak' => count(SusunanKak::dari($this->dataKegiatan, $sumber)->kurang),
            'rab' => count(SusunanRab::dari($this->dataKegiatan, $sumber)->kurang),
        ], $this->kak->daftarSumber);
    }

    /** @return list<AnggaranSumber> */
    #[Computed]
    public function anggaran(): array
    {
        return AnggaranKegiatan::untuk($this->dataKegiatan);
    }

    /**
     * Riwayat status terbaru lebih dulu.
     *
     * @return list<array{aksi: string, oleh: string, peran: string|null, pada: string, catatan: string|null}>
     */
    public function riwayat(): array
    {
        return array_reverse(array_map(fn (array $r) => [
            'aksi' => (string) ($r['aksi'] ?? ''),
            'oleh' => (string) ($r['oleh'] ?? ''),
            'peran' => $r['peran'] ?? null,
            'pada' => (string) ($r['pada'] ?? ''),
            'catatan' => filled($r['catatan'] ?? null) ? (string) $r['catatan'] : null,
        ], array_values((array) ($this->dataKegiatan['riwayat_status'] ?? []))));
    }

    /**
     * Posisi kegiatan ini di antrean Perlu diperiksa tahun aktif beserta tetangganya; null bila tidak sedang diperiksa.
     *
     * @return array{posisi: int, jumlah: int, sebelum: int|null, sesudah: int|null}|null
     */
    public function antrean(): ?array
    {
        if ($this->ringkasan['status'] !== StatusKegiatan::Diperiksa) {
            return null;
        }

        $tahun = StubData::tahunAktif()['tahun'];
        $kegiatan = array_values(array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => ($k['status'] ?? null) === 'diperiksa' && TahunKegiatan::dari($k) === $tahun));
        $id = array_column(AntreanPemeriksaan::untukTab(AntreanPemeriksaan::baris($kegiatan), 'diperiksa'), 'id');
        $i = array_search($this->kegiatanId, $id, true);

        return $i === false ? null : ['posisi' => $i + 1, 'jumlah' => count($id), 'sebelum' => $id[$i - 1] ?? null, 'sesudah' => $id[$i + 1] ?? null];
    }

    private function rapikanPilihan(): void
    {
        if (! array_key_exists($this->dokumen, self::DOKUMEN)) {
            $this->dokumen = 'kak';
        }
        if ($this->sumber !== '' && ! in_array($this->sumber, $this->kak->daftarSumber, true)) {
            $this->sumber = '';
        }
        unset($this->kak, $this->rab);
    }

    public function render()
    {
        return view('livewire.pemeriksaan.periksa')->title('Periksa · '.$this->ringkasan['nama']);
    }
}
