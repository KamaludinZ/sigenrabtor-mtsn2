<?php

namespace App\Support\Dokumen;

use App\Contracts\PenomoranDokumenRepository;
use App\Support\Pengaturan\RiwayatPengaturan;
use App\Support\Pengaturan\TahunDipilih;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Penomoran dokumen otomatis (PRD §9 "nomor dokumen mengikuti aturan dan tahun aktif"; kontrak PenomoranDokumenRepository):
 * - `aturan($tahun)` = per jenis DokumenKegiatan::JENIS: aturan tersimpan atau `BAWAAN` {pola (FormatSeragam::TOKEN),
 *   digit DIGIT, aktif} + `terakhir` (nomor urut terpakai) & `berikutnya`;
 * - `galat($aturan)` berkunci `{jenis}.pola|digit` (FormatSeragam::galatPola, digit 2–6), `rapikan()`;
 * - `simpanAturan($tahun, $isian, $pengguna)` (admin; tahun terkunci → AuthorizationException; ValidationException;
 *   tanpa perubahan tidak ditulis) + riwayat `ubah_penomoran`;
 * - `usulan($catatan)` = perkiraan nomor berikutnya untuk satu catatan Riwayat Dokumen (`nilaiCatatan`: jenis, leading,
 *   sumber, tahun anggaran catatan; bulan sekarang) tanpa memajukan penghitung — null bila jenis itu tidak dinomori;
 * - `terbitkan($nilai)` = penerbitan nomor sungguhan: nomor urut diambil atomik (`PenomoranDokumenRepository::
 *   ambilBerikutnya`), nomor yang sudah dipakai dokumen lain tahun itu (`nomorTerpakai`, mis. diketik manual) dilewati
 *   (maks. `MAKS_LEWATI`, selain itu RuntimeException) → {nomor, urut}; null bila jenis itu tidak dinomori otomatis.
 *   Dipakai PerbaruiRiwayatDokumen::beriNomor (nomor usulan → diterbitkan; nomor ketikan sendiri tidak memajukan
 *   penghitung) dan CatatRiwayatDokumen::dariArsip (arsip final bernomor otomatis).
 */
final class PenomoranDokumen
{
    public const DIGIT = [2, 3, 4, 5, 6];

    /** Nomor terpakai yang dilewati berturut-turut sebelum penerbitan otomatis menyerah. */
    public const MAKS_LEWATI = 20;

    /** Aturan bawaan per jenis dokumen. */
    public const BAWAAN = [
        'KAK+TOR' => ['pola' => '{jenis}/{urut}/{madrasah}/{bulan_romawi}/{tahun}', 'digit' => 3, 'aktif' => true],
        'KAK' => ['pola' => 'KAK/{urut}/{tahun}', 'digit' => 3, 'aktif' => true],
        'TOR' => ['pola' => 'TOR/{urut}/{tahun}', 'digit' => 3, 'aktif' => false],
        'RAB' => ['pola' => 'RAB/{urut}/{sumber}/{leading}/{tahun}', 'digit' => 3, 'aktif' => true],
        'lengkap' => ['pola' => 'DOK/{urut}/{leading}/{tahun}', 'digit' => 3, 'aktif' => true],
    ];

    /** @return array<string, array{pola: string, digit: int, aktif: bool, terakhir: int, berikutnya: int}> */
    public static function aturan(int $tahun): array
    {
        $repo = app(PenomoranDokumenRepository::class);
        $tersimpan = $repo->aturan($tahun);
        $terakhir = $repo->terakhir($tahun);
        $hasil = [];
        foreach (self::BAWAAN as $jenis => $bawaan) {
            $a = ($tersimpan[$jenis] ?? []) + $bawaan;
            $hasil[$jenis] = ['pola' => (string) $a['pola'], 'digit' => (int) $a['digit'], 'aktif' => (bool) $a['aktif'], 'terakhir' => $terakhir[$jenis] ?? 0, 'berikutnya' => ($terakhir[$jenis] ?? 0) + 1];
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $isian  {jenis: {pola, digit, aktif}}
     * @return array<string, array{pola: string, digit: int, aktif: bool}>
     */
    public static function rapikan(array $isian): array
    {
        $hasil = [];
        foreach (self::BAWAAN as $jenis => $bawaan) {
            $a = is_array($isian[$jenis] ?? null) ? $isian[$jenis] : [];
            $hasil[$jenis] = [
                'pola' => trim((string) preg_replace('/\s+/u', '', (string) ($a['pola'] ?? $bawaan['pola']))),
                'digit' => (int) ($a['digit'] ?? $bawaan['digit']),
                'aktif' => filter_var($a['aktif'] ?? $bawaan['aktif'], FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $hasil;
    }

    /**
     * @param  array<string, array{pola: string, digit: int, aktif: bool}>  $aturan  Sudah dirapikan.
     * @return array<string, string>
     */
    public static function galat(array $aturan): array
    {
        $galat = [];
        foreach ($aturan as $jenis => $a) {
            if (($pesan = FormatSeragam::galatPola($a['pola'])) !== null) {
                $galat["{$jenis}.pola"] = $pesan;
            }
            if (! in_array($a['digit'], self::DIGIT, true)) {
                $galat["{$jenis}.digit"] = 'Panjang nomor urut 2–6 angka.';
            }
        }

        return $galat;
    }

    /**
     * @param  array<string, mixed>  $isian
     * @param  array{role: string}  $pengguna
     * @return array{aturan: array<string, array{pola: string, digit: int, aktif: bool}>, ringkasan: string|null}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public static function simpanAturan(int $tahun, array $isian, array $pengguna): array
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Hanya admin yang bisa mengubah aturan penomoran.');
        }
        if (TahunDipilih::terkunci($tahun)) {
            throw new AuthorizationException("Tahun Anggaran {$tahun} dikunci — aturan penomoran hanya bisa dilihat.");
        }
        $aturan = self::rapikan($isian);
        if (($galat = self::galat($aturan)) !== []) {
            throw ValidationException::withMessages($galat);
        }
        $lama = array_map(fn (array $a) => array_intersect_key($a, array_flip(['pola', 'digit', 'aktif'])), self::aturan($tahun));
        $bagian = [];
        foreach ($aturan as $jenis => $a) {
            if ($a !== $lama[$jenis]) {
                $ubah = array_filter([
                    $a['pola'] !== $lama[$jenis]['pola'] ? "pola {$lama[$jenis]['pola']} → {$a['pola']}" : null,
                    $a['digit'] !== $lama[$jenis]['digit'] ? "{$lama[$jenis]['digit']} → {$a['digit']} digit" : null,
                    $a['aktif'] !== $lama[$jenis]['aktif'] ? ($a['aktif'] ? 'dinomori otomatis' : 'tidak dinomori') : null,
                ]);
                $bagian[] = (DokumenKegiatan::JENIS[$jenis] ?? $jenis).': '.implode(', ', $ubah);
            }
        }
        $ringkasan = $bagian === [] ? null : implode('; ', $bagian);
        if ($ringkasan !== null) {
            app(PenomoranDokumenRepository::class)->simpanAturan($tahun, $aturan);
            RiwayatPengaturan::catat('ubah_penomoran', "TA {$tahun} — {$ringkasan}");
        }

        return ['aturan' => $aturan, 'ringkasan' => $ringkasan];
    }

    /**
     * Nomor berikutnya dengan aturan $a (contoh di formulir) atau aturan tersimpan jenis itu.
     *
     * @param  array{jenis: string, leading?: string|null, sumber?: string|null, bulan?: int, tahun: int}  $nilai
     * @param  array{pola: string, digit: int, aktif: bool, berikutnya?: int}|null  $a
     */
    public static function nomor(array $nilai, ?array $a = null): ?string
    {
        $a ??= self::aturan($nilai['tahun'])[$nilai['jenis']] ?? null;
        if ($a === null || ! $a['aktif'] || FormatSeragam::galatPola($a['pola']) !== null) {
            return null;
        }
        $urut = $a['berikutnya'] ?? (app(PenomoranDokumenRepository::class)->terakhir($nilai['tahun'])[$nilai['jenis']] ?? 0) + 1;

        return FormatSeragam::nomor($a['pola'], $urut, $nilai + ['bulan' => (int) now()->format('n')], $a['digit']);
    }

    /**
     * Usulan nomor untuk satu catatan Riwayat Dokumen (null = jenis itu tidak dinomori otomatis); penghitung tidak maju.
     *
     * @param  array<string, mixed>  $catatan
     */
    public static function usulan(array $catatan): ?string
    {
        return self::nomor(self::nilaiCatatan($catatan));
    }

    /**
     * @param  array<string, mixed>  $catatan
     * @return array{jenis: string, leading: string|null, sumber: string|null, tahun: int}
     */
    public static function nilaiCatatan(array $catatan): array
    {
        return ['jenis' => (string) $catatan['jenis'], 'leading' => ($catatan['kode_leading'] ?? '') ?: null, 'sumber' => $catatan['sumber'] ?? null, 'tahun' => (int) $catatan['tahun_anggaran']];
    }

    /**
     * Terbitkan nomor dokumen berikutnya (penghitung maju atomik; nomor yang sudah dipakai dilewati).
     *
     * @param  array{jenis: string, leading?: string|null, sumber?: string|null, tahun: int}  $nilai
     * @return array{nomor: string, urut: int}|null
     *
     * @throws RuntimeException Penghitung gagal ditulis / terlalu banyak nomor terpakai berturut-turut.
     */
    public static function terbitkan(array $nilai): ?array
    {
        $a = self::aturan($nilai['tahun'])[$nilai['jenis']] ?? null;
        if ($a === null || ! $a['aktif'] || FormatSeragam::galatPola($a['pola']) !== null) {
            return null;
        }
        $terpakai = self::nomorTerpakai($nilai['tahun']);
        $repo = app(PenomoranDokumenRepository::class);
        for ($i = 0; $i < self::MAKS_LEWATI; $i++) {
            $urut = $repo->ambilBerikutnya($nilai['tahun'], $nilai['jenis']);
            $nomor = FormatSeragam::nomor($a['pola'], $urut, $nilai + ['bulan' => (int) now()->format('n')], $a['digit']);
            if (! isset($terpakai[mb_strtolower($nomor)])) {
                return ['nomor' => $nomor, 'urut' => $urut];
            }
        }

        throw new RuntimeException('Nomor otomatis berikutnya sudah dipakai dokumen lain — beri nomor secara manual atau ubah aturan penomoran.');
    }

    /** Nomor dokumen yang sudah dipakai di tahun itu (huruf kecil → true). @return array<string, true> */
    public static function nomorTerpakai(int $tahun): array
    {
        $hasil = [];
        foreach (RiwayatDokumen::semua() as $c) {
            if ((int) $c['tahun_anggaran'] === $tahun && filled($c['nomor_dokumen'] ?? null)) {
                $hasil[mb_strtolower((string) $c['nomor_dokumen'])] = true;
            }
        }

        return $hasil;
    }

    /** Tahun anggaran aktif. */
    public static function tahunAktif(): int
    {
        return (int) StubData::tahunAktif()['tahun'];
    }
}
