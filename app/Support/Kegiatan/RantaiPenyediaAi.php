<?php

namespace App\Support\Kegiatan;

use App\Support\Pengaturan\PenyediaAi;
use App\Support\Stub\StubPengaturan;

/**
 * Rantai penyedia AI satu permintaan (PRD §21 "Penyedia Aktif & Cadangan"): penyedia aktif dicoba lebih dulu, bila gagal
 * penyedia cadangan; keduanya gagal → GagalPenyediaAi berpesan kegagalan penyedia aktif (+ "Penyedia cadangan juga
 * gagal."). Failover cepat: penyedia aktif yang sedang dijeda KesehatanPenyediaAi (gagal nyata baru-baru ini) dicoba
 * terakhir selama cadangannya sehat (`dilewati`), sehingga permintaan tidak menunggu waktu habis berulang; setiap
 * kegagalan nyata dicatat dan jawaban berhasil memulihkan penyedia itu. Simulasi kegagalan di Pengaturan AI
 * (`simulasi_gagal`: jenis + cakupan aktif/semua) membuat penyedia yang dicakup gagal tanpa menghubungi apa pun dan tidak
 * dicatat sebagai kegagalan nyata. Tanpa penyedia aktif, permintaan dijawab penyusun tiruan ("tiruan").
 */
final class RantaiPenyediaAi
{
    /**
     * @template T
     *
     * @param  \Closure(string): T  $coba  Menjalankan permintaan untuk satu penyedia.
     * @return array{hasil: T, penyedia: string, gagal: list<GagalPenyediaAi>, aktif: string|null, dilewati: array{penyedia: string, jenis: string, judul: string, sampai: string}|null}
     *
     * @throws GagalPenyediaAi Semua penyedia gagal.
     */
    public static function jalankan(\Closure $coba): array
    {
        $ai = StubPengaturan::pengaturanAi();
        $aktif = $ai['provider_aktif'] ?? null;
        $cadangan = $aktif !== null ? ($ai['provider_cadangan'] ?? null) : null;
        $urutan = $aktif === null ? ['tiruan'] : array_values(array_filter([$aktif, $cadangan]));
        $dilewati = null;
        if ($cadangan !== null && ($jeda = KesehatanPenyediaAi::jeda($aktif)) !== null && KesehatanPenyediaAi::jeda($cadangan) === null) {
            $urutan = [$cadangan, $aktif];
            $dilewati = ['penyedia' => $aktif] + $jeda;
        }
        $simulasi = $ai['simulasi_gagal'] ?? null;
        $gagal = [];

        foreach ($urutan as $penyedia) {
            try {
                if ($simulasi !== null && isset(KegagalanAi::JENIS[$simulasi['jenis'] ?? '']) && (($simulasi['cakupan'] ?? 'aktif') === 'semua' || $penyedia === ($aktif ?? 'tiruan'))) {
                    throw new GagalPenyediaAi($simulasi['jenis'], $penyedia, false, true);
                }

                $hasil = $coba($penyedia);
                if ($penyedia !== 'tiruan') {
                    KesehatanPenyediaAi::catatBerhasil($penyedia);
                }

                return ['hasil' => $hasil, 'penyedia' => $penyedia, 'gagal' => $gagal, 'aktif' => $aktif, 'dilewati' => $penyedia !== $aktif ? $dilewati : null];
            } catch (GagalPenyediaAi $e) {
                $gagal[] = $e;
            } catch (GagalUsulanAi $e) {
                // Kegagalan tanpa jenis dari penyusun: pesannya dipertahankan.
                $gagal[] = new GagalPenyediaAi('jawaban_rusak', $penyedia, false, false, $e->getMessage());
            }
            $terakhir = end($gagal);
            if (! $terakhir->simulasi && $penyedia !== 'tiruan') {
                KesehatanPenyediaAi::catatGagal($penyedia, $terakhir->jenis);
            }
        }

        // Pesan utama = kegagalan penyedia yang dipilih admin sebagai aktif (bukan urutan coba).
        $utama = collect($gagal)->first(fn (GagalPenyediaAi $e) => $e->penyedia === ($aktif ?? 'tiruan')) ?? $gagal[0];
        throw new GagalPenyediaAi($utama->jenis, end($gagal)->penyedia, count($gagal) > 1, $utama->simulasi, $utama->pesanDasar);
    }

    /** Catatan untuk pengguna bila jawaban datang dari penyedia cadangan; null bila dari penyedia aktif atau tiruan. */
    public static function catatan(array $hasil): ?string
    {
        $aktif = $hasil['aktif'] ?? null;
        if ($aktif === null || $hasil['penyedia'] === $aktif) {
            return null;
        }

        $nama = PenyediaAi::nama($hasil['penyedia']) ?? $hasil['penyedia'];
        $gagalAktif = collect($hasil['gagal'])->first(fn (GagalPenyediaAi $e) => $e->penyedia === $aktif);
        if ($gagalAktif !== null) {
            return "Disusun penyedia cadangan ({$nama}) karena penyedia aktif gagal: ".mb_strtolower(KegagalanAi::judul($gagalAktif->jenis)).'.';
        }
        if (($hasil['dilewati'] ?? null) !== null) {
            return "Disusun penyedia cadangan ({$nama}) karena penyedia aktif sedang bermasalah: ".mb_strtolower(KegagalanAi::judul($hasil['dilewati']['jenis'])).' — dicoba lagi otomatis setelah jeda.';
        }

        return null;
    }
}
