<?php

namespace App\Support\Monitoring;

use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;
use App\Support\Rab\HasilRab;
use App\Support\Rab\KalkulatorRab;
use App\Support\Rab\RekapKelompok;
use App\Support\Rab\StatusPagu;
use App\Support\Rab\TabelPagu;
use Carbon\CarbonImmutable;

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

    /** Urutan tabel rincian per kegiatan → [label, arah bawaan]. Kelebihan = jumlah kelebihan RAB atas pagunya sendiri. */
    public const URUT_RINCIAN = [
        'total' => ['Total RAB terbesar', 'desc'],
        'pagu' => ['Pagu terbesar', 'desc'],
        'kelebihan' => ['Kelebihan pagu terbesar', 'desc'],
        'pemakaian' => ['Pemakaian pagu tertinggi', 'desc'],
        'nama' => ['Nama kegiatan', 'asc'],
        'leading' => ['Leading', 'asc'],
        'status' => ['Status dokumen', 'asc'],
    ];

    /**
     * @param  list<array{id: int, nama: string, kode_rktm: string|null, leading: string|null, user_id: int|null, diperbarui: string|null, status: StatusKegiatan, anggaran: list<AnggaranSumber>}>  $kegiatan
     * @param  int  $tanpaJadwal  Kegiatan yang tidak ikut saringan rentang tanggal karena tanggal & bulan pelaksanaannya kosong.
     */
    public function __construct(
        public array $kegiatan,
        public int $tanpaJadwal = 0,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $semuaKegiatan
     * @param  array{tahun?: int|null, leading?: string, leadings?: list<string>|null, sumber?: string, status?: string, dari?: string, sampai?: string}  $saringan
     *                                                                                                                                                               Kosong = semua; tahun = tahun anggaran pemilik (TahunKegiatan); leadings = cakupan akun leading;
     *                                                                                                                                                               dari/sampai (Y-m-d) = rentang tanggal pelaksanaan.
     */
    public static function dari(array $semuaKegiatan, array $saringan = []): self
    {
        $tahun = $saringan['tahun'] ?? null;
        $cakupan = $saringan['leadings'] ?? null;
        $leading = $saringan['leading'] ?? '';
        $sumber = $saringan['sumber'] ?? '';
        $status = $saringan['status'] ?? '';
        $dari = self::tanggal($saringan['dari'] ?? null);
        $sampai = self::tanggal($saringan['sampai'] ?? null);
        $baris = [];
        $tanpaJadwal = 0;
        AnggaranKegiatan::muat($semuaKegiatan);

        foreach ($semuaKegiatan as $k) {
            $statusKegiatan = StatusKegiatan::tryFrom($k['status'] ?? '') ?? StatusKegiatan::Draf;
            $anggaran = AnggaranKegiatan::untuk($k);

            if ($sumber !== '') {
                $anggaran = array_values(array_filter($anggaran, fn (AnggaranSumber $a) => $a->sumber === $sumber));
            }

            if (($tahun !== null && TahunKegiatan::dari($k) !== $tahun) || ($cakupan !== null && ! in_array($k['leading'] ?? null, $cakupan, true)) || ($leading !== '' && ($k['leading'] ?? null) !== $leading) || ($status !== '' && $statusKegiatan->value !== $status) || ($sumber !== '' && $anggaran === [])) {
                continue;
            }

            if ($dari !== null || $sampai !== null) {
                $cocok = self::dalamRentang($k, $dari, $sampai);
                $tanpaJadwal += $cocok === null ? 1 : 0;
                if ($cocok !== true) {
                    continue;
                }
            }

            $baris[] = [
                'id' => (int) $k['id'],
                'nama' => trim((string) ($k['nama_kegiatan'] ?? '')) ?: 'Tanpa nama',
                'kode_rktm' => ($k['kode_rktm'] ?? null) ?: null,
                'leading' => $k['leading'] ?? null,
                'user_id' => isset($k['user_id']) ? (int) $k['user_id'] : null,
                'diperbarui' => $k['diperbarui_pada'] ?? null,
                'status' => $statusKegiatan,
                'anggaran' => $anggaran,
            ];
        }

        return new self($baris, $tanpaJadwal);
    }

    /**
     * Pelaksanaan kegiatan beririsan dengan rentang [dari, sampai] (batas boleh terbuka): memakai tanggal mulai–selesai bila
     * ada, selain itu tiap bulan pelaksanaan pada tahun anggaran kegiatan. Null = tanggal & bulan pelaksanaan kosong.
     *
     * @param  array<string, mixed>  $k
     */
    private static function dalamRentang(array $k, ?CarbonImmutable $dari, ?CarbonImmutable $sampai): ?bool
    {
        $pelaksanaan = DataPelaksanaan::dari($k);
        $beririsan = fn (CarbonImmutable $awal, CarbonImmutable $akhir) => ($dari === null || $akhir >= $dari) && ($sampai === null || $awal <= $sampai);

        if ($pelaksanaan->mulai !== null) {
            return $beririsan($pelaksanaan->mulai, $pelaksanaan->selesai() ?? $pelaksanaan->mulai);
        }
        if ($pelaksanaan->bulan === []) {
            return null;
        }

        $tahun = TahunKegiatan::dari($k);
        foreach ($pelaksanaan->bulan as $bulan) {
            $awal = CarbonImmutable::create($tahun, $bulan, 1);
            if ($beririsan($awal, $awal->endOfMonth()->startOfDay())) {
                return true;
            }
        }

        return false;
    }

    /** "2026-07-01" → tanggal; kosong/tidak sah → null. */
    private static function tanggal(?string $teks): ?CarbonImmutable
    {
        if ($teks === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $teks)) {
            return null;
        }

        $tanggal = CarbonImmutable::createFromFormat('!Y-m-d', $teks);

        return $tanggal !== false && $tanggal->format('Y-m-d') === $teks ? $tanggal : null;
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
     * RAB, RAB melebihi pagunya sendiri, RAB tanpa pagu). $semua = tampilkan juga leading tanpa kegiatan. $urut = kunci
     * urutLeading() (bawaan urutan master; nilai kosong — mis. selisih yang belum bisa dihitung — selalu di akhir).
     *
     * @return list<array{kode: string|null, nama_unit: string, jumlah: int, perStatus: array<string, int>, pagu: int, total: int, selisih: int|null, persen: float|null, rab: int, melebihi: int, tanpa_pagu: int, sumber: array<string, array{pagu: int, total: int, selisih: int|null, persen: float|null, rab: int, melebihi: int, tanpa_pagu: int}>}>
     */
    public function perLeading(bool $semua = true, string $urut = 'kode', ?string $arah = null): array
    {
        $nol = ['pagu' => 0, 'total' => 0, 'rab' => 0, 'melebihi' => 0, 'tanpa_pagu' => 0];
        $kosong = fn (?string $kode, string $nama) => ['kode' => $kode, 'nama_unit' => $nama, 'jumlah' => 0, 'perStatus' => array_fill_keys(array_column(StatusKegiatan::cases(), 'value'), 0)]
            + $nol + ['sumber' => array_fill_keys(SumberDana::urutan(), $nol)];
        $hasil = [];
        $nonaktif = [];
        foreach (MasterKode::semuaLeading() as $l) {
            $hasil[$l['kode']] = $kosong($l['kode'], $l['nama_unit']);
            $nonaktif[$l['kode']] = ! $l['is_active'];
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
            // Leading nonaktif hanya tampil bila masih punya kegiatan.
            if (($semua && ! ($nonaktif[$b['kode'] ?? ''] ?? false)) || $b['jumlah'] > 0) {
                $b['sumber'] = array_map(fn (array $s) => self::sel($s), $b['sumber']);
                $lengkap[] = self::sel($b);
            }
        }

        $pilihan = self::urutLeading();
        $urut = isset($pilihan[$urut]) ? $urut : 'kode';
        $arah = in_array($arah, ['asc', 'desc'], true) ? $arah : $pilihan[$urut][1];
        $posisi = self::posisiLeading();
        [$grup, $angka] = array_pad(explode('_', $urut, 2), 2, null);
        $sumber = SumberDana::dariKunci((string) $grup)?->value;
        $kunci = match (true) {
            $urut === 'kode' => fn (array $b) => $posisi[$b['kode'] ?? ''] ?? null,
            $urut === 'jumlah' => fn (array $b) => $b['jumlah'],
            $sumber !== null => fn (array $b) => $b['sumber'][$sumber][$angka] ?? null,
            default => fn (array $b) => $b[$angka],
        };

        return self::urutkan($lengkap, $kunci, $arah, fn (array $a, array $b) => ($posisi[$a['kode'] ?? ''] ?? PHP_INT_MAX) <=> ($posisi[$b['kode'] ?? ''] ?? PHP_INT_MAX));
    }

    /**
     * Pilihan urutan tabel Total per leading → [label, arah bawaan]: kode leading, jumlah kegiatan, lalu pagu / total RAB /
     * selisih tiap sumber dana (`dipa_total`, `komite_selisih`, …) dan gabungannya (`semua_…`). Selisih naik = melebihi dulu.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function urutLeading(): array
    {
        $hasil = ['kode' => ['Kode leading', 'asc'], 'jumlah' => ['Kegiatan terbanyak', 'desc']];
        $grup = array_map(fn (SumberDana $s) => [$s->kunci(), $s->value], SumberDana::cases());
        $grup[] = ['semua', 'gabungan'];
        foreach ($grup as [$kunci, $nama]) {
            $hasil["{$kunci}_pagu"] = ["Pagu {$nama} terbesar", 'desc'];
            $hasil["{$kunci}_total"] = ["Total RAB {$nama} terbesar", 'desc'];
            $hasil["{$kunci}_selisih"] = ["Selisih {$nama} terkecil (melebihi dulu)", 'asc'];
        }

        return $hasil;
    }

    /**
     * Urutkan baris menurut $kunci; nilai null (belum bisa dihitung) selalu di akhir apa pun arahnya, seri menurut $seri.
     *
     * @template T
     *
     * @param  list<T>  $baris
     * @param  \Closure(T): (int|float|string|null)  $kunci
     * @param  \Closure(T, T): int  $seri
     * @return list<T>
     */
    private static function urutkan(array $baris, \Closure $kunci, string $arah, \Closure $seri): array
    {
        $kali = $arah === 'asc' ? 1 : -1;
        usort($baris, function ($a, $b) use ($kunci, $kali, $seri) {
            [$ka, $kb] = [$kunci($a), $kunci($b)];

            return match (true) {
                $ka === null && $kb === null => 0,
                $ka === null => 1,
                $kb === null => -1,
                default => ($ka <=> $kb) * $kali,
            } ?: $seri($a, $b);
        });

        return $baris;
    }

    /**
     * Posisi tiap kode leading di master (urutan baku; "1.10" sesudah "1.9", bukan sesudah "1.1").
     *
     * @return array<string, int>
     */
    private static function posisiLeading(): array
    {
        return array_flip(array_column(MasterKode::semuaLeading(), 'kode'));
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
     * Rincian per kegiatan (PRD §11 "Rincian per Kegiatan"): satu baris per kegiatan dengan RAB per sumber dana (pagu,
     * total, item, persen pemakaian, StatusPagu — tiap RAB dibandingkan dengan pagunya sendiri) dan angka gabungannya
     * (sel()), serta kelebihan = jumlah kelebihan RAB yang melampaui pagunya sendiri (tidak tertutup sisa RAB lain atau
     * RAB tanpa pagu). Bawaan diurutkan dari total RAB terbesar; kunci lain di URUT_RINCIAN (pemakaian = persen tertinggi
     * di antara RAB-nya); nilai kosong selalu di akhir, seri menurut nama lalu id.
     *
     * @return list<array{id: int, nama: string, kode_rktm: string|null, leading: string|null, leading_nama: string|null, status: StatusKegiatan, rab: list<array{sumber: string, pagu: int|null, total: int, item: int, persen: float|null, status: StatusPagu}>, pagu: int, total: int, selisih: int|null, persen: float|null, kelebihan: int, rab_jumlah: int, melebihi: int, tanpa_pagu: int}>
     */
    public function rincianKegiatan(string $urut = 'total', ?string $arah = null): array
    {
        $urut = isset(self::URUT_RINCIAN[$urut]) ? $urut : 'total';
        $arah = in_array($arah, ['asc', 'desc'], true) ? $arah : self::URUT_RINCIAN[$urut][1];
        $namaLeading = array_column(MasterKode::semuaLeading(), 'nama_unit', 'kode');

        $baris = array_map(function (array $k) use ($namaLeading) {
            $rab = array_map(fn (AnggaranSumber $a) => [
                'sumber' => $a->sumber,
                'pagu' => $a->pagu(),
                'total' => $a->total(),
                'item' => $a->jumlahItem(),
                'persen' => TabelPagu::persen($a->total(), $a->pagu()),
                'status' => $a->keteranganPagu(),
            ], $k['anggaran']);
            $angka = self::sel([
                'pagu' => array_sum(array_map(fn ($r) => (int) $r['pagu'], $rab)),
                'total' => array_sum(array_column($rab, 'total')),
                'rab' => count($rab),
                'melebihi' => count(array_filter($rab, fn ($r) => $r['status']->melebihi())),
                'tanpa_pagu' => count(array_filter($rab, fn ($r) => $r['pagu'] === null)),
            ]);

            return [
                'id' => $k['id'],
                'nama' => $k['nama'],
                'kode_rktm' => $k['kode_rktm'],
                'leading' => $k['leading'],
                'leading_nama' => $namaLeading[$k['leading'] ?? ''] ?? null,
                'status' => $k['status'],
                'rab' => $rab,
                'pagu' => $angka['pagu'],
                'total' => $angka['total'],
                'selisih' => $angka['selisih'],
                'persen' => $angka['persen'],
                'kelebihan' => array_sum(array_map(fn ($r) => $r['status']->melebihi() ? -$r['status']->selisih : 0, $rab)),
                'rab_jumlah' => $angka['rab'],
                'melebihi' => $angka['melebihi'],
                'tanpa_pagu' => $angka['tanpa_pagu'],
            ];
        }, $this->kegiatan);

        $posisi = self::posisiLeading();
        $kunci = match ($urut) {
            'pagu' => fn (array $b) => $b['pagu'],
            'kelebihan' => fn (array $b) => $b['kelebihan'],
            'pemakaian' => fn (array $b) => ($persen = array_filter(array_column($b['rab'], 'persen'), fn ($p) => $p !== null)) ? max($persen) : null,
            'nama' => fn (array $b) => mb_strtolower($b['nama']),
            'leading' => fn (array $b) => $posisi[$b['leading'] ?? ''] ?? null,
            'status' => fn (array $b) => $b['status']->langkah(),
            default => fn (array $b) => $b['total'],
        };

        return self::urutkan($baris, $kunci, $arah, fn (array $a, array $b) => (mb_strtolower($a['nama']) <=> mb_strtolower($b['nama'])) ?: $a['id'] <=> $b['id']);
    }

    /**
     * Rekap belanja per kelompok (bahan/jasa/lain) dan per kode akun seluruh kegiatan tersaring, per sumber dana: RAB
     * tiap sumber dari semua kegiatan digabung lalu disusun lewat RekapKelompok (bentuk sama dengan rekap RAB satu
     * kegiatan; item membawa `kegiatan_id`/`kegiatan`). Tiap kelompok ditambah `kegiatan` = jumlah kegiatan yang punya item
     * di kelompok itu. Sumber dana = yang muncul pada kegiatan tersaring (urut baku; semua bila belum ada).
     *
     * @return array{sumber: list<string>, total: int, totalPerSumber: array<string, int>, jumlahItem: int, kelompok: list<array{kunci: string, label: string, perSumber: array<string, int>, total: int, persen: float, jumlahItem: int, kegiatan: int, item: list<array<string, mixed>>}>, kode: list<array{kode: string, label: string, perSumber: array<string, int>, total: int}>}
     */
    public function perKelompok(): array
    {
        $muncul = array_merge([], ...array_map(fn (array $k) => array_map(fn (AnggaranSumber $a) => $a->sumber, $k['anggaran']), $this->kegiatan));
        $sumber = array_values(array_intersect(SumberDana::urutan(), $muncul)) ?: SumberDana::urutan();

        $gabungan = [];
        foreach ($sumber as $s) {
            $hasil = [];
            foreach ($this->kegiatan as $k) {
                foreach ($k['anggaran'] as $a) {
                    if ($a->sumber === $s) {
                        $rincian = array_map(fn (array $item) => $item + ['kegiatan_id' => $k['id'], 'kegiatan' => $k['nama']], $a->hasil->rincian);
                        $hasil[] = new HasilRab($rincian, $a->hasil->total, $a->hasil->perKategori, $a->hasil->perKode);
                    }
                }
            }
            $gabungan[$s] = KalkulatorRab::gabung($hasil);
        }

        $rekap = RekapKelompok::susun($gabungan);
        foreach ($rekap['kelompok'] as &$kelompok) {
            $kelompok['kegiatan'] = count(array_unique(array_column($kelompok['item'], 'kegiatan_id')));
        }
        unset($kelompok);

        return $rekap;
    }

    /**
     * Rekap per operator (pembuat kegiatan, activities.user_id): jumlah kegiatan per status, leading yang kegiatannya
     * dibuat, waktu perubahan terakhir, dan angka anggaran gabungan & per sumber dana (sel()). Diurutkan dari total RAB
     * terbesar lalu nama; kegiatan yang pembuatnya tidak tercatat dikumpulkan di baris terakhir.
     *
     * @param  list<array{id: int, name: string, role: string, jabatan?: string}>  $pengguna  Akun aplikasi (nama & peran).
     * @return list<array{user_id: int|null, nama: string, peran: string|null, jabatan: string|null, jumlah: int, perStatus: array<string, int>, leading: list<string>, terakhir: string|null, pagu: int, total: int, selisih: int|null, persen: float|null, rab: int, melebihi: int, tanpa_pagu: int, sumber: array<string, array{pagu: int, total: int, selisih: int|null, persen: float|null, rab: int, melebihi: int, tanpa_pagu: int}>}>
     */
    public function perOperator(array $pengguna): array
    {
        $akun = array_column($pengguna, null, 'id');
        $nol = ['pagu' => 0, 'total' => 0, 'rab' => 0, 'melebihi' => 0, 'tanpa_pagu' => 0];
        $hasil = [];

        foreach ($this->kegiatan as $k) {
            $id = $k['user_id'] !== null && isset($akun[$k['user_id']]) ? $k['user_id'] : null;
            $kunci = $id ?? 'tanpa';
            $hasil[$kunci] ??= [
                'user_id' => $id,
                'nama' => $id !== null ? $akun[$id]['name'] : 'Pembuat tidak tercatat',
                'peran' => $id !== null ? $akun[$id]['role'] : null,
                'jabatan' => $id !== null ? ($akun[$id]['jabatan'] ?? null) : null,
                'jumlah' => 0,
                'perStatus' => array_fill_keys(array_column(StatusKegiatan::cases(), 'value'), 0),
                'leading' => [],
                'terakhir' => null,
            ] + $nol + ['sumber' => array_fill_keys(SumberDana::urutan(), $nol)];
            $baris = &$hasil[$kunci];
            $baris['jumlah']++;
            $baris['perStatus'][$k['status']->value]++;
            if ($k['leading'] !== null && ! in_array($k['leading'], $baris['leading'], true)) {
                $baris['leading'][] = $k['leading'];
            }
            if ($k['diperbarui'] !== null && ($baris['terakhir'] === null || strtotime($k['diperbarui']) > strtotime($baris['terakhir']))) {
                $baris['terakhir'] = $k['diperbarui'];
            }
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

        $posisi = self::posisiLeading();
        $lengkap = array_map(function (array $b) use ($posisi) {
            usort($b['leading'], fn (string $x, string $y) => ($posisi[$x] ?? PHP_INT_MAX) <=> ($posisi[$y] ?? PHP_INT_MAX));
            $b['sumber'] = array_map(fn (array $s) => self::sel($s), $b['sumber']);

            return self::sel($b);
        }, array_values($hasil));

        return self::urutkan($lengkap, fn (array $b) => $b['user_id'] === null ? null : $b['total'], 'desc', fn (array $a, array $b) => mb_strtolower($a['nama']) <=> mb_strtolower($b['nama']));
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
