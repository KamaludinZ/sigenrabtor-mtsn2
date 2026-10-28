<?php

namespace App\Repositories;

use App\Contracts\NomorHalamanRepository;
use App\Support\Dokumen\NomorHalaman;
use Illuminate\Support\Facades\DB;

/**
 * Aturan nomor halaman di PostgreSQL (tabel page_number_settings, satu baris per tahun), padanan
 * StubNomorHalamanRepository: tahun tanpa baris memakai NomorHalaman::BAWAAN; dibaca selalu lewat NomorHalaman::rapikan.
 */
class EloquentNomorHalamanRepository implements NomorHalamanRepository
{
    public function untuk(int $tahun): array
    {
        $aturan = DB::table('page_number_settings')->where('tahun_anggaran', $tahun)->value('aturan');

        return NomorHalaman::rapikan($aturan === null ? NomorHalaman::BAWAAN : json_decode($aturan, true));
    }

    public function simpan(int $tahun, array $aturan): void
    {
        $waktu = now()->format('Y-m-d H:i:s.uP');
        DB::table('page_number_settings')->upsert(
            [['tahun_anggaran' => $tahun, 'aturan' => json_encode($aturan), 'created_at' => $waktu, 'updated_at' => $waktu]],
            ['tahun_anggaran'],
            ['aturan', 'updated_at'],
        );
    }
}
