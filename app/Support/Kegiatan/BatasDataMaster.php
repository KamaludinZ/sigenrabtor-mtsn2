<?php

namespace App\Support\Kegiatan;

use App\Support\Format;
use App\Support\Rab\AnggaranKegiatan;
use Illuminate\Validation\ValidationException;

/**
 * Validasi bisnis "Batas Aman" di server (PRD §9 & §20: AI hanya menyarankan kalimat — tidak pernah mengubah angka, kode,
 * atau pejabat) untuk jalur kalimat KAK berbantuan AI (halaman Kalimat KAK, keputusan saran lewat API):
 * - isian: jalur ini hanya boleh menulis `ISIAN` (BagianAi::KOLOM + cover_manual). `galatIsian()`/`periksaIsian()`
 *   menolak isian lain — data master & angka (`DATA_MASTER`: kode RKTM/SK/IKSK/EDM, 5 Budaya Mutu, leading, tahun
 *   anggaran, sumber dana & pagu, Data Pagu, panitia, status, RAB & termin) beserta tempat mengubahnya;
 * - isi: `galatTeks($teks, $kegiatan)` = kalimat yang bertentangan dengan data master kegiatan — kode SK/IKSK/RKTM yang
 *   bukan kode kegiatan ini (atau kegiatan belum memilih kode itu), nominal rupiah yang bukan pagu/total RAB/termin
 *   kegiatan ini, dan NIP. Memblokir pemakaian & penyimpanan (BatasAmanSaran tetap hanya memperingatkan angka atau nama
 *   yang belum dikenal). `pastikanTeks()` → ValidationException berkunci kolom.
 */
final class BatasDataMaster
{
    /** Isian yang boleh ditulis jalur kalimat KAK berbantuan AI. */
    public const ISIAN = [...BagianAi::KOLOM, 'cover_manual'];

    /** Isian data master & angka → [label, tempat mengubahnya]. */
    public const DATA_MASTER = [
        'kode_rktm' => ['Kode RKTM', 'halaman Kode & Klasifikasi'],
        'kode_sk' => ['Kode SK', 'halaman Kode & Klasifikasi'],
        'kode_iksk' => ['IKSK', 'halaman Kode & Klasifikasi'],
        'kode_edm' => ['Kode EDM', 'halaman Kode & Klasifikasi'],
        'budaya_mutu' => ['5 Budaya Mutu', 'halaman Kode & Klasifikasi'],
        'leading' => ['Leading', 'Data Kegiatan'],
        'tahun_anggaran' => ['Tahun anggaran', 'Pengaturan › Tahun Anggaran'],
        'sumber_dana' => ['Sumber dana & pagu', 'Data Kegiatan › Sumber dana'],
        'data_pagu_id' => ['Data Pagu', 'Data Kegiatan'],
        'panitia' => ['Pelaksana & panitia', 'Data Kegiatan'],
        'status' => ['Status kegiatan', 'panel status Data Kegiatan'],
        'rab' => ['Rincian RAB', 'halaman RAB'],
        'termin' => ['Termin pembayaran', 'halaman RAB › Termin pembayaran'],
    ];

    private const POLA_SK = '/(?<![\p{L}\d])SK\s*[.\-]?\s*(\d{1,3})(?![\d.]\d)/u';

    private const POLA_IKSK = '/(?<![\p{L}\d])IKSK\s*(\d{1,3})(?:\s*[.\-]\s*(\d{1,3}))?(?![\d.]\d)/u';

    /** Kode RKTM tiga bagian kecil (bukan nominal ribuan "10.000.000"). */
    private const POLA_RKTM = '/(?<![\d.,])(\d{1,2})\.(\d{1,2})\.(\d{1,2})(?![\d,]|\.\d)/u';

    private const POLA_RUPIAH = '/(?<![\p{L}])Rp\.?\s*(\d[\d.]*)(?:,(\d+|-))?(?:\s*(ribu|juta|miliar|triliun)\b)?/iu';

    private const POLA_NIP = '/(?<!\d)\d{18}(?!\d)|(?<!\d)\d{8}\s\d{6}\s\d\s\d{3}(?!\d)/';

    private const KALI = ['ribu' => 1_000, 'juta' => 1_000_000, 'miliar' => 1_000_000_000, 'triliun' => 1_000_000_000_000];

    /**
     * Galat isian di luar ISIAN, berkunci isian.
     *
     * @param  array<string, mixed>  $isian
     * @return array<string, string>
     */
    public static function galatIsian(array $isian): array
    {
        $galat = [];
        foreach (array_diff(array_keys($isian), self::ISIAN) as $kunci) {
            $galat[$kunci] = isset(self::DATA_MASTER[$kunci])
                ? self::DATA_MASTER[$kunci][0].' tidak bisa diubah lewat Kalimat KAK atau saran AI — ubah di '.self::DATA_MASTER[$kunci][1].'.'
                : "Isian {$kunci} bukan kalimat KAK — di sini hanya nama cover, latar belakang, tujuan, sasaran, dan pelaksanaan yang bisa diubah.";
        }

        return $galat;
    }

    /**
     * @param  array<string, mixed>  $isian
     *
     * @throws ValidationException
     */
    public static function periksaIsian(array $isian): void
    {
        if ($galat = self::galatIsian($isian)) {
            throw ValidationException::withMessages($galat);
        }
    }

    /**
     * Pertentangan kalimat dengan data master kegiatan (kosong = aman).
     *
     * @param  array<string, mixed>  $kegiatan
     * @return list<string>
     */
    public static function galatTeks(string $teks, array $kegiatan): array
    {
        $galat = [];
        if (preg_match(self::POLA_NIP, $teks)) {
            $galat[] = 'Kalimat memuat NIP — NIP hanya tercetak di lembar pengesahan.';
        }
        if ($kode = self::kodeAsing($teks, $kegiatan)) {
            $galat[] = 'Kalimat menyebut '.implode(', ', $kode).' yang bukan kode kegiatan ini — kode dipilih di halaman Kode & Klasifikasi, bukan diubah lewat kalimat.';
        }
        if ($nominal = self::nominalAsing($teks, $kegiatan)) {
            $galat[] = 'Kalimat menyebut '.implode(', ', array_map(Format::rupiah(...), $nominal)).' yang tidak sama dengan pagu, total RAB, atau termin kegiatan ini — angka anggaran diubah di Data Kegiatan atau halaman RAB.';
        }

        return $galat;
    }

    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array<string, string>  $teks  Kolom → teks yang akan dipakai/disimpan.
     *
     * @throws ValidationException Berkunci kolom (dengan $awalan, mis. "form.").
     */
    public static function pastikanTeks(array $kegiatan, array $teks, string $awalan = ''): void
    {
        $galat = [];
        foreach ($teks as $kolom => $isi) {
            if ($pesan = self::galatTeks((string) $isi, $kegiatan)) {
                $galat[$awalan.$kolom] = $pesan;
            }
        }
        if ($galat !== []) {
            throw ValidationException::withMessages($galat);
        }
    }

    /**
     * Kode SK/IKSK/RKTM di teks yang bukan kode kegiatan.
     *
     * @return list<string>
     */
    private static function kodeAsing(string $teks, array $kegiatan): array
    {
        $milik = fn (string $kolom) => trim((string) ($kegiatan[$kolom] ?? ''));
        $asing = [];

        preg_match_all(self::POLA_SK, $teks, $sk, PREG_SET_ORDER);
        foreach ($sk as $c) {
            $kode = 'SK.'.((int) $c[1]);
            if ($kode !== $milik('kode_sk')) {
                $asing[] = $kode;
            }
        }

        preg_match_all(self::POLA_IKSK, $teks, $iksk, PREG_SET_ORDER);
        foreach ($iksk as $c) {
            $kode = 'IKSK '.((int) $c[1]).(isset($c[2]) && $c[2] !== '' ? '.'.((int) $c[2]) : '');
            // "IKSK 19" tanpa nomor indikator cocok dengan IKSK 19.x milik kegiatan.
            $cocok = isset($c[2]) && $c[2] !== '' ? $kode === $milik('kode_iksk') : str_starts_with($milik('kode_iksk'), $kode.'.');
            if (! $cocok) {
                $asing[] = $kode;
            }
        }

        $rktm = preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $milik('kode_rktm'), $r) ? ((int) $r[1]).'.'.((int) $r[2]).'.'.((int) $r[3]) : null;
        preg_match_all(self::POLA_RKTM, $teks, $kodeRktm, PREG_SET_ORDER);
        foreach ($kodeRktm as $c) {
            $kode = ((int) $c[1]).'.'.((int) $c[2]).'.'.((int) $c[3]);
            if ($kode !== $rktm) {
                $asing[] = 'kode RKTM '.$kode;
            }
        }

        return array_values(array_unique($asing));
    }

    /**
     * Nominal rupiah di teks yang bukan angka anggaran kegiatan (pagu, total RAB, termin per sumber & gabungan).
     *
     * @return list<int>
     */
    private static function nominalAsing(string $teks, array $kegiatan): array
    {
        if (! preg_match_all(self::POLA_RUPIAH, $teks, $cocok, PREG_SET_ORDER)) {
            return [];
        }

        $dikenal = [];
        $pagu = $total = 0;
        foreach (AnggaranKegiatan::untuk($kegiatan) as $a) {
            array_push($dikenal, $a->total(), ...array_map(fn (array $t) => (int) ($t['nominal'] ?? 0), $a->termin));
            if ($a->pagu() !== null) {
                $dikenal[] = $a->pagu();
                $pagu += $a->pagu();
            }
            $total += $a->total();
        }
        array_push($dikenal, $pagu, $total);

        $asing = [];
        foreach ($cocok as $c) {
            $angka = (int) preg_replace('/\D/', '', $c[1]);
            if (($c[3] ?? '') !== '') {
                // "Rp1,5 juta" → 1.500.000.
                $desimal = ($c[2] ?? '') !== '' && $c[2] !== '-' ? (float) ('0.'.$c[2]) : 0.0;
                $angka = (int) round(($angka + $desimal) * self::KALI[mb_strtolower($c[3])]);
            }
            if ($angka > 0 && ! in_array($angka, $dikenal, true)) {
                $asing[] = $angka;
            }
        }

        return array_values(array_unique($asing));
    }
}
