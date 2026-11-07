<?php

namespace App\Support\Master\Impor;

use App\Contracts\KegiatanRepository;
use App\Support\Impor\GagalBacaImpor;
use App\Support\Impor\LembarExcel;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Master\JenisKode;
use App\Support\Master\RencanaAksi;
use App\Support\MasterKode;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pratinjau sebelum simpan (PRD §14): tiap jenis dalam berkas dibaca dari sheet-nya, diperiksa per baris, lalu
 * dibandingkan dengan master tahun itu — status baru/berubah/tetap/gagal, ditambah kode master yang hilang dari berkas
 * (beserta jumlah kegiatan pemakainya). `peringatan` = catatan tingkat jenis (kolom opsional yang tidak ada —
 * KolomMaster::opsionalHilang, jumlah 5 Budaya Mutu).
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
        'edm' => ['uraian' => 'Uraian', 'komponen' => 'Komponen', 'rktm' => 'RKTM terkait'],
        'budaya' => ['nama' => 'Budaya mutu'],
        'rencana_aksi' => ['uraian' => 'Rencana aksi', 'kode_sk' => 'SK', 'kode_iksk' => 'IKSK', 'kode_rktm' => 'RKTM', 'unit' => 'Unit', 'leading' => 'Leading', 'pagu_dipa' => 'DIPA', 'pagu_komite' => 'Komite', 'bulan' => 'Bulan'],
    ];

    /** Jenis → pembaca sheet. */
    private const PEMBACA = ['sk' => PemeriksaSk::class, 'iksk' => PemeriksaIksk::class, 'rktm' => PemeriksaRktm::class, 'edm' => PemeriksaEdm::class, 'budaya' => PemeriksaBudayaMutu::class, 'rencana_aksi' => PemeriksaRencanaAksi::class];

    /** Jenis yang boleh dicari di sheet lain bila judul kolomnya tidak ada di sheet yang dikenali dari namanya. */
    private const CARI_SHEET_LAIN = ['sk', 'iksk', 'rktm', 'edm', 'rencana_aksi'];

    /**
     * @param  array<string, string>  $sheet  Jenis → nama sheet yang dikenali.
     * @param  list<string>  $opsional  Jenis yang dilewati (bukan galat) bila kolomnya tidak ada.
     * @return array<string, array{jenis: string, sheet: string, galat: string|null, peringatan: list<string>, baris: list<array<string, mixed>>, ringkasan: array<string, int>}>
     *
     * @throws GagalBacaImpor Berkas tidak bisa dibuka.
     */
    public static function susun(string $path, string $namaAsli, array $sheet, int $tahun, array $opsional = []): array
    {
        $kegiatan = array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => TahunKegiatan::dari($k) === $tahun);
        $hasil = [];
        $buku = null; // Berkas dimuat sekali untuk semua jenis.

        foreach ($sheet as $jenis => $namaSheet) {
            $item = ['jenis' => $jenis, 'sheet' => $namaSheet, 'galat' => null, 'peringatan' => [], 'baris' => [], 'ringkasan' => array_fill_keys(array_keys(self::STATUS), 0)];
            try {
                $buku ??= LembarExcel::buka($path, $namaAsli)->getParent();
                $lembar = $buku?->getSheetByName($namaSheet) ?? LembarExcel::buka($path, $namaAsli, $namaSheet);
                [$lembar, $baca] = self::bacaLembar($lembar, $jenis, $tahun);
                if ($baca === [] && in_array($jenis, $opsional, true)) {
                    continue; // Jenis opsional tanpa satu baris pun di berkas (mis. sheet Perkin tanpa rencana aksi): tidak diubah.
                }
                if ($lembar->getTitle() !== $namaSheet && $namaSheet !== 'CSV') {
                    $item['peringatan'][] = 'Kolom '.JenisKode::DAFTAR[$jenis]['label']." tidak ada di sheet {$namaSheet} — dibaca dari sheet {$lembar->getTitle()}.";
                    $item['sheet'] = $lembar->getTitle();
                }
                $item['baris'] = $jenis === 'rencana_aksi'
                    ? self::bandingkanRencanaAksi($baca, $tahun, $kegiatan)
                    : self::bandingkan($baca, $jenis, $tahun, JenisKode::pemakaian($jenis, $kegiatan, $tahun));
                $item['peringatan'] = [...$item['peringatan'], ...KolomMaster::opsionalHilang(KolomMaster::judul($lembar, $jenis), $jenis)];
                if ($jenis === 'rencana_aksi' && count($item['baris']) > RencanaAksi::MAKS) {
                    $item['peringatan'][] = 'Lebih dari '.RencanaAksi::MAKS.' rencana aksi — bagi menjadi beberapa tahun/berkas.';
                }
                if ($jenis === 'budaya') {
                    $item['peringatan'] = [...$item['peringatan'], ...array_filter([PemeriksaBudayaMutu::peringatanJumlah(count(array_filter($baca, fn (array $b) => $b['status'] !== 'gagal')))])];
                }
            } catch (GagalPratinjauMaster $e) {
                if (in_array($jenis, $opsional, true)) {
                    continue; // Jenis opsional (sheet bersama) tanpa kolomnya: tidak diunggah.
                }
                $item['galat'] = $e->getMessage();
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
     * Baca $lembar dengan pembaca jenisnya; bila judul kolomnya tidak ada, coba sheet lain di berkas yang sama (jenis
     * berpola kode — CARI_SHEET_LAIN), mis. RKTM di sheet 5BM_SNP padahal yang dikenali dari namanya 8SNP.
     *
     * @return array{0: Worksheet, 1: list<array<string, mixed>>}
     *
     * @throws GagalPratinjauMaster Tidak ada sheet yang memuat kolomnya.
     */
    private static function bacaLembar(Worksheet $lembar, string $jenis, int $tahun): array
    {
        $baca = fn ($l) => $jenis === 'edm' ? PemeriksaEdm::baca($l, MasterKode::komponenEdm($tahun)) : self::PEMBACA[$jenis]::baca($l);
        try {
            return [$lembar, $baca($lembar)];
        } catch (GagalPratinjauMaster $e) {
            if (! in_array($jenis, self::CARI_SHEET_LAIN, true) || $lembar->getParent() === null) {
                throw $e;
            }
            foreach ($lembar->getParent()->getAllSheets() as $lain) {
                if ($lain === $lembar) {
                    continue;
                }
                try {
                    $hasil = $baca($lain);
                } catch (GagalPratinjauMaster) {
                    continue;
                }
                if (array_filter($hasil, fn (array $b) => $b['status'] !== 'gagal') !== []) {
                    return [$lain, $hasil];
                }
            }
            throw $e;
        }
    }

    /**
     * Bandingkan rencana aksi berkas dengan master tahun itu. Pembanding = RencanaAksi::kunci (nama + IKSK), lalu nama
     * saja bila tunggal. Isian tag yang kosong di berkas (SK, IKSK, RKTM, leading) memakai tag tersimpan rencana aksi itu
     * sehingga tag yang diisi admin di Master Kode tidak hilang saat berkas diunggah ulang. `cocok` = kode rencana aksi lama
     * yang dicocokkan (tag EDM & 5 Budaya Mutu manualnya ikut tersimpan). `dipakai` = kegiatan bernama sama.
     *
     * @param  list<array<string, mixed>>  $baris
     * @param  iterable<array<string, mixed>>  $kegiatan
     * @return list<array<string, mixed>>
     */
    public static function bandingkanRencanaAksi(array $baris, int $tahun, iterable $kegiatan): array
    {
        $perNama = [];
        foreach ($kegiatan as $k) {
            $n = PemeriksaRencanaAksi::kunciNama((string) ($k['nama_kegiatan'] ?? ''));
            $perNama[$n] = ($perNama[$n] ?? 0) + 1;
        }
        $lama = MasterKode::rencanaAksi($tahun);
        $perKunci = [];
        $perNamaLama = [];
        foreach ($lama as $i => $ra) {
            $perKunci[RencanaAksi::kunci($ra['uraian'], $ra['kode_iksk'] ?? '')] = $i;
            $perNamaLama[PemeriksaRencanaAksi::kunciNama($ra['uraian'])][] = $i;
        }
        $tampilLama = fn (array $ra) => [
            'uraian' => (string) $ra['uraian'], 'kode_sk' => (string) ($ra['kode_sk'] ?? ''), 'kode_iksk' => (string) ($ra['kode_iksk'] ?? ''),
            'kode_rktm' => (string) ($ra['kode_rktm'] ?? ''), 'unit' => (string) ($ra['unit'] ?? ''), 'leading' => (string) ($ra['kode_leading'] ?? ''),
            'pagu_dipa' => (string) (int) ($ra['pagu_dipa'] ?? 0), 'pagu_komite' => (string) (int) ($ra['pagu_komite'] ?? 0),
            'bulan' => implode(', ', array_map('intval', (array) ($ra['bulan'] ?? []))),
        ];

        $dipakaiLama = [];
        $hasil = [];
        foreach ($baris as $b) {
            $nama = PemeriksaRencanaAksi::kunciNama((string) $b['tampil']['uraian']);
            $b['dipakai'] = $perNama[$nama] ?? 0;
            $b['lama'] = null;
            $b['cocok'] = null;
            if ($b['status'] === 'gagal') {
                $hasil[] = $b;

                continue;
            }

            $i = $perKunci[RencanaAksi::kunci((string) $b['tampil']['uraian'], (string) $b['tampil']['kode_iksk'])] ?? null;
            if ($i === null && count($perNamaLama[$nama] ?? []) === 1) {
                $i = $perNamaLama[$nama][0];
            }
            if ($i !== null && isset($dipakaiLama[$i])) {
                $i = null;
            }
            if ($i === null) {
                $b['status'] = 'baru';
                $hasil[] = $b;

                continue;
            }

            $dipakaiLama[$i] = true;
            $sebelum = $tampilLama($lama[$i]);
            foreach (['kode_sk', 'kode_iksk', 'kode_rktm', 'leading'] as $tag) {
                if ($b['tampil'][$tag] === '' && $sebelum[$tag] !== '') {
                    $b['tampil'][$tag] = $sebelum[$tag];
                    $b['pesan'] = array_values(array_filter($b['pesan'], fn (string $p) => ! ($tag === 'leading' && str_starts_with($p, 'Unit '))
                        && ! ($tag === 'kode_rktm' && str_starts_with($p, 'Tanpa kode RKTM'))));
                }
            }
            $b['cocok'] = $lama[$i]['kode'];
            $b['status'] = $sebelum === $b['tampil'] ? 'tetap' : 'berubah';
            $b['lama'] = $b['status'] === 'berubah' ? $sebelum : null;
            $hasil[] = $b;
        }

        foreach ($lama as $i => $ra) {
            if (! isset($dipakaiLama[$i])) {
                $n = $perNama[PemeriksaRencanaAksi::kunciNama($ra['uraian'])] ?? 0;
                $hasil[] = [
                    'baris' => [], 'kode' => (string) $ra['kode'], 'tampil' => $tampilLama($ra), 'lama' => null, 'status' => 'hilang', 'cocok' => null,
                    'pesan' => $n > 0 ? ["Tidak ada di berkas, padahal {$n} kegiatan TA {$tahun} bernama sama — periksa sebelum menyimpan."] : ['Tidak ada di berkas.'],
                    'dipakai' => $n,
                ];
            }
        }

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
        // EDM: master menyimpan huruf komponen, berkas & pratinjau memakai namanya.
        $komponen = $jenis === 'edm' ? MasterKode::komponenEdm($tahun) : [];
        // EDM: relasi EDM ↔ RKTM master hanya ikut dibandingkan bila berkas memuat kolom Kode RKTM.
        $denganRelasi = $jenis === 'edm' && array_filter($baris, fn (array $b) => array_key_exists('rktm', $b['tampil'])) !== [];
        $relasi = [];
        if ($denganRelasi) {
            foreach (MasterKode::relasiEdmRktm($tahun) as $r) {
                $relasi[$r['kode_edm']][] = $r['kode_rktm'];
            }
        }
        $lama = [];
        foreach (JenisKode::baris($jenis, $tahun) as $b) {
            if ($jenis === 'edm') {
                $b['komponen'] = $komponen[$b['komponen']] ?? $b['komponen'];
                if ($denganRelasi) {
                    $b['rktm'] = implode(', ', $relasi[$b['kode']] ?? []);
                }
            }
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
