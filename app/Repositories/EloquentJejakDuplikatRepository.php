<?php

namespace App\Repositories;

use App\Contracts\JejakDuplikatRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Jejak duplikat di PostgreSQL (tabel duplikat_kegiatan), padanan StubJejakDuplikatRepository: bentuk sama, terbaru dulu
 * (waktu sama → yang dicatat belakangan dulu); hasil hanya satu jejak; catatan dikembalikan berkunci {jenis, teks}.
 */
class EloquentJejakDuplikatRepository implements JejakDuplikatRepository
{
    public function catat(array $jejak): array
    {
        $id = DB::table('duplikat_kegiatan')->insertGetId([
            'sumber_id' => $jejak['sumber_id'],
            'sumber_tahun' => $jejak['sumber_tahun'],
            'hasil_id' => $jejak['hasil_id'],
            'tahun_anggaran' => $jejak['tahun'],
            'catatan' => json_encode($jejak['catatan'], JSON_UNESCAPED_UNICODE),
            'user_id' => $jejak['user_id'],
            'pengguna' => $jejak['pengguna'],
            'dibuat_pada' => now()->format('Y-m-d H:i:s.uP'),
        ]);

        return self::jejak(DB::table('duplikat_kegiatan')->find($id));
    }

    public function untukHasil(int $hasilId): ?array
    {
        $baris = DB::table('duplikat_kegiatan')->where('hasil_id', $hasilId)->first();

        return $baris === null ? null : self::jejak($baris);
    }

    public function semua(int $tahun): array
    {
        return DB::table('duplikat_kegiatan')->where('tahun_anggaran', $tahun)->orderByDesc('dibuat_pada')->orderByDesc('id')->get()->map(self::jejak(...))->all();
    }

    /** @return array<string, mixed> */
    private static function jejak(object $r): array
    {
        return [
            'id' => (int) $r->id,
            'sumber_id' => $r->sumber_id === null ? null : (int) $r->sumber_id,
            'sumber_tahun' => (int) $r->sumber_tahun,
            'hasil_id' => (int) $r->hasil_id,
            'tahun' => (int) $r->tahun_anggaran,
            // jsonb mengurutkan ulang kunci: kembalikan ke bentuk {jenis, teks}.
            'catatan' => array_map(fn (array $c) => ['jenis' => $c['jenis'] ?? null, 'teks' => $c['teks'] ?? null], json_decode($r->catatan, true)),
            'user_id' => $r->user_id === null ? null : (int) $r->user_id,
            'pengguna' => $r->pengguna,
            'dibuat_pada' => CarbonImmutable::parse($r->dibuat_pada)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s.uP'),
        ];
    }
}
