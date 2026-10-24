<?php

namespace App\Support\Kegiatan;

use App\Support\MasterKode;

/**
 * Ringkasan kode & klasifikasi terpilih beserta uraiannya, berurutan seperti header RAB.
 */
class RingkasanKode
{
    /** Isian kegiatan yang membentuk ringkasan, urut seperti header RAB. */
    public const ISIAN = ['leading', 'kode_rktm', 'kode_sk', 'kode_iksk', 'kode_edm', 'budaya_mutu'];

    /**
     * @param  array{leading?: ?string, kode_rktm?: ?string, kode_sk?: ?string, kode_iksk?: ?string, kode_edm?: ?string, budaya_mutu?: int|string|null}  $kode
     * @return list<array{kunci: string, label: string, kode: string|null, uraian: string|null, detail: string|null}>
     */
    public static function susun(array $kode): array
    {
        $leading = MasterKode::cari(MasterKode::leadings(), $kode['leading'] ?? null);
        $rktm = MasterKode::cari(MasterKode::kodeRktm(), $kode['kode_rktm'] ?? null);
        $sk = MasterKode::cari(MasterKode::kodeSk(), $kode['kode_sk'] ?? null);
        $iksk = MasterKode::cari(MasterKode::kodeIksk(), $kode['kode_iksk'] ?? null);
        $edm = MasterKode::cari(MasterKode::kodeEdm(), $kode['kode_edm'] ?? null);
        $budaya = MasterKode::cari(MasterKode::budayaMutu(), $kode['budaya_mutu'] ?? null, 'nomor');
        $komponen = MasterKode::komponenEdm();

        return [
            ['kunci' => 'leading', 'label' => 'Leading sektor', 'kode' => $leading['kode'] ?? null, 'uraian' => $leading['nama_unit'] ?? null, 'detail' => isset($leading) ? 'Penanda tangan: '.$leading['jabatan_penanda_tangan'] : null],
            ['kunci' => 'rktm', 'label' => 'Kode RKTM', 'kode' => $rktm['kode'] ?? null, 'uraian' => $rktm['sub_kegiatan'] ?? null, 'detail' => isset($rktm) ? $rktm['snp'].' · '.$rktm['kegiatan'] : null],
            ['kunci' => 'sk', 'label' => 'Kode SK', 'kode' => $sk['kode'] ?? null, 'uraian' => $sk['uraian'] ?? null, 'detail' => null],
            ['kunci' => 'iksk', 'label' => 'IKSK', 'kode' => $iksk['kode'] ?? null, 'uraian' => $iksk['uraian'] ?? null, 'detail' => null],
            ['kunci' => 'edm', 'label' => 'Kode EDM', 'kode' => $edm['kode'] ?? null, 'uraian' => $edm['uraian'] ?? null, 'detail' => isset($edm) ? 'Komponen '.$edm['komponen'].' · '.($komponen[$edm['komponen']] ?? '') : null],
            ['kunci' => 'budaya', 'label' => '5 Budaya Mutu', 'kode' => isset($budaya) ? (string) $budaya['nomor'] : null, 'uraian' => $budaya['nama'] ?? null, 'detail' => null],
        ];
    }

    /**
     * @param  list<array{kode: string|null}>  $ringkasan
     */
    public static function jumlahTerisi(array $ringkasan): int
    {
        return count(array_filter($ringkasan, fn ($baris) => filled($baris['kode'])));
    }

    /**
     * Ringkasan sebagai teks polos untuk disalin, satu baris per kode.
     *
     * @param  list<array{label: string, kode: string|null, uraian: string|null}>  $ringkasan
     */
    public static function teks(array $ringkasan): string
    {
        return implode("\n", array_map(
            fn ($baris) => $baris['label'].': '.($baris['kode'] ? $baris['kode'].' — '.$baris['uraian'] : '(belum dipilih)'),
            $ringkasan,
        ));
    }
}
