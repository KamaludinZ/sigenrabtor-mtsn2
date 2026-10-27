<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\LeadingRepository;
use App\Contracts\PenggunaRepository;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Pengaturan\AturanLeading;
use App\Support\Pengaturan\RiwayatPengaturan;
use App\Support\Stub\StubData;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Pengaturan › Leading & User (PRD Fase 6 §15, F29–F30): tab Leading (kode, nama unit, jabatan penanda tangan, status,
 * akun tertaut, kegiatan tahun aktif) dan tab Akun (peran admin/leading, tautan leading, status, terakhir masuk). Tab di
 * `?tab=`. Tabel leading bisa dicari, disaring status, dan diurutkan (URL `cari_leading`/`status_leading`/`urut_leading`/`arah_leading`) serta
 * menampilkan pejabat penanda tangan tahun aktif. Leading ditambah/diubah lewat formulir inline (aturan AturanLeading,
 * kode yang dipakai kegiatan/akun terkunci, riwayat tambah_leading/ubah_leading) dan dinonaktifkan/diaktifkan
 * (`ubahStatusLeading`, konfirmasi beserta dampaknya, minimal satu tetap aktif). Kelola akun menyusul.
 */
#[Layout('layouts.app')]
class LeadingUser extends Component
{
    public const TAB = ['leading' => 'Leading', 'akun' => 'Akun'];

    /** Hak tiap peran (PRD "Pengguna dan Peran"). */
    public const PERAN = [
        'admin' => ['label' => 'Admin', 'hak' => 'Kelola user, tahun anggaran, master data, template, penanda tangan, dan pengaturan AI; melihat & memeriksa semua kegiatan; membuka kunci dokumen final.'],
        'leading' => ['label' => 'Leading', 'hak' => 'Mengisi KAK/TOR, RAB, matrik, jadwal, dan konteks kegiatan leading-nya; memakai bantuan AI; mencetak; mengunggah arsip final.'],
    ];

    /** Kunci urut tabel leading → arah bawaan. */
    public const URUT_LEADING = ['urutan' => 'asc', 'kode' => 'asc', 'nama' => 'asc', 'kegiatan' => 'desc'];

    #[Url(except: 'leading')]
    public string $tab = 'leading';

    #[Url(as: 'cari_leading', except: '')]
    public string $cariLeading = '';

    /** '' = semua, 'aktif', 'nonaktif'. */
    #[Url(as: 'status_leading', except: '')]
    public string $statusLeading = '';

    #[Url(as: 'urut_leading', except: 'urutan')]
    public string $urutLeading = 'urutan';

    #[Url(as: 'arah_leading', except: '')]
    public string $arahLeading = '';

    /** Leading yang disunting: 'baru' (tambah), kode leading (ubah), atau null. */
    public ?string $suntingLeading = null;

    /** @var array{kode: string, nama_unit: string, jabatan_penanda_tangan: string, urutan: string} */
    public array $isianLeading = ['kode' => '', 'nama_unit' => '', 'jabatan_penanda_tangan' => '', 'urutan' => ''];

    /** Kode leading yang menunggu konfirmasi dinonaktifkan. */
    public ?string $konfirmasiNonaktif = null;

    /** Kunci urut tabel akun → arah bawaan. */
    public const URUT_AKUN = ['nama' => 'asc', 'masuk' => 'desc', 'kegiatan' => 'desc'];

    #[Url(as: 'cari_akun', except: '')]
    public string $cariAkun = '';

    /** '' = semua, 'admin', 'leading'. */
    #[Url(as: 'peran_akun', except: '')]
    public string $peranAkun = '';

    /** '' = semua, 'aktif', 'nonaktif'. */
    #[Url(as: 'status_akun', except: '')]
    public string $statusAkun = '';

    #[Url(as: 'urut_akun', except: 'nama')]
    public string $urutAkun = 'nama';

    #[Url(as: 'arah_akun', except: '')]
    public string $arahAkun = '';

    public function mount(): void
    {
        $this->updatedTab();
        $this->rapikanSaringanLeading();
        $this->rapikanSaringanAkun();
    }

    public function updatedPeranAkun(): void
    {
        $this->rapikanSaringanAkun();
    }

    public function updatedStatusAkun(): void
    {
        $this->rapikanSaringanAkun();
    }

    public function urutkanAkun(string $kolom): void
    {
        if (! array_key_exists($kolom, self::URUT_AKUN)) {
            return;
        }

        $this->arahAkun = $this->urutAkun === $kolom ? ($this->arahAkunEfektif() === 'asc' ? 'desc' : 'asc') : '';
        $this->urutAkun = $kolom;
        if ($this->arahAkun === self::URUT_AKUN[$kolom]) {
            $this->arahAkun = '';
        }
    }

    public function arahAkunEfektif(): string
    {
        return in_array($this->arahAkun, ['asc', 'desc'], true) ? $this->arahAkun : self::URUT_AKUN[$this->urutAkun];
    }

    public function hapusSaringanAkun(): void
    {
        $this->reset('cariAkun', 'peranAkun', 'statusAkun');
    }

    /**
     * Akun setelah cari (nama, surel, jabatan) & saringan peran/status, terurut; yang belum pernah masuk di akhir saat
     * diurutkan per waktu masuk.
     *
     * @return list<array<string, mixed>>
     */
    public function akunTampil(): array
    {
        $cari = mb_strtolower(trim($this->cariAkun));
        $kegiatan = $this->kegiatanPerAkun();
        $baris = array_values(array_filter($this->akun, fn (array $a) => ($this->peranAkun === '' || $a['role'] === $this->peranAkun)
            && match ($this->statusAkun) {
                'aktif' => $a['is_active'],
                'nonaktif' => ! $a['is_active'],
                default => true,
            }
            && ($cari === '' || str_contains(mb_strtolower($a['name'].' '.$a['email'].' '.($a['jabatan'] ?? '')), $cari))));

        $arah = $this->arahAkunEfektif() === 'asc' ? 1 : -1;
        usort($baris, function (array $a, array $b) use ($arah, $kegiatan) {
            if ($this->urutAkun === 'masuk' && ($a['terakhir_masuk'] === null || $b['terakhir_masuk'] === null)) {
                return ($a['terakhir_masuk'] === null) <=> ($b['terakhir_masuk'] === null) ?: strnatcasecmp($a['name'], $b['name']);
            }
            $hasil = match ($this->urutAkun) {
                'masuk' => strtotime($a['terakhir_masuk']) <=> strtotime($b['terakhir_masuk']),
                'kegiatan' => ($kegiatan[$a['id']] ?? 0) <=> ($kegiatan[$b['id']] ?? 0),
                default => strnatcasecmp($a['name'], $b['name']),
            };

            return ($hasil * $arah) ?: strnatcasecmp($a['name'], $b['name']);
        });

        return $baris;
    }

    /**
     * Jumlah kegiatan tahun aktif yang dibuat tiap akun (user_id pembuat).
     *
     * @return array<int, int>
     */
    public function kegiatanPerAkun(): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $id = array_filter(array_map(fn (array $k) => TahunKegiatan::dari($k) === $tahun ? ($k['user_id'] ?? null) : null, app(KegiatanRepository::class)->semua()));

        return array_count_values(array_map('intval', $id));
    }

    public function updatedStatusLeading(): void
    {
        $this->rapikanSaringanLeading();
    }

    /** Judul kolom: kolom sama membalik arah, kolom lain memakai arah bawaannya. */
    public function urutkanLeading(string $kolom): void
    {
        if (! array_key_exists($kolom, self::URUT_LEADING)) {
            return;
        }

        $this->arahLeading = $this->urutLeading === $kolom
            ? ($this->arahLeadingEfektif() === 'asc' ? 'desc' : 'asc')
            : '';
        $this->urutLeading = $kolom;
        if ($this->arahLeading === self::URUT_LEADING[$kolom]) {
            $this->arahLeading = '';
        }
    }

    public function arahLeadingEfektif(): string
    {
        return in_array($this->arahLeading, ['asc', 'desc'], true) ? $this->arahLeading : self::URUT_LEADING[$this->urutLeading];
    }

    public function hapusSaringanLeading(): void
    {
        $this->reset('cariLeading', 'statusLeading');
    }

    /**
     * Leading setelah cari (kode, nama unit, jabatan) & saringan status, terurut (kode urut natural: 1.10 sesudah 1.9).
     *
     * @return list<array{kode: string, nama_unit: string, jabatan_penanda_tangan: string, urutan: int, is_active: bool}>
     */
    public function leadingTampil(): array
    {
        $cari = mb_strtolower(trim($this->cariLeading));
        $kegiatan = $this->kegiatanPerLeading();
        $baris = array_filter($this->leading, fn (array $l) => match ($this->statusLeading) {
            'aktif' => $l['is_active'],
            'nonaktif' => ! $l['is_active'],
            default => true,
        } && ($cari === '' || str_contains(mb_strtolower($l['kode'].' '.$l['nama_unit'].' '.$l['jabatan_penanda_tangan']), $cari)));

        $banding = match ($this->urutLeading) {
            'kode' => fn (array $a, array $b) => version_compare($a['kode'], $b['kode']),
            'nama' => fn (array $a, array $b) => strnatcasecmp($a['nama_unit'], $b['nama_unit']),
            'kegiatan' => fn (array $a, array $b) => ($kegiatan[$a['kode']] ?? 0) <=> ($kegiatan[$b['kode']] ?? 0),
            default => fn (array $a, array $b) => $a['urutan'] <=> $b['urutan'],
        };
        $arah = $this->arahLeadingEfektif() === 'asc' ? 1 : -1;
        usort($baris, fn (array $a, array $b) => ($banding($a, $b) * $arah) ?: version_compare($a['kode'], $b['kode']));

        return $baris;
    }

    /**
     * Pejabat penanda tangan tiap leading tahun aktif (Profil & Pejabat); nama null = belum diisi.
     *
     * @return array<string, array{jabatan: string, nama: string|null, nip: string|null}>
     */
    public function pejabatLeading(): array
    {
        return StubData::pejabatLeading();
    }

    public function updatedTab(): void
    {
        if (! array_key_exists($this->tab, self::TAB)) {
            $this->tab = 'leading';
        }
        $this->batalLeading();
    }

    public function tambahLeading(): void
    {
        $this->batalLeading();
        $this->konfirmasiNonaktif = null;
        $this->tab = 'leading';
        $this->suntingLeading = 'baru';
        $this->isianLeading['urutan'] = (string) (max([0, ...array_column($this->leading, 'urutan')]) + 1);
    }

    public function ubahLeading(string $kode): void
    {
        $leading = collect($this->leading)->firstWhere('kode', '===', $kode);
        if ($leading === null) {
            return;
        }

        $this->batalLeading();
        $this->suntingLeading = $kode;
        $this->isianLeading = ['kode' => $leading['kode'], 'nama_unit' => $leading['nama_unit'], 'jabatan_penanda_tangan' => $leading['jabatan_penanda_tangan'], 'urutan' => (string) $leading['urutan']];
    }

    public function batalLeading(): void
    {
        $this->reset('suntingLeading', 'isianLeading');
        $this->resetErrorBag(array_map(fn (string $k) => "isianLeading.{$k}", array_keys($this->isianLeading)));
    }

    public function simpanLeading(LeadingRepository $repo): void
    {
        $kodeLama = $this->suntingLeading === 'baru' ? null : $this->suntingLeading;
        $lama = $kodeLama === null ? null : collect($this->leading)->firstWhere('kode', '===', $kodeLama);
        if ($this->suntingLeading === null || ($kodeLama !== null && $lama === null)) {
            return;
        }

        $this->resetErrorBag(array_map(fn (string $k) => "isianLeading.{$k}", array_keys($this->isianLeading)));
        $isian = array_map(fn (mixed $v) => AturanLeading::rapikan((string) $v), $this->isianLeading);
        if ($kodeLama !== null && $this->kodeTerpakai($kodeLama) > 0) {
            $isian['kode'] = $kodeLama; // Kode terpakai tidak bisa diganti (input terkunci di layar).
        }
        $this->isianLeading = $isian;
        $galat = AturanLeading::galat($isian, $this->leading, $kodeLama);
        foreach ($galat as $kolom => $pesan) {
            $this->addError("isianLeading.{$kolom}", $pesan);
        }
        if ($galat !== []) {
            return;
        }

        $baru = [
            'kode' => AturanLeading::normalkanKode($isian['kode']),
            'nama_unit' => $isian['nama_unit'],
            'jabatan_penanda_tangan' => $isian['jabatan_penanda_tangan'],
        ] + ($isian['urutan'] === '' ? [] : ['urutan' => (int) $isian['urutan']]);

        if ($lama !== null) {
            $ringkasan = RiwayatPengaturan::perubahan(
                ['urutan' => (string) $lama['urutan']] + $lama,
                ['urutan' => (string) ($baru['urutan'] ?? $lama['urutan'])] + $baru,
                ['kode' => 'Kode', 'nama_unit' => 'Nama unit', 'jabatan_penanda_tangan' => 'Jabatan', 'urutan' => 'Urutan'],
            );
            if ($ringkasan === null) {
                $this->batalLeading();
                $this->dispatch('notify', message: 'Tidak ada yang berubah.', type: 'info');

                return;
            }
        }

        try {
            $repo->simpan($baru, $kodeLama);
        } catch (RuntimeException $e) {
            report($e);
            $this->dispatch('notify', message: 'Leading gagal disimpan. Isian masih ada di layar — coba simpan lagi.', type: 'error');

            return;
        }

        RiwayatPengaturan::catat($lama === null ? 'tambah_leading' : 'ubah_leading', $lama === null
            ? "Leading {$baru['kode']} {$baru['nama_unit']} — jabatan {$baru['jabatan_penanda_tangan']}"
            : "Leading {$kodeLama} — {$ringkasan}");
        unset($this->leading);
        $this->batalLeading();
        $this->dispatch('notify', message: 'Leading '.$baru['kode'].' '.$baru['nama_unit'].($lama === null ? ' ditambahkan.' : ' diperbarui.'), type: 'success');
    }

    /** Nonaktifkan meminta konfirmasi dulu (dampaknya ditampilkan); mengaktifkan kembali langsung. */
    public function ubahStatusLeading(string $kode, LeadingRepository $repo): void
    {
        $leading = collect($this->leading)->firstWhere('kode', '===', $kode);
        if ($leading === null) {
            return;
        }
        if ($leading['is_active'] && $this->konfirmasiNonaktif !== $kode) {
            $this->batalLeading();
            $this->konfirmasiNonaktif = $kode;

            return;
        }
        if ($leading['is_active'] && count(array_filter($this->leading, fn (array $l) => $l['is_active'])) === 1) {
            $this->konfirmasiNonaktif = null;
            $this->dispatch('notify', message: 'Minimal satu leading harus tetap aktif.', type: 'error');

            return;
        }

        $aktif = ! $leading['is_active'];
        try {
            $repo->simpan(['is_active' => $aktif] + $leading, $kode);
        } catch (RuntimeException $e) {
            report($e);
            $this->dispatch('notify', message: 'Status leading gagal disimpan — coba lagi.', type: 'error');

            return;
        }

        $label = "Leading {$kode} {$leading['nama_unit']}";
        RiwayatPengaturan::catat('ubah_status_leading', $label.($aktif ? ' diaktifkan kembali' : ' dinonaktifkan'));
        unset($this->leading);
        $this->konfirmasiNonaktif = null;
        $this->dispatch('notify', message: $label.($aktif ? ' aktif kembali — bisa dipilih untuk kegiatan.' : ' dinonaktifkan — tidak bisa dipilih untuk kegiatan baru.'), type: 'success');
    }

    /**
     * Dampak menonaktifkan leading: kegiatan semua tahun yang memakainya dan akun aktif yang hanya tertaut ke leading ini.
     *
     * @return array{kegiatan: int, akun: list<string>}
     */
    public function dampakNonaktif(string $kode): array
    {
        $kegiatan = count(array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => ($k['leading'] ?? null) === $kode));
        $akun = array_values(array_column(array_filter($this->akun, fn (array $a) => $a['is_active'] && $a['leadings'] === [$kode]), 'name'));

        return ['kegiatan' => $kegiatan, 'akun' => $akun];
    }

    /** Banyaknya kegiatan (semua tahun) & akun yang memakai kode leading — kode yang terpakai tidak bisa diganti. */
    public function kodeTerpakai(string $kode): int
    {
        $kegiatan = count(array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => ($k['leading'] ?? null) === $kode));

        return $kegiatan + count($this->akunPerLeading()[$kode] ?? []);
    }

    /**
     * @return list<array{kode: string, nama_unit: string, jabatan_penanda_tangan: string, urutan: int, is_active: bool}>
     */
    #[Computed]
    public function leading(): array
    {
        return app(LeadingRepository::class)->semua();
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function akun(): array
    {
        return app(PenggunaRepository::class)->semua();
    }

    /**
     * Akun tertaut per kode leading (aktif lebih dulu).
     *
     * @return array<string, list<array{id: int, name: string, is_active: bool}>>
     */
    public function akunPerLeading(): array
    {
        $hasil = [];
        foreach ($this->akun as $a) {
            foreach ($a['leadings'] as $kode) {
                $hasil[$kode][] = ['id' => $a['id'], 'name' => $a['name'], 'is_active' => $a['is_active']];
            }
        }

        return array_map(fn (array $daftar) => collect($daftar)->sortByDesc('is_active')->values()->all(), $hasil);
    }

    /**
     * Jumlah kegiatan tahun aktif per kode leading.
     *
     * @return array<string, int>
     */
    public function kegiatanPerLeading(): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $kode = array_filter(array_map(fn (array $k) => TahunKegiatan::dari($k) === $tahun ? ($k['leading'] ?? null) : null, app(KegiatanRepository::class)->semua()));

        return array_count_values(array_map('strval', $kode));
    }

    /**
     * Ringkasan untuk tab & kartu: leading aktif/nonaktif, leading aktif tanpa akun aktif, akun per peran & status.
     *
     * @return array{leading_aktif: int, leading_nonaktif: int, tanpa_akun: list<string>, akun_aktif: int, akun_nonaktif: int, per_peran: array<string, int>}
     */
    public function ringkasan(): array
    {
        $aktifPerLeading = array_map(fn (array $daftar) => count(array_filter($daftar, fn (array $a) => $a['is_active'])), $this->akunPerLeading());
        $leadingAktif = array_filter($this->leading, fn (array $l) => $l['is_active']);
        $akunAktif = array_filter($this->akun, fn (array $a) => $a['is_active']);

        return [
            'leading_aktif' => count($leadingAktif),
            'leading_nonaktif' => count($this->leading) - count($leadingAktif),
            'tanpa_akun' => array_values(array_map(fn (array $l) => $l['kode'], array_filter($leadingAktif, fn (array $l) => ($aktifPerLeading[$l['kode']] ?? 0) === 0))),
            'akun_aktif' => count($akunAktif),
            'akun_nonaktif' => count($this->akun) - count($akunAktif),
            'per_peran' => array_merge(['admin' => 0, 'leading' => 0], array_count_values(array_column($akunAktif, 'role'))),
        ];
    }

    private function rapikanSaringanAkun(): void
    {
        if (! in_array($this->peranAkun, ['', 'admin', 'leading'], true)) {
            $this->peranAkun = '';
        }
        if (! in_array($this->statusAkun, ['', 'aktif', 'nonaktif'], true)) {
            $this->statusAkun = '';
        }
        if (! array_key_exists($this->urutAkun, self::URUT_AKUN)) {
            $this->urutAkun = 'nama';
        }
        if (! in_array($this->arahAkun, ['', 'asc', 'desc'], true)) {
            $this->arahAkun = '';
        }
    }

    private function rapikanSaringanLeading(): void
    {
        if (! in_array($this->statusLeading, ['', 'aktif', 'nonaktif'], true)) {
            $this->statusLeading = '';
        }
        if (! array_key_exists($this->urutLeading, self::URUT_LEADING)) {
            $this->urutLeading = 'urutan';
        }
        if (! in_array($this->arahLeading, ['', 'asc', 'desc'], true)) {
            $this->arahLeading = '';
        }
    }

    public function render()
    {
        return view('livewire.pengaturan.leading-user')->title('Leading & User');
    }
}
