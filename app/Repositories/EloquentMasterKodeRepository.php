<?php

namespace App\Repositories;

use App\Contracts\MasterKodeRepository;
use Illuminate\Support\Facades\DB;

/**
 * Master kode per tahun di PostgreSQL (kode_sk, kode_iksk, kode_rktm, kode_edm, komponen_edm, budaya_mutu), padanan
 * StubMasterKodeRepository dengan bentuk data yang sama: baris urut `urutan` (urutan berkas), komponen EDM urut huruf,
 * budaya mutu urut nomor. Master satu jenis × tahun dibaca sekali per permintaan (MasterKode dipanggil berulang saat
 * render & validasi); tahun tanpa baris = belum diunggah (daftar kosong).
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
