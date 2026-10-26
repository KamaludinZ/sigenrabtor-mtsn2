<?php

namespace App\Support\Monitoring;

use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Support\MasterKode;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;
use App\Support\Rab\TabelPagu;

/**
 * Agregat Monitoring Anggaran (PRD Fase 5 §11) untuk tahun aktif: kegiatan yang cocok dengan saringan (leading, sumber
 * dana, status) beserta anggaran per sumber dananya, lalu ringkasan jumlah kegiatan per status, pagu & total RAB per
 * sumber dana, dan selisihnya. Semua angka dari AnggaranKegiatan sehingga sama dengan layar RAB & Data Kegiatan.
 */
final readonly class RingkasanAnggaran
{
    /** Kondisi satu RAB terhadap pagunya (label sama dengan saringan kondisi anggaran Data Kegiatan). */
    public const KONDISI_RAB = [
        'sesuai' => 'Sesuai pagu',
        'sisa' => 'Di bawah pagu',
        'melebihi' => 'Melebihi pagu',
        'tanpa_pagu' => 'Pagu belum diisi',
        'rab_kosong' => 'RAB belum diisi',
    ];

    /**
     * @param  list<array{id: int, nama: string, leading: string|null, status: StatusKegiatan, anggaran: list<AnggaranSumber>}>  $kegiatan
     */
    public function __construct(
        public array $kegiatan,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $semuaKegiatan
     * @param  array{leading?: string, leadings?: list<string>|null, sumber?: string, status?: string}  $saringan  Kosong = semua;
     *                                                                                                             leadings = cakupan akun leading.
     */
    public static function dari(array $semuaKegiatan, array $saringan = []): self
    {
        $cakupan = $saringan['leadings'] ?? null;
        $leading = $saringan['leading'] ?? '';
        $sumber = $saringan['sumber'] ?? '';
        $status = $saringan['status'] ?? '';
        $baris = [];

        foreach ($semuaKegiatan as $k) {
            $statusKegiatan = StatusKegiatan::tryFrom($k['status'] ?? '') ?? StatusKegiatan::Draf;
            $anggaran = AnggaranKegiatan::untuk($k);

            if ($sumber !== '') {
                $anggaran = array_values(array_filter($anggaran, fn (AnggaranSumber $a) => $a->sumber === $sumber));
            }

            if (($cakupan !== null && ! in_array($k['leading'] ?? null, $cakupan, true)) || ($leading !== '' && ($k['leading'] ?? null) !== $leading) || ($status !== '' && $statusKegiatan->value !== $status) || ($sumber !== '' && $anggaran === [])) {
                continue;
            }

            $baris[] = [
                'id' => (int) $k['id'],
                'nama' => trim((string) ($k['nama_kegiatan'] ?? '')) ?: 'Tanpa nama',
                'leading' => $k['leading'] ?? null,
                'status' => $statusKegiatan,
                'anggaran' => $anggaran,
            ];
        }

        return new self($baris);
    }

    public function jumlahKegiatan(): int
    {
        return count($this->kegiatan);
    }

    /**
     * Jumlah kegiatan per status (semua status tercantum, urut alur).
     *
     * @return array<string, int>
     */
    public function perStatus(): array
    {
        $hasil = array_fill_keys(array_column(StatusKegiatan::cases(), 'value'), 0);

        foreach ($this->kegiatan as $k) {
            $hasil[$k['status']->value]++;
        }

        return $hasil;
    }

    /**
     * Pagu, total RAB, selisih (pagu − total), jumlah RAB, dan RAB melebihi pagu per sumber dana.
     *
     * @return array<string, array{pagu: int, total: int, selisih: int, rab: int, melebihi: int, tanpa_pagu: int}>
     */
    public function perSumber(): array
    {
        $kosong = ['pagu' => 0, 'total' => 0, 'selisih' => 0, 'rab' => 0, 'melebihi' => 0, 'tanpa_pagu' => 0];
        $hasil = array_fill_keys(SumberDana::urutan(), $kosong);

        foreach ($this->kegiatan as $k) {
            foreach ($k['anggaran'] as $a) {
                $hasil[$a->sumber] ??= $kosong;
                $baris = &$hasil[$a->sumber];
                $baris['pagu'] += (int) $a->pagu();
                $baris['total'] += $a->total();
                $baris['rab']++;
                $baris['melebihi'] += $a->statusPagu() === 'melebihi' ? 1 : 0;
                $baris['tanpa_pagu'] += $a->pagu() === null ? 1 : 0;
                $baris['selisih'] = $baris['pagu'] - $baris['total'];
                unset($baris);
            }
        }

        return $hasil;
    }

    /**
     * Perbandingan pagu antar-sumber dana (DIPA vs Komite): angka perSumber() ditambah persen pemakaian pagu
     * (TabelPagu::persen), porsi tiap sumber terhadap gabungan pagu & total RAB (persen satu desimal, jumlahnya 100),
     * total RAB yang pagunya belum diisi, kelebihan RAB yang melampaui pagunya sendiri, dan jumlah RAB per kondisi
     * (KONDISI_RAB, satu kondisi per RAB). Porsi null bila gabungannya 0.
     *
     * @return array<string, array{pagu: int, total: int, selisih: int, rab: int, melebihi: int, tanpa_pagu: int, persen: float|null, porsi_pagu: float|null, porsi_total: float|null, total_tanpa_pagu: int, kelebihan: int, kondisi: array<string, int>}>
     */
    public function perbandinganSumber(): array
    {
        $hasil = [];
        foreach ($this->perSumber() as $sumber => $s) {
            $hasil[$sumber] = $s + ['persen' => TabelPagu::persen($s['total'], $s['pagu'] ?: null), 'porsi_pagu' => null, 'porsi_total' => null, 'total_tanpa_pagu' => 0, 'kelebihan' => 0, 'kondisi' => array_fill_keys(array_keys(self::KONDISI_RAB), 0)];
        }

        foreach ($this->kegiatan as $k) {
            foreach ($k['anggaran'] as $a) {
                $baris = &$hasil[$a->sumber];
                $status = $a->keteranganPagu();
                $baris['total_tanpa_pagu'] += $a->pagu() === null ? $a->total() : 0;
                $baris['kelebihan'] += $status->melebihi() ? -$status->selisih : 0;
                $baris['kondisi'][match (true) {
                    $a->pagu() === null => 'tanpa_pagu',
                    $a->jumlahItem() === 0 => 'rab_kosong',
                    default => $status->status,
                }]++;
                unset($baris);
            }
        }

        foreach (['pagu' => 'porsi_pagu', 'total' => 'porsi_total'] as $nilai => $porsi) {
            foreach (self::porsi(array_column($hasil, $nilai, null)) as $i => $persen) {
                $hasil[array_keys($hasil)[$i]][$porsi] = $persen;
            }
        }

        return $hasil;
    }

    /**
     * Jumlah kegiatan menurut sumber dananya: hanya satu sumber (kunci nama sumber), keduanya, atau belum memilih.
     *
     * @return array<string, int>
     */
    public function kombinasiSumber(): array
    {
        $hasil = array_fill_keys(SumberDana::urutan(), 0) + ['keduanya' => 0, 'belum' => 0];

        foreach ($this->kegiatan as $k) {
            $sumber = array_unique(array_map(fn (AnggaranSumber $a) => $a->sumber, $k['anggaran']));
            $hasil[match (count($sumber)) {
                0 => 'belum',
                1 => reset($sumber),
                default => 'keduanya',
            }]++;
        }

        return $hasil;
    }

    /**
     * Porsi tiap nilai terhadap jumlahnya dalam persen satu desimal; sisa pembulatan masuk ke porsi terakhir yang tidak
     * nol sehingga jumlahnya tepat 100. Jumlah 0 → semua null.
     *
     * @param  list<int>  $nilai
     * @return list<float|null>
     */
    private static function porsi(array $nilai): array
    {
        $jumlah = array_sum($nilai);
        if ($jumlah <= 0) {
            return array_fill(0, count($nilai), null);
        }

        $perseribu = array_map(fn (int $n) => intdiv($n * 1000 + intdiv($jumlah, 2), $jumlah), $nilai);
        $terakhir = array_key_last(array_filter($nilai));
        $perseribu[$terakhir] += 1000 - array_sum($perseribu);

        return array_map(fn (int $p) => $p / 10.0, $perseribu);
    }

    /**
     * Rekap per leading (urutan master leading; kegiatan tanpa leading di baris terakhir bila ada): jumlah kegiatan per
     * status, lalu angka anggaran gabungan dan per sumber dana (sel(): pagu, total RAB, selisih, persen pemakaian, jumlah
     * RAB, RAB melebihi pagunya sendiri, RAB tanpa pagu). $semua = tampilkan juga leading tanpa kegiatan.
     *
     * @return list<array{kode: string|null, nama_unit: string, jumlah: int, perStatus: array<string, int>, pagu: int, total: int, selisih: int|null, persen: float|null, rab: int, melebihi: int, tanpa_pagu: int, sumber: array<string, array{pagu: int, total: int, selisih: int|null, persen: float|null, rab: int, melebihi: int, tanpa_pagu: int}>}>
     */
    public function perLeading(bool $semua = true): array
    {
        $nol = ['pagu' => 0, 'total' => 0, 'rab' => 0, 'melebihi' => 0, 'tanpa_pagu' => 0];
        $kosong = fn (?string $kode, string $nama) => ['kode' => $kode, 'nama_unit' => $nama, 'jumlah' => 0, 'perStatus' => array_fill_keys(array_column(StatusKegiatan::cases(), 'value'), 0)]
            + $nol + ['sumber' => array_fill_keys(SumberDana::urutan(), $nol)];
        $hasil = [];
        foreach (MasterKode::leadings() as $l) {
            $hasil[$l['kode']] = $kosong($l['kode'], $l['nama_unit']);
        }

        foreach ($this->kegiatan as $k) {
            $kunci = $k['leading'] !== null && isset($hasil[$k['leading']]) ? $k['leading'] : '';
            $hasil[$kunci] ??= $kosong(null, 'Leading belum dipilih');
            $baris = &$hasil[$kunci];
            $baris['jumlah']++;
            $baris['perStatus'][$k['status']->value]++;
            foreach ($k['anggaran'] as $a) {
                $baris['sumber'][$a->sumber] ??= $nol;
                foreach ([&$baris, &$baris['sumber'][$a->sumber]] as &$sel) {
                    $sel['pagu'] += (int) $a->pagu();
                    $sel['total'] += $a->total();
                    $sel['rab']++;
                    $sel['melebihi'] += $a->statusPagu() === 'melebihi' ? 1 : 0;
                    $sel['tanpa_pagu'] += $a->pagu() === null ? 1 : 0;
                }
                unset($sel);
            }
            unset($baris);
        }

        $lengkap = [];
        foreach ($hasil as $b) {
            if ($semua || $b['jumlah'] > 0) {
                $b['sumber'] = array_map(fn (array $s) => self::sel($s), $b['sumber']);
                $lengkap[] = self::sel($b);
            }
        }

        return $lengkap;
    }

    /**
     * Lengkapi angka satu sel rekap (pagu, total, rab, melebihi, tanpa_pagu) dengan selisih (pagu − total) dan persen
     * pemakaian. Seperti baris jumlah TabelPagu keduanya hanya dihitung bila ada RAB dan semuanya sudah berpagu (null bila
     * belum), sebab RAB tanpa pagu tidak bisa dibandingkan; RAB yang melebihi pagunya sendiri tetap terhitung di
     * `melebihi` walau selisih gabungannya masih sisa.
     *
     * @template T of array{pagu: int, total: int, rab: int, melebihi: int, tanpa_pagu: int}
     *
     * @param  T  $angka
     * @return T&array{selisih: int|null, persen: float|null}
     */
    public static function sel(array $angka): array
    {
        $bisa = $angka['rab'] > 0 && $angka['tanpa_pagu'] === 0;

        return [
            ...$angka,
            'selisih' => $bisa ? $angka['pagu'] - $angka['total'] : null,
            'persen' => $bisa ? TabelPagu::persen($angka['total'], $angka['pagu'] ?: null) : null,
        ];
    }

    /**
     * Gabungan semua sumber dana.
     *
     * @return array{pagu: int, total: int, selisih: int, rab: int, melebihi: int, tanpa_pagu: int}
     */
    public function total(): array
    {
        $hasil = ['pagu' => 0, 'total' => 0, 'selisih' => 0, 'rab' => 0, 'melebihi' => 0, 'tanpa_pagu' => 0];

        foreach ($this->perSumber() as $s) {
            foreach ($hasil as $kunci => $_) {
                $hasil[$kunci] += $s[$kunci];
            }
        }

        return $hasil;
    }
}
