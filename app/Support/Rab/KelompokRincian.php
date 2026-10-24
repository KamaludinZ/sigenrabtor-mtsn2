<?php

namespace App\Support\Rab;

use App\Livewire\Forms\ItemBelanjaForm;

/**
 * Pengelompokan rincian RAB untuk tampilan dengan subtotal.
 */
class KelompokRincian
{
    public const MODE = [
        'urutan' => 'Urutan cetak',
        'kategori' => 'Per kelompok belanja',
        'kode' => 'Per kode akun',
    ];

    /**
     * Kelompokkan item (yang sudah memiliki 'jumlah'). Mode "urutan" menghasilkan satu kelompok tanpa judul.
     * Setiap item diberi 'nomor' sesuai urutan cetak agar tetap bisa dirujuk walau dikelompokkan.
     * $sertakanKosong (mode kategori): tampilkan juga kelompok tanpa item, mis. sebagai sasaran seret-lepas.
     *
     * @param  list<array<string, mixed>>  $rincian
     * @return list<array{kunci: string, label: string|null, item: list<array<string, mixed>>, subtotal: int, persen: float}>
     */
    public static function kelompokkan(array $rincian, string $mode, bool $sertakanKosong = false): array
    {
        $bernomor = array_map(fn (array $item, int $i) => $item + ['nomor' => $i + 1], $rincian, array_keys($rincian));
        $total = array_sum(array_column($rincian, 'jumlah'));

        if ($mode === 'urutan' || ! isset(self::MODE[$mode])) {
            return [self::kelompok('semua', null, $bernomor, $total)];
        }

        $kelompok = $mode === 'kategori' && $sertakanKosong
            ? array_fill_keys(array_keys(ItemBelanjaForm::LABEL_KATEGORI), [])
            : [];
        foreach ($bernomor as $item) {
            $kunci = $mode === 'kategori' ? ($item['kategori'] ?: 'lain') : ($item['kode'] ?: '');
            $kelompok[$kunci][] = $item;
        }

        // Urutan kelompok: kategori mengikuti bahan → jasa → lain; kode akun urut naik, tanpa kode di akhir.
        $urutan = $mode === 'kategori'
            ? array_values(array_intersect(array_keys(ItemBelanjaForm::LABEL_KATEGORI), array_keys($kelompok)))
            : self::urutKode(array_keys($kelompok));

        return array_map(
            fn (string $kunci) => self::kelompok((string) $kunci, self::label($mode, (string) $kunci), $kelompok[$kunci], $total),
            $urutan,
        );
    }

    private static function label(string $mode, string $kunci): string
    {
        if ($mode === 'kategori') {
            return ItemBelanjaForm::LABEL_KATEGORI[$kunci] ?? $kunci;
        }

        if ($kunci === '') {
            return 'Tanpa kode akun';
        }

        return isset(ItemBelanjaForm::KODE_AKUN[$kunci]) ? $kunci.' · '.ItemBelanjaForm::KODE_AKUN[$kunci] : $kunci;
    }

    /**
     * @param  list<int|string>  $kode
     * @return list<string>
     */
    private static function urutKode(array $kode): array
    {
        $kode = array_map('strval', $kode);
        usort($kode, fn (string $a, string $b) => [$a === '', $a] <=> [$b === '', $b]);

        return $kode;
    }

    /**
     * @param  list<array<string, mixed>>  $item
     * @return array{kunci: string, label: string|null, item: list<array<string, mixed>>, subtotal: int, persen: float}
     */
    private static function kelompok(string $kunci, ?string $label, array $item, int $total): array
    {
        $subtotal = array_sum(array_column($item, 'jumlah'));

        return [
            'kunci' => $kunci,
            'label' => $label,
            'item' => $item,
            'subtotal' => $subtotal,
            'persen' => $total > 0 ? round($subtotal / $total * 100, 1) : 0.0,
        ];
    }
}
