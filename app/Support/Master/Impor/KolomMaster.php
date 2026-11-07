<?php

namespace App\Support\Master\Impor;

use App\Support\Impor\LembarExcel;
use App\Support\Master\FormatKode;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Kolom berkas master kode per jenis (PRD §14) — satu sumber aturan kolom wajib & format teks untuk semua pembaca
 * (Pemeriksa*): label kolom, wajib/opsional, panjang maksimal isian (sejalan dengan kolom tabel master), dan judul yang
 * dikenali (konstanta JUDUL pembacanya).
 * - `judul($lembar, $jenis)`: baris judul tabel; kolom wajib yang tidak ada → GagalPratinjauMaster yang menyebut kolom
 *   mana yang belum ada (dan judul yang dikenali) bila ada baris judul yang hampir lengkap, selain itu daftar semua kolom
 *   wajib.
 * - `galatPanjang($jenis, $isian)`: isian yang melebihi batas panjang → pesan per kolom (baris menjadi gagal).
 * - `opsionalHilang($judul, $jenis)`: catatan tingkat jenis untuk kolom opsional yang tidak ada (memakai bawaan).
 */
final class KolomMaster
{
    /** Jenis → kolom: kunci → [label, wajib, panjang maksimal (null = tanpa batas teks)]. */
    public const KOLOM = [
        'sk' => ['kode' => ['Kode SK', true, null], 'uraian' => ['Uraian SK', true, 1000]],
        'iksk' => ['kode' => ['Kode IKSK', true, null], 'kode_sk' => ['Kode SK', true, null], 'uraian' => ['Uraian IKSK', true, 1000]],
        'rktm' => ['kode' => ['Kode', true, null], 'snp' => ['SNP', false, 300], 'kegiatan' => ['Kegiatan', false, 500], 'sub_kegiatan' => ['Sub Kegiatan', true, 1000]],
        'edm' => ['kode' => ['Kode', true, null], 'uraian' => ['Uraian', true, 1000], 'komponen' => ['Komponen', false, 100], 'rktm' => ['Kode RKTM', false, null]],
        'budaya' => ['nomor' => ['No', false, null], 'nama' => ['Budaya Mutu', true, 150]],
        'rencana_aksi' => [
            'uraian' => ['Rencana Aksi', true, 500], 'kode_sk' => ['Kode SK', false, null], 'kode_iksk' => ['Kode IKSK', false, null],
            'kode_rktm' => ['Kode RKTM', false, null], 'unit' => ['Unit/Tim Penerima Mandat', false, 150],
            'pagu_dipa' => ['Anggaran DIPA', false, null], 'pagu_komite' => ['Anggaran Komite', false, null],
        ],
    ];

    /** Jenis → pemeriksa pola isi kolom kode (memilih kolom "Kode" yang benar bila judulnya ada lebih dari satu). */
    private const POLA_KODE = ['sk', 'iksk', 'rktm', 'edm'];

    /** Kolom opsional yang tidak ada → catatan bawaan yang dipakai. */
    private const BAWAAN_OPSIONAL = [
        'rktm.snp' => 'SNP diambil dari baris judul standar (mis. "PENGEMBANGAN STANDAR ISI") atau master',
        'rktm.kegiatan' => 'nama kegiatan diambil dari kode n.n yang sama di berkas atau master',
        'edm.komponen' => 'nama komponen memakai master tahun itu',
        'edm.rktm' => false, // Relasi EDM ↔ RKTM opsional: tanpa kolom ini relasi master tetap (tanpa catatan).
        'budaya.nomor' => 'nomor mengikuti urutan baris',
        'rencana_aksi.kode_sk' => 'SK diisi dari tag di Master Kode',
        'rencana_aksi.kode_iksk' => 'IKSK diisi dari tag di Master Kode',
        'rencana_aksi.kode_rktm' => 'tag RKTM & EDM otomatis tidak terisi',
        'rencana_aksi.unit' => 'leading dipilih dari tag di Master Kode',
        'rencana_aksi.pagu_dipa' => 'anggaran DIPA dianggap 0',
        'rencana_aksi.pagu_komite' => 'anggaran Komite dianggap 0',
    ];

    /** Jenis → judul kolom yang dikenali (konstanta JUDUL pembacanya). */
    public static function sinonim(string $jenis): array
    {
        return match ($jenis) {
            'sk' => PemeriksaSk::JUDUL,
            'iksk' => PemeriksaIksk::JUDUL,
            'rktm' => PemeriksaRktm::JUDUL,
            'edm' => PemeriksaEdm::JUDUL,
            'budaya' => PemeriksaBudayaMutu::JUDUL,
            'rencana_aksi' => PemeriksaRencanaAksi::JUDUL,
        };
    }

    /** @return list<string> */
    public static function wajib(string $jenis): array
    {
        return array_keys(array_filter(self::KOLOM[$jenis], fn (array $k) => $k[1]));
    }

    /**
     * Baris judul tabel & huruf kolom tiap isian. Judul baku dicari lebih dulu (kolom "Kode" yang judulnya ada lebih dari
     * satu dipilih dari isinya — pilihKolomKode); bila tidak ada, sheet SK/IKSK/Rencana Aksi dicoba sebagai tata letak
     * cascade Perkin (TataLetakPerkin — `cascade` true). EDM tanpa kolom "Kode RKTM" memakai kolom kode RKTM di sheet yang
     * sama (sheet gabungan EDM ↔ RKTM, mis. 5BM_SNP) sebagai relasinya. Rencana Aksi membawa `bulan` (bulan → huruf kolom).
     *
     * @return array{baris: int, kolom: array<string, string>, cascade?: bool, bulan?: array<int, string>}
     *
     * @throws GagalPratinjauMaster
     */
    public static function judul(Worksheet $lembar, string $jenis): array
    {
        $sinonim = self::sinonim($jenis);
        $wajib = self::wajib($jenis);
        $judul = LembarExcel::cariJudul($lembar, $sinonim, $wajib);
        // Rencana aksi berjudul tanpa kolom Kode IKSK (mis. sheet Perkin asli): tata letak cascade lebih lengkap.
        if ($judul !== null && $jenis === 'rencana_aksi' && ! isset($judul['kolom']['kode_iksk']) && ($cascade = self::cascade($lembar, $jenis)) !== null) {
            return $cascade;
        }
        if ($judul !== null) {
            $judul = self::pilihKolomKode($lembar, $judul, $jenis);
            if ($jenis === 'edm' && ! isset($judul['kolom']['rktm'])) {
                try {
                    $rktm = self::judul($lembar, 'rktm');
                    if ($rktm['kolom']['kode'] !== $judul['kolom']['kode']) {
                        $judul['kolom']['rktm'] = $rktm['kolom']['kode'];
                    }
                } catch (GagalPratinjauMaster) {
                    // Sheet EDM tanpa kode RKTM: relasi master tetap.
                }
            }
            if ($jenis === 'rencana_aksi') {
                $judul['bulan'] = self::bulanJudul($lembar, $judul['baris']);
            }

            return $judul;
        }

        // Tata letak cascade hanya bila tidak ada baris judul baku yang hampir lengkap (selain itu sebut kolom yang kurang).
        $terdekat = LembarExcel::judulTerdekat($lembar, $sinonim, $wajib);
        if ($terdekat === null && in_array($jenis, ['sk', 'iksk', 'rencana_aksi'], true) && ($cascade = self::cascade($lembar, $jenis)) !== null) {
            return $cascade;
        }

        $label = fn (string $kunci) => '"'.self::KOLOM[$jenis][$kunci][0].'"';
        if ($terdekat === null) {
            throw new GagalPratinjauMaster('Judul kolom '.self::gabung(array_map($label, $wajib)).' tidak ditemukan di sheet '.$lembar->getTitle().'.');
        }

        $hilang = array_values(array_diff($wajib, array_keys($terdekat['kolom'])));
        $dikenali = implode('; ', array_map(fn (string $k) => self::KOLOM[$jenis][$k][0].': '.implode(', ', array_map(fn (string $j) => "\"{$j}\"", array_slice($sinonim[$k], 0, 3))), $hilang));

        throw new GagalPratinjauMaster('Kolom wajib '.self::gabung(array_map($label, $hilang)).' tidak ditemukan di baris judul sheet '.$lembar->getTitle()." (baris {$terdekat['baris']}) — judul yang dikenali {$dikenali}.");
    }

    /**
     * Pesan isian yang melebihi batas panjang kolomnya.
     *
     * @param  array<string, string>  $isian  Kunci kolom → teks.
     * @return list<string>
     */
    public static function galatPanjang(string $jenis, array $isian): array
    {
        $pesan = [];
        foreach ($isian as $kunci => $teks) {
            $maks = self::KOLOM[$jenis][$kunci][2] ?? null;
            if ($maks !== null && ($n = mb_strlen($teks)) > $maks) {
                $pesan[] = self::KOLOM[$jenis][$kunci][0]." terlalu panjang ({$n} karakter, maksimal {$maks}).";
            }
        }

        return $pesan;
    }

    /**
     * Catatan untuk kolom opsional yang tidak ada di baris judul.
     *
     * @param  array{kolom: array<string, string>}  $judul
     * @return list<string>
     */
    public static function opsionalHilang(array $judul, string $jenis): array
    {
        $pesan = [];
        foreach (self::KOLOM[$jenis] as $kunci => [$label, $wajib]) {
            if (! $wajib && ! isset($judul['kolom'][$kunci]) && (self::BAWAAN_OPSIONAL["{$jenis}.{$kunci}"] ?? null) !== false) {
                $pesan[] = "Kolom \"{$label}\" tidak ada — ".(self::BAWAAN_OPSIONAL["{$jenis}.{$kunci}"] ?? 'dikosongkan').'.';
            }
        }

        return $pesan;
    }

    /**
     * Isi sel teks sebagai judul kelompok (mis. "BIDANG TATA KELOLA", "PENGEMBANGAN STANDAR ISI"): bukan kode (tanpa
     * angka) sementara isian lain baris itu kosong — baris dilewati, bukan gagal.
     *
     * @param  list<string>  $lain
     */
    public static function judulKelompok(string $mentah, array $lain): bool
    {
        return $mentah !== '' && ! preg_match('/\d/', $mentah) && array_filter($lain, fn (string $v) => $v !== '' && $v !== '0') === [];
    }

    /**
     * Bila judul kolom "Kode" (sinonim kode) ada lebih dari satu di baris judul, pakai kolom yang isinya paling banyak
     * cocok dengan pola kode jenis itu (mis. sheet 5BM_SNP: "kode" nomor indikator vs "Kode" sub kegiatan RKTM).
     *
     * @param  array{baris: int, kolom: array<string, string>}  $judul
     * @return array{baris: int, kolom: array<string, string>}
     */
    private static function pilihKolomKode(Worksheet $lembar, array $judul, string $jenis): array
    {
        if (! in_array($jenis, self::POLA_KODE, true) || ! isset($judul['kolom']['kode'])) {
            return $judul;
        }
        $kenal = array_map([LembarExcel::class, 'normalkanJudul'], self::sinonim($jenis)['kode']);
        $calon = [];
        foreach ($lembar->getRowIterator($judul['baris'], $judul['baris'])->current()->getCellIterator('A', $lembar->getHighestDataColumn()) as $sel) {
            if (in_array(LembarExcel::normalkanJudul((string) $sel->getValue()), $kenal, true)) {
                $calon[] = $sel->getColumn();
            }
        }
        if (count($calon) < 2) {
            return $judul;
        }

        $cocok = fn (string $nilai) => match ($jenis) {
            'sk' => preg_match('/^SK\b/i', $nilai) && FormatKode::sk($nilai) !== null,
            'iksk' => preg_match('/^IKSK\b/i', $nilai) && FormatKode::iksk($nilai) !== null,
            'rktm' => PemeriksaRktm::normalkanKode($nilai) !== null,
            'edm' => PemeriksaEdm::normalkanKode($nilai) !== null,
        };
        $akhir = min($judul['baris'] + 400, $lembar->getHighestDataRow());
        $skor = [];
        foreach ($calon as $huruf) {
            $skor[$huruf] = 0;
            for ($b = $judul['baris'] + 1; $b <= $akhir; $b++) {
                $nilai = trim((string) LembarExcel::nilaiSel($lembar, $huruf.$b));
                if ($nilai !== '' && $cocok($nilai)) {
                    $skor[$huruf]++;
                }
            }
        }
        arsort($skor);
        $terbaik = (string) array_key_first($skor);
        if ($skor[$terbaik] > ($skor[$judul['kolom']['kode']] ?? 0)) {
            $judul['kolom']['kode'] = $terbaik;
        }

        return $judul;
    }

    /**
     * Tata letak cascade Perkin sebagai judul jenis $jenis (null bila kolom wajibnya tidak dikenali).
     *
     * @return array{baris: int, kolom: array<string, string>, cascade: bool, bulan?: array<int, string>}|null
     */
    private static function cascade(Worksheet $lembar, string $jenis): ?array
    {
        $tata = TataLetakPerkin::kenali($lembar);
        if ($tata === null) {
            return null;
        }
        $k = $tata['kolom'];
        $peta = match ($jenis) {
            'sk' => isset($k['kode_sk']) ? ['kode' => $k['kode_sk'], 'uraian' => $k['uraian_sk']] : null,
            'iksk' => isset($k['kode_iksk']) ? array_filter(['kode' => $k['kode_iksk'], 'kode_sk' => $k['kode_sk'] ?? null, 'uraian' => $k['uraian_iksk']]) : null,
            'rencana_aksi' => isset($k['uraian']) ? array_intersect_key($k, array_flip(['uraian', 'kode_sk', 'kode_iksk', 'kode_rktm', 'unit', 'pagu_dipa', 'pagu_komite'])) : null,
        };
        if ($peta === null || array_diff(self::wajib($jenis), array_keys($peta)) !== []) {
            return null;
        }

        return ['baris' => $tata['baris'], 'kolom' => $peta, 'cascade' => true] + ($jenis === 'rencana_aksi' ? ['bulan' => $tata['bulan']] : []);
    }

    /**
     * Kolom bulan 1–12 di baris judul atau dua baris di bawahnya (judul bertingkat "Time Line" → 1..12).
     *
     * @return array<int, string>
     */
    private static function bulanJudul(Worksheet $lembar, int $baris): array
    {
        $akhirKolom = Coordinate::columnIndexFromString($lembar->getHighestDataColumn());
        foreach ([$baris, $baris + 1, $baris - 1] as $b) {
            if ($b < 1) {
                continue;
            }
            $isi = [];
            for ($k = 1; $k <= $akhirKolom; $k++) {
                $nilai = TataLetakPerkin::nilai($lembar, Coordinate::stringFromColumnIndex($k).$b);
                if ($nilai !== null && $nilai !== '') {
                    $isi[$k] = $nilai;
                }
            }
            if (($bulan = TataLetakPerkin::bulanDariBaris($isi)) !== []) {
                return $bulan;
            }
        }

        return [];
    }

    /** ["A"] → "A"; ["A","B"] → "A" dan "B"; ["A","B","C"] → "A", "B", dan "C". */
    private static function gabung(array $bagian): string
    {
        return count($bagian) <= 2 ? implode(' dan ', $bagian) : implode(', ', array_slice($bagian, 0, -1)).', dan '.end($bagian);
    }
}
