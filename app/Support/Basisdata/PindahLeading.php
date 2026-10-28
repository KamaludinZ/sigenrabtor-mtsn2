<?php

namespace App\Support\Basisdata;

use App\Repositories\EloquentLeadingRepository;
use App\Support\Stub\StubLeadingRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sinkronkan leading dari penyimpanan tiruan (berkas JSON) ke PostgreSQL — berbeda dengan PindahStrukturAnggaran,
 * boleh pada database yang sudah berisi (perintah `php artisan sigenrabtor:pindah-leading`): per kode, leading yang belum
 * ada ditambahkan dan yang sudah ada diperbarui (nama unit, jabatan penanda tangan, urutan, status); leading yang hanya ada
 * di database dibiarkan dan dilaporkan (`hanya_database`, mungkin dipakai kegiatan). Dalam satu transaksi, lalu
 * diverifikasi: setiap leading tiruan harus terbaca sama persis lewat EloquentLeadingRepository — selisih membatalkan
 * semuanya. `uji` = jalankan & verifikasi lalu batalkan.
 */
final class PindahLeading
{
    /**
     * @return array{tambah: list<string>, ubah: list<string>, tetap: list<string>, hanya_database: list<string>}
     *
     * @throws RuntimeException Verifikasi berselisih atau database menolak (semua dibatalkan).
     */
    public static function jalankan(bool $uji = false): array
    {
        $hasil = null;
        try {
            DB::transaction(function () use ($uji, &$hasil) {
                $hasil = self::sinkronkan();
                $selisih = self::verifikasi();
                if ($selisih !== []) {
                    throw new RuntimeException("Verifikasi gagal, pemindahan leading dibatalkan:\n- ".implode("\n- ", $selisih));
                }
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                if ($uji) {
                    throw new UjiPindahSelesai;
                }
            });
        } catch (UjiPindahSelesai) {
            // Mode uji: transaksi sengaja dibatalkan sesudah verifikasi lolos.
        }

        return $hasil;
    }

    /** @return array{tambah: list<string>, ubah: list<string>, tetap: list<string>, hanya_database: list<string>} */
    private static function sinkronkan(): array
    {
        $waktu = now()->format('Y-m-d H:i:s.uP');
        $ada = DB::table('leadings')->get()->keyBy('kode');
        $hasil = ['tambah' => [], 'ubah' => [], 'tetap' => [], 'hanya_database' => []];

        foreach ((new StubLeadingRepository)->semua() as $l) {
            $lama = $ada[$l['kode']] ?? null;
            if ($lama === null) {
                DB::table('leadings')->insert($l + ['created_at' => $waktu, 'updated_at' => $waktu]);
                $hasil['tambah'][] = $l['kode'];
            } elseif ([$lama->nama_unit, $lama->jabatan_penanda_tangan, (int) $lama->urutan, (bool) $lama->is_active] !== [$l['nama_unit'], $l['jabatan_penanda_tangan'], $l['urutan'], $l['is_active']]) {
                DB::table('leadings')->where('kode', $l['kode'])->update(['nama_unit' => $l['nama_unit'], 'jabatan_penanda_tangan' => $l['jabatan_penanda_tangan'], 'urutan' => $l['urutan'], 'is_active' => $l['is_active'], 'updated_at' => $waktu]);
                $hasil['ubah'][] = $l['kode'];
            } else {
                $hasil['tetap'][] = $l['kode'];
            }
        }
        $hasil['hanya_database'] = array_values(array_diff($ada->keys()->all(), array_column((new StubLeadingRepository)->semua(), 'kode')));

        return $hasil;
    }

    /** @return list<string> */
    private static function verifikasi(): array
    {
        $db = collect((new EloquentLeadingRepository)->semua())->keyBy('kode');
        $selisih = [];
        foreach ((new StubLeadingRepository)->semua() as $l) {
            if (($db[$l['kode']] ?? null) !== $l) {
                $selisih[] = "Leading {$l['kode']} berbeda di database.";
            }
        }

        return $selisih;
    }
}
