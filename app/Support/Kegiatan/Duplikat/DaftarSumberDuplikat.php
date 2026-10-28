<?php

namespace App\Support\Kegiatan\Duplikat;

use App\Contracts\KegiatanRepository;
use App\Support\Stub\StubData;

/**
 * Daftar kegiatan tahun lalu yang bisa diduplikat ke tahun aktif (PRD §19 F22) untuk endpoint `kegiatan.duplikat.sumber`
 * (`GET /kegiatan/duplikat/sumber?dari=&leading=&cari=&bisa=`) dan tab Duplikat `Kegiatan\SalinImpor`: tahun sumber
 * (`dari`, tahun di luar DuplikatKegiatan::tahunSumber → tahun sumber terbaru), baris DuplikatKegiatan::sumber dalam
 * cakupan akun, lalu `saring()` (cari nama atau kode RKTM tanpa beda huruf, leading, `bisa` ya = hanya yang belum ada
 * di tahun aktif). Tiap baris membawa `bisa_diduplikat` dan tautan kegiatan tahun aktif yang sudah ada.
 */
final class DaftarSumberDuplikat
{
    /** Tahun sumber yang berlaku: $dari bila termasuk tahun sumber, selain itu yang terbaru; null bila tidak ada. */
    public static function tahun(?int $dari): ?int
    {
        $tahun = DuplikatKegiatan::tahunSumber();

        return in_array($dari, $tahun, true) ? $dari : ($tahun[0] ?? null);
    }

    /**
     * @param  list<array<string, mixed>>  $baris  DuplikatKegiatan::sumber.
     * @return list<array<string, mixed>>
     */
    public static function saring(array $baris, string $cari = '', string $leading = '', bool $hanyaBisa = false): array
    {
        $cari = mb_strtolower(trim($cari));

        return array_values(array_filter($baris, fn (array $b) => ($cari === '' || str_contains(mb_strtolower($b['nama'].' '.$b['kode_rktm']), $cari))
            && ($leading === '' || $b['leading'] === $leading)
            && (! $hanyaBisa || $b['sudah_ada'] === null)));
    }

    /**
     * @param  array{id: int, role: string, leadings?: list<string>}  $pengguna
     * @param  array<string, mixed>  $nilai  Saringan mentah (query string).
     * @return array<string, mixed>
     */
    public static function untuk(array $pengguna, array $nilai): array
    {
        $tahun = self::tahun(filter_var($nilai['dari'] ?? null, FILTER_VALIDATE_INT) ?: null);
        $semua = $tahun === null ? [] : DuplikatKegiatan::sumber(app(KegiatanRepository::class)->semua(), $tahun, $pengguna);
        $teks = fn (string $k) => is_string($nilai[$k] ?? null) ? trim($nilai[$k]) : '';
        $saringan = ['leading' => $teks('leading'), 'cari' => $teks('cari'), 'bisa' => $teks('bisa') === 'ya' ? 'ya' : ''];
        $baris = self::saring($semua, $saringan['cari'], $saringan['leading'], $saringan['bisa'] === 'ya');

        return [
            'tahun_aktif' => StubData::tahunAktif()['tahun'],
            'tahun_sumber' => DuplikatKegiatan::tahunSumber(),
            'dari' => $tahun,
            'saringan' => $saringan,
            'leading' => array_values(collect($semua)->filter(fn (array $b) => $b['leading'] !== null)->unique('leading')->map(fn (array $b) => ['kode' => $b['leading'], 'nama_unit' => $b['nama_leading']])->sortBy('kode', SORT_NATURAL)->values()->all()),
            'jumlah' => count($baris),
            'jumlah_bisa' => count(array_filter($baris, fn (array $b) => $b['sudah_ada'] === null)),
            'perubahan' => DuplikatKegiatan::PERUBAHAN,
            'kegiatan' => array_map(fn (array $b) => $b + [
                'bisa_diduplikat' => $b['sudah_ada'] === null,
                'tautan_sudah_ada' => $b['sudah_ada'] === null ? null : route('kegiatan.edit', ['kegiatan' => $b['sudah_ada']]),
            ], $baris),
        ];
    }
}
