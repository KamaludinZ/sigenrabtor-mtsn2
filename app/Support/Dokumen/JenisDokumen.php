<?php

namespace App\Support\Dokumen;

use App\Contracts\TemplateDokumenRepository;

/**
 * Pilihan jenis dokumen yang disusun/dicetak (PRD F4 & F12; kunci DokumenKegiatan::JENIS): keterangan, bagian yang ikut
 * (`ISI`: cover, kak, rab), perlu sumber dana (DokumenKegiatan::PER_SUMBER), master template per bagian (`template()`:
 * KAK/TOR memakai master jenis itu lalu master gabungan KAK+TOR; set lengkap memakai master KAK/TOR + RAB), dan cap DRAF
 * tiap pilihan dari StatusCetak yang sama dengan PemeriksaanCetak (KAK/TOR → `kak`, RAB → `rab`, lengkap → `set`).
 * Dipakai komponen <x-dokumen.pilih-jenis> di tab Susun KAK/TOR (`?jenis=`, bawaan `BAWAAN`; jenis per sumber dana pada
 * kegiatan tanpa sumber dana → `TANPA_SUMBER`).
 */
final class JenisDokumen
{
    /** Bawaan = set lengkap (seperti Pratinjau Dokumen); kegiatan tanpa sumber dana memakai TANPA_SUMBER. */
    public const BAWAAN = 'lengkap';

    public const TANPA_SUMBER = 'KAK+TOR';

    /** Urutan pilihan di layar: bawaan lebih dulu. */
    public const URUTAN = ['lengkap', 'KAK+TOR', 'KAK', 'TOR', 'RAB'];

    /** Jenis → keterangan singkat pilihan. */
    public const KETERANGAN = [
        'KAK+TOR' => 'Satu dokumen KAK/TOR sembilan bagian baku.',
        'KAK' => 'Kerangka Acuan Kerja saja, sembilan bagian baku.',
        'TOR' => 'Term of Reference saja, sembilan bagian baku.',
        'RAB' => 'Rencana Anggaran Biaya satu sumber dana.',
        'lengkap' => 'Cover, KAK/TOR, dan RAB satu sumber dana dalam satu berkas.',
    ];

    /** Jenis → bagian dokumen yang ikut, urut cetak. */
    public const ISI = [
        'KAK+TOR' => ['kak'],
        'KAK' => ['kak'],
        'TOR' => ['kak'],
        'RAB' => ['rab'],
        'lengkap' => ['cover', 'kak', 'rab'],
    ];

    public const LABEL_ISI = ['cover' => 'Cover', 'kak' => 'KAK/TOR', 'rab' => 'RAB'];

    /** Jenis → urutan jenis master template untuk bagian KAK/TOR-nya. */
    private const TEMPLATE_KAK = [
        'KAK+TOR' => ['KAK+TOR'],
        'KAK' => ['KAK', 'KAK+TOR'],
        'TOR' => ['TOR', 'KAK+TOR'],
        'lengkap' => ['KAK+TOR', 'KAK', 'TOR'],
    ];

    /** Jenis sah, selain itu BAWAAN. */
    public static function rapikan(?string $jenis): string
    {
        return isset(DokumenKegiatan::JENIS[$jenis ?? '']) ? $jenis : self::BAWAAN;
    }

    public static function perluSumber(string $jenis): bool
    {
        return in_array($jenis, DokumenKegiatan::PER_SUMBER, true);
    }

    /**
     * Master template default tahun itu per bagian jenis ini (null = susunan baku aplikasi).
     *
     * @return array{kak?: array{id: string, nama: string, jenis: string, variabel: list<string>, diperbarui_pada: string}|null, rab?: array{id: string, nama: string, jenis: string, variabel: list<string>, diperbarui_pada: string}|null}
     */
    public static function template(string $jenis, int $tahun): array
    {
        $semua = app(TemplateDokumenRepository::class)->semua($tahun);
        $cari = function (array $urutan) use ($semua): ?array {
            foreach ($urutan as $j) {
                $t = collect($semua)->first(fn (array $t) => $t['jenis'] === $j && $t['is_default']);
                if ($t !== null) {
                    return array_intersect_key($t, array_flip(['id', 'nama', 'jenis', 'variabel', 'diperbarui_pada']));
                }
            }

            return null;
        };
        $hasil = [];
        if (in_array('kak', self::ISI[$jenis], true)) {
            $hasil['kak'] = $cari(self::TEMPLATE_KAK[$jenis]);
        }
        if (in_array('rab', self::ISI[$jenis], true)) {
            $hasil['rab'] = $cari(['RAB']);
        }

        return $hasil;
    }

    /** Cap DRAF dokumen jenis ini (aturan yang sama dengan PemeriksaanCetak). */
    public static function statusDraf(string $jenis, StatusCetak $cetak): StatusDraf
    {
        return match ($jenis) {
            'RAB' => $cetak->rab,
            'lengkap' => $cetak->set,
            default => $cetak->kak,
        };
    }

    /**
     * Pilihan untuk <x-dokumen.pilih-jenis>: tiap jenis beserta keterangan, isi, master template, cap DRAF, dan apakah
     * bisa dipilih (jenis per sumber dana perlu kegiatan bersumber dana).
     *
     * @return list<array{kunci: string, label: string, keterangan: string, isi: list<string>, perlu_sumber: bool, bisa: bool, alasan: string|null, template: array<string, array<string, mixed>|null>, draf: bool}>
     */
    public static function pilihan(StatusCetak $cetak, ?string $sumber, int $tahun): array
    {
        return array_map(function (string $jenis) use ($cetak, $sumber, $tahun) {
            $bisa = ! self::perluSumber($jenis) || $sumber !== null;

            return [
                'kunci' => $jenis,
                'label' => DokumenKegiatan::JENIS[$jenis],
                'keterangan' => self::KETERANGAN[$jenis],
                'isi' => array_map(fn (string $i) => self::LABEL_ISI[$i], self::ISI[$jenis]),
                'perlu_sumber' => self::perluSumber($jenis),
                'bisa' => $bisa,
                'alasan' => $bisa ? null : 'Pilih sumber dana kegiatan dulu (Data Kegiatan › Sumber Dana).',
                'template' => self::template($jenis, $tahun),
                'draf' => self::statusDraf($jenis, $cetak)->draf,
            ];
        }, self::URUTAN);
    }
}
