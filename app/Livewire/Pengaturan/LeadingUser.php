<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\LeadingRepository;
use App\Contracts\PenggunaRepository;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Pengaturan\AturanAkun;
use App\Support\Pengaturan\AturanLeading;
use App\Support\Pengaturan\KelolaLeading;
use App\Support\Pengaturan\Nip;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Pengaturan\RiwayatPengaturan;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Pengaturan › Leading & User (PRD Fase 6 §15, F29–F30): tab Leading (kode, nama unit, jabatan penanda tangan, status,
 * akun tertaut, kegiatan tahun aktif) dan tab Akun (peran admin/leading, tautan leading, status, terakhir masuk). Tab di
 * `?tab=`. Tabel leading bisa dicari, disaring status, dan diurutkan (URL `cari_leading`/`status_leading`/`urut_leading`/`arah_leading`) serta
 * menampilkan pejabat penanda tangan tahun aktif. Leading ditambah/diubah lewat formulir inline (aturan AturanLeading,
 * kode yang dipakai kegiatan/akun terkunci, riwayat tambah_leading/ubah_leading) dan dinonaktifkan/diaktifkan
 * (`ubahStatusLeading`, konfirmasi beserta dampaknya, minimal satu tetap aktif). Akun ditambah/diubah lewat formulir
 * inline (aturan AturanAkun: surel unik, peran, tautan leading, NIP; riwayat tambah_akun/ubah_akun). Tautan akun dikelola
 * juga dari sisi leading lewat panel per baris (`bukaTautan`, `tautkanAkun`, `lepasTautan`; riwayat ubah_tautan_leading).
 * Akun dinonaktifkan/diaktifkan lewat `ubahStatusAkun` (konfirmasi, akun sendiri & admin aktif terakhir dilindungi). Setiap
 * aksi tulis menolak menimpa perubahan dari tab/admin lain (sidik data `$versi` dicatat `rendering()`).
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

    /** Sidik data leading & akun saat terakhir ditampilkan: pembanding perubahan dari tab/admin lain. */
    #[Locked]
    public ?string $versi = null;

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

    /** Kode leading yang panel tautan akunnya terbuka. */
    public ?string $tautanLeading = null;

    /** Id akun (teks) yang dipilih untuk ditautkan di panel. */
    public string $akunDitautkan = '';

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

    /** Id akun (teks) yang menunggu konfirmasi dinonaktifkan. */
    public ?string $konfirmasiNonaktifAkun = null;

    /** Akun yang disunting: 'baru' (tambah), id akun (ubah, sebagai teks), atau null. */
    public ?string $suntingAkun = null;

    /** @var array{name: string, email: string, role: string, jabatan: string, nip: string, leadings: list<string>} */
    public array $isianAkun = ['name' => '', 'email' => '', 'role' => 'leading', 'jabatan' => '', 'nip' => '', 'leadings' => []];

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

    public function tambahAkun(): void
    {
        $this->batalAkun();
        $this->tab = 'akun';
        $this->suntingAkun = 'baru';
    }

    public function ubahAkun(int $id): void
    {
        $akun = collect($this->akun)->firstWhere('id', $id);
        if ($akun === null) {
            return;
        }

        $this->batalAkun();
        $this->tab = 'akun';
        $this->suntingAkun = (string) $id;
        $this->isianAkun = ['name' => $akun['name'], 'email' => $akun['email'], 'role' => $akun['role'], 'jabatan' => (string) $akun['jabatan'], 'nip' => (string) $akun['nip'], 'leadings' => $akun['leadings']];
    }

    public function batalAkun(): void
    {
        $this->reset('suntingAkun', 'isianAkun', 'konfirmasiNonaktifAkun');
        $this->resetErrorBag(array_map(fn (string $k) => "isianAkun.{$k}", array_keys($this->isianAkun)));
    }

    public function simpanAkun(PenggunaRepository $repo): void
    {
        if ($this->berubahDiTempatLain()) {
            return;
        }
        $id = $this->suntingAkun === 'baru' ? null : (int) $this->suntingAkun;
        $lama = $id === null ? null : collect($this->akun)->firstWhere('id', $id);
        if ($this->suntingAkun === null || ($id !== null && $lama === null)) {
            return;
        }

        $this->resetErrorBag(array_map(fn (string $k) => "isianAkun.{$k}", array_keys($this->isianAkun)));
        $urutanLeading = array_column($this->leading, 'kode');
        $isian = [
            'name' => AturanAkun::rapikan((string) $this->isianAkun['name']),
            'email' => mb_strtolower(AturanAkun::rapikan((string) $this->isianAkun['email'])),
            'role' => (string) $this->isianAkun['role'],
            'jabatan' => AturanAkun::rapikan((string) $this->isianAkun['jabatan']),
            'nip' => Nip::bersihkan((string) $this->isianAkun['nip']),
            // Urut seperti daftar leading, tanpa dobel.
            'leadings' => array_values(array_intersect($urutanLeading, array_map('strval', (array) $this->isianAkun['leadings']))),
        ];
        $this->isianAkun = $isian;
        $aktif = array_column(array_filter($this->leading, fn (array $l) => $l['is_active']), 'kode');
        $galat = AturanAkun::galat($isian, $this->akun, $aktif, $id, StubData::currentUser()['id']);
        foreach ($galat as $kolom => $pesan) {
            $this->addError("isianAkun.{$kolom}", $pesan);
        }
        if ($galat !== []) {
            return;
        }

        $baru = ['name' => $isian['name'], 'email' => $isian['email'], 'role' => $isian['role'], 'jabatan' => $isian['jabatan'] ?: null, 'nip' => $isian['nip'] ?: null, 'leadings' => $isian['role'] === 'admin' ? [] : $isian['leadings']];
        $tampil = fn (array $a) => ['role' => self::PERAN[$a['role']]['label'], 'nip' => $a['nip'] ? Nip::format($a['nip']) : null] + $a;
        if ($lama !== null) {
            $ringkasan = RiwayatPengaturan::perubahan($tampil($lama), $tampil($baru), ['name' => 'Nama', 'email' => 'Surel', 'role' => 'Peran', 'jabatan' => 'Jabatan', 'nip' => 'NIP', 'leadings' => 'Leading']);
            if ($ringkasan === null) {
                $this->batalAkun();
                $this->dispatch('notify', message: 'Tidak ada yang berubah.', type: 'info');

                return;
            }
        }

        try {
            $idBaru = $repo->simpan($baru, $id);
        } catch (RuntimeException $e) {
            report($e);
            $this->dispatch('notify', message: 'Akun gagal disimpan. Isian masih ada di layar — coba simpan lagi.', type: 'error');

            return;
        }

        RiwayatPengaturan::catat($lama === null ? 'tambah_akun' : 'ubah_akun', $lama === null
            ? "Akun {$baru['name']} ({$baru['email']}) — ".self::PERAN[$baru['role']]['label'].($baru['leadings'] ? ' '.implode(', ', $baru['leadings']) : '')
            : "Akun {$lama['name']} — {$ringkasan}");
        unset($this->akun);
        $this->batalAkun();
        $this->dispatch('notify', message: 'Akun '.$baru['name'].($lama === null ? ' ditambahkan.' : ' diperbarui.'), type: 'success');
        $this->dispatch('akun-disimpan', id: $idBaru);
    }

    /**
     * Nonaktifkan akun (konfirmasi dulu) atau aktifkan kembali. Akun sendiri & admin aktif terakhir tidak bisa
     * dinonaktifkan; akun nonaktif tetap tercatat sebagai pembuat kegiatannya.
     */
    public function ubahStatusAkun(int $id, PenggunaRepository $repo): void
    {
        if ($this->berubahDiTempatLain()) {
            return;
        }
        $akun = collect($this->akun)->firstWhere('id', $id);
        if ($akun === null) {
            return;
        }

        if ($akun['is_active']) {
            $tolak = match (true) {
                $id === StubData::currentUser()['id'] => 'Akun Anda sendiri tidak bisa dinonaktifkan.',
                $akun['role'] === 'admin' && count(array_filter($this->akun, fn (array $a) => $a['role'] === 'admin' && $a['is_active'])) === 1 => 'Minimal satu admin aktif harus tetap ada.',
                default => null,
            };
            if ($tolak !== null) {
                $this->konfirmasiNonaktifAkun = null;
                $this->dispatch('notify', message: $tolak, type: 'error');

                return;
            }
            if ($this->konfirmasiNonaktifAkun !== (string) $id) {
                $this->batalAkun();
                $this->konfirmasiNonaktifAkun = (string) $id;

                return;
            }
        }

        $aktif = ! $akun['is_active'];
        try {
            $repo->simpan(['is_active' => $aktif], $id);
        } catch (RuntimeException $e) {
            report($e);
            $this->dispatch('notify', message: 'Status akun gagal disimpan — coba lagi.', type: 'error');

            return;
        }

        RiwayatPengaturan::catat('ubah_status_akun', "Akun {$akun['name']}".($aktif ? ' diaktifkan kembali' : ' dinonaktifkan'));
        unset($this->akun);
        $this->konfirmasiNonaktifAkun = null;
        $tanpaLeadingAktif = $aktif && $akun['role'] === 'leading'
            && array_intersect($akun['leadings'], array_column(array_filter($this->leading, fn (array $l) => $l['is_active']), 'kode')) === [];
        $this->dispatch('notify', message: "Akun {$akun['name']}".($aktif
            ? ($tanpaLeadingAktif ? ' aktif kembali, tetapi belum tertaut ke leading aktif — tautkan dulu agar bisa membuat kegiatan.' : ' aktif kembali — bisa masuk lagi.')
            : ' dinonaktifkan — tidak bisa masuk lagi.'), type: $tanpaLeadingAktif ? 'info' : 'success');
    }

    /** Jumlah kegiatan (semua tahun) yang dibuat akun $id — tetap tercatat atas namanya bila akun dinonaktifkan. */
    public function kegiatanBuatan(int $id): int
    {
        return count(array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => (int) ($k['user_id'] ?? 0) === $id));
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
        $this->batalAkun();
        $this->tutupTautan();
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

    public function simpanLeading(): void
    {
        if ($this->berubahDiTempatLain() || $this->suntingLeading === null) {
            return;
        }
        $kodeLama = $this->suntingLeading === 'baru' ? null : $this->suntingLeading;

        $this->resetErrorBag(array_map(fn (string $k) => "isianLeading.{$k}", array_keys($this->isianLeading)));
        $this->isianLeading = array_map(fn (mixed $v) => AturanLeading::rapikan((string) $v), $this->isianLeading);
        if ($kodeLama !== null && KelolaLeading::kodeTerpakai($kodeLama) > 0) {
            $this->isianLeading['kode'] = $kodeLama; // Kode terpakai tidak bisa diganti (input terkunci di layar).
        }

        try {
            $hasil = KelolaLeading::simpan($this->isianLeading, $kodeLama, StubData::currentUser(), $this->versi);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kolom => $pesan) {
                $this->addError("isianLeading.{$kolom}", $pesan[0]);
            }

            return;
        } catch (PengaturanBerubah) {
            $this->muatUlang();

            return;
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        } catch (RuntimeException $e) {
            report($e);
            $this->dispatch('notify', message: 'Leading gagal disimpan. Isian masih ada di layar — coba simpan lagi.', type: 'error');

            return;
        }

        $this->batalLeading();
        if ($hasil['ringkasan'] === null) {
            $this->dispatch('notify', message: 'Tidak ada yang berubah.', type: 'info');

            return;
        }
        unset($this->leading);
        $this->dispatch('notify', message: 'Leading '.$hasil['leading']['kode'].' '.$hasil['leading']['nama_unit'].($kodeLama === null ? ' ditambahkan.' : ' diperbarui.'), type: 'success');
    }

    public function bukaTautan(string $kode): void
    {
        if (collect($this->leading)->firstWhere('kode', '===', $kode) === null) {
            return;
        }

        $this->batalLeading();
        $this->konfirmasiNonaktif = null;
        $this->tautanLeading = $this->tautanLeading === $kode ? null : $kode;
        $this->akunDitautkan = '';
    }

    public function tutupTautan(): void
    {
        $this->reset('tautanLeading', 'akunDitautkan');
    }

    /**
     * Akun leading yang bisa ditautkan ke leading $kode: berperan leading, belum tertaut, aktif lebih dulu.
     *
     * @return list<array<string, mixed>>
     */
    public function calonTautan(string $kode): array
    {
        return collect($this->akun)
            ->filter(fn (array $a) => $a['role'] === 'leading' && ! in_array($kode, $a['leadings'], true))
            ->sortBy([['is_active', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    /** Tautkan akun terpilih ke leading yang panelnya terbuka (hanya leading aktif & akun berperan leading). */
    public function tautkanAkun(): void
    {
        if ($this->berubahDiTempatLain()) {
            return;
        }
        $this->resetErrorBag('akunDitautkan');
        if ($this->tautanLeading === null || $this->akunDitautkan === '') {
            $this->addError('akunDitautkan', 'Pilih akun leading yang akan ditautkan.');

            return;
        }

        $keterangan = $this->jalankanTautan(fn (array $pengguna) => KelolaLeading::tautkan($this->tautanLeading, (int) $this->akunDitautkan, $pengguna, $this->versi));
        if ($keterangan !== null) {
            $this->akunDitautkan = '';
            $this->dispatch('notify', message: $keterangan.'.', type: 'success');
        }
    }

    /** Lepas tautan akun $id dari leading yang panelnya terbuka. */
    public function lepasTautan(int $id): void
    {
        if ($this->berubahDiTempatLain() || $this->tautanLeading === null) {
            return;
        }

        $hasil = $this->jalankanTautan(fn (array $pengguna) => KelolaLeading::lepas($this->tautanLeading, $id, $pengguna, $this->versi));
        if ($hasil !== null) {
            $this->dispatch('notify', message: $hasil['keterangan'].'.', type: 'success');
            if ($hasil['tanpa_tautan']) {
                $nama = collect($this->akun)->firstWhere('id', $id)['name'] ?? 'Akun ini';
                $this->dispatch('notify', message: "{$nama} kini belum tertaut ke leading mana pun — akun ini belum bisa membuat kegiatan.", type: 'info');
            }
        }
    }

    /**
     * Jalankan aksi tautan KelolaLeading; galat ke `akunDitautkan`/notify, hasil null bila gagal.
     *
     * @param  callable(array<string, mixed>): mixed  $aksi
     */
    private function jalankanTautan(callable $aksi): mixed
    {
        try {
            $hasil = $aksi(StubData::currentUser());
        } catch (ValidationException $e) {
            $this->addError('akunDitautkan', collect($e->errors())->flatten()->first());

            return null;
        } catch (PengaturanBerubah) {
            $this->muatUlang();

            return null;
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return null;
        } catch (RuntimeException $e) {
            report($e);
            $this->dispatch('notify', message: 'Tautan akun gagal disimpan — coba lagi.', type: 'error');

            return null;
        }
        unset($this->akun);

        return $hasil;
    }

    /** Nonaktifkan meminta konfirmasi dulu (dampaknya ditampilkan); mengaktifkan kembali langsung. */
    public function ubahStatusLeading(string $kode): void
    {
        if ($this->berubahDiTempatLain()) {
            return;
        }
        $leading = collect($this->leading)->firstWhere('kode', '===', $kode);
        if ($leading === null) {
            return;
        }
        if ($leading['is_active'] && $this->konfirmasiNonaktif !== $kode) {
            $this->batalLeading();
            $this->konfirmasiNonaktif = $kode;

            return;
        }

        $this->konfirmasiNonaktif = null;
        $aktif = ! $leading['is_active'];
        try {
            KelolaLeading::ubahStatus($kode, $aktif, StubData::currentUser(), $this->versi);
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: collect($e->errors())->flatten()->first(), type: 'error');

            return;
        } catch (PengaturanBerubah) {
            $this->muatUlang();

            return;
        } catch (AuthorizationException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        } catch (RuntimeException $e) {
            report($e);
            $this->dispatch('notify', message: 'Status leading gagal disimpan — coba lagi.', type: 'error');

            return;
        }

        $label = "Leading {$kode} {$leading['nama_unit']}";
        unset($this->leading);
        $this->dispatch('notify', message: $label.($aktif ? ' aktif kembali — bisa dipilih untuk kegiatan.' : ' dinonaktifkan — tidak bisa dipilih untuk kegiatan baru.'), type: 'success');
    }

    /**
     * Dampak menonaktifkan leading (KelolaLeading::dampakNonaktif).
     *
     * @return array{kegiatan: int, akun: list<string>}
     */
    public function dampakNonaktif(string $kode): array
    {
        return KelolaLeading::dampakNonaktif($kode);
    }

    /** Banyaknya kegiatan (semua tahun) & akun yang memakai kode leading — kode yang terpakai tidak bisa diganti. */
    public function kodeTerpakai(string $kode): int
    {
        return KelolaLeading::kodeTerpakai($kode);
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

    public function rendering(): void
    {
        $this->versi = self::sidik();
    }

    /** Sidik isi data leading & akun (KelolaLeading::sidik; waktu masuk tidak dihitung sebagai perubahan). */
    private static function sidik(): string
    {
        return KelolaLeading::sidik();
    }

    /**
     * Data berubah di tab/admin lain sejak terakhir ditampilkan: aksi dibatalkan, tampilan dimuat ulang, isian formulir
     * yang sedang diketik tetap.
     */
    private function berubahDiTempatLain(): bool
    {
        if (self::sidik() === $this->versi) {
            return false;
        }

        $this->muatUlang();

        return true;
    }

    private function muatUlang(): void
    {
        unset($this->leading, $this->akun);
        $this->konfirmasiNonaktif = null;
        $this->dispatch('notify', message: 'Data leading & akun baru saja diubah di tempat lain. Tampilan sudah dimuat ulang — periksa lalu ulangi perubahan Anda.', type: 'error');
    }

    public function render()
    {
        return view('livewire.pengaturan.leading-user')->title('Leading & User');
    }
}
