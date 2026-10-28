<?php

namespace App\Support\Dokumen;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Kegiatan buku dokumen tahun aktif berdasarkan saringan (PRD §18, F19) untuk endpoint `buku.kegiatan`
 * (`GET /buku/kegiatan`, semua pengguna): tiap kegiatan muncul sekali per buku leading × sumber dana yang
 * membiayainya, dengan nomor urut, halaman awal, status, dan cap DRAF dari susunan buku itu sendiri (BukuDokumen::isi)
 * — sehingga saringan tidak mengubah nomor & halamannya. Cakupan akun lewat BukuDokumen::leadingUntuk (akun leading
 * hanya leading-nya; leading di luar cakupan → AuthorizationException). Saringan (`SARINGAN`, kosong = semua):
 * `leading`, `sumber`, `status` (satu atau beberapa StatusKegiatan, dipisah koma), `draf` (ya|tidak), `cari` (nama,
 * kode RKTM, tanpa beda huruf).
 */
final class KegiatanBuku
{
    public const SARINGAN = ['leading', 'sumber', 'status', 'draf', 'cari'];

    /**
     * Saringan yang dirapikan; nilai tak dikenal dibuang (sumber/status/draf) — leading diperiksa cakupannya di `untuk()`.
     *
     * @param  array<string, mixed>  $nilai
     * @return array{leading: string, sumber: string, status: list<string>, draf: string, cari: string}
     */
    public static function saringan(array $nilai): array
    {
        $teks = fn (string $k) => is_string($nilai[$k] ?? null) ? trim($nilai[$k]) : '';
        $status = is_array($nilai['status'] ?? null) ? $nilai['status'] : explode(',', $teks('status'));

        return [
            'leading' => $teks('leading'),
            'sumber' => SumberDana::tryFrom($teks('sumber'))?->value ?? '',
            'status' => array_values(array_intersect(array_column(StatusKegiatan::cases(), 'value'), array_map('trim', array_filter($status, 'is_string')))),
            'draf' => in_array($teks('draf'), ['ya', 'tidak'], true) ? $teks('draf') : '',
            'cari' => mb_strtolower($teks('cari')),
        ];
    }

    /**
     * @param  array{id: int, role: string, leadings?: list<string>}  $pengguna
     * @param  array<string, mixed>  $nilai  Saringan mentah (query string).
     * @return array<string, mixed>
     *
     * @throws AuthorizationException Leading di luar cakupan akun.
     */
    public static function untuk(array $pengguna, array $nilai): array
    {
        $saring = self::saringan($nilai);
        $kegiatan = BukuDokumen::tahunAktif(app(KegiatanRepository::class)->semua());
        $leadings = BukuDokumen::leadingUntuk($pengguna, $kegiatan);
        if ($saring['leading'] !== '') {
            $leadings = array_values(array_filter($leadings, fn (array $l) => $l['kode'] === $saring['leading']));
            if ($leadings === []) {
                throw new AuthorizationException("Buku leading {$saring['leading']} tidak dapat dibuka akun ini.");
            }
        }

        AnggaranKegiatan::muat($kegiatan);
        $baris = [];
        foreach (BukuDokumen::peta($leadings, $kegiatan) as $buku) {
            if ($buku['jumlah'] === 0 || ($saring['sumber'] !== '' && $buku['sumber'] !== $saring['sumber'])) {
                continue;
            }
            foreach (BukuDokumen::isi($kegiatan, $buku['leading'], $buku['sumber'])['baris'] as $b) {
                $baris[] = [
                    'leading' => $buku['leading'],
                    'nama_leading' => $buku['nama_leading'],
                    'sumber' => $buku['sumber'],
                    'no' => $b['no'],
                    'id' => $b['id'],
                    'nama_kegiatan' => $b['nama_kegiatan'],
                    'kode_rktm' => $b['kode_rktm'],
                    'bulan' => $b['bulan'],
                    'total' => $b['total'],
                    'status' => $b['status']->value,
                    'label_status' => $b['status']->label(),
                    'draf' => $b['draf'],
                    'halaman' => $b['halaman'],
                    'tautan' => route('kegiatan.pratinjau', ['kegiatan' => $b['id'], 'sumber' => $buku['sumber']]),
                    'tautan_buku' => route('buku.index', ['leading' => $buku['leading'], 'sumber' => $buku['sumber'], 'kegiatan' => $b['id']]),
                ];
            }
        }

        $baris = array_values(array_filter($baris, fn (array $b) => ($saring['status'] === [] || in_array($b['status'], $saring['status'], true))
            && ($saring['draf'] === '' || $b['draf'] === ($saring['draf'] === 'ya'))
            && ($saring['cari'] === '' || str_contains(mb_strtolower($b['nama_kegiatan'].' '.$b['kode_rktm']), $saring['cari']))));

        return [
            'tahun' => StubData::tahunAktif()['tahun'],
            'saringan' => $saring,
            'leading' => array_map(fn (array $l) => ['kode' => $l['kode'], 'nama_unit' => $l['nama_unit'], 'aktif' => $l['is_active']], $leadings),
            'jumlah' => count($baris),
            'jumlah_kegiatan' => count(array_unique(array_column($baris, 'id'))),
            'total' => array_sum(array_column($baris, 'total')),
            'draf' => count(array_filter($baris, fn (array $b) => $b['draf'])),
            'per_status' => array_merge(array_fill_keys(array_column(StatusKegiatan::cases(), 'value'), 0), array_count_values(array_column($baris, 'status'))),
            'kegiatan' => $baris,
        ];
    }
}
