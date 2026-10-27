<?php

namespace App\Support\Kegiatan\Impor;

use App\Support\Master\Impor\PemeriksaIksk;
use App\Support\Master\Impor\PemeriksaSk;
use App\Support\MasterKode;
use App\Support\Stub\StubData;

/**
 * Pemeriksaan baris cascade Perkin: isian kegiatan (nama, leading, pagu, bulan) memakai aturan impor kegiatan
 * (PemeriksaImporKegiatan), lalu kode SK & IKSK dicocokkan dengan master tahun aktif — format salah atau IKSK yang bukan
 * indikator SK-nya menggagalkan baris; kode yang tidak ada di master hanya peringatan (dikosongkan, dipilih nanti di
 * Data Kegiatan); SK kosong diisi dari induk IKSK-nya.
 */
final class PemeriksaPerkin
{
    /**
     * @param  list<array{baris: int, nilai: array<string, mixed>}>  $barisMentah  Dari PembacaPerkin::baca().
     * @param  list<string>  $namaAda  Nama kegiatan tahun aktif.
     * @return list<array<string, mixed>> Baris PemeriksaImporKegiatan::periksa() + kode_sk, kode_iksk (null bila kosong/dikosongkan).
     */
    public static function periksa(array $barisMentah, array $namaAda, array $leadings, array $semuaLeading, ?string $leadingBawaan = null): array
    {
        $diperiksa = PemeriksaImporKegiatan::periksa($barisMentah, $namaAda, $leadings, $semuaLeading, $leadingBawaan);
        $sk = array_column(MasterKode::kodeSk(), null, 'kode');
        $iksk = array_column(MasterKode::kodeIksk(), null, 'kode');
        $tahun = StubData::tahunAktif()['tahun'];

        foreach ($barisMentah as $i => ['nilai' => $nilai]) {
            [$kodeSk, $kodeIksk, $gagal, $peringatan] = self::kode($nilai['kode_sk'] ?? null, $nilai['kode_iksk'] ?? null, $sk, $iksk, $tahun);
            $baris = &$diperiksa[$i];

            if ($baris['status'] === 'siap' && $gagal !== []) {
                $baris['status'] = 'gagal';
            }
            if ($baris['status'] === 'gagal') {
                array_push($baris['pesan'], ...$gagal);
                $baris['peringatan'] = [];
            } elseif ($baris['status'] === 'siap') {
                $baris['peringatan'] = [...$peringatan, ...$baris['peringatan']];
            }
            $baris['kode_sk'] = $kodeSk;
            $baris['kode_iksk'] = $kodeIksk;
            unset($baris);
        }

        return $diperiksa;
    }

    /**
     * Data kegiatan Draf dari baris siap: isian impor kegiatan + kode SK & IKSK.
     *
     * @param  array<string, mixed>  $baris
     * @return array<string, mixed>
     */
    public static function dataKegiatan(array $baris): array
    {
        return array_merge(PemeriksaImporKegiatan::dataKegiatan($baris), ['kode_sk' => $baris['kode_sk'], 'kode_iksk' => $baris['kode_iksk'], 'asal' => 'impor_perkin']);
    }

    /**
     * Susunan cascade baris yang siap: SK → IKSK → jumlah kegiatan & pagu per sumber dana, urut kode master; kegiatan
     * tanpa kode dikelompokkan di akhir (kode null).
     *
     * @param  list<array<string, mixed>>  $baris  Hasil periksa().
     * @return list<array{kode: string|null, uraian: string|null, kegiatan: int, pagu: array<string, int>, iksk: list<array{kode: string|null, uraian: string|null, kegiatan: int, pagu: array<string, int>}>}>
     */
    public static function cascade(array $baris): array
    {
        $sk = array_column(MasterKode::kodeSk(), 'uraian', 'kode');
        $iksk = array_column(MasterKode::kodeIksk(), 'uraian', 'kode');
        $kelompok = [];

        $tambah = function (array &$simpul, array $dana): void {
            $simpul['kegiatan']++;
            foreach ($dana as $d) {
                $simpul['pagu'][$d['sumber']] = ($simpul['pagu'][$d['sumber']] ?? 0) + $d['pagu'];
            }
        };
        foreach (array_filter($baris, fn (array $b) => $b['status'] === 'siap') as $b) {
            $s = $b['kode_sk'] ?? '';
            $i = $b['kode_iksk'] ?? '';
            $kelompok[$s] ??= ['kode' => $b['kode_sk'], 'uraian' => $sk[$s] ?? null, 'kegiatan' => 0, 'pagu' => [], 'iksk' => []];
            $kelompok[$s]['iksk'][$i] ??= ['kode' => $b['kode_iksk'], 'uraian' => $iksk[$i] ?? null, 'kegiatan' => 0, 'pagu' => []];
            $tambah($kelompok[$s], $b['sumber_dana']);
            $tambah($kelompok[$s]['iksk'][$i], $b['sumber_dana']);
        }

        uasort($kelompok, fn (array $a, array $b) => self::urutKode($a['kode'], $b['kode']));

        return array_values(array_map(function (array $k) {
            uasort($k['iksk'], fn (array $a, array $b) => self::urutKode($a['kode'], $b['kode']));
            $k['iksk'] = array_values($k['iksk']);

            return $k;
        }, $kelompok));
    }

    /** Urutan kode SK/IKSK menurut nomornya ("SK.9" sebelum "SK.10"); kosong di akhir. */
    private static function urutKode(?string $a, ?string $b): int
    {
        $nomor = fn (string $kode) => ltrim(preg_replace('/[^\d.]/', '', $kode), '.');

        return match (true) {
            $a === $b => 0,
            $a === null => 1,
            $b === null => -1,
            default => version_compare($nomor($a), $nomor($b)),
        };
    }

    /**
     * @param  array<string, array{kode: string}>  $sk
     * @param  array<string, array{kode: string, kode_sk: string}>  $iksk
     * @return array{0: string|null, 1: string|null, 2: list<string>, 3: list<string>} SK, IKSK, pesan gagal, peringatan.
     */
    private static function kode(mixed $mentahSk, mixed $mentahIksk, array $sk, array $iksk, int $tahun): array
    {
        $teksSk = trim((string) $mentahSk);
        $teksIksk = trim((string) $mentahIksk);
        $kodeSk = $teksSk === '' ? null : PemeriksaSk::normalkanKode($teksSk);
        $kodeIksk = $teksIksk === '' ? null : PemeriksaIksk::normalkanKode($teksIksk);
        $gagal = [];
        $peringatan = [];

        if ($teksSk !== '' && $kodeSk === null) {
            $gagal[] = "Kode SK \"{$teksSk}\" tidak berformat SK.n (mis. SK.10).";
        }
        if ($teksIksk !== '' && $kodeIksk === null) {
            $gagal[] = "Kode IKSK \"{$teksIksk}\" tidak berformat IKSK n.m (mis. IKSK 10.2).";
        }
        if ($gagal !== []) {
            return [null, null, $gagal, []];
        }

        if ($kodeIksk !== null && isset($iksk[$kodeIksk])) {
            $induk = $iksk[$kodeIksk]['kode_sk'];
            if ($kodeSk !== null && $kodeSk !== $induk) {
                return [null, null, ["{$kodeIksk} adalah indikator {$induk}, bukan {$kodeSk}."], []];
            }
            $kodeSk = $induk;
        } elseif ($kodeIksk !== null) {
            $peringatan[] = "{$kodeIksk} tidak ada di master TA {$tahun} — dikosongkan, pilih nanti di Data Kegiatan.";
            $kodeIksk = null;
        }

        if ($kodeSk !== null && ! isset($sk[$kodeSk])) {
            $peringatan[] = "{$kodeSk} tidak ada di master TA {$tahun} — dikosongkan, pilih nanti di Data Kegiatan.";
            $kodeSk = null;
        }
        if ($teksSk === '' && $teksIksk === '') {
            $peringatan[] = 'Kode SK & IKSK kosong — pilih nanti di Data Kegiatan.';
        }

        return [$kodeSk, $kodeIksk, [], $peringatan];
    }
}
