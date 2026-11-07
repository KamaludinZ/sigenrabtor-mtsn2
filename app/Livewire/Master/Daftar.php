<?php

namespace App\Livewire\Master;

use App\Contracts\KegiatanRepository;
use App\Contracts\MasterKodeRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Master\IkskPerSk;
use App\Support\Master\JenisKode;
use App\Support\Master\KelolaRencanaAksi;
use App\Support\Master\RencanaAksi;
use App\Support\Master\SalinMasterKode;
use App\Support\MasterKode;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Master Kode Tahunan (PRD Fase 6 §14, F7): kode SK, IKSK, RKTM, EDM, 5 Budaya Mutu, dan Rencana Aksi per tahun
 * anggaran — tab per jenis dengan jumlah baris & pemakaian oleh kegiatan tahun itu. Tahun di `?ta=` ('' = tahun aktif),
 * jenis di `?jenis=`, kata kunci di `?cari=`, saringan tag rencana aksi di `?tag=`. Unggah Excel lewat panel anak
 * Master\UnggahMaster; template Excel lewat rute `master.template` (kosong atau berisi master tahun itu). Tag rencana aksi
 * disunting di tempat (KelolaRencanaAksi).
 */
#[Layout('layouts.app')]
class Daftar extends Component
{
    #[Url(as: 'ta', except: '')]
    public string $tahun = '';

    #[Url(except: 'sk')]
    public string $jenis = 'sk';

    /** Panel unggah master terbuka (tahun yang dilihat, bukan tahun terkunci). */
    public bool $unggahTerbuka = false;

    /** Kata kunci (kode/uraian) tab yang dilihat. */
    #[Url(except: '')]
    public string $cari = '';

    /** Saringan tab Rencana Aksi: kunci SARING_TAG. */
    #[Url(as: 'tag', except: 'semua')]
    public string $saringTag = 'semua';

    /** Saringan tag rencana aksi → label. */
    public const SARING_TAG = ['semua' => 'Semua', 'tanpa_leading' => 'Tanpa leading', 'tanpa_rktm' => 'Tanpa RKTM', 'asing' => 'Kode tak ada di master'];

    /** Kode rencana aksi yang tag-nya sedang disunting. */
    public ?string $tagTerbuka = null;

    /** @var array{kode_sk: string, kode_iksk: string, kode_rktm: string, kode_leading: string, edm_otomatis: bool, kode_edm: list<string>, budaya_mutu: list<int|string>} */
    public array $isianTag = [];

    /** Versi rencana aksi tahun itu saat formulir tag dibuka (KelolaRencanaAksi::versi). */
    #[Locked]
    public ?string $versiTag = null;

    public function mount(): void
    {
        $this->rapikan();
    }

    public function updatedTahun(): void
    {
        $this->rapikan();
        $this->unggahTerbuka = false;
        $this->reset('tagTerbuka', 'isianTag', 'versiTag');
    }

    public function bukaUnggah(): void
    {
        $this->unggahTerbuka = ! $this->tahunTerkunci();
    }

    #[On('unggah-master-ditutup')]
    public function tutupUnggah(bool $dibatalkan = false): void
    {
        $this->unggahTerbuka = false;
        if ($dibatalkan) {
            $this->dispatch('notify', message: 'Unggahan dibatalkan — master kode TA '.$this->tahunDilihat().' tidak berubah.', type: 'info');
        }
    }

    public function updatedJenis(): void
    {
        $this->rapikan();
        $this->reset('cari', 'saringTag', 'tagTerbuka', 'isianTag', 'versiTag');
    }

    public function updatedSaringTag(): void
    {
        if (! array_key_exists($this->saringTag, self::SARING_TAG)) {
            $this->saringTag = 'semua';
        }
    }

    public function updatedCari(): void
    {
        $this->cari = mb_substr($this->cari, 0, 100);
    }

    /**
     * Baris tab yang dilihat setelah kata kunci (dan saringan tag untuk Rencana Aksi).
     *
     * @return list<array<string, mixed>>
     */
    public function barisTampil(): array
    {
        $baris = $this->jenis === 'rencana_aksi' ? $this->rencanaAksi : $this->baris;
        if ($this->jenis === 'rencana_aksi' && $this->saringTag !== 'semua') {
            $baris = array_values(array_filter($baris, fn (array $ra) => match ($this->saringTag) {
                'tanpa_leading' => $ra['tag']['kode_leading'] === null,
                'tanpa_rktm' => $ra['tag']['kode_rktm'] === null,
                'asing' => $ra['asing'] !== [],
                default => true,
            }));
        }
        $kata = array_filter(preg_split('/\s+/', mb_strtolower(trim($this->cari))) ?: []);
        if ($kata !== []) {
            $baris = array_values(array_filter($baris, function (array $b) use ($kata) {
                $teks = mb_strtolower((string) json_encode(array_values(array_diff_key($b, ['tag' => true, 'asing' => true])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                return collect($kata)->every(fn (string $k) => str_contains($teks, $k));
            }));
        }

        return $baris;
    }

    /**
     * Rencana aksi tahun yang dilihat beserta tag efektifnya (RencanaAksi::baris).
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function rencanaAksi(): array
    {
        return RencanaAksi::baris($this->tahunDilihat());
    }

    /**
     * Jumlah rencana aksi per saringan tag.
     *
     * @return array<string, int>
     */
    public function jumlahSaringTag(): array
    {
        $ra = $this->rencanaAksi;

        return [
            'semua' => count($ra),
            'tanpa_leading' => count(array_filter($ra, fn (array $r) => $r['tag']['kode_leading'] === null)),
            'tanpa_rktm' => count(array_filter($ra, fn (array $r) => $r['tag']['kode_rktm'] === null)),
            'asing' => count(array_filter($ra, fn (array $r) => $r['asing'] !== [])),
        ];
    }

    /** Buka formulir tag rencana aksi $kode (tahun terbuka saja). */
    public function ubahTag(string $kode): void
    {
        $ra = collect($this->rencanaAksi)->firstWhere('kode', $kode);
        if ($ra === null || $this->tahunTerkunci()) {
            return;
        }
        $this->resetErrorBag();
        $this->tagTerbuka = $kode;
        $this->versiTag = KelolaRencanaAksi::versi($this->tahunDilihat());
        $this->isianTag = [
            'kode_sk' => (string) ($ra['kode_sk'] ?? ''), 'kode_iksk' => (string) ($ra['kode_iksk'] ?? ''), 'kode_rktm' => (string) ($ra['kode_rktm'] ?? ''),
            'kode_leading' => (string) ($ra['kode_leading'] ?? ''), 'edm_otomatis' => ! is_array($ra['kode_edm']),
            'kode_edm' => $ra['tag']['kode_edm'], 'budaya_mutu' => array_map('strval', $ra['tag']['budaya_mutu']),
        ];
    }

    public function batalTag(): void
    {
        $this->reset('tagTerbuka', 'isianTag', 'versiTag');
        $this->resetErrorBag();
    }

    /** SK diganti: IKSK tetap bila masih indikatornya, kosong bila tidak (indikator tunggal dipilih otomatis). */
    public function updatedIsianTag(mixed $nilai, ?string $kunci = null): void
    {
        if ($kunci === 'kode_sk') {
            $this->isianTag['kode_iksk'] = (string) $this->isianTag['kode_sk'] === ''
                ? ''
                : IkskPerSk::sesudahGantiSk((string) $this->isianTag['kode_sk'], (string) ($this->isianTag['kode_iksk'] ?? ''), $this->tahunDilihat())['iksk'];
        }
        if (in_array($kunci, ['edm_otomatis', 'kode_rktm'], true) && ($this->isianTag['edm_otomatis'] ?? false)) {
            $this->isianTag['kode_edm'] = RencanaAksi::edmUntukRktm(($this->isianTag['kode_rktm'] ?? '') ?: null, $this->tahunDilihat());
        }
    }

    public function simpanTag(): void
    {
        if ($this->tagTerbuka === null) {
            return;
        }
        $this->resetErrorBag();
        $tahun = $this->tahunDilihat();
        try {
            $hasil = KelolaRencanaAksi::ubahTag($tahun, $this->tagTerbuka, $this->isianTag, StubData::currentUser(), $this->versiTag);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kunci => $pesan) {
                $this->addError("isianTag.{$kunci}", $pesan[0]);
            }

            return;
        } catch (PengaturanBerubah $e) {
            unset($this->rencanaAksi);
            $this->versiTag = KelolaRencanaAksi::versi($tahun);
            $this->dispatch('notify', message: $e->getMessage().' Daftar sudah dimuat ulang — periksa lalu simpan lagi.', type: 'error');

            return;
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        } catch (RuntimeException $e) {
            report($e);
            $this->dispatch('notify', message: 'Tag rencana aksi gagal disimpan — coba lagi.', type: 'error');

            return;
        }

        unset($this->rencanaAksi);
        $this->reset('tagTerbuka', 'isianTag', 'versiTag');
        $this->dispatch('notify', message: $hasil['pesan'], type: $hasil['berubah'] ? 'success' : 'info');
    }

    /**
     * Pilihan formulir tag: SK, IKSK (SK terpilih), RKTM, EDM, 5 Budaya Mutu, leading aktif.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function pilihanTag(): array
    {
        $tahun = $this->tahunDilihat();

        return [
            'sk' => MasterKode::kodeSk($tahun),
            'iksk' => IkskPerSk::pilihan(($this->isianTag['kode_sk'] ?? '') ?: null, $tahun),
            'rktm' => MasterKode::kodeRktm($tahun),
            'edm' => MasterKode::kodeEdm($tahun),
            'budaya' => MasterKode::budayaMutu($tahun),
            'leading' => MasterKode::leadings(),
        ];
    }

    /**
     * Tahun anggaran terdaftar (terbaru lebih dulu) beserta ketersediaan masternya.
     *
     * @return list<array{tahun: int, is_active: bool, is_locked: bool, tersedia: bool}>
     */
    #[Computed]
    public function daftarTahun(): array
    {
        return array_map(fn (array $t) => [
            'tahun' => $t['tahun'],
            'is_active' => $t['is_active'],
            'is_locked' => $t['is_locked'],
            'tersedia' => MasterKode::tersedia($t['tahun']),
        ], app(TahunAnggaranRepository::class)->semua());
    }

    /** Tahun sumber yang disarankan untuk menyalin master ke tahun yang dilihat (SalinMasterKode::sumber). */
    public function sumberSalin(): ?int
    {
        return $this->tahunTerkunci() ? null : SalinMasterKode::sumber($this->tahunDilihat());
    }

    /** Salin master tahun $dari ke tahun yang dilihat — hanya jenis yang masih kosong. */
    public function salinMaster(int $dari): void
    {
        $ke = $this->tahunDilihat();
        try {
            $hasil = SalinMasterKode::salin($dari, $ke, StubData::currentUser());
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: $e->validator->errors()->first(), type: 'error');

            return;
        } catch (RuntimeException $e) {
            report($e);
            $this->dispatch('notify', message: "Master kode TA {$ke} gagal disalin — coba lagi.", type: 'error');

            return;
        }

        unset($this->jumlah, $this->baris, $this->pemakaian, $this->daftarTahun, $this->rencanaAksi);
        $this->dispatch('notify', message: $hasil['disalin'] === []
            ? "Master kode TA {$ke} sudah terisi — tidak ada yang disalin."
            : "Master kode TA {$dari} disalin ke TA {$ke} (".array_sum($hasil['disalin']).' kode).', type: $hasil['disalin'] === [] ? 'info' : 'success');
    }

    /**
     * Unggahan Excel terakhir yang disimpan untuk tahun yang dilihat (MasterKodeRepository::unggahan).
     *
     * @return array<string, mixed>|null
     */
    public function unggahanTerakhir(): ?array
    {
        return app(MasterKodeRepository::class)->unggahan($this->tahunDilihat())[0] ?? null;
    }

    public function tahunDilihat(): int
    {
        return $this->tahun !== '' ? (int) $this->tahun : StubData::tahunAktif()['tahun'];
    }

    public function tahunTerkunci(): bool
    {
        return (bool) (collect($this->daftarTahun)->firstWhere('tahun', $this->tahunDilihat())['is_locked'] ?? false);
    }

    public function tersedia(): bool
    {
        return MasterKode::tersedia($this->tahunDilihat());
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function jumlah(): array
    {
        return JenisKode::jumlah($this->tahunDilihat());
    }

    /**
     * Baris jenis yang dipilih.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function baris(): array
    {
        return JenisKode::baris($this->jenis, $this->tahunDilihat());
    }

    /**
     * Kode jenis terpilih → jumlah kegiatan tahun yang dilihat yang memakainya.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function pemakaian(): array
    {
        $tahun = $this->tahunDilihat();
        $kegiatan = array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => TahunKegiatan::dari($k) === $tahun);

        return JenisKode::pemakaian($this->jenis, $kegiatan, $tahun);
    }

    /**
     * Uraian SK per kode (kolom SK di tab IKSK) dan jumlah IKSK per SK (tab SK).
     *
     * @return array{uraian_sk: array<string, string>, iksk_per_sk: array<string, int>}
     */
    public function relasiSk(): array
    {
        $tahun = $this->tahunDilihat();

        return [
            'uraian_sk' => array_column(MasterKode::kodeSk($tahun), 'uraian', 'kode'),
            'iksk_per_sk' => array_count_values(array_column(MasterKode::kodeIksk(null, $tahun), 'kode_sk')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function komponenEdm(): array
    {
        return MasterKode::komponenEdm($this->tahunDilihat());
    }

    private function rapikan(): void
    {
        if (! array_key_exists($this->jenis, JenisKode::DAFTAR)) {
            $this->jenis = 'sk';
        }

        $terdaftar = in_array((int) $this->tahun, array_column($this->daftarTahun, 'tahun'), true);
        if (! $terdaftar || (int) $this->tahun === StubData::tahunAktif()['tahun']) {
            $this->tahun = '';
        }

        unset($this->jumlah, $this->baris, $this->pemakaian, $this->rencanaAksi);
    }

    public function render()
    {
        return view('livewire.master.daftar')->title('Master Kode');
    }
}
