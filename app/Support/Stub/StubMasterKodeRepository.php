<?php

namespace App\Support\Stub;

use App\Contracts\MasterKodeRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Master kode tiruan dari berkas data/master-{tahun}.php, ditimpa per bagian oleh master yang disimpan dari unggahan Excel
 * (berkas JSON di disk "local", config `sigenrabtor.stub.master_kode_file`: {master: {tahun: {bagian: baris}},
 * unggahan: [...]}). Tahun tanpa keduanya dianggap belum diunggah (daftar kosong).
 */
class StubMasterKodeRepository implements MasterKodeRepository
{
    /** Bagian master (kunci data & tabel). */
    public const BAGIAN = ['kode_sk', 'kode_iksk', 'kode_rktm', 'kode_edm', 'komponen_edm', 'budaya_mutu'];

    /** @var array<int, array<string, mixed>> */
    private array $cache = [];

    public function tahunTersedia(): array
    {
        $tahun = array_map(
            fn (string $berkas) => (int) preg_replace('/\D/', '', basename($berkas)),
            glob(__DIR__.'/data/master-*.php') ?: [],
        );
        foreach ($this->baca()['master'] as $t => $bagian) {
            if (array_filter((array) $bagian) !== []) {
                $tahun[] = (int) $t;
            }
        }
        $tahun = array_values(array_unique($tahun));
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

    public function ganti(int $tahun, array $master): void
    {
        if (($asing = array_diff(array_keys($master), self::BAGIAN)) !== []) {
            throw new InvalidArgumentException('Bagian master tidak dikenal: '.implode(', ', $asing).'.');
        }

        $semua = $this->baca();
        foreach ($master as $bagian => $baris) {
            $semua['master'][(string) $tahun][$bagian] = $bagian === 'komponen_edm' ? $baris : array_values($baris);
        }
        $this->tulis($semua);
        unset($this->cache[$tahun]);
    }

    public function catatUnggahan(array $unggahan): string
    {
        $id = (string) Str::uuid();
        $semua = $this->baca();
        $semua['unggahan'][] = ['id' => $id] + $unggahan + ['waktu' => now()->format('Y-m-d\\TH:i:s.uP')];
        $this->tulis($semua);

        return $id;
    }

    public function unggahan(int $tahun): array
    {
        return array_values(array_reverse(array_filter($this->baca()['unggahan'], fn (array $u) => (int) $u['tahun'] === $tahun)));
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
        $kosong = array_fill_keys(self::BAGIAN, []);
        $tersimpan = array_intersect_key((array) ($this->baca()['master'][(string) $tahun] ?? []), $kosong);

        return $this->cache[$tahun] = array_merge($kosong, is_file($berkas) ? (require $berkas) : [], $tersimpan);
    }

    /** @return array{master: array<string, array<string, mixed>>, unggahan: list<array<string, mixed>>} */
    private function baca(): array
    {
        $isi = Storage::disk('local')->exists($this->berkas()) ? json_decode((string) Storage::disk('local')->get($this->berkas()), true) : null;

        return ['master' => (array) ($isi['master'] ?? []), 'unggahan' => array_values((array) ($isi['unggahan'] ?? []))];
    }

    /** @param  array<string, mixed>  $semua */
    private function tulis(array $semua): void
    {
        if (! Storage::disk('local')->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            throw new RuntimeException('Master kode gagal ditulis.');
        }
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.master_kode_file', 'stub/master-kode.json');
    }
}
