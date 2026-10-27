<?php

namespace App\Support\Kegiatan\Impor;

use App\Support\Kegiatan\KodeHilang;
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
     * @return list<array<string, mixed>> Baris PemeriksaImporKegiatan::periksa() + kode_sk, kode_iksk (null bila kosong/dikosongkan),
     *                                    asal_sk (berkas|atas|induk), asal_iksk (berkas|atas), dikosongkan (kode di luar master).
     */
    public static function periksa(array $barisMentah, array $namaAda, array $leadings, array $semuaLeading, ?string $leadingBawaan = null): array
    {
        $diperiksa = PemeriksaImporKegiatan::periksa($barisMentah, $namaAda, $leadings, $semuaLeading, $leadingBawaan);
        $sk = array_column(MasterKode::kodeSk(), null, 'kode');
        $iksk = array_column(MasterKode::kodeIksk(), null, 'kode');
        $tahun = StubData::tahunAktif()['tahun'];

        foreach ($barisMentah as $i => $mentah) {
            $kode = self::kode($mentah['nilai']['kode_sk'] ?? null, $mentah['nilai']['kode_iksk'] ?? null, $sk, $iksk, $tahun);
            $baris = &$diperiksa[$i];

            if ($baris['status'] === 'siap' && $kode['gagal'] !== []) {
                $baris['status'] = 'gagal';
            }
            if ($baris['status'] === 'gagal') {
                array_push($baris['pesan'], ...$kode['gagal']);
                $baris['peringatan'] = [];
            } elseif ($baris['status'] === 'siap') {
                $baris['peringatan'] = [...$kode['peringatan'], ...$baris['peringatan']];
            }
            $ikut = $mentah['ikut'] ?? [];
            $baris['kode_sk'] = $kode['sk'];
            $baris['kode_iksk'] = $kode['iksk'];
            $baris['asal_sk'] = $kode['sk'] === null ? null : ($kode['sk_induk'] ? 'induk' : (($ikut['kode_sk'] ?? false) ? 'atas' : 'berkas'));
            $baris['asal_iksk'] = $kode['iksk'] === null ? null : (($ikut['kode_iksk'] ?? false) ? 'atas' : 'berkas');
            $baris['dikosongkan'] = $kode['dikosongkan'];
            unset($baris);
        }

        return $diperiksa;
    }

    /**
     * Data kegiatan Draf dari baris siap: isian impor kegiatan + kode SK & IKSK + catatan kode yang dikosongkan.
     *
     * @param  array<string, mixed>  $baris
     * @return array<string, mixed>
     */
    public static function dataKegiatan(array $baris): array
    {
        // Kode di luar master yang dikosongkan dicatat sebagai penanda kode hilang (KodeHilang).
        $hilang = [];
        foreach ($baris['dikosongkan'] ?? [] as $kode) {
            $hilang[str_starts_with($kode, 'IKSK') ? 'kode_iksk' : 'kode_sk'] = KodeHilang::catatan($kode, 'impor');
        }

        return array_merge(PemeriksaImporKegiatan::dataKegiatan($baris), ['kode_sk' => $baris['kode_sk'], 'kode_iksk' => $baris['kode_iksk'], 'kode_hilang' => $hilang, 'asal' => 'impor_perkin']);
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
     * Kode SK & IKSK satu baris terhadap master: kode terpakai, pesan gagal, peringatan, SK yang diisi dari induk IKSK,
     * dan kode yang dikosongkan karena tidak ada di master.
     *
     * @param  array<string, array{kode: string}>  $sk
     * @param  array<string, array{kode: string, kode_sk: string}>  $iksk
     * @return array{sk: string|null, iksk: string|null, gagal: list<string>, peringatan: list<string>, sk_induk: bool, dikosongkan: list<string>}
     */
    private static function kode(mixed $mentahSk, mixed $mentahIksk, array $sk, array $iksk, int $tahun): array
    {
        $teksSk = trim((string) $mentahSk);
        $teksIksk = trim((string) $mentahIksk);
        $kodeSk = $teksSk === '' ? null : PemeriksaSk::normalkanKode($teksSk);
        $kodeIksk = $teksIksk === '' ? null : PemeriksaIksk::normalkanKode($teksIksk);
        $hasil = ['sk' => null, 'iksk' => null, 'gagal' => [], 'peringatan' => [], 'sk_induk' => false, 'dikosongkan' => []];

        if ($teksSk !== '' && $kodeSk === null) {
            $hasil['gagal'][] = "Kode SK \"{$teksSk}\" tidak berformat SK.n (mis. SK.10).";
        }
        if ($teksIksk !== '' && $kodeIksk === null) {
            $hasil['gagal'][] = "Kode IKSK \"{$teksIksk}\" tidak berformat IKSK n.m (mis. IKSK 10.2).";
        }
        if ($hasil['gagal'] !== []) {
            return $hasil;
        }

        if ($kodeIksk !== null && isset($iksk[$kodeIksk])) {
            $induk = $iksk[$kodeIksk]['kode_sk'];
            if ($kodeSk !== null && $kodeSk !== $induk) {
                return ['gagal' => ["{$kodeIksk} adalah indikator {$induk}, bukan {$kodeSk}."]] + $hasil;
            }
            $hasil['sk_induk'] = $kodeSk === null;
            $kodeSk = $induk;
        } elseif ($kodeIksk !== null) {
            $hasil['peringatan'][] = "{$kodeIksk} tidak ada di master TA {$tahun} — dikosongkan, pilih nanti di Data Kegiatan.";
            $hasil['dikosongkan'][] = $kodeIksk;
            $kodeIksk = null;
        }

        if ($kodeSk !== null && ! isset($sk[$kodeSk])) {
            $hasil['peringatan'][] = "{$kodeSk} tidak ada di master TA {$tahun} — dikosongkan, pilih nanti di Data Kegiatan.";
            $hasil['dikosongkan'][] = $kodeSk;
            $kodeSk = null;
        }
        if ($teksSk === '' && $teksIksk === '') {
            $hasil['peringatan'][] = 'Kode SK & IKSK kosong — pilih nanti di Data Kegiatan.';
        }

        return ['sk' => $kodeSk, 'iksk' => $kodeIksk] + $hasil;
    }
}
