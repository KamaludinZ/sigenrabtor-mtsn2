<?php

namespace App\Support\Dokumen;

use App\Contracts\BukuDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\PembuatPdfBuku;
use App\Enums\SumberDana;
use App\Support\MasterKode;
use App\Support\Pengaturan\TautanAkunLeading;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Pemicu, pemantau, dan riwayat pembuatan PDF buku per leading × sumber dana tahun aktif (PRD §18 F19 "Gabung PDF") —
 * dipakai halaman Buku (`Buku\Daftar::gabungPdf`/`pantauPdf`) dan endpoint `buku.pdf.mulai` (`POST /buku/pdf`),
 * `buku.pdf.status` (`GET /buku/pdf/{id}`), `buku.riwayat` (`GET /buku/riwayat`); berkasnya diunduh lewat `buku.unduh`.
 * - `mulai($pengguna, $leading, $sumber)`: sumber SumberDana (galat `sumber`), leading dalam cakupan akun
 *   (BukuDokumen::leadingUntuk → AuthorizationException), buku berisi kegiatan (galat `buku`); pembuatan buku yang sama
 *   yang masih berjalan dikembalikan apa adanya (`baru` false) alih-alih memulai lagi; catatan dari `catatan()` (cuplikan
 *   kegiatan, halaman, DRAF) → PembuatPdfBuku::mulai;
 * - `pantau($id, $pengguna)`: akses TautanAkunLeading; selama berjalan dimajukan PembuatPdfBuku::pantau;
 * - `data($buku)` = bentuk JSON (status & langkah ProsesPdfBuku, tautan status/unduh, nama berkas).
 */
final class PdfBuku
{
    /**
     * Catatan buku untuk PembuatPdfBuku dari isi buku (BukuDokumen::isi).
     *
     * @param  array<string, mixed>  $isi
     * @param  array{name: string}  $pengguna
     * @return array<string, mixed>
     */
    public static function catatan(array $isi, string $leading, string $sumber, array $pengguna): array
    {
        return [
            'tahun' => StubData::tahunAktif()['tahun'],
            'leading' => $leading,
            'sumber' => $sumber,
            'jumlah_kegiatan' => count($isi['baris']),
            'halaman' => $isi['simulasi']['terakhir'],
            'ada_draf' => collect($isi['baris'])->contains('draf', true),
            'kegiatan' => array_map(fn (array $b) => ['id' => $b['id'], 'nama' => $b['nama_kegiatan'], 'halaman' => $b['halaman'], 'hasil' => $b['draf'] ? 'draf' : 'siap'], $isi['baris']),
            'dibuat_oleh' => $pengguna['name'],
        ];
    }

    /**
     * Pembuatan PDF terakhir buku itu (catatan riwayat ber-`pdf`) di tahun aktif; null bila belum pernah.
     *
     * @return array<string, mixed>|null
     */
    public static function terakhir(string $leading, string $sumber): ?array
    {
        return collect(app(BukuDokumenRepository::class)->riwayat(StubData::tahunAktif()['tahun']))
            ->first(fn (array $b) => $b['leading'] === $leading && $b['sumber'] === $sumber && isset($b['pdf']));
    }

    /**
     * @param  array{id: int, name: string, role: string, leadings?: list<string>}  $pengguna
     * @return array{buku: array<string, mixed>, baru: bool}
     *
     * @throws ValidationException Berkunci `sumber` atau `buku`.
     * @throws AuthorizationException Leading di luar cakupan akun.
     */
    public static function mulai(array $pengguna, string $leading, string $sumber): array
    {
        if (SumberDana::tryFrom($sumber) === null) {
            throw ValidationException::withMessages(['sumber' => 'Sumber dana harus DIPA atau Komite.']);
        }
        $kegiatan = BukuDokumen::tahunAktif(app(KegiatanRepository::class)->semua());
        if (! in_array($leading, array_column(BukuDokumen::leadingUntuk($pengguna, $kegiatan), 'kode'), true)) {
            throw new AuthorizationException("Buku leading {$leading} tidak dapat dibuka akun ini.");
        }
        $isi = BukuDokumen::isi($kegiatan, $leading, $sumber);
        if ($isi['baris'] === []) {
            $unit = MasterKode::leading($leading)['nama_unit'] ?? $leading;
            throw ValidationException::withMessages(['buku' => "Buku {$unit} {$sumber} belum berisi kegiatan."]);
        }

        if (($berjalan = self::terakhir($leading, $sumber)) !== null && ProsesPdfBuku::berjalan($berjalan['pdf'])) {
            return ['buku' => $berjalan, 'baru' => false];
        }

        return ['buku' => app(PembuatPdfBuku::class)->mulai(self::catatan($isi, $leading, $sumber, $pengguna)), 'baru' => true];
    }

    /**
     * @param  array{role: string, leadings?: list<string>}  $pengguna
     * @return array<string, mixed>
     *
     * @throws RuntimeException Buku tidak ditemukan.
     * @throws AuthorizationException
     */
    public static function pantau(string $id, array $pengguna): array
    {
        $buku = app(BukuDokumenRepository::class)->temukan($id) ?? throw new RuntimeException('Buku tidak ditemukan.');
        if (! TautanAkunLeading::bolehAkses($pengguna, $buku['leading'])) {
            throw new AuthorizationException("Buku leading {$buku['leading']} tidak dapat dibuka akun ini.");
        }

        return ProsesPdfBuku::berjalan($buku['pdf'] ?? null) ? app(PembuatPdfBuku::class)->pantau($id) : $buku;
    }

    /**
     * Bentuk JSON satu catatan buku.
     *
     * @param  array<string, mixed>  $buku
     * @return array<string, mixed>
     */
    public static function data(array $buku): array
    {
        $pdf = $buku['pdf'] ?? null;
        $siap = ($pdf['status'] ?? null) === 'selesai' && filled($buku['file_pdf_path'] ?? null);

        return [
            'id' => $buku['id'],
            'tahun' => $buku['tahun'],
            'leading' => $buku['leading'],
            'nama_leading' => MasterKode::leading($buku['leading'])['nama_unit'] ?? $buku['leading'],
            'sumber' => $buku['sumber'],
            'jumlah_kegiatan' => $buku['jumlah_kegiatan'],
            'halaman' => $buku['halaman'],
            'ada_draf' => $buku['ada_draf'],
            'kegiatan' => $buku['kegiatan'],
            'dibuat_oleh' => $buku['dibuat_oleh'],
            'dibuat_pada' => $buku['dibuat_pada'],
            'pdf' => $pdf === null ? null : $pdf + ['label_tahap' => ProsesPdfBuku::TAHAP[$pdf['tahap'] ?? ''] ?? null],
            'langkah' => $pdf === null ? [] : ProsesPdfBuku::langkah($pdf),
            'berjalan' => ProsesPdfBuku::berjalan($pdf),
            'siap_unduh' => $siap,
            'nama_berkas' => BukuDokumen::namaBerkas($buku['leading'], $buku['sumber']),
            'tautan_status' => $pdf === null ? null : route('buku.pdf.status', ['buku' => $buku['id']]),
            'tautan_unduh' => $siap ? route('buku.unduh', ['buku' => $buku['id']]) : null,
        ];
    }

    /**
     * Riwayat buku tahun aktif dalam cakupan akun, terbaru dulu, tersaring leading/sumber ('' = semua).
     *
     * @param  array{role: string, leadings?: list<string>}  $pengguna
     * @return list<array<string, mixed>>
     */
    public static function riwayat(array $pengguna, string $leading = '', string $sumber = ''): array
    {
        return array_values(array_map(self::data(...), array_filter(
            app(BukuDokumenRepository::class)->riwayat(StubData::tahunAktif()['tahun']),
            fn (array $b) => TautanAkunLeading::bolehAkses($pengguna, $b['leading'])
                && ($leading === '' || $b['leading'] === $leading) && ($sumber === '' || $b['sumber'] === $sumber),
        )));
    }
}
