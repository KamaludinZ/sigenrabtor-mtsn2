<?php

namespace App\Support\Dokumen;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Enums\SumberDana;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;

/**
 * Daftar dokumen siap cetak halaman Cetak & Unduh (PRD §6 "Cetak per Kegiatan", F12, F16): satu baris per kegiatan tahun
 * aktif × sumber dana yang dipilih (tanpa sumber dana: satu set cover + KAK/TOR) dalam cakupan akun (AksesKegiatan) —
 * keadaan cetak dari StatusCetak (siap/DRAF + alasan pertama), lembar yang tersedia, nama berkas baku
 * (CetakDokumen::namaBerkas), dan ringkas RAB. `saring()` menerapkan saringan halaman (cari nama, leading, sumber,
 * keadaan); `ringkasan()` = jumlah dokumen, siap, DRAF, dan kegiatan.
 */
final class DaftarCetak
{
    /** Keadaan cetak → label. */
    public const KEADAAN = ['siap' => 'Siap dicetak', 'draf' => 'Bercap DRAF'];

    /**
     * @param  array{id?: int|null, role: string, leadings?: list<string>}  $pengguna
     * @return list<array<string, mixed>>
     */
    public static function baris(array $pengguna, ?int $tahun = null): array
    {
        $tahun ??= StubData::tahunAktif()['tahun'];
        $kegiatan = array_values(array_filter(
            AksesKegiatan::saring(app(KegiatanRepository::class)->semua(), $pengguna),
            fn (array $k) => TahunKegiatan::dari($k) === $tahun,
        ));
        AnggaranKegiatan::muat($kegiatan);

        $baris = [];
        foreach ($kegiatan as $k) {
            $anggaran = AnggaranKegiatan::perSumber($k);
            $status = StatusKegiatan::tryFrom((string) ($k['status'] ?? '')) ?? StatusKegiatan::Draf;
            $leading = MasterKode::leading($k['leading'] ?? null);
            foreach (StatusCetak::semua($k) as $cetak) {
                $lembar = $cetak->sumber === null ? ['cover', 'kak'] : ['cover', 'kak', 'rab'];
                $rab = $cetak->sumber !== null ? ($anggaran[$cetak->sumber] ?? null) : null;
                $baris[] = [
                    'kunci' => $k['id'].'|'.($cetak->sumber ?? ''),
                    'kegiatan_id' => (int) $k['id'],
                    'nama' => trim((string) ($k['nama_kegiatan'] ?? '')) ?: 'Tanpa nama',
                    'leading' => (string) ($k['leading'] ?? ''),
                    'nama_leading' => $leading['nama_unit'] ?? null,
                    'sumber' => $cetak->sumber,
                    'status' => $status->value,
                    'label_status' => $status->label(),
                    'keadaan' => $cetak->draf() ? 'draf' : 'siap',
                    'alasan' => array_column($cetak->set->alasan, 'teks'),
                    'lembar' => $lembar,
                    'nama_berkas' => CetakDokumen::namaBerkas($cetak, $lembar),
                    'rincian' => $rab?->jumlahItem() ?? 0,
                    'total' => $rab?->total() ?? 0,
                ];
            }
        }
        $urutan = array_flip(array_map(fn (SumberDana $s) => $s->value, SumberDana::cases()));
        usort($baris, fn (array $a, array $b) => [mb_strtolower($a['nama']), $urutan[$a['sumber']] ?? -1] <=> [mb_strtolower($b['nama']), $urutan[$b['sumber']] ?? -1]);

        return $baris;
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @param  array{cari?: string, leading?: string, sumber?: string, keadaan?: string}  $saringan
     * @return list<array<string, mixed>>
     */
    public static function saring(array $baris, array $saringan): array
    {
        $cari = mb_strtolower(trim((string) ($saringan['cari'] ?? '')));

        return array_values(array_filter($baris, fn (array $b) => ($cari === '' || str_contains(mb_strtolower($b['nama']), $cari))
            && (($saringan['leading'] ?? '') === '' || $b['leading'] === $saringan['leading'])
            && (($saringan['sumber'] ?? '') === '' || $b['sumber'] === $saringan['sumber'])
            && (($saringan['keadaan'] ?? '') === '' || $b['keadaan'] === $saringan['keadaan'])));
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return array{jumlah: int, siap: int, draf: int, kegiatan: int}
     */
    public static function ringkasan(array $baris): array
    {
        return [
            'jumlah' => count($baris),
            'siap' => count(array_filter($baris, fn (array $b) => $b['keadaan'] === 'siap')),
            'draf' => count(array_filter($baris, fn (array $b) => $b['keadaan'] === 'draf')),
            'kegiatan' => count(array_unique(array_column($baris, 'kegiatan_id'))),
        ];
    }
}
