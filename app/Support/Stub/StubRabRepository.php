<?php

namespace App\Support\Stub;

use App\Contracts\RabRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * RAB tiruan di berkas JSON (disk "local"), satu entri per kegiatan × sumber dana:
 * { "<kegiatanId>": { "<sumber>": { "rincian": [item, …], "termin": [{tanggal, nominal}, …] } } }.
 *
 * Saat berkas belum ada, contoh RAB disiapkan dari StubRab. Berkas format lama (sumber → daftar item langsung)
 * tetap terbaca dan ditulis ulang dalam format baru saat ada perubahan.
 */
class StubRabRepository implements RabRepository
{
    public function rincian(int $kegiatanId, string $sumber): array
    {
        return $this->baca()[$kegiatanId][$sumber]['rincian'] ?? [];
    }

    public function tambahItem(int $kegiatanId, string $sumber, array $item): string
    {
        $semua = $this->baca();
        $id = (string) Str::uuid();

        $semua[$kegiatanId][$sumber] = $this->anggaran($semua, $kegiatanId, $sumber);
        $semua[$kegiatanId][$sumber]['rincian'][] = ['id' => $id] + $item;
        $this->tulis($semua);

        return $id;
    }

    public function ubahItem(int $kegiatanId, string $sumber, string $id, array $item): bool
    {
        $semua = $this->baca();
        $index = $this->indeks($semua[$kegiatanId][$sumber]['rincian'] ?? [], $id);

        if ($index === null) {
            return false;
        }

        $semua[$kegiatanId][$sumber]['rincian'][$index] = ['id' => $id] + $item;
        $this->tulis($semua);

        return true;
    }

    public function hapusItem(int $kegiatanId, string $sumber, string $id): ?array
    {
        $semua = $this->baca();
        $daftar = $semua[$kegiatanId][$sumber]['rincian'] ?? [];
        $index = $this->indeks($daftar, $id);

        if ($index === null) {
            return null;
        }

        $item = $daftar[$index];
        array_splice($daftar, $index, 1);
        $semua[$kegiatanId][$sumber]['rincian'] = $daftar;
        $this->tulis($semua);

        return ['index' => $index, 'item' => $item];
    }

    public function sisipkanItem(int $kegiatanId, string $sumber, int $index, array $item): void
    {
        $semua = $this->baca();
        $anggaran = $this->anggaran($semua, $kegiatanId, $sumber);

        array_splice($anggaran['rincian'], max(0, min($index, count($anggaran['rincian']))), 0, [$item]);
        $semua[$kegiatanId][$sumber] = $anggaran;
        $this->tulis($semua);
    }

    public function termin(int $kegiatanId, string $sumber): array
    {
        return $this->baca()[$kegiatanId][$sumber]['termin'] ?? [];
    }

    public function simpanTermin(int $kegiatanId, string $sumber, array $termin): void
    {
        $semua = $this->baca();

        $semua[$kegiatanId][$sumber] = $this->anggaran($semua, $kegiatanId, $sumber);
        $semua[$kegiatanId][$sumber]['termin'] = array_values(array_map(
            fn (array $t) => ['tanggal' => $t['tanggal'] ?? null, 'nominal' => (int) $t['nominal']],
            $termin,
        ));
        $this->tulis($semua);
    }

    public function sumberTersimpan(int $kegiatanId): array
    {
        $perSumber = $this->baca()[$kegiatanId] ?? [];

        return array_values(array_map('strval', array_keys(array_filter(
            $perSumber,
            fn (array $anggaran) => $anggaran['rincian'] !== [] || $anggaran['termin'] !== [],
        ))));
    }

    public function muat(array $kegiatanIds): void
    {
        // Berkas tiruan dibaca utuh setiap kali; tidak ada yang perlu dimuat lebih dulu.
    }

    /**
     * Entri anggaran satu sumber dana (dibuat kosong bila belum ada).
     *
     * @param  array<int|string, array<string, array{rincian: list<array<string, mixed>>, termin: list<array<string, mixed>>}>>  $semua
     * @return array{rincian: list<array<string, mixed>>, termin: list<array<string, mixed>>}
     */
    private function anggaran(array $semua, int $kegiatanId, string $sumber): array
    {
        return $semua[$kegiatanId][$sumber] ?? ['rincian' => [], 'termin' => []];
    }

    /**
     * @param  list<array<string, mixed>>  $daftar
     */
    private function indeks(array $daftar, string $id): ?int
    {
        $index = array_search($id, array_column($daftar, 'id'), true);

        return $index === false ? null : $index;
    }

    /**
     * @return array<int|string, array<string, array{rincian: list<array<string, mixed>>, termin: list<array<string, mixed>>}>>
     */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            $this->tulis(self::contoh());
        }

        $isi = json_decode((string) $this->disk()->get($this->berkas()), true);

        return is_array($isi) ? array_map(fn ($perSumber) => array_map(self::baku(...), (array) $perSumber), $isi) : [];
    }

    /**
     * Bentuk baku satu entri; format lama berupa daftar item langsung.
     *
     * @param  array<mixed>  $anggaran
     * @return array{rincian: list<array<string, mixed>>, termin: list<array<string, mixed>>}
     */
    private static function baku(array $anggaran): array
    {
        if (array_is_list($anggaran)) {
            return ['rincian' => $anggaran, 'termin' => []];
        }

        return ['rincian' => $anggaran['rincian'] ?? [], 'termin' => $anggaran['termin'] ?? []];
    }

    /**
     * Contoh RAB untuk semua kegiatan contoh beserta termin pembayarannya.
     *
     * @return array<int, array<string, array{rincian: list<array<string, mixed>>, termin: list<array<string, mixed>>}>>
     */
    public static function contoh(): array
    {
        $contoh = [];

        foreach (StubRab::DAFTAR_CONTOH as $kegiatanId => $daftarSumber) {
            foreach ($daftarSumber as $sumber) {
                $contoh[$kegiatanId][$sumber] = [
                    'rincian' => array_map(fn (array $item) => ['id' => (string) Str::uuid()] + $item, StubRab::rincian($kegiatanId, $sumber)),
                    'termin' => StubRab::termin($kegiatanId, $sumber),
                ];
            }
        }

        return $contoh;
    }

    /**
     * @param  array<int|string, array<string, array{rincian: list<array<string, mixed>>, termin: list<array<string, mixed>>}>>  $semua
     */
    private function tulis(array $semua): void
    {
        $this->disk()->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.rab_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
