<?php

namespace App\Support\Pengaturan;

use App\Contracts\TahunAnggaranRepository;
use App\Contracts\TemplateCoverRepository;
use App\Support\Dokumen\TeksCover;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Simpan konfigurasi template cover di server (PRD §7A, tabel cover_templates; hanya admin, tahun terkunci hanya bisa
 * dilihat) — dipakai tab Cover Pengaturan › Template Dokumen: unggah gambar JPG/PNG ≤ MAKS_KB minimal 600 px dengan
 * kotak teks bawaan (`tambah`), simpan tata letak kotak teks (`simpanTataLetak`: selalu TeksCover::rapikan, susunan
 * kosong ditolak, ringkasan perubahan `ringkasTataLetak()` di riwayat), dan hapus (`hapus`: default pindah ke cover
 * terbaru). Riwayat `unggah_cover`/`ubah_tata_letak_cover`/`hapus_cover`.
 */
final class SimpanTemplateCover
{
    /** Batas ukuran gambar cover (KB). */
    public const MAKS_KB = 5120;

    /** @return array<string, list<string>> */
    public static function aturan(): array
    {
        return [
            'gambar' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:'.self::MAKS_KB, 'dimensions:min_width=600,min_height=600'],
            'nama' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public static function pesan(): array
    {
        return [
            'gambar.required' => 'Pilih gambar desain cover.',
            'gambar.image' => 'Berkas harus berupa gambar JPG atau PNG.',
            'gambar.mimes' => 'Berkas harus berupa gambar JPG atau PNG.',
            'gambar.max' => 'Ukuran gambar maksimal '.(self::MAKS_KB / 1024).' MB.',
            'gambar.dimensions' => 'Gambar terlalu kecil — minimal 600 piksel di setiap sisi agar tajam saat dicetak.',
            'nama.required' => 'Beri nama cover, mis. “Cover default 2026”.',
            'nama.max' => 'Nama cover maksimal 100 karakter.',
        ];
    }

    /**
     * @param  array{role: string}  $pengguna
     * @return array<string, mixed> Cover tersimpan (bentuk TemplateCoverRepository).
     *
     * @throws ValidationException Berkunci gambar/nama.
     */
    public static function tambah(int $tahun, ?UploadedFile $gambar, string $nama, array $pengguna): array
    {
        self::pastikanBoleh($tahun, $pengguna);
        Validator::make(['gambar' => $gambar, 'nama' => trim($nama)], self::aturan(), self::pesan())->validate();

        $repo = app(TemplateCoverRepository::class);
        $cover = $repo->temukan($repo->tambah($tahun, trim($nama), $gambar, TeksCover::bawaan()));
        RiwayatPengaturan::catat('unggah_cover', "Cover “{$cover['nama']}” diunggah ({$cover['lebar']} × {$cover['tinggi']} piksel)".($cover['is_default'] ? ', menjadi cover default' : '').'.');

        return $cover;
    }

    /**
     * @param  list<array<string, mixed>>  $kotak  Kotak teks dari editor (dirapikan di sini).
     * @param  array{role: string}  $pengguna
     * @return array<string, mixed> Cover sesudah disimpan.
     *
     * @throws ValidationException Cover tidak ada atau susunan kosong (`kotak`).
     */
    public static function simpanTataLetak(string $id, array $kotak, array $pengguna): array
    {
        $repo = app(TemplateCoverRepository::class);
        $cover = $repo->temukan($id) ?? throw ValidationException::withMessages(['kotak' => 'Cover tidak ditemukan — muat ulang halaman.']);
        self::pastikanBoleh($cover['tahun'], $pengguna);

        $rapi = TeksCover::rapikan($kotak);
        if ($rapi === []) {
            throw ValidationException::withMessages(['kotak' => 'Tampilkan minimal satu kotak teks di cover.']);
        }

        $repo->ubahTeks($id, $rapi);
        RiwayatPengaturan::catat('ubah_tata_letak_cover', "Tata letak “{$cover['nama']}” diubah — ".self::ringkasTataLetak(TeksCover::rapikan($cover['teks_items']), $rapi).'.');

        return $repo->temukan($id);
    }

    /**
     * @param  array{role: string}  $pengguna
     * @return array{cover: array<string, mixed>, pengganti: array<string, mixed>|null}|null null bila cover tidak ada.
     */
    public static function hapus(string $id, array $pengguna): ?array
    {
        $repo = app(TemplateCoverRepository::class);
        $cover = $repo->temukan($id);
        if ($cover === null) {
            return null;
        }
        self::pastikanBoleh($cover['tahun'], $pengguna);

        $repo->hapus($id);
        $pengganti = $cover['is_default'] ? collect($repo->semua($cover['tahun']))->firstWhere('is_default', true) : null;
        RiwayatPengaturan::catat('hapus_cover', "Cover “{$cover['nama']}” dihapus".($pengganti ? "; “{$pengganti['nama']}” menjadi cover default" : '').'.');

        return ['cover' => $cover, 'pengganti' => $pengganti];
    }

    /**
     * "2 kotak diubah, disembunyikan: “…”, ditampilkan: “…”" untuk riwayat.
     *
     * @param  list<array<string, mixed>>  $lama
     * @param  list<array<string, mixed>>  $baru
     */
    public static function ringkasTataLetak(array $lama, array $baru): string
    {
        $perKolom = fn (array $kotak) => array_column($kotak, null, 'field');
        [$lama, $baru] = [$perKolom($lama), $perKolom($baru)];
        $nama = fn (array $kolom) => implode(', ', array_map(fn ($f) => '“'.str_replace(['“', '”'], '', TeksCover::FIELD[$f]).'”', $kolom));
        $diubah = count(array_filter(array_keys(array_intersect_key($baru, $lama)), fn ($f) => $baru[$f] !== $lama[$f]));
        $bagian = [$diubah.' kotak diubah'];

        if (($hilang = array_keys(array_diff_key($lama, $baru))) !== []) {
            $bagian[] = 'disembunyikan: '.$nama($hilang);
        }
        if (($muncul = array_keys(array_diff_key($baru, $lama))) !== []) {
            $bagian[] = 'ditampilkan: '.$nama($muncul);
        }

        return implode(', ', $bagian);
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanBoleh(int $tahun, array $pengguna): void
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Template dokumen diatur admin.');
        }
        if (collect(app(TahunAnggaranRepository::class)->semua())->firstWhere('tahun', $tahun)['is_locked'] ?? false) {
            throw new AuthorizationException("Tahun Anggaran {$tahun} terkunci — templatenya hanya bisa dilihat.");
        }
    }
}
