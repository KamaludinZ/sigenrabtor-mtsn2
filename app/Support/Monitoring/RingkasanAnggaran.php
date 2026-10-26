<?php

namespace App\Support\Monitoring;

use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Support\MasterKode;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;

/**
 * Agregat Monitoring Anggaran (PRD Fase 5 §11) untuk tahun aktif: kegiatan yang cocok dengan saringan (leading, sumber
 * dana, status) beserta anggaran per sumber dananya, lalu ringkasan jumlah kegiatan per status, pagu & total RAB per
 * sumber dana, dan selisihnya. Semua angka dari AnggaranKegiatan sehingga sama dengan layar RAB & Data Kegiatan.
 */
final readonly class RingkasanAnggaran
{
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
     * Rekap per leading (urutan master leading; kegiatan tanpa leading di baris terakhir bila ada): jumlah kegiatan per
     * status, pagu, total RAB, selisih, dan RAB melebihi pagu. $semua = tampilkan juga leading tanpa kegiatan.
     *
     * @return list<array{kode: string|null, nama_unit: string, jumlah: int, perStatus: array<string, int>, pagu: int, total: int, selisih: int, melebihi: int}>
     */
    public function perLeading(bool $semua = true): array
    {
        $kosong = fn (?string $kode, string $nama) => ['kode' => $kode, 'nama_unit' => $nama, 'jumlah' => 0, 'perStatus' => array_fill_keys(array_column(StatusKegiatan::cases(), 'value'), 0), 'pagu' => 0, 'total' => 0, 'selisih' => 0, 'melebihi' => 0];
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
                $baris['pagu'] += (int) $a->pagu();
                $baris['total'] += $a->total();
                $baris['melebihi'] += $a->statusPagu() === 'melebihi' ? 1 : 0;
            }
            $baris['selisih'] = $baris['pagu'] - $baris['total'];
            unset($baris);
        }

        return array_values(array_filter($hasil, fn (array $b) => $semua || $b['jumlah'] > 0));
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
