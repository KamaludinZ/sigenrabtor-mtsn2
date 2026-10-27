<?php

namespace App\Livewire\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Support\Kegiatan\Impor\PemeriksaImporKegiatan;
use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\Kegiatan\KodeHilang;
use App\Support\Kegiatan\KunciDokumen;
use App\Support\MasterKode;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;
use App\Support\Rab\StatusPagu;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Halaman Data Kegiatan: tabel kegiatan tahun anggaran aktif dengan pencarian, filter lengkap, urutan kolom,
 * ringkasan pagu & total RAB, ringkasan kondisi batas pagu (PRD F12), halaman, serta unduh template dan impor dari Excel.
 */
#[Layout('layouts.app')]
class Daftar extends Component
{
    public const SUMBER = ['dipa' => 'Dibiayai DIPA', 'komite' => 'Dibiayai Komite', 'keduanya' => 'DIPA dan Komite', 'belum' => 'Belum dipilih'];

    public const STATUS_PAGU = [
        'sesuai' => 'Sesuai pagu',
        'sisa' => 'Di bawah pagu',
        'melebihi' => 'Melebihi pagu',
        'tanpa_pagu' => 'Pagu belum diisi',
        'rab_kosong' => 'RAB belum diisi',
    ];

    public const ASAL = ['impor' => 'Impor Excel', 'manual' => 'Diisi manual'];

    /** Kolom yang bisa diurutkan → arah bawaan saat pertama diklik. */
    public const URUTAN = ['nama' => 'asc', 'leading' => 'asc', 'bulan' => 'asc', 'pagu' => 'desc', 'total' => 'desc', 'status' => 'asc', 'diperbarui' => 'desc'];

    public const PER_HALAMAN = [10, 25, 50, 100];

    /** Isian filter (bukan urutan/halaman); mengubahnya kembali ke halaman 1. */
    private const FILTER = ['cari', 'leading', 'sumber', 'status', 'bulan', 'statusPagu', 'asal'];

    #[Url(except: '')]
    public string $cari = '';

    #[Url(except: '')]
    public string $leading = '';

    #[Url(except: '')]
    public string $sumber = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $bulan = '';

    #[Url(as: 'pagu', except: '')]
    public string $statusPagu = '';

    #[Url(except: '')]
    public string $asal = '';

    #[Url(except: 'diperbarui')]
    public string $urut = 'diperbarui';

    #[Url(except: 'desc')]
    public string $arah = 'desc';

    #[Url(as: 'per', except: 25)]
    public int $perHalaman = 25;

    #[Url(as: 'hal', except: 1)]
    public int $halaman = 1;

    #[Url(as: 'impor', except: false)]
    public bool $imporTerbuka = false;

    /**
     * Semua kegiatan dengan data yang dipakai tabel & filter.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function semuaBaris(): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $leading = array_column(MasterKode::semuaLeading(), 'nama_unit', 'kode');

        return array_map(function (array $k) use ($tahun, $leading) {
            $anggaran = AnggaranKegiatan::untuk($k);
            $bulan = array_values(array_filter(array_map('intval', (array) ($k['bulan_pelaksanaan'] ?? [])), fn ($b) => $b >= 1 && $b <= 12));
            sort($bulan);

            return [
                'id' => (int) $k['id'],
                'nama' => trim((string) ($k['nama_kegiatan'] ?? '')) ?: 'Tanpa nama',
                'kode_rktm' => $k['kode_rktm'] ?? null,
                'leading' => $k['leading'] ?? null,
                'leading_nama' => $leading[$k['leading'] ?? ''] ?? null,
                'bulan' => $bulan,
                'bulan_teks' => self::teksBulanSingkat($bulan),
                'bulan_lengkap' => JadwalPelaksanaan::teksBulan($bulan, $tahun),
                'sumber' => array_map(fn ($a) => ['sumber' => $a->sumber, 'pagu' => $a->pagu(), 'total' => $a->total(), 'item' => $a->jumlahItem()], $anggaran),
                'pagu' => array_sum(array_map(fn ($a) => (int) $a->pagu(), $anggaran)),
                'total' => array_sum(array_map(fn ($a) => $a->total(), $anggaran)),
                'item' => array_sum(array_map(fn ($a) => $a->jumlahItem(), $anggaran)),
                'status_pagu' => self::statusPaguKegiatan($anggaran),
                'status' => StatusKegiatan::tryFrom($k['status'] ?? '') ?? StatusKegiatan::Draf,
                'kunci' => KunciDokumen::dari($k),
                'diperbarui' => $k['diperbarui_pada'] ?? null,
                'asal' => in_array($k['asal'] ?? null, ['impor_excel', 'impor_perkin'], true) ? 'impor' : 'manual',
                'kode_hilang' => KodeHilang::dari($k),
            ];
        }, app(KegiatanRepository::class)->semua());
    }

    /**
     * Baris setelah filter & urutan.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function terfilter(): array
    {
        $cari = PemeriksaImporKegiatan::kunciNama($this->cari);
        $baris = array_values(array_filter($this->semuaBaris, fn (array $b) => $this->cocok($b, $cari)));

        $kunci = match ($this->urut) {
            'nama' => fn ($b) => mb_strtolower($b['nama']),
            'leading' => fn ($b) => $b['leading'] ?? 'zzz',
            'bulan' => fn ($b) => $b['bulan'][0] ?? 13,
            'pagu' => fn ($b) => $b['pagu'],
            'total' => fn ($b) => $b['total'],
            'status' => fn ($b) => $b['status']->langkah(),
            default => fn ($b) => $b['diperbarui'] ?? '',
        };
        $arah = $this->arah === 'asc' ? 1 : -1;
        usort($baris, fn ($a, $b) => ($kunci($a) <=> $kunci($b)) * $arah ?: $a['id'] <=> $b['id']);

        return $baris;
    }

    /**
     * Baris cocok dengan semua filter aktif; $abaikanKondisi melewatkan filter kondisi anggaran (untuk menghitung tombolnya).
     *
     * @param  array<string, mixed>  $b
     */
    private function cocok(array $b, string $cari, bool $abaikanKondisi = false): bool
    {
        $sumber = array_column($b['sumber'], 'sumber');

        return ($cari === '' || str_contains(PemeriksaImporKegiatan::kunciNama($b['nama'].' '.$b['kode_rktm']), $cari))
            && match ($this->leading) {
                '' => true,
                'belum' => $b['leading'] === null || $b['leading'] === '',
                default => $b['leading'] === $this->leading,
            }
        && match ($this->sumber) {
            'dipa' => in_array(SumberDana::Dipa->value, $sumber, true),
            'komite' => in_array(SumberDana::Komite->value, $sumber, true),
            'keduanya' => count($sumber) === 2,
            'belum' => $sumber === [],
            default => true,
        }
        && ($this->status === '' || $b['status']->value === $this->status)
        && ($this->bulan === '' || in_array((int) $this->bulan, $b['bulan'], true))
        && ($abaikanKondisi || $this->statusPagu === '' || $b['status_pagu'] === $this->statusPagu)
        && ($this->asal === '' || $b['asal'] === $this->asal);
    }

    /**
     * Jumlah kegiatan per kondisi anggaran (kunci STATUS_PAGU) dari kegiatan yang cocok dengan filter lain, sehingga
     * tombol kondisi tetap menunjukkan pilihan lain saat salah satunya sedang dipakai.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function jumlahPerKondisi(): array
    {
        $cari = PemeriksaImporKegiatan::kunciNama($this->cari);
        $jumlah = array_fill_keys(array_keys(self::STATUS_PAGU), 0);

        foreach ($this->semuaBaris as $b) {
            if (isset($jumlah[$b['status_pagu']]) && $this->cocok($b, $cari, abaikanKondisi: true)) {
                $jumlah[$b['status_pagu']]++;
            }
        }

        return $jumlah;
    }

    /**
     * RAB berisi yang perlu perhatian batas pagu dari kegiatan terfilter, per kegiatan × sumber dana: yang melebihi pagu
     * lebih dulu (kelebihan terbesar di atas), lalu yang pagunya belum diisi (total terbesar di atas).
     *
     * @return list<array{id: int, nama: string, sumber: string, pagu: int|null, total: int, status: StatusPagu}>
     */
    #[Computed]
    public function perluPerhatian(): array
    {
        $daftar = [];

        foreach ($this->terfilter as $b) {
            foreach ($b['sumber'] as $s) {
                $status = StatusPagu::hitung($s['total'], $s['pagu']);

                if ($s['item'] > 0 && in_array($status->status, ['melebihi', 'tanpa_pagu'], true)) {
                    $daftar[] = ['id' => $b['id'], 'nama' => $b['nama'], 'sumber' => $s['sumber'], 'pagu' => $s['pagu'], 'total' => $s['total'], 'status' => $status];
                }
            }
        }

        $kunci = fn (array $p) => $p['status']->melebihi() ? [0, $p['status']->selisih, $p['id']] : [1, -$p['total'], $p['id']];
        usort($daftar, fn ($a, $b) => $kunci($a) <=> $kunci($b));

        return $daftar;
    }

    /** Tombol kondisi anggaran: pilih untuk menyaring tabel, pilih lagi untuk melepas. */
    public function saringKondisi(string $kondisi): void
    {
        if (! isset(self::STATUS_PAGU[$kondisi])) {
            return;
        }

        $this->statusPagu = $this->statusPagu === $kondisi ? '' : $kondisi;
        $this->halaman = 1;
    }

    /**
     * Baris halaman aktif.
     *
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function halamanIni(): array
    {
        return array_slice($this->terfilter, ($this->halaman - 1) * $this->perHalaman, $this->perHalaman);
    }

    public function jumlahHalaman(): int
    {
        return max(1, (int) ceil(count($this->terfilter) / $this->perHalaman));
    }

    /**
     * Jumlah kegiatan, pagu per sumber, dan total RAB dari baris yang terfilter.
     *
     * @return array{kegiatan: int, pagu: array<string, int>, total: int, melebihi: int}
     */
    #[Computed]
    public function ringkasan(): array
    {
        $pagu = array_fill_keys(SumberDana::urutan(), 0);
        foreach ($this->terfilter as $b) {
            foreach ($b['sumber'] as $s) {
                $pagu[$s['sumber']] = ($pagu[$s['sumber']] ?? 0) + (int) $s['pagu'];
            }
        }

        return [
            'kegiatan' => count($this->terfilter),
            'pagu' => $pagu,
            'total' => array_sum(array_column($this->terfilter, 'total')),
            'melebihi' => count(array_filter($this->terfilter, fn ($b) => $b['status_pagu'] === 'melebihi')),
        ];
    }

    /**
     * Filter yang sedang aktif sebagai chip: properti → label tampil.
     *
     * @return array<string, string>
     */
    public function filterAktif(): array
    {
        $leading = array_column(MasterKode::semuaLeading(), 'nama_unit', 'kode') + ['belum' => 'Leading belum dipilih'];

        return array_filter([
            'cari' => $this->cari !== '' ? "Cari: “{$this->cari}”" : null,
            'leading' => $leading[$this->leading] ?? null,
            'sumber' => self::SUMBER[$this->sumber] ?? null,
            'status' => StatusKegiatan::tryFrom($this->status)?->label(),
            'bulan' => JadwalPelaksanaan::NAMA_BULAN[(int) $this->bulan] ?? null,
            'statusPagu' => self::STATUS_PAGU[$this->statusPagu] ?? null,
            'asal' => self::ASAL[$this->asal] ?? null,
        ]);
    }

    public function updated(string $properti): void
    {
        if (in_array($properti, self::FILTER, true)) {
            $this->halaman = 1;
        }

        if ($properti === 'perHalaman') {
            $this->perHalaman = in_array($this->perHalaman, self::PER_HALAMAN, true) ? $this->perHalaman : 25;
            $this->halaman = 1;
        }
    }

    public function hapusFilter(string $properti): void
    {
        if (in_array($properti, self::FILTER, true)) {
            $this->reset($properti);
            $this->halaman = 1;
        }
    }

    public function hapusSemuaFilter(): void
    {
        $this->reset(...self::FILTER);
        $this->halaman = 1;
    }

    /** Klik judul kolom: urutkan; klik lagi membalik arah. */
    public function urutkan(string $kolom): void
    {
        if (! isset(self::URUTAN[$kolom])) {
            return;
        }

        $this->arah = $this->urut === $kolom ? ($this->arah === 'asc' ? 'desc' : 'asc') : self::URUTAN[$kolom];
        $this->urut = $kolom;
        $this->halaman = 1;
    }

    public function keHalaman(int $halaman): void
    {
        $this->halaman = max(1, min($halaman, $this->jumlahHalaman()));
    }

    public function bukaImpor(): void
    {
        $this->imporTerbuka = ! $this->imporTerbuka;
    }

    /** Anggaran sebuah kegiatan berubah di tab lain: render ulang menghitung ulang total RAB & kondisi pagu tabel. */
    #[On('anggaran-diperbarui')]
    public function anggaranDiperbarui(): void
    {
        unset($this->semuaBaris, $this->terfilter, $this->halamanIni, $this->ringkasan, $this->jumlahPerKondisi, $this->perluPerhatian);
    }

    #[On('kegiatan-diimpor')]
    public function segarkan(int $jumlah = 0): void
    {
        unset($this->semuaBaris, $this->terfilter, $this->halamanIni, $this->ringkasan);

        if ($jumlah > 0) {
            $this->dispatch('notify', message: "{$jumlah} kegiatan hasil impor ditambahkan ke tabel.", type: 'success');
        }
    }

    /**
     * Ringkasan kondisi anggaran satu kegiatan untuk filter & kolom Total RAB.
     *
     * @param  list<AnggaranSumber>  $anggaran
     */
    private static function statusPaguKegiatan(array $anggaran): string
    {
        $status = array_map(fn ($a) => $a->statusPagu(), $anggaran);

        return match (true) {
            $anggaran === [] => 'tanpa_sumber',
            array_sum(array_map(fn ($a) => $a->jumlahItem(), $anggaran)) === 0 => 'rab_kosong',
            in_array('melebihi', $status, true) => 'melebihi',
            in_array('tanpa_pagu', $status, true) => 'tanpa_pagu',
            array_unique($status) === ['sesuai'] => 'sesuai',
            default => 'sisa',
        };
    }

    /**
     * [8, 9] → "Agu – Sep", [3, 6] → "Mar, Jun" (tahun anggaran sudah tertera di kepala halaman).
     *
     * @param  list<int>  $bulan
     */
    private static function teksBulanSingkat(array $bulan): ?string
    {
        if ($bulan === []) {
            return null;
        }

        $singkat = fn (int $b) => mb_substr(JadwalPelaksanaan::NAMA_BULAN[$b], 0, 3);
        $berurutan = end($bulan) - $bulan[0] === count($bulan) - 1;

        return count($bulan) > 1 && $berurutan
            ? $singkat($bulan[0]).' – '.$singkat(end($bulan))
            : implode(', ', array_map($singkat, $bulan));
    }

    public function render()
    {
        if ($this->halaman > $this->jumlahHalaman()) {
            $this->halaman = $this->jumlahHalaman();
        }

        return view('livewire.kegiatan.daftar', ['tahun' => StubData::tahunAktif()['tahun']])->title('Data Kegiatan');
    }
}
