<?php

namespace App\Repositories;

use App\Contracts\LeadingRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Leading di PostgreSQL (tabel leadings), padanan StubLeadingRepository: urut `urutan` lalu kode (seperti tiruan); simpan
 * = tambah (urutan bawaan terbesar + 1, aktif) atau ubah berdasarkan kode lama (kode boleh berganti — rujukan kegiatan,
 * akun, Data Pagu, dan pejabat ikut lewat FK ON UPDATE CASCADE). Penolakan database (kode/nama ganda, format, minimal satu
 * leading aktif) dilaporkan sebagai RuntimeException berpesan ramah.
 */
class EloquentLeadingRepository implements LeadingRepository
{
    public function semua(): array
    {
        $semua = DB::table('leadings')->get(['kode', 'nama_unit', 'jabatan_penanda_tangan', 'urutan', 'is_active'])
            ->map(fn (object $l) => [
                'kode' => $l->kode,
                'nama_unit' => $l->nama_unit,
                'jabatan_penanda_tangan' => $l->jabatan_penanda_tangan,
                'urutan' => (int) $l->urutan,
                'is_active' => (bool) $l->is_active,
            ])->all();
        usort($semua, fn (array $a, array $b) => [$a['urutan'], $a['kode']] <=> [$b['urutan'], $b['kode']]);

        return $semua;
    }

    public function simpan(array $data, ?string $kodeLama = null): void
    {
        $waktu = now()->format('Y-m-d H:i:s.uP');

        try {
            DB::transaction(function () use ($data, $kodeLama, $waktu) {
                $lama = $kodeLama === null ? null : DB::table('leadings')->where('kode', $kodeLama)->first();
                $baris = [
                    'kode' => (string) $data['kode'],
                    'nama_unit' => (string) $data['nama_unit'],
                    'jabatan_penanda_tangan' => (string) $data['jabatan_penanda_tangan'],
                    'urutan' => (int) ($data['urutan'] ?? $lama?->urutan ?? ((int) DB::table('leadings')->max('urutan') + 1)),
                    'is_active' => (bool) ($data['is_active'] ?? $lama?->is_active ?? true),
                    'updated_at' => $waktu,
                ];
                if ($lama === null) {
                    DB::table('leadings')->insert($baris + ['created_at' => $waktu]);
                } else {
                    DB::table('leadings')->where('kode', $kodeLama)->update($baris);
                }
            });
        } catch (QueryException $e) {
            throw new RuntimeException('Leading '.($data['kode'] ?? '').' gagal disimpan ke database — periksa kode & nama unit (tidak boleh ganda) dan minimal satu leading aktif.', previous: $e);
        }
    }
}
