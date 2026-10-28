<?php

namespace App\Repositories;

use App\Contracts\DasarHukumRepository;
use App\Support\Stub\StubDasarHukumRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Dasar hukum & latar belakang baku per tahun di PostgreSQL (tabel dasar_hukum & latar_belakang_baku), padanan
 * StubDasarHukumRepository: tahun "diatur" = punya baris latar_belakang_baku, yang `updated_at`-nya (mikrodetik) menjadi
 * `diperbarui_pada`; simpan = satu transaksi yang mengganti seluruh butir tahun itu (id lama dipertahankan, butir baru
 * UUID, `created_at` butir lama tetap). Penolakan database (aturan isian, butir dobel) dilaporkan sebagai
 * RuntimeException berpesan ramah; data lama tetap utuh.
 */
class EloquentDasarHukumRepository implements DasarHukumRepository
{
    public function untukTahun(int $tahun): array
    {
        $latar = DB::table('latar_belakang_baku')->where('tahun_anggaran', $tahun)->first();
        if ($latar === null) {
            return ['dasar_hukum' => [], ...StubDasarHukumRepository::bawaan(), 'diatur' => false, 'diperbarui_pada' => null];
        }

        return [
            'dasar_hukum' => DB::table('dasar_hukum')->where('tahun_anggaran', $tahun)->orderBy('urutan')->get(['id', 'teks'])
                ->map(fn (object $b) => ['id' => $b->id, 'teks' => $b->teks])->all(),
            'latar_belakang' => $latar->latar_belakang,
            'kalimat_penutup' => $latar->kalimat_penutup,
            'diatur' => true,
            'diperbarui_pada' => CarbonImmutable::parse($latar->updated_at)->setTimezone(config('app.timezone'))->format('Y-m-d\\TH:i:s.uP'),
        ];
    }

    public function simpan(int $tahun, array $data): void
    {
        $waktu = now()->format('Y-m-d H:i:s.uP');

        try {
            DB::transaction(function () use ($tahun, $data, $waktu) {
                DB::table('latar_belakang_baku')->upsert(
                    [['tahun_anggaran' => $tahun, 'latar_belakang' => (string) $data['latar_belakang'], 'kalimat_penutup' => (string) $data['kalimat_penutup'], 'created_at' => $waktu, 'updated_at' => $waktu]],
                    ['tahun_anggaran'],
                    ['latar_belakang', 'kalimat_penutup', 'updated_at'],
                );

                $dibuat = DB::table('dasar_hukum')->where('tahun_anggaran', $tahun)->pluck('created_at', 'id')->all();
                DB::table('dasar_hukum')->where('tahun_anggaran', $tahun)->delete();
                DB::table('dasar_hukum')->insert(array_map(function (array $b, int $i) use ($tahun, $dibuat, $waktu) {
                    $id = filled($b['id'] ?? null) ? (string) $b['id'] : (string) Str::uuid();

                    return ['tahun_anggaran' => $tahun, 'id' => $id, 'urutan' => $i + 1, 'teks' => (string) $b['teks'], 'created_at' => $dibuat[$id] ?? $waktu, 'updated_at' => $waktu];
                }, array_values($data['dasar_hukum']), array_keys(array_values($data['dasar_hukum']))));
            });
        } catch (QueryException $e) {
            throw new RuntimeException("Dasar hukum TA {$tahun} gagal disimpan ke database — periksa isian butir (tidak boleh kosong, dobel, atau memakai penanda yang tidak dikenal).", previous: $e);
        }
    }

    public function teksBawaan(): array
    {
        return StubDasarHukumRepository::bawaan();
    }
}
