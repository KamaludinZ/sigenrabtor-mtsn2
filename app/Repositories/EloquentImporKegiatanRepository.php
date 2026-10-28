<?php

namespace App\Repositories;

use App\Contracts\ImporKegiatanRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Impor kegiatan di PostgreSQL (tabel impor_kegiatan & draf_impor_kegiatan), padanan StubImporKegiatanRepository: bentuk
 * sama (waktu ISO bermikrodetik zona aplikasi), baris urut nomor baris; setiap tulis satu transaksi dan rekap dijaga
 * CHECK tabel.
 */
class EloquentImporKegiatanRepository implements ImporKegiatanRepository
{
    public function mulai(array $impor, array $baris): array
    {
        $id = (string) Str::uuid();
        $jumlah = fn (string $status) => count(array_filter($baris, fn (array $b) => $b['status'] === $status));

        DB::transaction(function () use ($id, $impor, $baris, $jumlah) {
            DB::table('impor_kegiatan')->insert([
                'id' => $id, 'tahun_anggaran' => $impor['tahun'], 'jenis' => $impor['jenis'], 'nama_berkas' => $impor['nama_berkas'],
                'lembar' => $impor['lembar'], 'user_id' => $impor['user_id'], 'pengguna' => $impor['pengguna'],
                'jumlah_baris' => count($baris), 'jumlah_siap' => $jumlah('siap'), 'jumlah_dilewati' => $jumlah('dilewati'), 'jumlah_gagal' => $jumlah('gagal'),
                'dibuat_pada' => now()->format('Y-m-d H:i:s.uP'),
            ]);
            foreach (array_chunk($baris, 200) as $potong) {
                DB::table('draf_impor_kegiatan')->insert(array_map(fn (array $b) => [
                    'impor_id' => $id, 'nomor_baris' => $b['nomor_baris'], 'status' => $b['status'],
                    'data' => json_encode($b['data'] === [] ? new \stdClass : $b['data'], JSON_UNESCAPED_UNICODE),
                    'pesan' => json_encode(array_values($b['pesan']), JSON_UNESCAPED_UNICODE),
                    'peringatan' => json_encode(array_values($b['peringatan']), JSON_UNESCAPED_UNICODE),
                ], $potong));
            }
        });

        return $this->temukan($id);
    }

    public function temukan(string $id): ?array
    {
        if (! Str::isUuid($id)) {
            return null;
        }
        $r = DB::table('impor_kegiatan')->where('id', $id)->first();

        return $r === null ? null : [
            'id' => $r->id,
            'tahun' => (int) $r->tahun_anggaran,
            'jenis' => $r->jenis,
            'nama_berkas' => $r->nama_berkas,
            'lembar' => $r->lembar,
            'user_id' => $r->user_id === null ? null : (int) $r->user_id,
            'pengguna' => $r->pengguna,
            'status' => $r->status,
            'jumlah_baris' => (int) $r->jumlah_baris,
            'jumlah_siap' => (int) $r->jumlah_siap,
            'jumlah_dilewati' => (int) $r->jumlah_dilewati,
            'jumlah_gagal' => (int) $r->jumlah_gagal,
            'jumlah_dibuat' => (int) $r->jumlah_dibuat,
            'dibuat_pada' => self::waktu($r->dibuat_pada),
            'selesai_pada' => self::waktu($r->selesai_pada),
        ];
    }

    public function baris(string $id): array
    {
        if (! Str::isUuid($id)) {
            return [];
        }

        return DB::table('draf_impor_kegiatan')->where('impor_id', $id)->orderBy('nomor_baris')->get()->map(fn (object $b) => [
            'nomor_baris' => (int) $b->nomor_baris,
            'status' => $b->status,
            'data' => json_decode($b->data, true),
            'pesan' => json_decode($b->pesan, true),
            'peringatan' => json_decode($b->peringatan, true),
            'kegiatan_id' => $b->kegiatan_id === null ? null : (int) $b->kegiatan_id,
            'diproses_pada' => self::waktu($b->diproses_pada),
        ])->all();
    }

    public function tandaiDibuat(string $id, int $nomor, int $kegiatanId): void
    {
        DB::transaction(function () use ($id, $nomor, $kegiatanId) {
            $baris = DB::table('draf_impor_kegiatan')->where(['impor_id' => $id, 'nomor_baris' => $nomor, 'status' => 'siap'])->lockForUpdate()->first()
                ?? throw new InvalidArgumentException("Baris siap {$nomor} impor {$id} tidak ditemukan.");
            DB::table('draf_impor_kegiatan')->where('id', $baris->id)->update(['kegiatan_id' => $kegiatanId, 'diproses_pada' => now()->format('Y-m-d H:i:s.uP')]);
            if ($baris->kegiatan_id === null) {
                DB::table('impor_kegiatan')->where('id', $id)->increment('jumlah_dibuat');
            }
        });
    }

    public function akhiri(string $id, string $status): array
    {
        if (! in_array($status, ['selesai', 'dibatalkan'], true) || DB::table('impor_kegiatan')->where('id', $id)->update(['status' => $status, 'selesai_pada' => now()->format('Y-m-d H:i:s.uP')]) === 0) {
            throw new InvalidArgumentException("Impor {$id} tidak bisa diakhiri sebagai {$status}.");
        }

        return $this->temukan($id);
    }

    private static function waktu(?string $waktu): ?string
    {
        return $waktu === null ? null : CarbonImmutable::parse($waktu)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s.uP');
    }
}
