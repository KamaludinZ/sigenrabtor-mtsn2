<?php

namespace App\Support\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Master\IkskPerSk;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Rab\SimpanSumberDana;
use App\Support\Teks;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Ubah data kegiatan yang sudah ada (PRD §16 "Perbaikan di Data": leading memperbaiki data, bukan file Word) — dipakai
 * Simpan Draf Data Kegiatan (`Form::simpan` untuk kegiatan tersimpan) dan endpoint `kegiatan.ubah`
 * (`PATCH /kegiatan/{id}`; bacaan + versi lewat `kegiatan.data`):
 * - hanya isian ISIAN (status lewat alur status; RAB, termin, matrik & jadwal lewat penyimpanannya sendiri) — kunci lain
 *   ditolak (galat `isian`); yang tidak dikirim tetap seperti tersimpan;
 * - `rapikan()` = perapian yang sama dengan form: uraian & konteks Teks::rapikan, pelaksanaan SimpanPelaksanaan::rapikan
 *   (bulan & tanggal pelaporan turunan), sumber dana SimpanSumberDana::rapikan, panitia SusunanPanitia::rapikan, nama cover
 *   kapital & mengikuti nama kegiatan selama tidak manual (nama cover tanpa `cover_manual` = manual bila berbeda), IKSK
 *   disesuaikan saat SK diganti (IkskPerSk::sesudahGantiSk);
 * - akses AksesKegiatan, status Draf/Dikembalikan & tahun anggaran terbuka (KunciKegiatan) → AuthorizationException;
 *   `$versi` (= `versi()` data saat dibaca) usang → PengaturanBerubah; aturan ValidasiKegiatan::periksa untuk isian yang
 *   berubah (antarkolom dari data utuh);
 * - status, riwayat, dan kolom lain tersimpan tidak tersentuh; tanpa perubahan tidak ditulis; penanda kode hilang gugur
 *   untuk kolom yang diisi lagi (KodeHilang::sisa).
 */
final class UbahDataKegiatan
{
    /** Isian data kegiatan yang bisa diubah (bentuk KegiatanRepository). */
    public const ISIAN = [
        'nama_kegiatan', 'nama_kegiatan_cover', 'cover_manual', 'latar_belakang', 'tujuan', 'sasaran', 'tanggal_dokumen',
        ...SimpanPelaksanaan::ISIAN,
        'leading', 'kode_rktm', 'kode_sk', 'kode_iksk', 'kode_edm', 'budaya_mutu',
        'konteks', 'sumber_dana', 'data_pagu_id', 'panitia',
    ];

    /** Bagian di luar PerbaikanKegiatan::BAGIAN untuk ringkasan bagian yang berubah. */
    private const BAGIAN_LAIN = ['konteks' => 'Catatan konteks', 'data_pagu_id' => 'Sumber dana & pagu'];

    /**
     * Sidik data kegiatan tersimpan — berubah bila kegiatan diubah di tempat lain.
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function versi(array $kegiatan): string
    {
        return md5((string) json_encode($kegiatan));
    }

    /**
     * Isian ISIAN data tersimpan (null bila belum pernah diisi).
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, mixed>
     */
    public static function isian(array $kegiatan): array
    {
        return array_combine(self::ISIAN, array_map(fn (string $k) => $kegiatan[$k] ?? null, self::ISIAN));
    }

    /**
     * Alasan isian kegiatan tidak bisa diubah; null = bisa.
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function alasanTerkunci(array $kegiatan): ?string
    {
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
        if (! $status->dapatDiedit()) {
            return 'Kegiatan berstatus '.$status->label().' sehingga isiannya terkunci.';
        }

        return ($tahun = KunciKegiatan::tahunTerkunci($kegiatan)) === null ? null : KunciKegiatan::pesanTahun($tahun);
    }

    /**
     * Data endpoint `kegiatan.data`.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, mixed>
     */
    public static function data(array $kegiatan): array
    {
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;

        return [
            'id' => (int) $kegiatan['id'],
            'status' => $status->value,
            'label_status' => $status->label(),
            'tahun_anggaran' => TahunKegiatan::dari($kegiatan),
            'dapat_diubah' => ($alasan = self::alasanTerkunci($kegiatan)) === null,
            'alasan_terkunci' => $alasan,
            'isian' => self::isian($kegiatan),
            'versi' => self::versi($kegiatan),
            'diperbarui_pada' => $kegiatan['diperbarui_pada'] ?? null,
        ];
    }

    /**
     * Bentuk simpan isian yang dikirim beserta isian turunannya (pelaksanaan, nama cover, IKSK).
     *
     * @param  array<string, mixed>  $isian
     * @param  array<string, mixed>  $tersimpan
     * @return array<string, mixed>
     */
    public static function rapikan(array $isian, array $tersimpan): array
    {
        $ada = fn (string $k) => array_key_exists($k, $isian);
        $baris = fn (mixed $v) => trim(preg_replace('/\s+/u', ' ', (string) $v));
        $kosongNull = fn (mixed $v) => blank($v) ? null : (is_string($v) ? trim($v) : $v);
        $boolean = fn (mixed $v) => in_array($v, [0, 1, '0', '1'], true) ? (bool) $v : $v;
        $hasil = [];

        foreach (['latar_belakang', 'tujuan', 'sasaran', 'konteks'] as $k) {
            if ($ada($k)) {
                $hasil[$k] = is_scalar($isian[$k]) || $isian[$k] === null ? Teks::rapikan((string) $isian[$k]) : $isian[$k];
            }
        }
        if ($ada('nama_kegiatan')) {
            $hasil['nama_kegiatan'] = is_scalar($isian['nama_kegiatan']) ? $baris($isian['nama_kegiatan']) : $isian['nama_kegiatan'];
        }
        if ($ada('tanggal_dokumen')) {
            $hasil['tanggal_dokumen'] = filled($isian['tanggal_dokumen']) ? $isian['tanggal_dokumen'] : null;
        }
        if (array_intersect(array_keys($isian), SimpanPelaksanaan::ISIAN) !== []) {
            $hasil += SimpanPelaksanaan::rapikan($isian, $tersimpan);
        }
        foreach (['leading', 'kode_rktm', 'kode_sk', 'kode_iksk', 'kode_edm', 'data_pagu_id'] as $k) {
            if ($ada($k)) {
                $hasil[$k] = $kosongNull($isian[$k]);
            }
        }
        if ($ada('budaya_mutu')) {
            $hasil['budaya_mutu'] = blank($isian['budaya_mutu']) ? null : (filter_var($isian['budaya_mutu'], FILTER_VALIDATE_INT) === false ? $isian['budaya_mutu'] : (int) $isian['budaya_mutu']);
        }
        if ($ada('kode_sk') && ! $ada('kode_iksk') && $hasil['kode_sk'] !== ($tersimpan['kode_sk'] ?? null)) {
            $hasil['kode_iksk'] = $hasil['kode_sk'] === null || ! is_string($hasil['kode_sk']) ? null
                : (IkskPerSk::sesudahGantiSk($hasil['kode_sk'], (string) ($tersimpan['kode_iksk'] ?? ''), TahunKegiatan::dari($tersimpan))['iksk'] ?: null);
        }
        if ($ada('sumber_dana')) {
            $hasil['sumber_dana'] = is_array($isian['sumber_dana']) ? SimpanSumberDana::rapikan($isian['sumber_dana']) : $isian['sumber_dana'];
        }
        if ($ada('panitia')) {
            $sah = is_array($isian['panitia']) && array_filter($isian['panitia'], fn ($a) => ! is_array($a)) === [];
            $hasil['panitia'] = $sah ? SusunanPanitia::rapikan($isian['panitia']) : $isian['panitia'];
        }

        // Nama cover kapital; mengikuti nama kegiatan sampai diubah manual (sama dengan KegiatanForm::setNamaCoverManual).
        if ($ada('nama_kegiatan') || $ada('nama_kegiatan_cover') || $ada('cover_manual')) {
            $nama = mb_strtoupper(is_string($n = $hasil['nama_kegiatan'] ?? $tersimpan['nama_kegiatan'] ?? '') ? $n : '');
            $manual = $ada('cover_manual') ? $boolean($isian['cover_manual']) : (bool) ($tersimpan['cover_manual'] ?? false);
            if ($ada('nama_kegiatan_cover') && is_scalar($isian['nama_kegiatan_cover'])) {
                $cover = mb_strtoupper($baris($isian['nama_kegiatan_cover']));
                // Dikirim bersama (form) = apa adanya; nama cover saja = manual bila berbeda dari nama kegiatan.
                $manual = $ada('cover_manual') ? $manual : $cover !== $nama;
            } else {
                $cover = $manual === false ? $nama : ($tersimpan['nama_kegiatan_cover'] ?? $nama);
            }
            $hasil['nama_kegiatan_cover'] = $cover;
            $hasil['cover_manual'] = $manual;
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $isian
     * @param  array{id: int, name: string, role: string, leadings?: list<string>}  $pengguna
     * @return array{kegiatan: array<string, mixed>, diubah: list<string>, bagian: list<string>, versi: string, pesan: string}
     *
     * @throws RuntimeException Kegiatan tidak ditemukan.
     * @throws AuthorizationException Bukan cakupan akun, status terkunci, atau tahun anggaran terkunci.
     * @throws PengaturanBerubah Kegiatan berubah di tempat lain sejak $versi.
     * @throws ValidationException Berkunci isian (atau `isian` untuk kunci yang tidak dikenal).
     */
    public static function ubah(int $id, array $isian, array $pengguna, ?string $versi = null): array
    {
        $repo = app(KegiatanRepository::class);
        $tersimpan = $repo->temukan($id) ?? throw new RuntimeException('Kegiatan tidak ditemukan.');
        if (! AksesKegiatan::bolehLihat($tersimpan, $pengguna)) {
            throw new AuthorizationException(AksesKegiatan::PESAN_TOLAK);
        }
        if ($versi !== null && $versi !== self::versi($tersimpan)) {
            throw new PengaturanBerubah('Data kegiatan ini baru saja diubah di tempat lain. Muat data terbaru lalu ulangi perubahan.');
        }
        if (($alasan = self::alasanTerkunci($tersimpan)) !== null) {
            throw new AuthorizationException($alasan);
        }
        if (($asing = array_diff(array_keys($isian), self::ISIAN)) !== []) {
            throw ValidationException::withMessages(['isian' => 'Isian '.implode(', ', $asing).' tidak bisa diubah di sini — status lewat alur status; RAB, termin, matrik & jadwal lewat penyimpanannya masing-masing.']);
        }
        if ($isian === []) {
            throw ValidationException::withMessages(['isian' => 'Tidak ada isian yang dikirim.']);
        }

        $baru = self::rapikan($isian, $tersimpan);
        $gabungan = array_merge($tersimpan, $baru);
        ValidasiKegiatan::periksa($gabungan, array_keys($baru), $pengguna, $tersimpan['leading'] ?? null);

        // Kosong, tidak dicentang, dan daftar kosong sama dengan belum diisi.
        $baku = fn (mixed $v) => json_encode(in_array($v, ['', false, []], true) ? null : $v);
        $diubah = array_values(array_filter(array_keys($baru), fn (string $k) => $baku($baru[$k]) !== $baku($tersimpan[$k] ?? null)));
        if ($diubah === []) {
            return ['kegiatan' => $tersimpan, 'diubah' => [], 'bagian' => [], 'versi' => self::versi($tersimpan), 'pesan' => 'Tidak ada perubahan data kegiatan.'];
        }

        $gabungan['kode_hilang'] = KodeHilang::sisa((array) ($tersimpan['kode_hilang'] ?? []), $gabungan);
        $repo->simpan($gabungan, $id);
        $kegiatan = $repo->temukan($id);

        return ['kegiatan' => $kegiatan, 'diubah' => $diubah, 'bagian' => self::bagian($diubah), 'versi' => self::versi($kegiatan), 'pesan' => 'Data kegiatan tersimpan.'];
    }

    /**
     * Label bagian (urutan PerbaikanKegiatan::BAGIAN) dari kolom yang berubah.
     *
     * @param  list<string>  $kolom
     * @return list<string>
     */
    public static function bagian(array $kolom): array
    {
        $label = array_column(array_filter(PerbaikanKegiatan::BAGIAN, fn (array $b) => array_intersect($b['kolom'], $kolom) !== []), 'label');
        foreach (self::BAGIAN_LAIN as $k => $l) {
            in_array($k, $kolom, true) && $label[] = $l;
        }

        return array_values(array_unique($label));
    }
}
