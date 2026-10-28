<?php

namespace App\Support\Stub;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Support\Dokumen\DrafDokumen;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Draf dokumen tiruan: berkas JSON di disk "local" berisi daftar draf. Selama berkas belum ada, isinya contoh satu draf
 * KAK/TOR Komite kegiatan 1 yang disimpan sebelum tempat pelaksanaannya diganti (bagian 5 tampil "berubah sejak draf").
 */
class StubDrafDokumenRepository implements DrafDokumenRepository
{
    /** @var list<array<string, mixed>>|null */
    private ?array $contoh = null;

    public function untukKegiatan(int $kegiatanId): array
    {
        $milik = array_values(array_filter($this->baca(), fn (array $d) => $d['kegiatan_id'] === $kegiatanId));
        usort($milik, fn (array $a, array $b) => strcmp($b['disimpan_pada'], $a['disimpan_pada']));

        return $milik;
    }

    public function temukan(int $kegiatanId, string $jenis, ?string $sumber): ?array
    {
        return collect($this->baca())->first(fn (array $d) => $this->sama($d, $kegiatanId, $jenis, $sumber));
    }

    public function simpan(int $kegiatanId, array $draf): array
    {
        $semua = $this->baca();
        $lama = collect($semua)->search(fn (array $d) => $this->sama($d, $kegiatanId, $draf['jenis'], $draf['sumber']));
        $baru = ['id' => $lama !== false ? $semua[$lama]['id'] : (string) Str::uuid(), 'kegiatan_id' => $kegiatanId] + $draf;
        $baru['disimpan_pada'] = now()->format('Y-m-d\TH:i:s.uP');
        if ($lama !== false) {
            $semua[$lama] = $baru;
        } else {
            $semua[] = $baru;
        }
        $this->tulis($semua);

        return $baru;
    }

    public function hapus(int $kegiatanId, string $jenis, ?string $sumber): bool
    {
        $semua = $this->baca();
        $sisa = array_values(array_filter($semua, fn (array $d) => ! $this->sama($d, $kegiatanId, $jenis, $sumber)));
        if (count($sisa) === count($semua)) {
            return false;
        }
        $this->tulis($sisa);

        return true;
    }

    /**
     * Contoh: draf KAK/TOR Komite kegiatan 1 oleh akun leading 1.6, direkam dari data kegiatan itu dengan tempat
     * pelaksanaan lama.
     *
     * @return list<array<string, mixed>>
     */
    public function contoh(): array
    {
        if ($this->contoh !== null) {
            return $this->contoh;
        }
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        if ($kegiatan === null) {
            return $this->contoh = [];
        }
        $tahun = StubData::tahunAktif()['tahun'];
        $draf = DrafDokumen::rekam(['tempat' => 'Ruang Rapat MTsN 2 Kota Malang'] + $kegiatan, 'KAK+TOR', 'Komite', ['id' => 1, 'name' => 'Muslimin, M.Pd.']);

        return $this->contoh = [['id' => '0d3f6a52-7c1e-4b8a-9f2d-5e6a7b8c9d01'] + $draf + ['disimpan_pada' => "{$tahun}-01-08T10:15:00.000000+07:00"]];
    }

    /** @param  array<string, mixed>  $d */
    private function sama(array $d, int $kegiatanId, string $jenis, ?string $sumber): bool
    {
        return $d['kegiatan_id'] === $kegiatanId && $d['jenis'] === $jenis && $d['sumber'] === $sumber;
    }

    /** @return list<array<string, mixed>> */
    private function baca(): array
    {
        if (! Storage::disk('local')->exists($this->berkas())) {
            return $this->contoh();
        }
        $isi = json_decode((string) Storage::disk('local')->get($this->berkas()), true);

        return is_array($isi) ? $isi : $this->contoh();
    }

    /** @param  list<array<string, mixed>>  $semua */
    private function tulis(array $semua): void
    {
        if (! Storage::disk('local')->put($this->berkas(), json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            throw new RuntimeException('Draf dokumen gagal disimpan — coba lagi.');
        }
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.draf_dokumen_file');
    }
}
