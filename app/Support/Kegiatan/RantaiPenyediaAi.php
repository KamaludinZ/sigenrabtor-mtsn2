<?php

namespace App\Support\Kegiatan;

use App\Support\Pengaturan\PenyediaAi;
use App\Support\Stub\StubPengaturan;

/**
 * Rantai penyedia AI satu permintaan (PRD §21 "Penyedia Aktif & Cadangan"): penyedia aktif dicoba lebih dulu, bila gagal
 * penyedia cadangan; keduanya gagal → GagalPenyediaAi. Simulasi kegagalan di Pengaturan AI (`simulasi_gagal`: jenis +
 * cakupan aktif/semua) membuat penyedia yang dicakup gagal dengan jenis itu tanpa menghubungi apa pun. Tanpa penyedia
 * aktif, permintaan dijawab penyusun tiruan ("tiruan").
 */
final class RantaiPenyediaAi
{
    /**
     * @template T
     *
     * @param  \Closure(string): T  $coba  Menjalankan permintaan untuk satu penyedia.
     * @return array{hasil: T, penyedia: string, gagal: list<GagalPenyediaAi>}
     *
     * @throws GagalPenyediaAi Semua penyedia gagal.
     */
    public static function jalankan(\Closure $coba): array
    {
        $ai = StubPengaturan::pengaturanAi();
        $urutan = array_values(array_filter([$ai['provider_aktif'] ?? 'tiruan', $ai['provider_aktif'] !== null ? $ai['provider_cadangan'] : null]));
        $simulasi = $ai['simulasi_gagal'] ?? null;
        $gagal = [];

        foreach ($urutan as $ke => $penyedia) {
            try {
                if ($simulasi !== null && isset(KegagalanAi::JENIS[$simulasi['jenis'] ?? '']) && (($simulasi['cakupan'] ?? 'aktif') === 'semua' || $ke === 0)) {
                    throw new GagalPenyediaAi($simulasi['jenis'], $penyedia, false, true);
                }

                return ['hasil' => $coba($penyedia), 'penyedia' => $penyedia, 'gagal' => $gagal];
            } catch (GagalPenyediaAi $e) {
                $gagal[] = $e;
            } catch (GagalUsulanAi $e) {
                // Kegagalan tanpa jenis dari penyusun: pesannya dipertahankan.
                $gagal[] = new GagalPenyediaAi('jawaban_rusak', $penyedia, false, false, $e->getMessage());
            }
        }

        $terakhir = end($gagal);
        throw new GagalPenyediaAi($gagal[0]->jenis, $terakhir->penyedia, count($gagal) > 1, $gagal[0]->simulasi, $gagal[0]->pesanDasar);
    }

    /** Catatan untuk pengguna bila jawaban datang dari penyedia cadangan; null bila dari penyedia pertama. */
    public static function catatan(array $hasil): ?string
    {
        if ($hasil['gagal'] === []) {
            return null;
        }

        return 'Disusun penyedia cadangan ('.(PenyediaAi::nama($hasil['penyedia']) ?? $hasil['penyedia']).') karena penyedia aktif gagal: '.mb_strtolower(KegagalanAi::judul($hasil['gagal'][0]->jenis)).'.';
    }
}
