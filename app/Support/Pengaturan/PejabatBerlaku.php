<?php

namespace App\Support\Pengaturan;

use App\Contracts\PejabatRepository;
use App\Contracts\PenggunaRepository;
use App\Support\MasterKode;
use App\Support\Stub\StubData;

/**
 * Pejabat penanda tangan yang berlaku pada satu tahun anggaran (PRD F8, aturan 4 "pejabat yang berlaku tahun itu"; bawaan
 * tahun aktif) — satu-satunya jalur dokumen & pengaturan mengambil pejabat: pejabat tahun itu dan aturan blok "Mengetahui"
 * dibaca sekali dari PejabatRepository per objek. Bentuk mentah (NIP tanpa pemisah) lewat `$kepala`/`$komite`/`$leading`;
 * bentuk cetak (NIP berformat) lewat `pimpinan()`/`pembuat()`/`mengetahui()`.
 *
 * Pejabat leading juga untuk leading yang kini nonaktif (pejabat tersimpan tahun itu, atau jabatan master leading), agar
 * dokumen kegiatan lamanya tetap lengkap. Selama nama pejabat leading belum diisi admin, kolom Pembuat memakai nama akun
 * leading yang tidak bergantung pada siapa yang membuka dokumen: pembuat kegiatan (`user_id`; kegiatan belum tersimpan =
 * pengguna yang sedang menyusunnya) bila akunnya aktif, berperan leading, dan tertaut ke leading itu — selain itu satu-
 * satunya akun leading aktif yang tertaut; lebih dari satu atau tidak ada = kosong (admin perlu mengisi pejabat leading).
 */
final class PejabatBerlaku
{
    /** @var array<string, array{jabatan: string, nama: string|null, nip: string|null}|null> */
    private array $leadingLain = [];

    /** @var list<array<string, mixed>>|null */
    private ?array $akun = null;

    /**
     * @param  array{jabatan: string, nama: string|null, nip: string|null}  $kepala
     * @param  array{jabatan: string, nama: string|null, nip: string|null}  $komite
     * @param  array<string, array{jabatan: string, nama: string|null, nip: string|null}>  $leading  Leading aktif.
     * @param  array<string, bool>  $aturanMengetahui  Sumber dana → blok "Mengetahui" tampil.
     */
    private function __construct(
        public readonly int $tahun,
        public readonly array $kepala,
        public readonly array $komite,
        public readonly array $leading,
        public readonly array $aturanMengetahui,
    ) {}

    /** Pejabat tahun $tahun; null = tahun anggaran aktif. */
    public static function tahun(?int $tahun = null): self
    {
        $tahun ??= StubData::tahunAktif()['tahun'];
        $repo = app(PejabatRepository::class);
        $pejabat = $repo->untukTahun($tahun);

        return new self($tahun, $pejabat['kepala'], $pejabat['komite'], $pejabat['leading'], $repo->aturanMengetahui($tahun));
    }

    /**
     * Kepala Madrasah & Ketua Komite dengan NIP berformat cetak.
     *
     * @return array{kepala: array{jabatan: string, nama: string|null, nip: string|null}, komite: array{jabatan: string, nama: string|null, nip: string|null}}
     */
    public function pimpinan(): array
    {
        return ['kepala' => self::cetak($this->kepala), 'komite' => self::cetak($this->komite)];
    }

    /**
     * Pejabat leading $kode (mentah) — leading aktif dari pejabat tahun ini; leading nonaktif dari pejabat tersimpan tahun
     * ini atau jabatan master leading. Null bila kode tidak dikenal.
     *
     * @return array{jabatan: string, nama: string|null, nip: string|null}|null
     */
    public function pejabatLeading(?string $kode): ?array
    {
        if ($kode === null || $kode === '') {
            return null;
        }
        if (isset($this->leading[$kode])) {
            return $this->leading[$kode];
        }
        if (! array_key_exists($kode, $this->leadingLain)) {
            $master = MasterKode::leading($kode);
            $this->leadingLain[$kode] = $master === null ? null
                : (app(PejabatRepository::class)->leadingTersimpan($this->tahun, $master['kode']) ?? ['jabatan' => $master['jabatan_penanda_tangan'], 'nama' => null, 'nip' => null]);
        }

        return $this->leadingLain[$kode];
    }

    /**
     * Kolom "Pembuat" dokumen kegiatan: pejabat leading kegiatan (NIP berformat); selama namanya kosong, nama akun leading
     * (lihat keterangan kelas) tanpa NIP. Null bila leading kegiatan belum dipilih/tidak dikenal.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array{jabatan: string, nama: string|null, nip: string|null}|null
     */
    public function pembuat(array $kegiatan): ?array
    {
        $kode = MasterKode::leading($kegiatan['leading'] ?? null)['kode'] ?? null;
        $pejabat = $this->pejabatLeading($kode);
        if ($pejabat === null) {
            return null;
        }
        if ($pejabat['nama']) {
            return self::cetak($pejabat);
        }

        return ['jabatan' => $pejabat['jabatan'], 'nama' => $this->namaAkunLeading($kode, self::penyusun($kegiatan)), 'nip' => null];
    }

    /**
     * Blok "Mengetahui" (Ketua Komite, NIP berformat) dokumen sumber dana $sumber menurut aturan tahun ini (bawaan tampil);
     * null bila tidak ditampilkan. Tanpa sumber dana: tampil.
     *
     * @return array{jabatan: string, nama: string|null, nip: string|null}|null
     */
    public function mengetahui(?string $sumber): ?array
    {
        return $sumber === null || ($this->aturanMengetahui[$sumber] ?? true) ? self::cetak($this->komite) : null;
    }

    /**
     * Nama akun leading pengganti pejabat leading yang belum diisi: akun $penyusun bila aktif, berperan leading, dan
     * tertaut ke $kode; selain itu satu-satunya akun leading aktif yang tertaut ke $kode.
     */
    public function namaAkunLeading(string $kode, ?int $penyusun): ?string
    {
        $this->akun ??= app(PenggunaRepository::class)->semua();
        $tertaut = array_values(array_filter($this->akun, fn (array $a) => $a['is_active'] && $a['role'] === 'leading' && in_array($kode, $a['leadings'], true)));
        $akun = ($penyusun !== null ? collect($tertaut)->firstWhere('id', $penyusun) : null) ?? (count($tertaut) === 1 ? $tertaut[0] : null);

        return $akun['name'] ?? null;
    }

    /** Id akun penyusun kegiatan: `user_id` tersimpan; kegiatan belum tersimpan = pengguna yang sedang masuk. */
    private static function penyusun(array $kegiatan): ?int
    {
        $id = filter_var($kegiatan['user_id'] ?? null, FILTER_VALIDATE_INT);
        if ($id !== false) {
            return $id;
        }

        return filled($kegiatan['id'] ?? null) ? null : StubData::currentUser()['id'];
    }

    /**
     * @param  array{jabatan: string, nama: string|null, nip: string|null}  $pejabat
     * @return array{jabatan: string, nama: string|null, nip: string|null}
     */
    private static function cetak(array $pejabat): array
    {
        return ['jabatan' => $pejabat['jabatan'], 'nama' => $pejabat['nama'], 'nip' => $pejabat['nip'] ? Nip::format($pejabat['nip']) : null];
    }
}
