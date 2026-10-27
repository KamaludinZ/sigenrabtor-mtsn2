<?php

namespace App\Support\Pengaturan;

use App\Support\Kegiatan\KalimatKak;
use App\Support\MasterKode;

/**
 * Penanda di teks baku pengaturan (dasar hukum, latar belakang baku, kalimat penutup): disimpan mentah, diisi saat
 * ditampilkan atau dicetak sehingga teks yang disalin ke tahun lain tidak perlu diketik ulang. `{tahun}` boleh di semua
 * teks; variabel kegiatan (VARIABEL_KEGIATAN) hanya di kalimat penutup latar belakang — tempatnya ditentukan admin, nilainya
 * dari data tiap kegiatan. Kembaran klien `isiTeksBaku`/`bagianTeksBaku`/`penandaAsing` (resources/js/teks-baku.js;
 * kasus bersama tests/fixtures/teks-baku.json) untuk pratinjau saat mengetik.
 */
class TeksBaku
{
    public const TAHUN = '{tahun}';

    public const NAMA_KEGIATAN = '{nama_kegiatan}';

    /** Variabel kegiatan untuk kalimat penutup: penanda → label (urutan = urutan tombol sisip). */
    public const VARIABEL_KEGIATAN = [
        self::NAMA_KEGIATAN => 'Nama kegiatan',
        '{leading}' => 'Unit leading',
        '{uraian_rktm}' => 'Sub kegiatan RKTM',
        '{uraian_sk}' => 'Sasaran kegiatan (SK)',
        '{uraian_iksk}' => 'Indikator (IKSK)',
    ];

    /** Isi penanda tahun dan, bila diberikan, nama kegiatan (tanpa awalan "Kegiatan" — templatenya menulis sendiri). */
    public static function isi(string $teks, int $tahun, ?string $namaKegiatan = null): string
    {
        return self::isiNilai($teks, $tahun, $namaKegiatan === null ? [] : [self::NAMA_KEGIATAN => KalimatKak::tanpaAwalanKegiatan($namaKegiatan)]);
    }

    /**
     * Isi penanda tahun dan penanda yang ada di $nilai; nilai null (atau tidak ada) membiarkan penandanya.
     *
     * @param  array<string, string|null>  $nilai  Penanda → nilai.
     */
    public static function isiNilai(string $teks, int $tahun, array $nilai): string
    {
        return strtr($teks, [self::TAHUN => (string) $tahun] + array_filter($nilai, fn (?string $v) => $v !== null));
    }

    /**
     * Nilai variabel kegiatan dari data satu kegiatan (null = belum diisi di Data Kegiatan).
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, string|null>
     */
    public static function nilaiKegiatan(array $kegiatan): array
    {
        $nama = trim((string) ($kegiatan['nama_kegiatan'] ?? ''));
        $rktm = MasterKode::cari(MasterKode::kodeRktm(), $kegiatan['kode_rktm'] ?? null);
        $sk = MasterKode::cari(MasterKode::kodeSk(), $kegiatan['kode_sk'] ?? null);
        $iksk = MasterKode::cari(MasterKode::kodeIksk(), $kegiatan['kode_iksk'] ?? null);
        $leading = MasterKode::cari(MasterKode::leadings(), $kegiatan['leading'] ?? null);

        return [
            self::NAMA_KEGIATAN => $nama === '' ? null : KalimatKak::tanpaAwalanKegiatan($nama),
            '{leading}' => $leading['nama_unit'] ?? null,
            '{uraian_rktm}' => $rktm ? ($rktm['sub_kegiatan'] ?? $rktm['kegiatan'] ?? null) : null,
            '{uraian_sk}' => $sk['uraian'] ?? null,
            '{uraian_iksk}' => $iksk['uraian'] ?? null,
        ];
    }

    /**
     * Label variabel kegiatan yang dipakai $teks tetapi nilainya kosong, urut kemunculan.
     *
     * @param  array<string, string|null>  $nilai
     * @return list<string>
     */
    public static function kosong(string $teks, array $nilai): array
    {
        preg_match_all('/\{[a-z_]+\}/', $teks, $temuan);
        $dipakai = array_values(array_unique(array_intersect($temuan[0], array_keys(self::VARIABEL_KEGIATAN))));

        return array_map(fn (string $p) => self::VARIABEL_KEGIATAN[$p], array_values(array_filter($dipakai, fn (string $p) => ($nilai[$p] ?? null) === null)));
    }

    /**
     * Teks dipotong di variabel kegiatan (penanda lain tetap teks), untuk menandai tempat sisip di layar.
     *
     * @return list<array{teks: string, penanda: string|null}>
     */
    public static function potong(string $teks): array
    {
        $pola = '/('.implode('|', array_map(fn (string $p) => preg_quote($p, '/'), array_keys(self::VARIABEL_KEGIATAN))).')/';

        return array_values(array_map(
            fn (string $bagian) => ['teks' => $bagian, 'penanda' => isset(self::VARIABEL_KEGIATAN[$bagian]) ? $bagian : null],
            array_filter(preg_split($pola, $teks, -1, PREG_SPLIT_DELIM_CAPTURE), fn (string $b) => $b !== ''),
        ));
    }

    /**
     * Penanda berkurung kurawal di $teks yang tidak termasuk $boleh, urut kemunculan tanpa dobel.
     *
     * @param  list<string>  $boleh
     * @return list<string>
     */
    public static function penandaAsing(string $teks, array $boleh): array
    {
        preg_match_all('/\{[^{}]*\}/u', $teks, $temuan);

        return array_values(array_diff(array_unique($temuan[0]), $boleh));
    }

    /**
     * Penanda yang boleh di kalimat penutup: tahun + variabel kegiatan.
     *
     * @return list<string>
     */
    public static function bolehPenutup(): array
    {
        return [self::TAHUN, ...array_keys(self::VARIABEL_KEGIATAN)];
    }
}
