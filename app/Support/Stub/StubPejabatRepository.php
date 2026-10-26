<?php

namespace App\Support\Stub;

use App\Contracts\PejabatRepository;
use App\Support\MasterKode;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Pejabat penanda tangan tiruan di berkas JSON (disk "local"), berkunci tahun → peran (→ kode leading). Jabatan bawaan
 * mengikuti nama singkat madrasah (Kepala/Ketua Komite) dan jabatan penanda tangan master leading; nama & NIP kosong
 * sampai diisi admin.
 */
class StubPejabatRepository implements PejabatRepository
{
    public function untukTahun(int $tahun): array
    {
        $tersimpan = $this->baca()[(string) $tahun] ?? [];
        $bawaan = self::bawaan();
        $gabung = fn (array $dasar, mixed $isi) => is_array($isi) ? array_merge($dasar, array_intersect_key($isi, $dasar)) : $dasar;

        $leading = [];
        foreach ($bawaan['leading'] as $kode => $dasar) {
            $leading[$kode] = $gabung($dasar, $tersimpan['leading'][$kode] ?? null);
        }

        return [
            'kepala' => $gabung($bawaan['kepala'], $tersimpan['kepala'] ?? null),
            'komite' => $gabung($bawaan['komite'], $tersimpan['komite'] ?? null),
            'leading' => $leading,
        ];
    }

    public function simpan(int $tahun, string $peran, ?string $leading, array $pejabat): void
    {
        if (! in_array($peran, ['kepala', 'komite', 'leading'], true) || ($peran === 'leading' && ! isset(self::bawaan()['leading'][$leading]))) {
            throw new InvalidArgumentException("Peran pejabat [{$peran}] tidak dikenal.");
        }

        $isi = ['jabatan' => (string) $pejabat['jabatan'], 'nama' => $pejabat['nama'] ?: null, 'nip' => $pejabat['nip'] ?: null];
        $semua = $this->baca();
        if ($peran === 'leading') {
            $semua[(string) $tahun]['leading'][$leading] = $isi;
        } else {
            $semua[(string) $tahun][$peran] = $isi;
        }
        $this->disk()->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Jabatan bawaan tiap peran (nama & NIP kosong).
     *
     * @return array{kepala: array{jabatan: string, nama: null, nip: null}, komite: array{jabatan: string, nama: null, nip: null}, leading: array<string, array{jabatan: string, nama: null, nip: null}>}
     */
    public static function bawaan(): array
    {
        $singkat = StubData::madrasah()['nama_singkat'];

        return [
            'kepala' => ['jabatan' => "Kepala {$singkat}", 'nama' => null, 'nip' => null],
            'komite' => ['jabatan' => "Ketua Komite {$singkat}", 'nama' => null, 'nip' => null],
            'leading' => array_map(fn (array $l) => ['jabatan' => $l['jabatan_penanda_tangan'], 'nama' => null, 'nip' => null], array_column(MasterKode::leadings(), null, 'kode')),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function baca(): array
    {
        $isi = $this->disk()->exists($this->berkas()) ? json_decode((string) $this->disk()->get($this->berkas()), true) : null;

        return is_array($isi) ? $isi : [];
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.pejabat_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
