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

    /** Label isian kode, dipakai ringkasan, notifikasi, dan riwayat perubahan. */
    public const LABEL = [
        'leading' => 'Leading sektor', 'kode_rktm' => 'Kode RKTM', 'kode_sk' => 'Kode SK',
        'kode_iksk' => 'IKSK', 'kode_edm' => 'Kode EDM', 'budaya_mutu' => '5 Budaya Mutu',
    ];

    /** Kartu di halaman Kode & Klasifikasi tempat tiap kode dipilih (id section untuk tautan #). */
    public const KARTU = ['leading' => 'kode-leading', 'rktm' => 'kode-rktm', 'sk' => 'kode-perkin', 'iksk' => 'kode-perkin', 'edm' => 'kode-edm', 'budaya' => 'kode-budaya'];

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
            ['kunci' => 'leading', 'label' => self::LABEL['leading'], 'kode' => $leading['kode'] ?? null, 'uraian' => $leading['nama_unit'] ?? null, 'detail' => isset($leading) ? 'Penanda tangan: '.$leading['jabatan_penanda_tangan'] : null],
            ['kunci' => 'rktm', 'label' => self::LABEL['kode_rktm'], 'kode' => $rktm['kode'] ?? null, 'uraian' => $rktm['sub_kegiatan'] ?? null, 'detail' => isset($rktm) ? $rktm['snp'].' · '.$rktm['kegiatan'] : null],
            ['kunci' => 'sk', 'label' => self::LABEL['kode_sk'], 'kode' => $sk['kode'] ?? null, 'uraian' => $sk['uraian'] ?? null, 'detail' => null],
            ['kunci' => 'iksk', 'label' => self::LABEL['kode_iksk'], 'kode' => $iksk['kode'] ?? null, 'uraian' => $iksk['uraian'] ?? null, 'detail' => null],
            ['kunci' => 'edm', 'label' => self::LABEL['kode_edm'], 'kode' => $edm['kode'] ?? null, 'uraian' => $edm['uraian'] ?? null, 'detail' => isset($edm) ? 'Komponen '.$edm['komponen'].' · '.($komponen[$edm['komponen']] ?? '') : null],
            ['kunci' => 'budaya', 'label' => self::LABEL['budaya_mutu'], 'kode' => isset($budaya) ? (string) $budaya['nomor'] : null, 'uraian' => $budaya['nama'] ?? null, 'detail' => null],
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
     * Nilai kode dari data kegiatan dalam bentuk baku (kosong → null, budaya mutu → int) agar bisa dibandingkan.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string|int|null>
     */
    public static function nilaiBaku(array $data): array
    {
        $baku = [];

        foreach (self::ISIAN as $isian) {
            $nilai = blank($data[$isian] ?? null) ? null : $data[$isian];
            $baku[$isian] = $isian === 'budaya_mutu' && $nilai !== null ? (int) $nilai : $nilai;
        }

        return $baku;
    }

    /** Nilai baku untuk dibaca manusia di riwayat/bentrok: null → "kosong", budaya mutu → "2. Profesionalitas". */
    public static function tampilNilai(string $isian, string|int|null $nilai): string
    {
        if ($nilai === null) {
            return 'kosong';
        }

        if ($isian === 'budaya_mutu') {
            return $nilai.'. '.(MasterKode::cari(MasterKode::budayaMutu(), $nilai, 'nomor')['nama'] ?? '?');
        }

        return (string) $nilai;
    }

    /**
     * Isian yang nilainya berbeda antara dua versi, beserta nilai lama → baru.
     *
     * @param  array<string, string|int|null>  $lama
     * @param  array<string, string|int|null>  $baru
     * @return array<string, array{0: string|int|null, 1: string|int|null}>
     */
    public static function selisih(array $lama, array $baru): array
    {
        $selisih = [];

        foreach (self::ISIAN as $isian) {
            if (($lama[$isian] ?? null) !== ($baru[$isian] ?? null)) {
                $selisih[$isian] = [$lama[$isian] ?? null, $baru[$isian] ?? null];
            }
        }

        return $selisih;
    }

    /**
     * Status kelengkapan untuk notifikasi: 'lengkap', 'wajib' (kode SK kosong — kegiatan belum bisa diajukan), atau 'sebagian'.
     *
     * @param  list<array{kunci: string, label: string, kode: string|null}>  $ringkasan
     * @return array{status: string, terisi: int, total: int, kosong: list<array{kunci: string, label: string, kartu: string}>, pesan: string}
     */
    public static function status(array $ringkasan): array
    {
        $kosong = array_values(array_map(
            fn ($baris) => ['kunci' => $baris['kunci'], 'label' => $baris['label'], 'kartu' => self::KARTU[$baris['kunci']]],
            array_filter($ringkasan, fn ($baris) => blank($baris['kode'])),
        ));
        $total = count($ringkasan);
        $terisi = $total - count($kosong);

        [$status, $pesan] = match (true) {
            $kosong === [] => ['lengkap', 'Semua kode lengkap — header RAB siap dicetak.'],
            in_array('sk', array_column($kosong, 'kunci'), true) => ['wajib', 'Kode SK belum dipilih — wajib sebelum kegiatan diajukan.'],
            default => ['sebagian', "{$terisi} dari {$total} kode terpilih. Draf tetap bisa disimpan; lengkapi agar header RAB terisi penuh."],
        };

        return compact('status', 'terisi', 'total', 'kosong', 'pesan');
    }

    /**
     * Notifikasi setelah kode disimpan, menyebut kode yang masih kosong.
     *
     * @param  array{status: string, terisi: int, total: int, kosong: list<array{label: string}>}  $status
     * @return array{message: string, type: string}
     */
    public static function notifikasiSimpan(array $status): array
    {
        if ($status['kosong'] === []) {
            return ['message' => 'Kode & klasifikasi tersimpan. Semua kode lengkap.', 'type' => 'success'];
        }

        return [
            'message' => "Kode & klasifikasi tersimpan. {$status['terisi']} dari {$status['total']} kode terpilih — belum: ".implode(', ', array_column($status['kosong'], 'label')).'.',
            'type' => 'warning',
        ];
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
