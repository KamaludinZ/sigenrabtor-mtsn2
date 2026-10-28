<?php

namespace App\Repositories;

use App\Contracts\DataPaguRepository;
use App\Enums\SumberDana;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data Pagu di PostgreSQL (data_pagu + data_pagu_sumber), padanan StubDataPaguRepository dengan bentuk data yang sama:
 * `pagu` berkunci semua SumberDana (null = sumber tanpa pagu), urut nama tanpa membedakan huruf besar/kecil. Nama
 * ganda dalam satu tahun ditolak database (indeks unik data_pagu_nama_unik) selain oleh aturan halaman Data Pagu.
 */
class EloquentDataPaguRepository implements DataPaguRepository
{
    public function semua(int $tahun): array
    {
        $baris = DB::table('data_pagu')->where('tahun_anggaran', $tahun)->orderByRaw('lower(nama_kegiatan)')->orderBy('id')->get();

        return $this->denganPagu($baris->all());
    }

    public function temukan(string $id): ?array
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return $this->denganPagu(DB::table('data_pagu')->where('id', $id)->get()->all())[0] ?? null;
    }

    public function simpan(array $data, ?string $id = null): string
    {
        return DB::transaction(function () use ($data, $id) {
            $lama = $id !== null ? $this->temukan($id) : null;
            $id = $lama['id'] ?? (string) Str::uuid();
            $waktu = now()->format('Y-m-d H:i:s.uP');
            $baris = [
                'tahun_anggaran' => (int) $data['tahun'],
                'nama_kegiatan' => (string) $data['nama_kegiatan'],
                'kode_leading' => ($data['leading'] ?? null) ?: null,
                'asal' => $data['asal'] ?? ($lama['asal'] ?? 'manual'),
                'updated_at' => $waktu,
            ];

            if ($lama) {
                DB::table('data_pagu')->where('id', $id)->update($baris);
            } else {
                DB::table('data_pagu')->insert(['id' => $id, 'created_at' => $waktu] + $baris);
            }

            DB::table('data_pagu_sumber')->where('data_pagu_id', $id)->delete();
            foreach (SumberDana::urutan() as $sumber) {
                $pagu = $data['pagu'][$sumber] ?? null;
                if ($pagu !== null) {
                    DB::table('data_pagu_sumber')->insert(['data_pagu_id' => $id, 'sumber_dana' => $sumber, 'pagu' => (int) $pagu]);
                }
            }

            return $id;
        });
    }

    public function hapus(string $id): void
    {
        if (Str::isUuid($id)) {
            DB::table('data_pagu')->where('id', $id)->delete();
        }
    }

    /**
     * @param  list<object>  $baris
     * @return list<array<string, mixed>>
     */
    private function denganPagu(array $baris): array
    {
        $pagu = [];
        foreach (DB::table('data_pagu_sumber')->whereIn('data_pagu_id', array_column($baris, 'id'))->get() as $p) {
            $pagu[$p->data_pagu_id][$p->sumber_dana] = (int) $p->pagu;
        }

        return array_map(fn (object $b) => [
            'id' => $b->id,
            'tahun' => (int) $b->tahun_anggaran,
            'nama_kegiatan' => $b->nama_kegiatan,
            'leading' => $b->kode_leading,
            'pagu' => array_map(fn (string $s) => $pagu[$b->id][$s] ?? null, array_combine(SumberDana::urutan(), SumberDana::urutan())),
            'asal' => $b->asal,
            'dibuat_pada' => self::waktu($b->created_at),
            'diperbarui_pada' => self::waktu($b->updated_at),
        ], $baris);
    }

    private static function waktu(?string $waktu): ?string
    {
        return $waktu === null ? null : CarbonImmutable::parse($waktu)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s.uP');
    }
}
