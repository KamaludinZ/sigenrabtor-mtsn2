<?php

namespace App\Support\Pemeriksaan;

use App\Enums\StatusKegiatan;
use App\Support\Dokumen\SusunanKak;
use App\Support\MasterKode;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\StatusPagu;
use App\Support\Stub\StubPengaturan;

/**
 * Baris halaman Pemeriksaan (PRD §16, F17–F18): kegiatan tahun aktif dikelompokkan per status alur mutu, dengan pembuat,
 * waktu pengajuan/perubahan status terakhir (riwayat_status), RAB per sumber dana, dan jumlah isian dokumen yang kurang.
 * Antrean "Perlu diperiksa" diurutkan dari pengajuan terlama (yang paling lama menunggu lebih dulu).
 */
final class AntreanPemeriksaan
{
    /** Tab halaman: status → label (urutan = urutan alur kerja admin). */
    public const TAB = [
        'diperiksa' => 'Perlu diperiksa',
        'dikembalikan' => 'Dikembalikan',
        'disetujui' => 'Disetujui',
        'final' => 'Final',
        'draf' => 'Belum diajukan',
    ];

    /** Keterangan singkat tiap tab. */
    public const KETERANGAN = [
        'diperiksa' => 'Diajukan leading dan menunggu diperiksa — setujui atau kembalikan dengan catatan.',
        'dikembalikan' => 'Sedang diperbaiki leading di Data Kegiatan, lalu diajukan ulang.',
        'disetujui' => 'Siap dicetak dan ditandatangani; menunggu arsip PDF final dari leading.',
        'final' => 'Arsip final tersimpan dan data terkunci; hanya admin yang dapat membuka kunci.',
        'draf' => 'Masih disusun leading dan belum diajukan untuk diperiksa.',
    ];

    /**
     * @param  list<array<string, mixed>>  $kegiatan  Kegiatan tahun yang dipantau.
     * @return list<array{id: int, nama: string, leading: string|null, nama_leading: string|null, pembuat: string|null, status: StatusKegiatan, diajukan: array{oleh: string, pada: string}|null, terakhir: array{aksi: string, oleh: string, pada: string}|null, anggaran: list<array{sumber: string, total: int, pagu: StatusPagu}>, kurang: int}>
     */
    public static function baris(array $kegiatan): array
    {
        $akun = array_column(StubPengaturan::pengguna(), 'name', 'id');

        return array_map(function (array $k) use ($akun) {
            $riwayat = array_values((array) ($k['riwayat_status'] ?? []));
            $terakhir = $riwayat === [] ? null : end($riwayat);
            $ajukan = collect($riwayat)->filter(fn (array $r) => ($r['aksi'] ?? null) === 'ajukan')->last();
            $leading = MasterKode::leading($k['leading'] ?? null);

            return [
                'id' => (int) $k['id'],
                'nama' => trim((string) ($k['nama_kegiatan'] ?? '')) ?: 'Kegiatan tanpa nama',
                'leading' => $k['leading'] ?? null,
                'nama_leading' => $leading['nama_unit'] ?? null,
                'pembuat' => $akun[$k['user_id'] ?? null] ?? null,
                'status' => StatusKegiatan::tryFrom((string) ($k['status'] ?? '')) ?? StatusKegiatan::Draf,
                'diajukan' => $ajukan ? ['oleh' => (string) $ajukan['oleh'], 'pada' => (string) $ajukan['pada']] : null,
                'terakhir' => $terakhir ? ['aksi' => (string) $terakhir['aksi'], 'oleh' => (string) $terakhir['oleh'], 'pada' => (string) $terakhir['pada']] : null,
                'anggaran' => array_map(fn ($a) => ['sumber' => $a->sumber, 'total' => $a->total(), 'pagu' => $a->keteranganPagu()], AnggaranKegiatan::untuk($k)),
                'kurang' => count(SusunanKak::dari($k)->kurang),
            ];
        }, $kegiatan);
    }

    /**
     * Baris satu tab, terurut: Perlu diperiksa = pengajuan terlama dulu; tab lain = perubahan status terbaru dulu.
     *
     * @param  list<array<string, mixed>>  $baris  Hasil baris().
     * @return list<array<string, mixed>>
     */
    public static function untukTab(array $baris, string $tab): array
    {
        $hasil = array_values(array_filter($baris, fn (array $b) => $b['status']->value === $tab));
        $waktu = fn (array $b) => strtotime(($tab === 'diperiksa' ? $b['diajukan']['pada'] ?? null : null) ?? $b['terakhir']['pada'] ?? '1970-01-01');
        usort($hasil, fn (array $a, array $b) => $tab === 'diperiksa' ? $waktu($a) <=> $waktu($b) : $waktu($b) <=> $waktu($a));

        return $hasil;
    }

    /**
     * Jumlah kegiatan per tab.
     *
     * @param  list<array<string, mixed>>  $baris
     * @return array<string, int>
     */
    public static function jumlah(array $baris): array
    {
        $jumlah = array_fill_keys(array_keys(self::TAB), 0);
        foreach ($baris as $b) {
            $jumlah[$b['status']->value]++;
        }

        return $jumlah;
    }
}
