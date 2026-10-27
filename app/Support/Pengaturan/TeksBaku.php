<?php

namespace App\Support\Pengaturan;

use App\Support\Kegiatan\KalimatKak;

/**
 * Penanda di teks baku pengaturan (dasar hukum, latar belakang baku, kalimat penutup): disimpan mentah, diisi saat
 * ditampilkan atau dicetak sehingga teks yang disalin ke tahun lain tidak perlu diketik ulang. Kembaran klien `isiTeksBaku`/
 * `bagianTeksBaku` (resources/js/teks-baku.js) untuk pratinjau saat mengetik.
 */
class TeksBaku
{
    public const TAHUN = '{tahun}';

    public const NAMA_KEGIATAN = '{nama_kegiatan}';

    /** Penanda yang dikenali beserta artinya. */
    public const PENANDA = [
        self::TAHUN => 'Tahun anggaran',
        self::NAMA_KEGIATAN => 'Nama kegiatan (tanpa awalan "Kegiatan")',
    ];

    /** Isi penanda tahun dan, bila diberikan, nama kegiatan (tanpa awalan "Kegiatan" — templatenya menulis sendiri). */
    public static function isi(string $teks, int $tahun, ?string $namaKegiatan = null): string
    {
        $ganti = [self::TAHUN => (string) $tahun];
        if ($namaKegiatan !== null) {
            $ganti[self::NAMA_KEGIATAN] = KalimatKak::tanpaAwalanKegiatan($namaKegiatan);
        }

        return strtr($teks, $ganti);
    }

    /**
     * Penanda berkurung kurawal di $teks yang tidak termasuk $boleh, urut kemunculan tanpa dobel (kembaran klien
     * `penandaAsing`, resources/js/teks-baku.js; kasus bersama tests/fixtures/teks-baku.json).
     *
     * @param  list<string>  $boleh
     * @return list<string>
     */
    public static function penandaAsing(string $teks, array $boleh): array
    {
        preg_match_all('/\{[^{}]*\}/u', $teks, $temuan);

        return array_values(array_diff(array_unique($temuan[0]), $boleh));
    }
}
