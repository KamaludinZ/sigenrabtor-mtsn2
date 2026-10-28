<?php

namespace App\Support\Dokumen;

use App\Support\Kegiatan\TeksKhusus;
use App\Support\Stub\StubData;

/**
 * Sinkronisasi tahun di dokumen satu kegiatan × sumber dana (PRD aturan 3 "Satu tahun untuk semua"): tempat tahun
 * tercetak di header/judul yang disusun sistem (cover, judul RAB, sebutan sumber dana, jadwal) — selalu tahun aktif —
 * dan tahun yang terketik di isian bebas (nama kegiatan, latar belakang, tujuan, sasaran, uraian, teks khusus, rincian RAB) yang
 * mungkin sisa dokumen tahun lalu. Kunci = jangkar `#cetak-{kunci}` di pratinjau.
 */
final class TahunDokumen
{
    /** Kolom isian bebas yang ikut tercetak → [dokumen (kunci), label dokumen, bagian, jangkar]. */
    public const ISIAN = [
        'nama_kegiatan' => [['kak', 'rab'], 'KAK & RAB', 'Nama kegiatan', 'rab-judul'],
        'nama_kegiatan_cover' => [['cover'], 'Cover', 'Nama kegiatan (cover)', 'cover-nama_kegiatan'],
        'latar_belakang' => [['kak'], 'KAK', '1. Latar Belakang', 'kak-bagian-1'],
        'tujuan' => [['kak'], 'KAK', '3. Tujuan', 'kak-bagian-3'],
        'sasaran' => [['kak'], 'KAK', '3. Sasaran', 'kak-bagian-3'],
        'uraian_pelaksanaan' => [['kak'], 'KAK', '4b. Pelaksanaan', 'kak-pelaksanaan'],
        'tempat' => [['kak'], 'KAK', '5. Tempat Pelaksanaan', 'kak-bagian-5'],
    ];

    private const POLA_TAHUN = '/\b(?:19|20)\d{2}\b/u';

    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array{teks: list<array<string, mixed>>, nilai: array<string, string>}  $cover  Isi cover yang ditampilkan.
     * @return list<array{kunci: string, di: list<string>, dokumen: string, bagian: string, teks: string, tahun: list<int>, sinkron: bool, jenis: string}>
     */
    public static function dari(array $kegiatan, SusunanKak $kak, SusunanRab $rab, array $cover, ?int $tahun = null): array
    {
        $tahun ??= StubData::tahunAktif()['tahun'];
        $tempat = [];
        $tambah = function (string $kunci, array $di, string $dokumen, string $bagian, string $teks, string $jenis) use (&$tempat, $tahun): void {
            preg_match_all(self::POLA_TAHUN, $teks, $cocok);
            $ditemukan = array_values(array_unique(array_map('intval', $cocok[0])));
            if ($jenis === 'isian' && $ditemukan === []) {
                return;
            }
            $tempat[] = ['kunci' => $kunci, 'di' => $di, 'dokumen' => $dokumen, 'bagian' => $bagian, 'teks' => $teks, 'tahun' => $ditemukan,
                'sinkron' => $ditemukan !== [] && array_diff($ditemukan, [$tahun]) === [], 'jenis' => $jenis];
        };

        // Header & judul yang disusun sistem.
        if (collect($cover['teks'])->contains('field', 'tahun')) {
            $tambah('cover-tahun', ['cover'], 'Cover', 'Tahun anggaran', $cover['nilai']['tahun'], 'baku');
        }
        $tambah('rab-judul', ['rab'], 'RAB', 'Judul', $rab->judul, 'baku');
        $sebutan = collect($rab->kepala)->firstWhere('label', 'Jumlah yang diajukan')['uraian'] ?? null;
        if ($sebutan) {
            $tambah('kak-pembiayaan', ['kak', 'rab'], 'KAK & RAB', 'Sebutan sumber dana', $sebutan, 'baku');
        }
        $jadwal = collect($kak->bagian)->firstWhere('nomor', 7)['isi'] ?? [];
        $waktu = collect($jadwal)->where('jenis', 'jadwal')->flatMap(fn (array $b) => array_column($b['baris'], 'waktu'))->filter()->unique()->values()->all();
        if ($waktu !== []) {
            $tambah('kak-bagian-7', ['kak'], 'KAK', '7. Jadwal Kegiatan', implode('; ', $waktu), 'baku');
        }

        // Isian bebas yang menyebut tahun.
        foreach (self::ISIAN as $kolom => [$di, $dokumen, $bagian, $jangkar]) {
            $tambah($jangkar, $di, $dokumen, $bagian, trim((string) ($kegiatan[$kolom] ?? '')), 'isian');
        }
        foreach (TeksKhusus::dari($kegiatan) as $kunci => $teks) {
            [$nomor, $label] = TeksKhusus::BIDANG[$kunci];
            $tambah("kak-bagian-{$nomor}", ['kak'], 'KAK', $label, implode(' ', (array) $teks), 'isian');
        }
        foreach ($rab->anggaran?->hasil->rincian ?? [] as $item) {
            $tambah('rab-rincian', ['rab'], 'RAB', 'Rincian: '.mb_strimwidth((string) $item['uraian'], 0, 60, '…'), (string) $item['uraian'], 'isian');
        }

        return $tempat;
    }

    /**
     * Ringkasan sinkronisasi satu dokumen (cover|kak|rab) untuk penanda di kepala dokumen.
     *
     * @param  list<array{di: list<string>, sinkron: bool}>  $tempat
     * @return array{jumlah: int, beda: int}
     */
    public static function untukDokumen(array $tempat, string $dokumen): array
    {
        $milik = array_filter($tempat, fn (array $t) => in_array($dokumen, $t['di'], true));

        return ['jumlah' => count($milik), 'beda' => count(array_filter($milik, fn (array $t) => ! $t['sinkron']))];
    }

    /**
     * Tempat yang menyebut tahun selain tahun aktif: "KAK · 3. Tujuan (2025)".
     *
     * @param  list<array{dokumen: string, bagian: string, tahun: list<int>, sinkron: bool}>  $tempat
     * @return list<string>
     */
    public static function beda(array $tempat, ?int $tahun = null): array
    {
        $tahun ??= StubData::tahunAktif()['tahun'];

        return array_values(array_map(
            fn (array $t) => "{$t['dokumen']} · {$t['bagian']} (".implode(', ', array_diff($t['tahun'], [$tahun])).')',
            array_filter($tempat, fn (array $t) => ! $t['sinkron']),
        ));
    }
}
