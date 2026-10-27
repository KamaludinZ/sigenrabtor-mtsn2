<?php

namespace App\Support\Master\Impor;

use App\Support\Impor\GagalBacaImpor;
use App\Support\Master\JenisKode;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Berkas unggahan master kode (PRD §14): batas berkas, cakupan unggahan (berkas master lengkap atau satu jenis), dan
 * pengenalan sheet per jenis dari nama sheet — sheet Perkin memuat SK & IKSK, SNP memuat RKTM, EDM, dan 5 Budaya Mutu.
 */
final class BerkasMaster
{
    public const MAKS_KB = 5120;

    /** Cakupan 'semua' = satu berkas master berisi sheet tiap jenis; selain itu satu kunci JenisKode. */
    public const SEMUA = 'semua';

    /** Jenis → nama sheet yang dikenali (huruf kecil, spasi tunggal), urut prioritas. */
    public const LEMBAR = [
        'sk' => ['perkin', 'sk', 'kode sk', 'sasaran kegiatan'],
        'iksk' => ['perkin', 'iksk', 'kode iksk', 'indikator'],
        'rktm' => ['snp', 'rktm', 'kode rktm'],
        'edm' => ['edm', 'kode edm'],
        'budaya' => ['5 budaya mutu', 'budaya mutu', 'budaya'],
    ];

    /**
     * Pilihan cakupan unggahan: kunci → label.
     *
     * @return array<string, string>
     */
    public static function cakupan(): array
    {
        return [self::SEMUA => 'Berkas master lengkap'] + array_map(fn (array $j) => $j['label'], JenisKode::DAFTAR);
    }

    /**
     * Jenis yang tercakup.
     *
     * @return list<string>
     */
    public static function jenisUntuk(string $cakupan): array
    {
        return $cakupan === self::SEMUA ? array_keys(JenisKode::DAFTAR) : [$cakupan];
    }

    /**
     * Nama sheet berkas (CSV: satu lembar bernama "CSV").
     *
     * @return list<string>
     *
     * @throws GagalBacaImpor
     */
    public static function lembar(string $path, string $namaAsli): array
    {
        $ekstensi = mb_strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));
        if ($ekstensi === 'csv') {
            return ['CSV'];
        }

        try {
            $pembaca = IOFactory::createReader($ekstensi === 'xls' ? 'Xls' : 'Xlsx');
            if (! $pembaca->canRead($path)) {
                throw new GagalBacaImpor('isi tidak sesuai ekstensi');
            }
            $nama = $pembaca->listWorksheetNames($path);
        } catch (Throwable) {
            throw new GagalBacaImpor('Berkas "'.basename($namaAsli).'" tidak dapat dibaca sebagai Excel. Simpan ulang sebagai .xlsx lalu unggah lagi.');
        }

        return array_values(array_map('strval', $nama));
    }

    /**
     * Sheet untuk tiap jenis dalam cakupan (null = tidak ditemukan). Unggahan satu jenis dari berkas satu sheet (atau CSV)
     * memakai sheet itu walau namanya lain.
     *
     * @param  list<string>  $lembar
     * @return array<string, string|null>
     */
    public static function kenali(array $lembar, string $cakupan): array
    {
        $kunci = array_combine(array_map(fn (string $n) => mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $n))), $lembar), $lembar);
        $hasil = [];

        foreach (self::jenisUntuk($cakupan) as $jenis) {
            $hasil[$jenis] = null;
            foreach (self::LEMBAR[$jenis] as $calon) {
                if (isset($kunci[$calon])) {
                    $hasil[$jenis] = $kunci[$calon];
                    break;
                }
            }
            if ($hasil[$jenis] === null && $cakupan !== self::SEMUA && count($lembar) === 1) {
                $hasil[$jenis] = $lembar[0];
            }
        }

        return $hasil;
    }

    /** Ukuran berkas terbaca, mis. "24 KB" atau "1,2 MB". */
    public static function ukuran(int $bita): string
    {
        return $bita < 1024 * 1024
            ? max(1, (int) round($bita / 1024)).' KB'
            : str_replace('.', ',', (string) round($bita / 1024 / 1024, 1)).' MB';
    }
}
