<?php

namespace App\Support\Rab;

use App\Livewire\Forms\ItemBelanjaForm;

/**
 * Rekap anggaran satu kegiatan per kelompok belanja dan per kode akun, lintas sumber dana.
 */
class RekapKelompok
{
    /**
     * @param  array<string, HasilRab>  $perSumber  'DIPA'/'Komite' → hasil hitung RAB-nya.
     * @return array{
     *     sumber: list<string>,
     *     total: int,
     *     totalPerSumber: array<string, int>,
     *     jumlahItem: int,
     *     kelompok: list<array{kunci: string, label: string, perSumber: array<string, int>, total: int, persen: float, jumlahItem: int, item: list<array<string, mixed>>}>,
     *     kode: list<array{kode: string, label: string, perSumber: array<string, int>, total: int}>
     * }
     */
    public static function susun(array $perSumber): array
    {
        $sumber = array_keys($perSumber);
        $total = array_sum(array_map(fn (HasilRab $h) => $h->total, $perSumber));

        $kelompok = [];
        foreach (ItemBelanjaForm::LABEL_KATEGORI as $kunci => $label) {
            $item = [];
            $nilai = [];

            foreach ($perSumber as $nama => $hasil) {
                $nilai[$nama] = $hasil->perKategori[$kunci] ?? 0;

                foreach ($hasil->rincian as $baris) {
                    if (($baris['kategori'] ?? 'lain') === $kunci) {
                        $item[] = $baris + ['sumber' => $nama];
                    }
                }
            }

            usort($item, fn ($a, $b) => $b['jumlah'] <=> $a['jumlah']);
            $subtotal = array_sum($nilai);

            $kelompok[] = [
                'kunci' => $kunci,
                'label' => $label,
                'perSumber' => $nilai,
                'total' => $subtotal,
                'persen' => $total > 0 ? round($subtotal / $total * 100, 1) : 0.0,
                'jumlahItem' => count($item),
                'item' => $item,
            ];
        }

        $semuaKode = [];
        foreach ($perSumber as $hasil) {
            $semuaKode = [...$semuaKode, ...array_keys($hasil->perKode)];
        }
        $semuaKode = array_values(array_unique(array_map('strval', $semuaKode)));
        usort($semuaKode, fn (string $a, string $b) => [$a === '', $a] <=> [$b === '', $b]);

        $kode = array_map(function (string $k) use ($perSumber) {
            $nilai = array_map(fn (HasilRab $h) => $h->perKode[$k] ?? 0, $perSumber);

            return [
                'kode' => $k,
                'label' => $k === '' ? 'Tanpa kode akun' : (ItemBelanjaForm::KODE_AKUN[$k] ?? 'Kode akun lain'),
                'perSumber' => $nilai,
                'total' => array_sum($nilai),
            ];
        }, $semuaKode);

        return [
            'sumber' => $sumber,
            'total' => $total,
            'totalPerSumber' => array_map(fn (HasilRab $h) => $h->total, $perSumber),
            'jumlahItem' => array_sum(array_map(fn (HasilRab $h) => $h->jumlahItem(), $perSumber)),
            'kelompok' => $kelompok,
            'kode' => $kode,
        ];
    }
}
