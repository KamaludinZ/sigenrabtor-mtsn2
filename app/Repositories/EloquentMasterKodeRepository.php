<?php

namespace App\Repositories;

use App\Contracts\MasterKodeRepository;
use App\Support\Stub\StubMasterKodeRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

/**
 * Master kode per tahun di PostgreSQL (kode_sk, kode_iksk, kode_rktm, kode_edm, komponen_edm, budaya_mutu), padanan
 * StubMasterKodeRepository dengan bentuk data yang sama: baris urut `urutan` (urutan berkas), komponen EDM urut huruf,
 * budaya mutu urut nomor. Master satu jenis × tahun dibaca sekali per permintaan (MasterKode dipanggil berulang saat
 * render & validasi); tahun tanpa baris = belum diunggah (daftar kosong). `ganti()` mengganti bagian master satu tahun
 * dalam satu transaksi (cache dibuang); `catatUnggahan()`/`unggahan()` memakai tabel master_kode_unggahan.
 */
class EloquentMasterKodeRepository implements MasterKodeRepository
{
    /** @var array<string, mixed> "jenis:tahun" → hasil. */
    private array $cache = [];

    public function tahunTersedia(): array
    {
        $tahun = DB::table('kode_sk')->select('tahun_anggaran')
            ->union(DB::table('kode_iksk')->select('tahun_anggaran'))
            ->union(DB::table('kode_rktm')->select('tahun_anggaran'))
            ->union(DB::table('kode_edm')->select('tahun_anggaran'))
            ->union(DB::table('budaya_mutu')->select('tahun_anggaran'))
            ->pluck('tahun_anggaran')->map(fn ($t) => (int) $t)->unique()->sort()->values()->all();

        return $tahun;
    }

    public function kodeSk(int $tahun): array
    {
        return $this->baris('kode_sk', $tahun, ['kode', 'uraian']);
    }

    public function kodeIksk(int $tahun): array
    {
        return $this->baris('kode_iksk', $tahun, ['kode', 'kode_sk', 'uraian']);
    }

    public function kodeRktm(int $tahun): array
    {
        return $this->baris('kode_rktm', $tahun, ['kode', 'snp', 'kegiatan', 'sub_kegiatan']);
    }

    public function kodeEdm(int $tahun): array
    {
        return $this->baris('kode_edm', $tahun, ['kode', 'komponen', 'uraian']);
    }

    public function komponenEdm(int $tahun): array
    {
        return $this->cache["komponen_edm:{$tahun}"] ??= DB::table('komponen_edm')->where('tahun_anggaran', $tahun)
            ->orderBy('huruf')->pluck('nama', 'huruf')->all();
    }

    public function budayaMutu(int $tahun): array
    {
        return $this->cache["budaya_mutu:{$tahun}"] ??= DB::table('budaya_mutu')->where('tahun_anggaran', $tahun)
            ->orderBy('nomor')->get(['nomor', 'nama'])->map(fn ($b) => ['nomor' => (int) $b->nomor, 'nama' => $b->nama])->all();
    }

    public function ganti(int $tahun, array $master): void
    {
        if (($asing = array_diff(array_keys($master), StubMasterKodeRepository::BAGIAN)) !== []) {
            throw new InvalidArgumentException('Bagian master tidak dikenal: '.implode(', ', $asing).'.');
        }

        try {
            DB::transaction(function () use ($tahun, $master) {
                // IKSK dihapus sebelum SK-nya dan disisipkan sesudahnya (FK IKSK → SK tertunda sampai commit).
                foreach (['kode_iksk', 'kode_sk', 'kode_rktm', 'kode_edm', 'komponen_edm', 'budaya_mutu'] as $tabel) {
                    if (array_key_exists($tabel, $master)) {
                        DB::table($tabel)->where('tahun_anggaran', $tahun)->delete();
                    }
                }
                foreach (['kode_sk', 'kode_iksk', 'kode_rktm', 'komponen_edm', 'kode_edm', 'budaya_mutu'] as $tabel) {
                    if (! array_key_exists($tabel, $master)) {
                        continue;
                    }
                    $baris = match ($tabel) {
                        'komponen_edm' => array_map(fn (string $huruf, string $nama) => ['huruf' => $huruf, 'nama' => $nama], array_keys($master[$tabel]), $master[$tabel]),
                        'budaya_mutu' => array_map(fn (array $b) => ['nomor' => (int) $b['nomor'], 'nama' => $b['nama']], $master[$tabel]),
                        default => array_map(fn (array $b, int $i) => ['urutan' => $i + 1] + $b, array_values($master[$tabel]), array_keys(array_values($master[$tabel]))),
                    };
                    foreach (array_chunk(array_map(fn (array $b) => ['tahun_anggaran' => $tahun] + $b, $baris), 500) as $potong) {
                        DB::table($tabel)->insert($potong);
                    }
                }
            });
        } catch (QueryException $e) {
            throw new RuntimeException("Master kode TA {$tahun} gagal disimpan ke database — master lama tetap.", previous: $e);
        } finally {
            $this->cache = [];
        }
    }

    public function catatUnggahan(array $unggahan): string
    {
        $id = (string) Str::uuid();
        DB::table('master_kode_unggahan')->insert([
            'id' => $id,
            'tahun_anggaran' => $unggahan['tahun'],
            'cakupan' => $unggahan['cakupan'],
            'nama_berkas' => $unggahan['nama_berkas'],
            'ringkasan' => json_encode($unggahan['ringkasan'] === [] ? new stdClass : $unggahan['ringkasan']),
            'user_id' => $unggahan['user_id'],
            'pengguna' => $unggahan['pengguna'],
            'created_at' => now()->format('Y-m-d H:i:s.uP'),
        ]);

        return $id;
    }

    public function unggahan(int $tahun): array
    {
        return DB::table('master_kode_unggahan')->where('tahun_anggaran', $tahun)->orderByDesc('created_at')->get()
            ->map(fn (object $u) => [
                'id' => $u->id, 'tahun' => (int) $u->tahun_anggaran, 'cakupan' => $u->cakupan, 'nama_berkas' => $u->nama_berkas,
                'ringkasan' => json_decode($u->ringkasan, true), 'user_id' => $u->user_id === null ? null : (int) $u->user_id, 'pengguna' => $u->pengguna,
                'waktu' => CarbonImmutable::parse($u->created_at)->setTimezone(config('app.timezone'))->format('Y-m-d\\TH:i:s.uP'),
            ])->all();
    }

    /**
     * @param  list<string>  $kolom
     * @return list<array<string, string>>
     */
    private function baris(string $tabel, int $tahun, array $kolom): array
    {
        return $this->cache["{$tabel}:{$tahun}"] ??= DB::table($tabel)->where('tahun_anggaran', $tahun)
            ->orderBy('urutan')->orderBy('id')->get($kolom)->map(fn ($b) => (array) $b)->all();
    }
}
