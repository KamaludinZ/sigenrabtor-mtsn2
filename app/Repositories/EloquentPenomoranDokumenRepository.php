<?php

namespace App\Repositories;

use App\Contracts\PenomoranDokumenRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penomoran dokumen di PostgreSQL (padanan StubPenomoranDokumenRepository; migrasi 000047 & 000032): aturan per jenis di
 * `aturan_penomoran` (jenis yang tidak tersimpan = bawaan), waktu terakhir diubah di `pengaturan_format.penomoran_diperbarui_pada`,
 * nomor terakhir di `number_sequences` — `ambilBerikutnya()` lewat fungsi atomik `nomor_dokumen_berikutnya(jenis, tahun)`,
 * `catatPakai()` = upsert GREATEST. Penolakan database → RuntimeException berpesan ramah. Binding masih tiruan.
 */
class EloquentPenomoranDokumenRepository implements PenomoranDokumenRepository
{
    public function aturan(int $tahun): array
    {
        return DB::table('aturan_penomoran')->where('tahun_anggaran', $tahun)->orderBy('jenis')->get()
            ->mapWithKeys(fn (object $r) => [$r->jenis => ['pola' => (string) $r->pola, 'digit' => (int) $r->digit, 'aktif' => (bool) $r->aktif]])
            ->all();
    }

    public function meta(int $tahun): array
    {
        $waktu = DB::table('pengaturan_format')->where('tahun_anggaran', $tahun)->value('penomoran_diperbarui_pada');

        return ['diperbarui_pada' => $waktu === null ? null : CarbonImmutable::parse($waktu)->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s.uP')];
    }

    public function simpanAturan(int $tahun, array $aturan): void
    {
        $waktu = now()->format('Y-m-d H:i:s.uP');
        $this->tulis(function () use ($tahun, $aturan, $waktu) {
            DB::table('aturan_penomoran')->where('tahun_anggaran', $tahun)->delete();
            DB::table('aturan_penomoran')->insert(array_map(
                fn (string $jenis, array $a) => ['tahun_anggaran' => $tahun, 'jenis' => $jenis, 'pola' => $a['pola'], 'digit' => $a['digit'], 'aktif' => $a['aktif'], 'created_at' => $waktu, 'updated_at' => $waktu],
                array_keys($aturan), $aturan,
            ));
            DB::table('pengaturan_format')->upsert(
                ['tahun_anggaran' => $tahun, 'penomoran_diperbarui_pada' => $waktu, 'created_at' => $waktu, 'updated_at' => $waktu],
                ['tahun_anggaran'], ['penomoran_diperbarui_pada', 'updated_at'],
            );
        }, 'Aturan penomoran gagal disimpan — periksa pola & digit, lalu coba lagi.');
    }

    public function terakhir(int $tahun): array
    {
        return DB::table('number_sequences')->where('tahun', $tahun)->pluck('nomor_terakhir', 'jenis')->map(fn ($n) => (int) $n)->all();
    }

    public function catatPakai(int $tahun, string $jenis, int $urut): void
    {
        $this->tulis(fn () => DB::statement(
            'INSERT INTO number_sequences (jenis, tahun, nomor_terakhir, created_at, updated_at) VALUES (?, ?, ?, now(), now())
             ON CONFLICT (jenis, tahun) DO UPDATE SET nomor_terakhir = GREATEST(number_sequences.nomor_terakhir, EXCLUDED.nomor_terakhir), updated_at = now()',
            [$jenis, $tahun, $urut],
        ), 'Nomor dokumen gagal dicatat — coba lagi.');
    }

    public function ambilBerikutnya(int $tahun, string $jenis): int
    {
        return (int) $this->tulis(
            fn () => DB::selectOne('SELECT nomor_dokumen_berikutnya(?, ?) AS urut', [$jenis, $tahun])->urut,
            'Nomor dokumen berikutnya gagal diambil — coba lagi.',
        );
    }

    private function tulis(callable $aksi, string $pesan): mixed
    {
        try {
            return DB::transaction($aksi);
        } catch (QueryException $e) {
            report($e);
            throw new RuntimeException($pesan, previous: $e);
        }
    }
}
