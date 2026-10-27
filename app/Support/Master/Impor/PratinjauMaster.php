<?php

namespace App\Support\Master\Impor;

use App\Contracts\KegiatanRepository;
use App\Support\Impor\GagalBacaImpor;
use App\Support\Impor\LembarExcel;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Master\JenisKode;

/**
 * Pratinjau sebelum simpan (PRD §14): tiap jenis dalam berkas dibaca dari sheet-nya, diperiksa per baris, lalu
 * dibandingkan dengan master tahun itu — status baru/berubah/tetap/gagal, ditambah kode master yang hilang dari berkas
 * (beserta jumlah kegiatan pemakainya). Jenis yang belum punya pembaca ditandai `dibaca` false.
 */
final class PratinjauMaster
{
    /** Urutan & label status pratinjau. */
    public const STATUS = ['baru' => 'Baru', 'berubah' => 'Berubah', 'tetap' => 'Tetap', 'hilang' => 'Hilang', 'gagal' => 'Gagal'];

    /** Jenis → kolom tampil (kolom pertama = teks utama baris) beserta labelnya. */
    public const KOLOM = [
        'sk' => ['uraian' => 'Uraian'],
        'iksk' => ['uraian' => 'Uraian', 'kode_sk' => 'SK induk'],
        'rktm' => ['sub_kegiatan' => 'Sub kegiatan', 'kegiatan' => 'Kegiatan', 'snp' => 'Standar (SNP)'],
    ];

    /** Jenis → pembaca sheet (jenis lain menyusul). */
    private const PEMBACA = ['sk' => PemeriksaSk::class, 'iksk' => PemeriksaIksk::class, 'rktm' => PemeriksaRktm::class];

    /**
     * @param  array<string, string>  $sheet  Jenis → nama sheet yang dikenali.
     * @return array<string, array{jenis: string, sheet: string, dibaca: bool, galat: string|null, baris: list<array<string, mixed>>, ringkasan: array<string, int>}>
     *
     * @throws GagalBacaImpor Berkas tidak bisa dibuka.
     */
    public static function susun(string $path, string $namaAsli, array $sheet, int $tahun): array
    {
        $kegiatan = array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => TahunKegiatan::dari($k) === $tahun);
        $hasil = [];

        foreach ($sheet as $jenis => $namaSheet) {
            $item = ['jenis' => $jenis, 'sheet' => $namaSheet, 'dibaca' => isset(self::PEMBACA[$jenis]), 'galat' => null, 'baris' => [], 'ringkasan' => array_fill_keys(array_keys(self::STATUS), 0)];
            if ($item['dibaca']) {
                try {
                    $lembar = LembarExcel::buka($path, $namaAsli, $namaSheet);
                    $item['baris'] = self::bandingkan(self::PEMBACA[$jenis]::baca($lembar), $jenis, $tahun, JenisKode::pemakaian($jenis, $kegiatan));
                } catch (GagalPratinjauMaster $e) {
                    $item['galat'] = $e->getMessage();
                }
            }
            $hasil[$jenis] = $item;
        }

        if (isset($hasil['iksk']) && $hasil['iksk']['galat'] === null) {
            $hasil['iksk']['baris'] = self::periksaInduk($hasil['iksk']['baris'], $hasil['sk'] ?? null, $tahun);
        }
        foreach ($hasil as &$item) {
            foreach ($item['baris'] as $b) {
                $item['ringkasan'][$b['status']]++;
            }
        }
        unset($item);

        return $hasil;
    }

    /**
     * IKSK yang SK induknya tidak akan ada setelah disimpan menjadi gagal: dibandingkan dengan SK berkas ini (bila sheet
     * SK ikut diunggah dan terbaca, tanpa yang gagal/hilang) atau master SK tahun itu.
     *
     * @param  list<array<string, mixed>>  $baris
     * @param  array<string, mixed>|null  $sk  Hasil pratinjau SK berkas yang sama.
     * @return list<array<string, mixed>>
     */
    public static function periksaInduk(array $baris, ?array $sk, int $tahun): array
    {
        $dariBerkas = $sk !== null && $sk['galat'] === null;
        $induk = $dariBerkas
            ? array_column(array_filter($sk['baris'], fn (array $b) => ! in_array($b['status'], ['gagal', 'hilang'], true)), 'kode')
            : array_column(JenisKode::baris('sk', $tahun), 'kode');
        $asal = $dariBerkas ? 'SK berkas ini' : "master SK TA {$tahun}";

        return array_map(function (array $b) use ($induk, $asal) {
            if (! in_array($b['status'], ['gagal', 'hilang'], true) && ! in_array($b['tampil']['kode_sk'], $induk, true)) {
                $b['status'] = 'gagal';
                $b['lama'] = null;
                $b['pesan'][] = "SK induk {$b['tampil']['kode_sk']} tidak ada di {$asal}.";
            }

            return $b;
        }, $baris);
    }

    /**
     * Beri status baru/berubah/tetap (baris gagal tetap), sertakan uraian lama yang berubah, lalu tambahkan kode master
     * yang tidak ada di berkas (hilang). Setiap baris membawa jumlah kegiatan pemakai kodenya.
     *
     * @param  list<array{baris: list<int>, kode: string, tampil: array<string, string>, status: string|null, pesan: list<string>}>  $baris
     * @param  array<string, int>  $pakai
     * @return list<array{baris: list<int>, kode: string, tampil: array<string, string>, lama: array<string, string>|null, status: string, pesan: list<string>, dipakai: int}>
     */
    public static function bandingkan(array $baris, string $jenis, int $tahun, array $pakai): array
    {
        $kunci = JenisKode::DAFTAR[$jenis]['kunci'];
        $lama = [];
        foreach (JenisKode::baris($jenis, $tahun) as $b) {
            $lama[(string) $b[$kunci]] = $b;
        }

        $hasil = [];
        $ada = [];
        foreach ($baris as $b) {
            $sebelum = $lama[$b['kode']] ?? null;
            $kolom = array_keys($b['tampil']);
            $tampilLama = $sebelum ? array_combine($kolom, array_map(fn (string $k) => (string) ($sebelum[$k] ?? ''), $kolom)) : null;
            if ($b['status'] !== 'gagal') {
                $ada[$b['kode']] = true;
                $b['status'] = match (true) {
                    $sebelum === null => 'baru',
                    $tampilLama === $b['tampil'] => 'tetap',
                    default => 'berubah',
                };
            }
            $hasil[] = $b + ['lama' => $b['status'] === 'berubah' ? $tampilLama : null, 'dipakai' => $pakai[$b['kode']] ?? 0];
        }

        foreach ($lama as $kode => $b) {
            if (! isset($ada[(string) $kode])) {
                $n = $pakai[(string) $kode] ?? 0;
                $hasil[] = [
                    'baris' => [], 'kode' => (string) $kode, 'tampil' => array_map('strval', array_diff_key($b, [$kunci => true])), 'lama' => null, 'status' => 'hilang',
                    'pesan' => $n > 0 ? ["Tidak ada di berkas, padahal dipakai {$n} kegiatan TA {$tahun} — periksa sebelum menyimpan."] : ['Tidak ada di berkas.'],
                    'dipakai' => $n,
                ];
            }
        }

        return $hasil;
    }
}
