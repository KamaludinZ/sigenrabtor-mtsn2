<?php

namespace App\Support\Master;

use App\Contracts\MasterKodeRepository;
use App\Support\MasterKode;

/**
 * Pergantian kode RKTM antar-tahun (kode sub kegiatan dinomori ulang tiap tahun): kode yang tidak ada di master tahun
 * tujuan dicari penggantinya lewat nama sub kegiatan yang sama (tanpa beda huruf, spasi, tanda baca tepi, dan rincian
 * berkurung "[1 KEG x …]"). Dipakai pratinjau unggahan RKTM, saran kode pengganti kegiatan (KodeHilang), duplikat
 * kegiatan tahun lalu, dan penyesuaian tag RKTM rencana aksi.
 */
final class PetaRktm
{
    /** Nama sub kegiatan → kunci pembanding. */
    public static function kunci(string $nama): string
    {
        $nama = (string) preg_replace('/\[[^\]]*\]/u', ' ', mb_strtolower($nama));

        return trim((string) preg_replace('/\s+/u', ' ', $nama), " \t\n\r\0\x0B.,;:-");
    }

    /**
     * Nama sub kegiatan kode $kode: dari master tahun $dari (bila diberi), lalu tahun lain yang bermaster (terbaru dulu,
     * selain $kecuali).
     */
    public static function nama(string $kode, ?int $dari = null, ?int $kecuali = null): ?string
    {
        $tahun = array_reverse(app(MasterKodeRepository::class)->tahunTersedia());
        foreach ([...($dari !== null ? [$dari] : []), ...$tahun] as $t) {
            if ($t === $kecuali) {
                continue;
            }
            if (($baris = MasterKode::cari(MasterKode::kodeRktm($t), $kode)) !== null) {
                return $baris['sub_kegiatan'];
            }
        }

        return null;
    }

    /**
     * Kode pengganti $kode di master tahun $ke: null bila $kode masih ada di sana, atau bila tidak ada satu pun kode
     * bernama sub kegiatan sama (lebih dari satu = ambigu → null). $nama = nama yang dicari (bawaan nama kode di tahun
     * $dari/tahun lain).
     */
    public static function pengganti(string $kode, int $ke, ?int $dari = null, ?string $nama = null): ?string
    {
        $tujuan = MasterKode::kodeRktm($ke);
        if (MasterKode::cari($tujuan, $kode) !== null) {
            return null;
        }
        if ($nama === null && ($tercatat = self::dariUnggahan($kode, $ke)) !== null) {
            return $tercatat;
        }
        $nama ??= self::nama($kode, $dari, $ke);
        if ($nama === null || self::kunci($nama) === '') {
            return null;
        }
        $cocok = array_values(array_filter($tujuan, fn (array $r) => self::kunci($r['sub_kegiatan']) === self::kunci($nama)));

        return count($cocok) === 1 ? $cocok[0]['kode'] : null;
    }

    /**
     * Kode baru $kode menurut pergantian nomor yang tercatat saat master RKTM tahun $tahun diunggah (ringkasan unggahan
     * `rktm.pindah`, terbaru dulu; rantai pergantian diikuti) — hanya bila kode barunya masih ada di master.
     */
    public static function dariUnggahan(string $kode, int $tahun): ?string
    {
        $peta = [];
        foreach (array_reverse(app(MasterKodeRepository::class)->unggahan($tahun)) as $u) {
            foreach ((array) ($u['ringkasan']['rktm']['pindah'] ?? []) as $p) {
                $peta[(string) $p['lama']] = (string) $p['baru'];
            }
        }
        $ada = array_column(MasterKode::kodeRktm($tahun), 'kode', 'kode');
        for ($i = 0, $kini = $kode; $i < 10 && isset($peta[$kini]); $i++) {
            $kini = $peta[$kini];
            if (isset($ada[$kini])) {
                return $kini;
            }
        }

        return null;
    }

    /**
     * Perbandingan dua daftar RKTM (baris {kode, sub_kegiatan}): tetap (kode & nama sama), berganti kode (nama sama,
     * kode lain), berganti nama (kode sama, nama lain), baru, tidak ada lagi.
     *
     * @param  list<array{kode: string, sub_kegiatan: string}>  $lama
     * @param  list<array{kode: string, sub_kegiatan: string}>  $baru
     * @return array{tetap: int, pindah: list<array{lama: string, baru: string, nama: string}>, ganti_nama: list<string>, baru: list<string>, hilang: list<string>}
     */
    public static function banding(array $lama, array $baru): array
    {
        $lamaKode = array_column($lama, 'sub_kegiatan', 'kode');
        $baruKode = array_column($baru, 'sub_kegiatan', 'kode');
        $perNama = fn (array $daftar) => array_reduce(array_keys($daftar), function (array $acc, string $k) use ($daftar) {
            $acc[self::kunci($daftar[$k])][] = $k;

            return $acc;
        }, []);
        $lamaNama = $perNama($lamaKode);
        $baruNama = $perNama($baruKode);

        $hasil = ['tetap' => 0, 'pindah' => [], 'ganti_nama' => [], 'baru' => [], 'hilang' => []];
        $terpakai = [];
        foreach ($baruKode as $kode => $nama) {
            $kode = (string) $kode;
            if (isset($lamaKode[$kode])) {
                self::kunci($lamaKode[$kode]) === self::kunci($nama) ? $hasil['tetap']++ : $hasil['ganti_nama'][] = $kode;
                $terpakai[$kode] = true;

                continue;
            }
            $asal = array_values(array_filter($lamaNama[self::kunci($nama)] ?? [], fn (string $k) => ! isset($baruKode[$k])));
            if (count($asal) === 1 && count($baruNama[self::kunci($nama)] ?? []) === 1) {
                $hasil['pindah'][] = ['lama' => $asal[0], 'baru' => $kode, 'nama' => $nama];
                $terpakai[$asal[0]] = true;
            } else {
                $hasil['baru'][] = $kode;
            }
        }
        foreach (array_keys($lamaKode) as $kode) {
            if (! isset($terpakai[(string) $kode]) && ! isset($baruKode[(string) $kode])) {
                $hasil['hilang'][] = (string) $kode;
            }
        }

        return $hasil;
    }
}
