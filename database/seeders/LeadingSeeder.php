<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Leading baku MTsN 2 Kota Malang (PRD §3 peran Leading: Waka Kurikulum 1.1 … PMM 1.8) untuk instalasi baru. Idempoten:
 * hanya kode yang belum ada yang ditambahkan — leading yang sudah diubah admin (nama, jabatan, urutan, status) tidak
 * ditimpa.
 */
class LeadingSeeder extends Seeder
{
    /** [kode, nama unit, jabatan penanda tangan] urut tampil. */
    public const BAKU = [
        ['1.1', 'Waka Kurikulum', 'Wakil Kepala Bidang Kurikulum'],
        ['1.2', 'Waka Kesiswaan', 'Wakil Kepala Bidang Kesiswaan'],
        ['1.3', 'Waka Sarpras', 'Wakil Kepala Bidang Sarana dan Prasarana'],
        ['1.4', 'Waka Humas', 'Wakil Kepala Bidang Hubungan Masyarakat'],
        ['1.5', 'Ka. TU', 'Kepala Tata Usaha'],
        ['1.6', 'Tim Penjamin Mutu', 'Ketua Tim Penjamin Mutu'],
        ['1.7', 'Mahad', 'Pengasuh Mahad'],
        ['1.8', 'PMM', 'Koordinator PMM'],
    ];

    public function run(): void
    {
        $waktu = now()->format('Y-m-d H:i:s.uP');
        DB::table('leadings')->insertOrIgnore(array_map(fn (array $l, int $i) => [
            'kode' => $l[0], 'nama_unit' => $l[1], 'jabatan_penanda_tangan' => $l[2], 'urutan' => $i + 1, 'is_active' => true,
            'created_at' => $waktu, 'updated_at' => $waktu,
        ], self::BAKU, array_keys(self::BAKU)));
    }
}
