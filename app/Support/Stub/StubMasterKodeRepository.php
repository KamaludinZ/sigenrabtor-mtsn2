<?php

namespace App\Support\Stub;

use App\Contracts\MasterKodeRepository;

/**
 * Master kode tiruan dari berkas data/master-{tahun}.php. Tahun tanpa berkas dianggap belum diunggah (daftar kosong).
 */
class StubMasterKodeRepository implements MasterKodeRepository
{
    /** @var array<int, array<string, mixed>> */
    private array $cache = [];

    public function tahunTersedia(): array
    {
        $tahun = array_map(
            fn (string $berkas) => (int) preg_replace('/\D/', '', basename($berkas)),
            glob(__DIR__.'/data/master-*.php') ?: [],
        );
        sort($tahun);

        return $tahun;
    }

    public function kodeSk(int $tahun): array
    {
        return $this->data($tahun)['kode_sk'];
    }

    public function kodeIksk(int $tahun): array
    {
        return $this->data($tahun)['kode_iksk'];
    }

    public function kodeRktm(int $tahun): array
    {
        return $this->data($tahun)['kode_rktm'];
    }

    public function kodeEdm(int $tahun): array
    {
        return array_map(
            fn (array $edm) => ['kode' => $edm['kode'], 'komponen' => $edm['komponen'] ?? substr($edm['kode'], 0, 1), 'uraian' => $edm['uraian']],
            $this->data($tahun)['kode_edm'],
        );
    }

    public function komponenEdm(int $tahun): array
    {
        return $this->data($tahun)['komponen_edm'];
    }

    public function budayaMutu(int $tahun): array
    {
        return $this->data($tahun)['budaya_mutu'];
    }

    /**
     * @return array<string, mixed>
     */
    private function data(int $tahun): array
    {
        if (isset($this->cache[$tahun])) {
            return $this->cache[$tahun];
        }

        $berkas = __DIR__."/data/master-{$tahun}.php";
        $kosong = ['kode_sk' => [], 'kode_iksk' => [], 'kode_rktm' => [], 'kode_edm' => [], 'komponen_edm' => [], 'budaya_mutu' => []];

        return $this->cache[$tahun] = is_file($berkas) ? (require $berkas) + $kosong : $kosong;
    }
}
