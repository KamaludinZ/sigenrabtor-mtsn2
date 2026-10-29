<?php

namespace App\Support\Dokumen;

/**
 * Satu alur penelusuran daftar Riwayat Dokumen: kata kunci (`RiwayatDokumen::cari`) lalu saringan jenis/format/rentang
 * tanggal (`RiwayatDokumen::saring`) atas catatan dalam cakupan akun, beserta semua angka yang menyertai daftar — dipakai
 * halaman `Dokumen\Riwayat` (dan kelak endpoint daftarnya) agar daftar, ringkasan, hitungan pilihan, dan keterangan hasil
 * selalu berasal dari kriteria yang sama.
 *
 * - `kriteria` = kata kunci rapi + `RiwayatDokumen::rapikanSaringan`; `aktif()` = ada kriteria yang diterapkan.
 * - `cocok` = catatan yang lolos semua kriteria (urut catatan, terbaru dulu); `total` = jumlah catatan dalam cakupan.
 * - `pilihan($kunci)` = hitungan tiap pilihan jenis/format terhadap kriteria lain (pilihan itu sendiri diabaikan) sehingga
 *   pengguna melihat berapa dokumen yang akan tampil sebelum memilih.
 * - `ringkasan()` = `RiwayatDokumen::ringkasan` hasil + `semua` (ringkasan seluruh catatan, untuk "dari …").
 * - `keterangan()` = kalimat hasil ("3 dari 9 dokumen cocok dengan “lomba” · RAB · …"), null bila tanpa kriteria.
 * - `perKegiatan()` = `RiwayatDokumen::perKegiatan` hasil, tiap kegiatan + `jumlah_semua` (dokumennya tanpa kriteria) dan
 *   tiap baris dokumen + `lebih_baru` = versi terbaru jenis × sumber × format itu yang tidak cocok dengan kriteria (null bila
 *   versi yang tampil memang yang terbaru) — saringan tanggal tidak boleh membuat versi lama tampak terbaru.
 * - `halaman($tampilan, $urut, $batas)` = [kelompok per tahun dipotong `$batas`, jumlah butir] untuk tampilan
 *   `kegiatan` (per kegiatan) atau `kronologis` (dokumen, `terlama` membalik urutan & tahun).
 */
class PenelusuranRiwayat
{
    /** Kriteria penelusuran (kata kunci + saringan). */
    public const KRITERIA = ['cari', ...RiwayatDokumen::SARINGAN];

    /** Saringan yang berupa pilihan (hitungan per pilihan). */
    public const PILIHAN = ['jenis', 'format'];

    /** @var array{cari: string, jenis: string, format: string, dari: string, sampai: string, terbalik: bool} */
    public readonly array $kriteria;

    /** @var list<array<string, mixed>> */
    public readonly array $cocok;

    public readonly int $total;

    /**
     * @param  list<array<string, mixed>>  $catatan  Catatan siap tampil dalam cakupan akun (`RiwayatDokumen::untuk`).
     * @param  array<string, mixed>  $nilai  Kriteria mentah (`KRITERIA`).
     */
    public function __construct(private readonly array $catatan, array $nilai)
    {
        $this->kriteria = ['cari' => self::rapikanCari($nilai['cari'] ?? '')] + RiwayatDokumen::rapikanSaringan($nilai);
        $this->cocok = $this->terapkan($this->kriteria);
        $this->total = count($catatan);
    }

    /** @param  array<string, mixed>  $nilai */
    public static function dari(array $catatan, array $nilai): self
    {
        return new self($catatan, $nilai);
    }

    /** Kata kunci dirapikan: tanpa spasi tepi, maksimal 100 karakter. */
    public static function rapikanCari(mixed $cari): string
    {
        return mb_substr(trim(is_string($cari) ? $cari : ''), 0, 100);
    }

    public function aktif(): bool
    {
        return $this->kriteria['cari'] !== '' || $this->label() !== [];
    }

    /** @return array<string, string> Label saringan yang diterapkan (chip), tanpa kata kunci. */
    public function label(): array
    {
        return RiwayatDokumen::labelSaringan($this->kriteria);
    }

    /**
     * Hitungan tiap pilihan saringan `jenis` atau `format` bila pilihan itu dipilih bersama kriteria lain yang berlaku.
     *
     * @return array<string, int>
     */
    public function pilihan(string $kunci): array
    {
        if (! in_array($kunci, self::PILIHAN, true)) {
            throw new \InvalidArgumentException("Saringan {$kunci} bukan pilihan.");
        }
        $lain = $this->terapkan([$kunci => ''] + $this->kriteria);
        $daftar = $kunci === 'jenis' ? array_keys(DokumenKegiatan::JENIS) : array_keys(RiwayatDokumen::FORMAT);
        $hitung = array_fill_keys($daftar, 0);
        foreach ($lain as $c) {
            if (isset($hitung[$c[$kunci]])) {
                $hitung[$c[$kunci]]++;
            }
        }

        return $hitung;
    }

    /** @return array<string, mixed> Ringkasan hasil + `semua` (ringkasan seluruh catatan). */
    public function ringkasan(): array
    {
        return RiwayatDokumen::ringkasan($this->cocok) + ['semua' => RiwayatDokumen::ringkasan($this->catatan)];
    }

    public function keterangan(): ?string
    {
        if (! $this->aktif()) {
            return null;
        }
        $bagian = [count($this->cocok).' dari '.$this->total.' dokumen'.($this->kriteria['cari'] !== '' ? ' cocok dengan “'.$this->kriteria['cari'].'”' : '')];

        return implode(' · ', [...$bagian, ...array_values($this->label())]);
    }

    /** @return list<array<string, mixed>> */
    public function perKegiatan(): array
    {
        $semua = [];
        foreach (RiwayatDokumen::perKegiatan($this->catatan) as $k) {
            $semua[$k['kunci']] = ['jumlah' => $k['jumlah'], 'terbaru' => array_column(array_map(fn (array $d) => ['kunci' => $d['kunci'], 'terbaru' => $d['terbaru']], $k['dokumen']), 'terbaru', 'kunci')];
        }

        return array_map(function (array $k) use ($semua) {
            $penuh = $semua[$k['kunci']] ?? ['jumlah' => $k['jumlah'], 'terbaru' => []];
            $k['jumlah_semua'] = $penuh['jumlah'];
            $k['dokumen'] = array_map(function (array $d) use ($penuh) {
                $terbaru = $penuh['terbaru'][$d['kunci']] ?? null;

                return $d + ['lebih_baru' => $terbaru !== null && $terbaru['id'] !== $d['terbaru']['id'] ? $terbaru : null];
            }, $k['dokumen']);

            return $k;
        }, RiwayatDokumen::perKegiatan($this->cocok));
    }

    /**
     * Isi tampilan: [kelompok per tahun anggaran (dipotong `$batas`), jumlah butir seluruhnya].
     *
     * @return array{0: array<int, list<array<string, mixed>>>, 1: int}
     */
    public function halaman(string $tampilan, string $urut, int $batas): array
    {
        $terlama = $tampilan === 'kronologis' && $urut === 'terlama';
        $butir = $tampilan === 'kegiatan' ? $this->perKegiatan() : ($terlama ? array_reverse($this->cocok) : $this->cocok);
        $kelompok = [];
        foreach (array_slice($butir, 0, max(0, $batas)) as $b) {
            $kelompok[(int) $b['tahun_anggaran']][] = $b;
        }
        $terlama ? ksort($kelompok) : krsort($kelompok);

        return [$kelompok, count($butir)];
    }

    /**
     * @param  array<string, mixed>  $kriteria
     * @return list<array<string, mixed>>
     */
    private function terapkan(array $kriteria): array
    {
        return RiwayatDokumen::saring(RiwayatDokumen::cari($this->catatan, $kriteria['cari'] ?? ''), $kriteria);
    }
}
