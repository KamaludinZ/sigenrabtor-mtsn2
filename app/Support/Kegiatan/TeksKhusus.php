<?php

namespace App\Support\Kegiatan;

use App\Support\Dokumen\FormatKakTor;
use App\Support\Stub\StubData;

/**
 * Teks baku KAK/TOR yang bisa disesuaikan per kegiatan (PRD F4 "Template Master Ber-variabel": isi baku dipakai kecuali
 * disesuaikan). Hanya prosa tanpa angka, tanggal, kode, atau jabatan master — tahapan persiapan (4a), kalimat analisis
 * hasil (4c), dan kalimat pengarah (6b); format 5 bab juga penanggung jawab (II.5a), narasumber (II.6), output & outcome
 * (IV); kalimat pelaksanaan/pelaporan/tempat/pelaksana/pembiayaan tetap diturunkan dari
 * isian & master agar sinkron. Tersimpan di kegiatan `teks_khusus` {kunci: teks|daftar} lewat UbahDataKegiatan; teks
 * boleh memakai penanda `{nama_kegiatan}` (diisi nama tanpa awalan "Kegiatan" saat dicetak, `cetak()`); sama dengan teks
 * baku (`bakuMentah()` = KalimatKak berpenanda) = tidak disimpan. Aturan `galat()` dipakai ValidasiKegiatan; database:
 * kolom activities.teks_khusus (NULL = semua baku) dengan CHECK `teks_khusus_sah()` dari konstanta yang sama (migrasi
 * 000038).
 */
final class TeksKhusus
{
    public const PENANDA = '{nama_kegiatan}';

    /**
     * Kunci → [nomor bagian KAK/TOR di format asalnya, label, jenis daftar|paragraf, panjang min, panjang maks per
     * teks/butir]. Nomor & bagian tempatnya per format lewat FormatKakTor (penanggung jawab, narasumber, output, outcome
     * hanya ada di format 5 bab — nomornya nomor bab).
     */
    public const BIDANG = [
        'persiapan' => [4, 'Tahapan persiapan (4a)', 'daftar', 5, 500],
        'analisis_hasil' => [4, 'Kalimat analisis hasil (4c)', 'paragraf', 10, 1000],
        'pengarah' => [6, 'Kalimat pengarah (6b)', 'paragraf', 10, 1000],
        'penanggung_jawab' => [2, 'Kalimat penanggung jawab (II.5a)', 'paragraf', 10, 1000],
        'narasumber' => [2, 'Narasumber / fasilitator dan materi (II.6)', 'paragraf', 10, 1000],
        'output' => [4, 'Output (IV.1)', 'paragraf', 10, 1000],
        'outcome' => [4, 'Outcome (IV.2)', 'paragraf', 10, 1000],
    ];

    /** Label bidang di format 5 bab bila letaknya berbeda dari label BIDANG. */
    private const LABEL_LIMA_BAB = [
        'persiapan' => 'Tahapan persiapan (III.1)',
        'analisis_hasil' => 'Kalimat analisis hasil (III.3)',
    ];

    public const MAKS_BUTIR = 10;

    /** @return list<string> Kunci bidang satu bagian KAK/TOR format itu (urut BIDANG). */
    public static function untukBagian(int $nomor, string $format = FormatKakTor::SEMBILAN_BAGIAN): array
    {
        return array_values(array_intersect(array_keys(self::BIDANG), FormatKakTor::isi($format, $nomor)));
    }

    /** Label bidang menurut letaknya di format itu. */
    public static function label(string $kunci, string $format = FormatKakTor::SEMBILAN_BAGIAN): string
    {
        return $format === FormatKakTor::LIMA_BAB ? (self::LABEL_LIMA_BAB[$kunci] ?? self::BIDANG[$kunci][1]) : self::BIDANG[$kunci][1];
    }

    /**
     * Teks baku berpenanda (dasar penyuntingan & pembanding).
     *
     * @return string|list<string>
     */
    public static function bakuMentah(string $kunci): string|array
    {
        return match ($kunci) {
            'persiapan' => KalimatKak::persiapan(self::PENANDA),
            'analisis_hasil' => KalimatKak::analisisHasil(self::PENANDA),
            'pengarah' => KalimatKak::pengarah(self::PENANDA, StubData::madrasah()['nama']),
            'penanggung_jawab' => KalimatKak::penanggungJawab(self::PENANDA, StubData::madrasah()['nama']),
            'narasumber' => KalimatKak::narasumber(self::PENANDA, StubData::madrasah()['nama']),
            'output' => KalimatKak::output(self::PENANDA),
            'outcome' => KalimatKak::outcome(self::PENANDA),
        };
    }

    /**
     * Teks tersimpan yang sah (kunci terdaftar) kegiatan ini.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, string|list<string>>
     */
    public static function dari(array $kegiatan): array
    {
        return self::urutkan(array_intersect_key(array_filter((array) ($kegiatan['teks_khusus'] ?? []), fn ($t) => filled($t)), self::BIDANG));
    }

    /**
     * Urutan baku kunci (BIDANG, lalu kunci lain) — penyimpanan jsonb mengurutkan ulang kunci objek, sedangkan
     * pembanding perubahan (UbahDataKegiatan) membandingkan bentuk JSON-nya.
     *
     * @param  array<string, mixed>  $teks
     * @return array<string, mixed>
     */
    public static function urutkan(array $teks): array
    {
        return array_merge(array_intersect_key(array_fill_keys(array_keys(self::BIDANG), null), $teks), $teks);
    }

    /** @param  array<string, mixed>  $kegiatan */
    public static function disesuaikan(string $kunci, array $kegiatan): bool
    {
        return isset(self::dari($kegiatan)[$kunci]);
    }

    /**
     * Teks siap cetak: tersimpan atau baku, penanda diisi nama kegiatan.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return string|list<string>
     */
    public static function cetak(string $kunci, array $kegiatan): string|array
    {
        $nama = KalimatKak::tanpaAwalanKegiatan(trim((string) ($kegiatan['nama_kegiatan'] ?? '')) ?: '…');
        $teks = self::dari($kegiatan)[$kunci] ?? self::bakuMentah($kunci);
        $isi = fn (string $t) => str_replace(self::PENANDA, $nama, $t);

        return is_array($teks) ? array_map($isi, $teks) : $isi($teks);
    }

    /**
     * Bentuk simpan: spasi dirapikan; daftar dari teks berbaris (satu butir per baris) atau list; kosong & sama dengan
     * teks baku dibuang; kunci tak dikenal dibiarkan agar ditolak galat().
     *
     * @param  array<string, mixed>  $isian
     * @return array<string, mixed>
     */
    public static function rapikan(array $isian): array
    {
        $baris = fn (mixed $t) => is_scalar($t) ? trim((string) preg_replace('/\s+/u', ' ', (string) $t)) : $t;
        $hasil = [];
        foreach ($isian as $kunci => $teks) {
            if (! isset(self::BIDANG[$kunci])) {
                $hasil[$kunci] = $teks;

                continue;
            }
            if (self::BIDANG[$kunci][2] === 'daftar') {
                $butir = is_array($teks) ? $teks : preg_split('/\R/u', (string) $teks);
                $teks = array_values(array_filter(array_map($baris, $butir), fn ($b) => $b !== '' && $b !== null));
            } else {
                $teks = $baris($teks);
            }
            if ($teks !== '' && $teks !== [] && $teks !== self::bakuMentah($kunci)) {
                $hasil[$kunci] = $teks;
            }
        }

        return self::urutkan($hasil);
    }

    /**
     * Galat teks khusus berkunci bidang (kosong = sah).
     *
     * @param  array<string, mixed>  $teksKhusus
     * @return array<string, string>
     */
    public static function galat(array $teksKhusus): array
    {
        $galat = [];
        foreach ($teksKhusus as $kunci => $teks) {
            if (! isset(self::BIDANG[$kunci])) {
                $galat[$kunci] = "Teks {$kunci} tidak bisa disesuaikan.";

                continue;
            }
            [, $label, $jenis, $min, $maks] = self::BIDANG[$kunci];
            $daftar = $jenis === 'daftar' ? $teks : [$teks];
            if (! is_array($daftar) || $daftar === [] || array_filter($daftar, fn ($t) => ! is_string($t)) !== []) {
                $galat[$kunci] = "{$label}: isi teksnya.";
            } elseif (count($daftar) > self::MAKS_BUTIR) {
                $galat[$kunci] = "{$label}: paling banyak ".self::MAKS_BUTIR.' butir.';
            } elseif (collect($daftar)->contains(fn (string $t) => mb_strlen($t) < $min || mb_strlen($t) > $maks)) {
                $galat[$kunci] = "{$label}: tiap ".($jenis === 'daftar' ? 'butir' : 'kalimat')." {$min}–{$maks} karakter.";
            } elseif (collect($daftar)->contains(fn (string $t) => preg_match('/\{(?!nama_kegiatan\})[^{}]*\}/u', $t))) {
                $galat[$kunci] = "{$label}: penanda yang boleh hanya ".self::PENANDA.'.';
            }
        }

        return $galat;
    }
}
