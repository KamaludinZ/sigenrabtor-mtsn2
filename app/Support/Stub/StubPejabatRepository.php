<?php

namespace App\Support\Stub;

use App\Contracts\PejabatRepository;
use App\Enums\SumberDana;
use App\Support\MasterKode;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Pejabat penanda tangan tiruan di berkas JSON (disk "local"), berkunci tahun → peran (→ kode leading). Jabatan bawaan
 * mengikuti nama singkat madrasah (Kepala/Ketua Komite) dan jabatan penanda tangan master leading; nama & NIP kosong
 * sampai diisi admin. Saat berkas belum ada, contoh fiktif TA 2025 (tahun terkunci) disiapkan agar tampilan per tahun &
 * salin dari tahun lalu bisa dicoba; TA 2026 sengaja kosong.
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

    public function aturanMengetahui(int $tahun): array
    {
        $tersimpan = $this->baca()[(string) $tahun]['mengetahui'] ?? [];
        $bawaan = array_fill_keys(SumberDana::urutan(), true);

        return array_merge($bawaan, array_map('boolval', array_intersect_key(is_array($tersimpan) ? $tersimpan : [], $bawaan)));
    }

    public function simpanAturanMengetahui(int $tahun, array $aturan): void
    {
        $semua = $this->baca();
        $semua[(string) $tahun]['mengetahui'] = array_map('boolval', array_intersect_key($aturan, array_flip(SumberDana::urutan())));
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
     * Contoh fiktif pejabat TA 2025 (nama & NIP rekaan; NIP berformat sah).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function contoh(): array
    {
        return ['2025' => [
            'kepala' => ['jabatan' => 'Kepala MTsN 2 Kota Malang', 'nama' => 'Drs. H. Mahmud Arifin, M.Pd.I.', 'nip' => '196805121994031002'],
            'komite' => ['jabatan' => 'Ketua Komite MTsN 2 Kota Malang', 'nama' => 'H. Sugeng Riyadi, S.E.', 'nip' => null],
            'leading' => [
                '1.1' => ['jabatan' => 'Wakil Kepala Bidang Kurikulum', 'nama' => 'Siti Rohmah, M.Pd.', 'nip' => '197803152005012003'],
                '1.6' => ['jabatan' => 'Ketua Tim Penjamin Mutu', 'nama' => 'Muslimin, M.Pd.', 'nip' => '198001102006041001'],
            ],
        ]];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            return self::contoh();
        }

        $isi = json_decode((string) $this->disk()->get($this->berkas()), true);

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
