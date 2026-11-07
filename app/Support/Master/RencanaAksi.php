<?php

namespace App\Support\Master;

use App\Support\Kegiatan\Impor\PemeriksaImporKegiatan;
use App\Support\MasterKode;

/**
 * Rencana aksi cascade Perkin per tahun anggaran beserta tag kodenya (satu sumber aturan untuk Master Kode, pratinjau
 * unggahan, dan pengisian kode kegiatan):
 * - `kunci()` = pembanding rencana aksi (nama tanpa beda huruf/spasi + IKSK) — dipakai pratinjau & penyimpanan agar tag
 *   manual (EDM, 5 Budaya Mutu) tetap terbawa saat berkas diunggah ulang;
 * - `cocokkanLeading()` = unit/tim penerima mandat → kode leading aktif (nama unit, jabatan penanda tangan, atau kode;
 *   lalu kecocokan sebagian yang tunggal);
 * - `tag()` = tag efektif: SK, IKSK, RKTM, leading dari baris; EDM dari tag manual atau otomatis lewat relasi EDM ↔ RKTM
 *   (`tagEdm()`); 5 Budaya Mutu dari tag manual;
 * - `untukKegiatan()` = rencana aksi yang cocok dengan satu kegiatan (nama sama, lalu kode RKTM sama).
 */
final class RencanaAksi
{
    /** Batas jumlah rencana aksi per tahun. */
    public const MAKS = 1000;

    /** Kunci pembanding rencana aksi: nama (tanpa beda huruf/spasi) + IKSK. */
    public static function kunci(string $uraian, ?string $iksk): string
    {
        return PemeriksaImporKegiatan::kunciNama($uraian).'|'.($iksk ?? '');
    }

    /** Unit/tim penerima mandat ("Waka Kurikulum", "Ka. TU", "1.3") → kode leading aktif; null bila tidak cocok/ambigu. */
    public static function cocokkanLeading(string $unit): ?string
    {
        $rapikan = fn (string $t) => (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($t));
        $cari = $rapikan($unit);
        if ($cari === '') {
            return null;
        }

        $leading = MasterKode::leadings();
        foreach ($leading as $l) {
            if ($cari === $rapikan($l['nama_unit']) || $cari === $rapikan($l['jabatan_penanda_tangan']) || trim($unit) === $l['kode']) {
                return $l['kode'];
            }
        }

        // Kecocokan sebagian (mis. "Ma'had Nawaina" → "Mahad") hanya bila tunggal & cukup panjang.
        $calon = array_values(array_filter($leading, function (array $l) use ($cari, $rapikan) {
            $nama = $rapikan($l['nama_unit']);

            return mb_strlen($nama) >= 4 && (str_contains($cari, $nama) || str_contains($nama, $cari));
        }));

        return count($calon) === 1 ? $calon[0]['kode'] : null;
    }

    /** Unit → kunci pembanding (huruf kecil, tanpa spasi & tanda baca). */
    public static function kunciUnit(string $unit): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($unit));
    }

    /**
     * Leading yang sudah diberikan admin untuk unit yang sama pada rencana aksi tahun itu, lalu tahun bermaster
     * sebelumnya (satu leading saja; lebih dari satu = ambigu → null). Dipakai unggahan untuk unit yang tidak cocok
     * dengan nama/jabatan leading (mis. "Tim Manajemen Mutu").
     */
    public static function leadingDariUnit(string $unit, int $tahun): ?string
    {
        $kunci = self::kunciUnit($unit);
        if ($kunci === '') {
            return null;
        }
        $sebelum = SalinMasterKode::sumber($tahun);
        foreach (array_filter([$tahun, $sebelum !== null && $sebelum < $tahun ? $sebelum : null]) as $t) {
            $leading = array_values(array_unique(array_filter(array_map(
                fn (array $ra) => self::kunciUnit((string) ($ra['unit'] ?? '')) === $kunci ? ($ra['kode_leading'] ?? null) : null,
                MasterKode::rencanaAksi($t),
            ))));
            if (count($leading) === 1 && MasterKode::cari(MasterKode::leadings(), $leading[0]) !== null) {
                return $leading[0];
            }
        }

        return null;
    }

    /**
     * Kode EDM yang merekomendasikan kode RKTM $kodeRktm (relasi EDM ↔ RKTM tahun itu).
     *
     * @param  list<array{kode_edm: string, kode_rktm: string}>|null  $relasi
     * @return list<string>
     */
    public static function edmUntukRktm(?string $kodeRktm, int $tahun, ?array $relasi = null): array
    {
        if ($kodeRktm === null || $kodeRktm === '') {
            return [];
        }
        $relasi ??= MasterKode::relasiEdmRktm($tahun);

        return array_values(array_unique(array_column(array_filter($relasi, fn (array $r) => $r['kode_rktm'] === $kodeRktm), 'kode_edm')));
    }

    /**
     * Tag EDM rencana aksi: tag manual bila ada (`kode_edm` bukan null), selain itu otomatis dari relasi EDM ↔ RKTM.
     *
     * @param  array<string, mixed>  $ra
     * @param  list<array{kode_edm: string, kode_rktm: string}>|null  $relasi
     * @return list<string>
     */
    public static function tagEdm(array $ra, int $tahun, ?array $relasi = null): array
    {
        return is_array($ra['kode_edm'] ?? null) ? array_values($ra['kode_edm']) : self::edmUntukRktm($ra['kode_rktm'] ?? null, $tahun, $relasi);
    }

    /**
     * Tag efektif satu rencana aksi.
     *
     * @param  array<string, mixed>  $ra
     * @param  list<array{kode_edm: string, kode_rktm: string}>|null  $relasi
     * @return array{kode_sk: string|null, kode_iksk: string|null, kode_rktm: string|null, kode_leading: string|null, kode_edm: list<string>, edm_otomatis: bool, budaya_mutu: list<int>}
     */
    public static function tag(array $ra, int $tahun, ?array $relasi = null): array
    {
        return [
            'kode_sk' => $ra['kode_sk'] ?? null,
            'kode_iksk' => $ra['kode_iksk'] ?? null,
            'kode_rktm' => $ra['kode_rktm'] ?? null,
            'kode_leading' => $ra['kode_leading'] ?? null,
            'kode_edm' => self::tagEdm($ra, $tahun, $relasi),
            'edm_otomatis' => ! is_array($ra['kode_edm'] ?? null),
            'budaya_mutu' => array_values(array_map('intval', (array) ($ra['budaya_mutu'] ?? []))),
        ];
    }

    /**
     * Semua rencana aksi tahun itu beserta tag efektifnya (`tag`) dan kode tag yang tidak ada di master (`asing`).
     *
     * @return list<array<string, mixed>>
     */
    public static function baris(int $tahun): array
    {
        $relasi = MasterKode::relasiEdmRktm($tahun);
        $ada = [
            'kode_sk' => array_column(MasterKode::kodeSk($tahun), 'kode'),
            'kode_iksk' => array_column(MasterKode::kodeIksk(null, $tahun), 'kode'),
            'kode_rktm' => array_column(MasterKode::kodeRktm($tahun), 'kode'),
            'kode_edm' => array_column(MasterKode::kodeEdm($tahun), 'kode'),
        ];

        return array_map(function (array $ra) use ($tahun, $relasi, $ada) {
            $tag = self::tag($ra, $tahun, $relasi);
            $asing = [];
            foreach (['kode_sk', 'kode_iksk', 'kode_rktm'] as $kolom) {
                if ($tag[$kolom] !== null && ! in_array($tag[$kolom], $ada[$kolom], true)) {
                    $asing[] = $tag[$kolom];
                }
            }
            foreach ($tag['kode_edm'] as $edm) {
                if (! in_array($edm, $ada['kode_edm'], true)) {
                    $asing[] = $edm;
                }
            }

            return $ra + ['tag' => $tag, 'asing' => $asing];
        }, MasterKode::rencanaAksi($tahun));
    }

    /**
     * Rencana aksi yang cocok dengan kegiatan: nama sama (tanpa beda huruf/spasi) lebih dulu, lalu kode RKTM sama.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return list<array<string, mixed>> Baris baris() + `cocok` ('nama'|'rktm').
     */
    public static function untukKegiatan(array $kegiatan, int $tahun): array
    {
        $nama = PemeriksaImporKegiatan::kunciNama((string) ($kegiatan['nama_kegiatan'] ?? ''));
        $rktm = (string) ($kegiatan['kode_rktm'] ?? '');
        $hasil = ['nama' => [], 'rktm' => []];
        foreach (self::baris($tahun) as $ra) {
            if ($nama !== '' && PemeriksaImporKegiatan::kunciNama($ra['uraian']) === $nama) {
                $hasil['nama'][] = $ra + ['cocok' => 'nama'];
            } elseif ($rktm !== '' && $ra['kode_rktm'] === $rktm) {
                $hasil['rktm'][] = $ra + ['cocok' => 'rktm'];
            }
        }

        return [...$hasil['nama'], ...$hasil['rktm']];
    }

    /**
     * Rencana aksi → jumlah kegiatan tahun itu yang memakainya (nama kegiatan sama dengan nama rencana aksi).
     *
     * @param  iterable<array<string, mixed>>  $kegiatan
     * @return array<string, int> Kode rencana aksi → jumlah.
     */
    public static function pemakaian(iterable $kegiatan, int $tahun): array
    {
        $perNama = [];
        foreach ($kegiatan as $k) {
            $kunci = PemeriksaImporKegiatan::kunciNama((string) ($k['nama_kegiatan'] ?? ''));
            if ($kunci !== '') {
                $perNama[$kunci] = ($perNama[$kunci] ?? 0) + 1;
            }
        }
        $hasil = [];
        foreach (MasterKode::rencanaAksi($tahun) as $ra) {
            $n = $perNama[PemeriksaImporKegiatan::kunciNama($ra['uraian'])] ?? 0;
            if ($n > 0) {
                $hasil[$ra['kode']] = $n;
            }
        }

        return $hasil;
    }

    /** "1, 3, 12" ↔ [1, 3, 12]. @return list<int> */
    public static function bulanDariTeks(string $teks): array
    {
        $bulan = array_values(array_unique(array_filter(array_map('intval', preg_split('/[,\s]+/', $teks) ?: []), fn (int $n) => $n >= 1 && $n <= 12)));
        sort($bulan);

        return $bulan;
    }
}
