<?php

namespace App\Support\Kegiatan;

use App\Contracts\MatrikJadwalRepository;
use App\Enums\SumberDana;
use App\Support\Rab\AnggaranKegiatan;

/**
 * Perbaikan di data (PRD §16 "Perbaikan di Data"): kegiatan yang dikembalikan admin (atau dibuka kuncinya) diperbaiki
 * leading lewat isian aplikasi, bukan file Word. Saat dikembalikan, entri riwayat menyimpan sidik per bagian data
 * (sidik()); bagian yang berubah sejak itu (bandingkan()) ditampilkan ke leading selama perbaikan dan ke admin saat
 * pengajuan ulang diperiksa. Konteks kegiatan (bahan AI, tidak tercetak) tidak dihitung.
 */
final readonly class PerbaikanKegiatan
{
    /** Aksi riwayat yang membuka kegiatan untuk diperbaiki. */
    public const AKSI = ['kembalikan', 'buka_kunci'];

    /** Bagian data kegiatan → label & kolom (urutan = urutan Data Kegiatan). */
    public const BAGIAN = [
        'identitas' => ['label' => 'Identitas kegiatan', 'kolom' => ['nama_kegiatan', 'nama_kegiatan_cover', 'cover_manual', 'leading', 'latar_belakang', 'tujuan', 'sasaran', 'tanggal_dokumen', 'tanggal_laporan', 'laporan_otomatis']],
        'kode' => ['label' => 'Kode & klasifikasi', 'kolom' => ['kode_rktm', 'kode_sk', 'kode_iksk', 'kode_edm', 'budaya_mutu']],
        'pelaksanaan' => ['label' => 'Pelaksanaan', 'kolom' => ['tanggal_mulai', 'lama_hari', 'hari_kerja', 'bulan_pelaksanaan', 'tempat', 'peserta_jumlah', 'peserta_jenis', 'uraian_pelaksanaan']],
        'panitia' => ['label' => 'Pelaksana & panitia', 'kolom' => ['panitia']],
        'sumber-dana' => ['label' => 'Sumber dana & pagu', 'kolom' => ['sumber_dana']],
    ];

    /**
     * @param  list<string>|null  $diubah  Label bagian yang berubah sejak dikembalikan; null bila tidak tercatat.
     */
    public function __construct(
        public string $aksi,
        public string $oleh,
        public string $pada,
        public ?string $catatan,
        public bool $diajukanUlang,
        public ?string $diajukanPada,
        public ?array $diubah,
    ) {}

    /**
     * Perbaikan terakhir kegiatan (entri kembalikan/buka kunci terakhir); null bila belum pernah dikembalikan.
     *
     * @param  array<string, mixed>  $kegiatan  Data tersimpan (riwayat_status, kolom, dan id untuk RAB/matrik).
     */
    public static function dari(array $kegiatan): ?self
    {
        $riwayat = array_values((array) ($kegiatan['riwayat_status'] ?? []));
        $indeks = null;
        foreach ($riwayat as $i => $entri) {
            if (in_array($entri['aksi'] ?? null, self::AKSI, true)) {
                $indeks = $i;
            }
        }
        if ($indeks === null) {
            return null;
        }

        $entri = $riwayat[$indeks];
        $ajukan = collect(array_slice($riwayat, $indeks + 1))->last(fn (array $r) => ($r['aksi'] ?? null) === 'ajukan');

        return new self(
            (string) $entri['aksi'],
            (string) ($entri['oleh'] ?? ''),
            (string) ($entri['pada'] ?? ''),
            filled($entri['catatan'] ?? null) ? (string) $entri['catatan'] : null,
            $ajukan !== null,
            $ajukan['pada'] ?? null,
            is_array($entri['sidik'] ?? null) ? self::bandingkan($entri['sidik'], self::sidik($kegiatan)) : null,
        );
    }

    /**
     * Sidik tiap bagian data: kolom kegiatan per BAGIAN, RAB (rincian + termin) per sumber dana, matrik & jadwal.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, string>
     */
    public static function sidik(array $kegiatan): array
    {
        $sidik = [];
        foreach (self::BAGIAN as $kunci => $bagian) {
            $sidik[$kunci] = self::hash(array_map(fn (string $kolom) => $kegiatan[$kolom] ?? null, $bagian['kolom']));
        }
        foreach (AnggaranKegiatan::untuk($kegiatan) as $anggaran) {
            $sidik['rab:'.$anggaran->sumber] = self::hash([$anggaran->hasil->rincian, $anggaran->termin]);
        }
        if ($id = filter_var($kegiatan['id'] ?? null, FILTER_VALIDATE_INT)) {
            $repo = app(MatrikJadwalRepository::class);
            $sidik['matrik'] = self::hash([$repo->matrik($id)->butir, $repo->jadwal($id)->baris]);
        }

        return $sidik;
    }

    /**
     * Label bagian yang sidiknya berbeda (termasuk yang baru ada atau hilang), urut seperti Data Kegiatan.
     *
     * @param  array<string, string>  $lama
     * @param  array<string, string>  $baru
     * @return list<string>
     */
    public static function bandingkan(array $lama, array $baru): array
    {
        $semua = array_keys($lama + $baru);
        $urutan = [...array_keys(self::BAGIAN), ...array_map(fn (SumberDana $s) => 'rab:'.$s->value, SumberDana::cases()), 'matrik'];
        $kunci = [...array_intersect($urutan, $semua), ...array_diff($semua, $urutan)];

        return array_values(array_map(
            fn (string $k) => self::BAGIAN[$k]['label'] ?? ($k === 'matrik' ? 'Matrik & jadwal' : 'RAB '.substr($k, 4)),
            array_filter($kunci, fn (string $k) => ($lama[$k] ?? null) !== ($baru[$k] ?? null)),
        ));
    }

    /** Sidik nilai yang tahan beda tipe/spasi penyimpanan (40 vs "40", '' vs null). */
    private static function hash(mixed $nilai): string
    {
        return md5(json_encode(self::normalkan($nilai)));
    }

    private static function normalkan(mixed $nilai): mixed
    {
        return match (true) {
            is_array($nilai) => array_map(self::normalkan(...), $nilai),
            is_bool($nilai) => $nilai ? '1' : '0',
            $nilai === null => null,
            default => ($teks = trim((string) $nilai)) === '' ? null : $teks,
        };
    }
}
