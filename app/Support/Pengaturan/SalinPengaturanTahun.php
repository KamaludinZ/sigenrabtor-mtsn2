<?php

namespace App\Support\Pengaturan;

use App\Contracts\DaftarIsiRepository;
use App\Contracts\DasarHukumRepository;
use App\Contracts\NomorHalamanRepository;
use App\Contracts\PejabatRepository;
use App\Contracts\PenomoranDokumenRepository;
use App\Contracts\PilihanTemplateRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Contracts\TemplateCoverRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Enums\SumberDana;
use App\Support\Dokumen\DaftarIsi;
use App\Support\Dokumen\FormatKakTor;
use App\Support\Dokumen\NomorHalaman;
use App\Support\Master\JenisKode;
use App\Support\Master\SalinMasterKode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Salin master & pengaturan dari tahun anggaran lain (PRD Fase 5 "Tahun Anggaran Aktif — master dapat disalin dari tahun
 * sebelumnya", alur 1 & "Ganti tahun anggaran"): tahun baru tidak perlu diisi ulang dari nol. Per `BAGIAN` (urutan =
 * urutan salin; template sebelum pilihan template agar pilihannya bisa dicocokkan) hanya yang di tahun tujuan masih kosong
 * — isian tahun tujuan tidak pernah tertimpa:
 * - `master_kode` lewat SalinMasterKode (per kelompok jenis), `pejabat` lewat KelolaPejabat::salin (peran yang masih
 *   kosong) + blok Mengetahui selama tujuan masih bawaan;
 * - `dasar_hukum` (butir, paragraf baku, kalimat penutup — teks mentah, `{tahun}` ikut tahun tujuan; SK RKTM/RKAM tetap
 *   per tahun) selama tujuan belum diatur;
 * - `cover` & `master_kak` (berkas disalin, default tetap default) selama tujuan belum punya template;
 * - `daftar_isi` & `nomor_halaman` selama tujuan masih bawaan;
 * - `pilihan_template` selama tujuan kosong — format KAK/TOR pilihan admin (FormatKakTor) dan id template yang dicocokkan
 *   ke template bernama (dan berjenis) sama di tujuan;
 * - `penomoran` (aturan pola/digit; nomor urut tidak ikut) selama tujuan belum mengubah aturan.
 * `rencana()` = keadaan tiap bagian (`STATUS`), `salin()` = khusus admin, tujuan terdaftar & tidak terkunci; bagian yang
 * gagal dilaporkan tanpa membatalkan bagian lain; riwayat `salin_pengaturan_tahun`.
 */
final class SalinPengaturanTahun
{
    /** Bagian → [label, keterangan]. */
    public const BAGIAN = [
        'master_kode' => ['Master kode', 'SK, IKSK, RKTM, EDM, dan 5 Budaya Mutu — per kelompok yang masih kosong.'],
        'pejabat' => ['Pejabat penanda tangan', 'Kepala, Ketua Komite, dan pejabat leading yang masih kosong, beserta blok Mengetahui.'],
        'dasar_hukum' => ['Dasar hukum & latar belakang baku', 'Butir dasar hukum, paragraf baku, dan kalimat penutup; {tahun} otomatis menjadi tahun tujuan. SK RKTM/RKAM tetap diisi per tahun.'],
        'cover' => ['Template cover', 'Gambar cover beserta tata letak kotak teksnya; cover default tetap default.'],
        'master_kak' => ['Master KAK/TOR', 'Berkas .docx ber-variabel per jenis; default tiap jenis tetap default.'],
        'daftar_isi' => ['Daftar isi buku', 'Konfigurasi bawaan dan khusus per leading × sumber dana.'],
        'nomor_halaman' => ['Nomor halaman', 'Format, posisi, nomor awal, dan halaman tanpa nomor.'],
        'pilihan_template' => ['Pilihan template & format KAK/TOR', 'Format KAK/TOR tahun itu serta template per jenis kegiatan & khusus leading (dicocokkan dengan template bernama sama di tahun tujuan).'],
        'penomoran' => ['Aturan penomoran dokumen', 'Pola & digit nomor per jenis dokumen; nomor urut tahun tujuan mulai dari awal.'],
    ];

    /** Keadaan bagian → label. `salin`/`sebagian` = ada yang akan disalin. */
    public const STATUS = [
        'salin' => 'Akan disalin',
        'sebagian' => 'Disalin yang masih kosong',
        'terisi' => 'Sudah terisi',
        'kosong' => 'Tidak ada di tahun sumber',
    ];

    /** Hasil per bagian sesudah salin → label. */
    public const HASIL = ['disalin' => 'Disalin', 'dilewati' => 'Tidak diubah', 'gagal' => 'Gagal'];

    /**
     * Tahun sumber yang disarankan: tahun lain terdekat sebelum $ke yang punya sesuatu untuk disalin, lalu sesudahnya.
     */
    public static function sumber(int $ke): ?int
    {
        $lain = array_filter(array_column(app(TahunAnggaranRepository::class)->semua(), 'tahun'), fn (int $t) => $t !== $ke);
        $sebelum = array_filter($lain, fn (int $t) => $t < $ke);
        $sesudah = array_filter($lain, fn (int $t) => $t > $ke);
        rsort($sebelum);
        sort($sesudah);

        foreach ([...$sebelum, ...$sesudah] as $tahun) {
            foreach (array_keys(self::BAGIAN) as $bagian) {
                if (self::isi($bagian, $tahun) !== null) {
                    return $tahun;
                }
            }
        }

        return null;
    }

    /**
     * Keadaan tiap bagian bila $dari disalin ke $ke.
     *
     * @return array{dari: int, ke: int, terkunci: bool, bagian: list<array{kunci: string, label: string, keterangan: string, sumber: string|null, tujuan: string|null, status: string, label_status: string, bisa: bool}>, jumlah_bisa: int}
     *
     * @throws ValidationException Berkunci `dari` (sama dengan tujuan / tidak terdaftar) atau `tahun` (tujuan tidak terdaftar).
     */
    public static function rencana(int $dari, int $ke): array
    {
        self::pastikanTahun($dari, $ke);

        $bagian = [];
        foreach (self::BAGIAN as $kunci => [$label, $keterangan]) {
            $status = self::status($kunci, $dari, $ke);
            $bagian[] = [
                'kunci' => $kunci, 'label' => $label, 'keterangan' => $keterangan,
                'sumber' => self::isi($kunci, $dari), 'tujuan' => self::isi($kunci, $ke),
                'status' => $status, 'label_status' => self::STATUS[$status], 'bisa' => in_array($status, ['salin', 'sebagian'], true),
            ];
        }

        return [
            'dari' => $dari, 'ke' => $ke, 'terkunci' => TahunDipilih::terkunci($ke),
            'bagian' => $bagian, 'jumlah_bisa' => count(array_filter($bagian, fn (array $b) => $b['bisa'])),
        ];
    }

    /**
     * Salin bagian terpilih ($bagian kosong = semua yang bisa) dari $dari ke $ke.
     *
     * @param  list<string>|null  $bagian
     * @param  array{id?: int, name?: string, role: string}  $pengguna
     * @return array{dari: int, ke: int, hasil: list<array{kunci: string, label: string, status: string, label_status: string, jumlah: int, pesan: string}>, disalin: int, pesan: string}
     *
     * @throws AuthorizationException Bukan admin, tahun tujuan belum dibuat atau terkunci.
     * @throws ValidationException Berkunci `dari`/`tahun`/`bagian`.
     */
    public static function salin(int $dari, int $ke, ?array $bagian, array $pengguna): array
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Pengaturan tahun anggaran diatur admin.');
        }
        if (TahunDipilih::terkunci($ke)) {
            throw new AuthorizationException("Tahun Anggaran {$ke} terkunci — pengaturannya hanya bisa dilihat. Buka kuncinya dulu untuk menyalin.");
        }
        self::pastikanTahun($dari, $ke);
        $asing = array_diff($bagian ?? [], array_keys(self::BAGIAN));
        if ($asing !== []) {
            throw ValidationException::withMessages(['bagian' => 'Bagian tidak dikenal: '.implode(', ', $asing).'.']);
        }
        $dipilih = $bagian === null || $bagian === [] ? array_keys(self::BAGIAN) : $bagian;

        $hasil = [];
        foreach (self::BAGIAN as $kunci => [$label]) {
            $status = self::status($kunci, $dari, $ke);
            if (! in_array($kunci, $dipilih, true) || ! in_array($status, ['salin', 'sebagian'], true)) {
                $hasil[] = self::hasil($kunci, 'dilewati', 0, in_array($kunci, $dipilih, true) ? self::STATUS[$status].'.' : 'Tidak dipilih.');

                continue;
            }
            try {
                [$jumlah, $pesan] = self::jalankan($kunci, $dari, $ke, $pengguna);
                $hasil[] = self::hasil($kunci, $jumlah > 0 ? 'disalin' : 'dilewati', $jumlah, $pesan);
            } catch (RuntimeException|ValidationException|AuthorizationException $e) {
                $hasil[] = self::hasil($kunci, 'gagal', 0, $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage());
            }
        }

        $disalin = array_values(array_filter($hasil, fn (array $h) => $h['status'] === 'disalin'));
        $gagal = array_values(array_filter($hasil, fn (array $h) => $h['status'] === 'gagal'));
        if ($disalin !== []) {
            RiwayatPengaturan::catat('salin_pengaturan_tahun', "Pengaturan TA {$dari} disalin ke TA {$ke}: ".implode('; ', array_map(fn (array $h) => "{$h['label']} ({$h['pesan']})", $disalin))
                .($gagal !== [] ? '. Gagal: '.implode(', ', array_column($gagal, 'label')) : '').'.');
        }

        $pesan = match (true) {
            $disalin === [] && $gagal === [] => "Tidak ada yang disalin — pengaturan TA {$ke} yang dipilih sudah terisi atau tidak ada di TA {$dari}.",
            $disalin === [] => "Salin dari TA {$dari} gagal: ".implode(', ', array_column($gagal, 'label')).'.',
            default => count($disalin)." bagian pengaturan TA {$dari} disalin ke TA {$ke}".($gagal !== [] ? '; gagal: '.implode(', ', array_column($gagal, 'label')) : '').'.',
        };

        return ['dari' => $dari, 'ke' => $ke, 'hasil' => $hasil, 'disalin' => count($disalin), 'pesan' => $pesan];
    }

    /**
     * Ringkasan isi bagian di tahun $tahun; null = kosong (belum diatur / masih bawaan).
     */
    public static function isi(string $bagian, int $tahun): ?string
    {
        $jumlah = fn (int $n, string $satuan) => $n > 0 ? "{$n} {$satuan}" : null;

        return match ($bagian) {
            'master_kode' => self::isiMasterKode($tahun),
            'pejabat' => $jumlah(count(self::pejabatTerisi($tahun)), 'pejabat'),
            'dasar_hukum' => ($dh = app(DasarHukumRepository::class)->untukTahun($tahun))['diatur'] ? count($dh['dasar_hukum']).' butir dasar hukum' : null,
            'cover' => $jumlah(count(app(TemplateCoverRepository::class)->semua($tahun)), 'cover'),
            'master_kak' => $jumlah(count(app(TemplateDokumenRepository::class)->semua($tahun)), 'master'),
            'daftar_isi' => self::isiDaftarIsi($tahun),
            'nomor_halaman' => ($aturan = app(NomorHalamanRepository::class)->untuk($tahun)) === NomorHalaman::rapikan(NomorHalaman::BAWAAN) ? null : $aturan['format'],
            'pilihan_template' => self::isiPilihan($tahun),
            'penomoran' => $jumlah(count(app(PenomoranDokumenRepository::class)->aturan($tahun)), 'aturan jenis dokumen'),
            default => null,
        };
    }

    private static function status(string $bagian, int $dari, int $ke): string
    {
        if (self::isi($bagian, $dari) === null) {
            return 'kosong';
        }
        if (self::isi($bagian, $ke) === null) {
            return 'salin';
        }

        return match ($bagian) {
            'master_kode' => collect(SalinMasterKode::KELOMPOK)->contains(fn (array $k) => self::kelompokTerisi($k, $dari) && ! self::kelompokTerisi($k, $ke)) ? 'sebagian' : 'terisi',
            'pejabat' => array_diff_key(self::pejabatTerisi($dari), self::pejabatTerisi($ke)) !== [] ? 'sebagian' : 'terisi',
            default => 'terisi',
        };
    }

    /**
     * @param  array{id?: int, name?: string, role: string}  $pengguna
     * @return array{0: int, 1: string} Jumlah yang disalin & ringkasannya.
     */
    private static function jalankan(string $bagian, int $dari, int $ke, array $pengguna): array
    {
        switch ($bagian) {
            case 'master_kode':
                $disalin = SalinMasterKode::salin($dari, $ke, $pengguna)['disalin'];

                return [array_sum($disalin), implode(', ', array_map(fn (string $j, int $n) => JenisKode::DAFTAR[$j]['label']." {$n}", array_keys($disalin), $disalin))];

            case 'pejabat':
                $jumlah = KelolaPejabat::salin($dari, $ke, $pengguna);
                $repo = app(PejabatRepository::class);
                $mengetahui = $repo->aturanMengetahui($dari);
                $ubahMengetahui = $repo->aturanMengetahui($ke) === array_fill_keys(SumberDana::urutan(), true) && $mengetahui !== $repo->aturanMengetahui($ke);
                if ($ubahMengetahui) {
                    $repo->simpanAturanMengetahui($ke, $mengetahui);
                }

                return [$jumlah + (int) $ubahMengetahui, "{$jumlah} pejabat".($ubahMengetahui ? ' + blok Mengetahui' : '')];

            case 'dasar_hukum':
                $sumber = app(DasarHukumRepository::class)->untukTahun($dari);
                app(DasarHukumRepository::class)->simpan($ke, [
                    'dasar_hukum' => array_map(fn (array $b) => ['teks' => $b['teks']], $sumber['dasar_hukum']),
                    'latar_belakang' => $sumber['latar_belakang'],
                    'kalimat_penutup' => $sumber['kalimat_penutup'],
                ]);

                return [max(1, count($sumber['dasar_hukum'])), count($sumber['dasar_hukum']).' butir + latar belakang baku'];

            case 'cover':
                $repo = app(TemplateCoverRepository::class);
                $jumlah = 0;
                $hilang = [];
                foreach ($repo->semua($dari) as $cover) {
                    if (($berkas = self::berkas($cover['gambar_path'], basename($cover['gambar_path']))) === null) {
                        $hilang[] = $cover['nama'];

                        continue;
                    }
                    $repo->tambah($ke, $cover['nama'], $berkas, $cover['teks_items']);
                    $jumlah++;
                }

                return [$jumlah, "{$jumlah} cover".self::teksHilang($hilang)];

            case 'master_kak':
                $repo = app(TemplateDokumenRepository::class);
                $jumlah = 0;
                $hilang = [];
                foreach ($repo->semua($dari) as $master) {
                    if (($berkas = self::berkas($master['file_path'], $master['nama_berkas'] ?? basename($master['file_path']))) === null) {
                        $hilang[] = $master['nama'];

                        continue;
                    }
                    // Pemilik, catatan versi, default, kemunculan variabel, & status ikut tersalin.
                    $baru = $repo->tambah($ke, $master['nama'], $master['jenis'], $berkas, $master['variabel'] ?? [], [
                        'leading' => $master['leading'] ?? null, 'catatan' => $master['catatan'] ?? null, 'default' => (bool) $master['is_default'],
                        'jumlah' => $master['jumlah'] ?? [], 'oleh' => ['id' => $pengguna['id'] ?? null, 'nama' => $pengguna['name'] ?? null],
                    ]);
                    if (! ($master['aktif'] ?? true)) {
                        $repo->ubahStatus($baru, false);
                    }
                    $jumlah++;
                }

                return [$jumlah, "{$jumlah} master".self::teksHilang($hilang)];

            case 'daftar_isi':
                $repo = app(DaftarIsiRepository::class);
                $repo->simpanBawaan($ke, $repo->bawaan($dari));
                $khusus = $repo->khusus($dari);
                foreach ($khusus as $buku => $konfigurasi) {
                    [$leading, $sumber] = explode('|', (string) $buku, 2) + [1 => ''];
                    $repo->simpanKhusus($ke, $leading, $sumber, $konfigurasi);
                }

                return [1 + count($khusus), 'bawaan'.($khusus !== [] ? ' + '.count($khusus).' khusus' : '')];

            case 'nomor_halaman':
                $aturan = app(NomorHalamanRepository::class)->untuk($dari);
                app(NomorHalamanRepository::class)->simpan($ke, $aturan);

                return [1, $aturan['format']];

            case 'pilihan_template':
                $repo = app(PilihanTemplateRepository::class);
                $format = FormatKakTor::pilihanTahun($dari);
                $salinFormat = $format !== null && FormatKakTor::pilihanTahun($ke) === null;
                if ($salinFormat) {
                    $repo->simpanFormat($ke, $format);
                }
                $pilihan = self::cocokkanPilihan($repo->untuk($dari), $ke);
                $jumlah = self::jumlahPilihan($pilihan);
                if ($jumlah > 0) {
                    $repo->simpan($ke, $pilihan);
                }
                $teksFormat = $salinFormat ? FormatKakTor::label($format) : null;
                if ($jumlah === 0 && self::jumlahPilihan($repo->untuk($dari)) > 0) {
                    return [(int) $salinFormat, ucfirst(($teksFormat ? "{$teksFormat}; " : '')."template bernama sama belum ada di TA {$ke} — salin template cover/master KAK/TOR dulu.")];
                }

                return [$jumlah + (int) $salinFormat, implode(' + ', array_filter([$teksFormat, $jumlah > 0 ? "{$jumlah} pilihan" : null]))];

            case 'penomoran':
                $aturan = app(PenomoranDokumenRepository::class)->aturan($dari);
                app(PenomoranDokumenRepository::class)->simpanAturan($ke, $aturan);

                return [count($aturan), count($aturan).' jenis dokumen'];
        }

        return [0, ''];
    }

    /**
     * Pilihan template $dari dengan id template diganti id template bernama (dan berjenis) sama di tahun $ke; yang tidak
     * ditemukan menjadi null, baris khusus leading tanpa template dibuang.
     *
     * @param  array{jenis: array<string, array{kak?: string|null, cover?: string|null}>, leading: list<array<string, mixed>>}  $pilihan
     * @return array{jenis: array<string, array{kak: string|null, cover: string|null}>, leading: list<array{leading: string, jenis: string, kak: string|null, cover: string|null}>}
     */
    private static function cocokkanPilihan(array $pilihan, int $ke): array
    {
        $masterLama = app(TemplateDokumenRepository::class);
        $coverLama = app(TemplateCoverRepository::class);
        $masterBaru = $masterLama->semua($ke);
        $coverBaru = $coverLama->semua($ke);
        $kak = function (?string $id) use ($masterLama, $masterBaru): ?string {
            $lama = $id === null ? null : $masterLama->temukan($id);

            return $lama === null ? null : (collect($masterBaru)->first(fn (array $t) => $t['nama'] === $lama['nama'] && $t['jenis'] === $lama['jenis'])['id'] ?? null);
        };
        $cover = function (?string $id) use ($coverLama, $coverBaru): ?string {
            $lama = $id === null ? null : $coverLama->temukan($id);

            return $lama === null ? null : (collect($coverBaru)->first(fn (array $c) => $c['nama'] === $lama['nama'])['id'] ?? null);
        };

        $jenis = array_map(fn (array $p) => ['kak' => $kak($p['kak'] ?? null), 'cover' => $cover($p['cover'] ?? null)], $pilihan['jenis']);
        $leading = [];
        foreach ($pilihan['leading'] as $p) {
            $baris = ['leading' => (string) ($p['leading'] ?? ''), 'jenis' => (string) ($p['jenis'] ?? ''), 'kak' => $kak($p['kak'] ?? null), 'cover' => $cover($p['cover'] ?? null)];
            if ($baris['kak'] !== null || $baris['cover'] !== null) {
                $leading[] = $baris;
            }
        }

        return ['jenis' => $jenis, 'leading' => $leading];
    }

    /** Ringkasan pilihan template & format KAK/TOR pilihan admin tahun itu; null = semua bawaan. */
    private static function isiPilihan(int $tahun): ?string
    {
        $jumlah = self::jumlahPilihan(app(PilihanTemplateRepository::class)->untuk($tahun));
        $format = FormatKakTor::pilihanTahun($tahun);
        $bagian = array_filter([$format ? FormatKakTor::label($format) : null, $jumlah > 0 ? "{$jumlah} pilihan" : null]);

        return $bagian === [] ? null : implode(' + ', $bagian);
    }

    /** @param  array{jenis?: array<string, array<string, mixed>>, leading?: list<array<string, mixed>>}  $pilihan */
    private static function jumlahPilihan(array $pilihan): int
    {
        $terisi = fn (array $p) => count(array_filter([$p['kak'] ?? null, $p['cover'] ?? null], fn (mixed $v) => filled($v)));

        return array_sum(array_map($terisi, $pilihan['jenis'] ?? [])) + array_sum(array_map($terisi, $pilihan['leading'] ?? []));
    }

    private static function isiMasterKode(int $tahun): ?string
    {
        $jumlah = array_filter(array_map(fn (string $j) => count(JenisKode::baris($j, $tahun)), array_combine(array_keys(JenisKode::DAFTAR), array_keys(JenisKode::DAFTAR))));

        return $jumlah === [] ? null : implode(' · ', array_map(fn (string $j, int $n) => JenisKode::DAFTAR[$j]['label']." {$n}", array_keys($jumlah), $jumlah));
    }

    /** @param  list<string>  $kelompok */
    private static function kelompokTerisi(array $kelompok, int $tahun): bool
    {
        return collect($kelompok)->contains(fn (string $j) => JenisKode::baris($j, $tahun) !== []);
    }

    /**
     * Peran pejabat yang namanya terisi di tahun $tahun.
     *
     * @return array<string, true> kepala|komite|leading:{kode} → true
     */
    private static function pejabatTerisi(int $tahun): array
    {
        $pejabat = app(PejabatRepository::class)->untukTahun($tahun);
        $terisi = [];
        foreach (['kepala', 'komite'] as $peran) {
            if (filled($pejabat[$peran]['nama'])) {
                $terisi[$peran] = true;
            }
        }
        foreach ($pejabat['leading'] as $kode => $p) {
            if (filled($p['nama'])) {
                $terisi["leading:{$kode}"] = true;
            }
        }

        return $terisi;
    }

    private static function isiDaftarIsi(int $tahun): ?string
    {
        $repo = app(DaftarIsiRepository::class);
        $diubah = $repo->bawaan($tahun) !== DaftarIsi::rapikan(DaftarIsi::BAWAAN);
        $khusus = count($repo->khusus($tahun));

        return ! $diubah && $khusus === 0 ? null : ($diubah ? 'bawaan diubah' : 'bawaan').($khusus > 0 ? " + {$khusus} khusus" : '');
    }

    /** Berkas tersimpan di disk local sebagai unggahan (disalin oleh repositori), null bila berkasnya hilang. */
    private static function berkas(?string $path, string $nama): ?UploadedFile
    {
        $disk = Storage::disk('local');
        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        return new UploadedFile($disk->path($path), $nama, null, null, true);
    }

    /** @param  list<string>  $hilang */
    private static function teksHilang(array $hilang): string
    {
        return $hilang === [] ? '' : '; berkas tidak ditemukan: '.implode(', ', $hilang);
    }

    /** @return array{kunci: string, label: string, status: string, label_status: string, jumlah: int, pesan: string} */
    private static function hasil(string $kunci, string $status, int $jumlah, string $pesan): array
    {
        return ['kunci' => $kunci, 'label' => self::BAGIAN[$kunci][0], 'status' => $status, 'label_status' => self::HASIL[$status], 'jumlah' => $jumlah, 'pesan' => $pesan];
    }

    private static function pastikanTahun(int $dari, int $ke): void
    {
        $terdaftar = array_column(app(TahunAnggaranRepository::class)->semua(), 'tahun');
        if (! in_array($ke, $terdaftar, true)) {
            throw ValidationException::withMessages(['tahun' => "Tahun Anggaran {$ke} belum dibuat."]);
        }
        if ($dari === $ke || ! in_array($dari, $terdaftar, true)) {
            throw ValidationException::withMessages(['dari' => 'Pilih tahun anggaran lain sebagai sumber salinan.']);
        }
    }
}
