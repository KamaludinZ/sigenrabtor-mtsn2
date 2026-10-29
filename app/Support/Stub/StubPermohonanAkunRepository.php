<?php

namespace App\Support\Stub;

use App\Contracts\PermohonanAkunRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Permohonan akun tiruan di berkas JSON (disk "local", daftar terbaru dulu). Selama berkas belum ada, isinya `contoh()`:
 * satu permohonan fiktif yang menunggu persetujuan admin (tanpa sandi — fiktif, tidak bisa dipakai masuk).
 */
class StubPermohonanAkunRepository implements PermohonanAkunRepository
{
    /** @return list<array<string, mixed>> */
    public static function contoh(): array
    {
        return [[
            'id' => 'pa-2026-1',
            'nama' => 'Nur Laili Fitriani, S.Pd.',
            'surel' => 'nur.laili@example.com',
            'jabatan' => 'Koordinator Literasi',
            'nip' => null,
            'leadings' => ['1.3'],
            'catatan' => 'Mohon akun untuk menyusun KAK/TOR kegiatan Gerakan Literasi Madrasah.',
            'sandi_hash' => null,
            'status' => 'menunggu',
            'diajukan_pada' => '2026-01-08T09:15:00.000000+07:00',
            'diputuskan_pada' => null,
            'diputuskan_oleh' => null,
            'alasan' => null,
        ]];
    }

    public function semua(): array
    {
        return $this->baca();
    }

    public function temukan(string $id): ?array
    {
        return collect($this->baca())->firstWhere('id', $id);
    }

    public function ajukan(array $permohonan): array
    {
        $baru = ['id' => (string) Str::uuid()] + $permohonan + [
            'status' => 'menunggu',
            'diajukan_pada' => now()->format('Y-m-d\TH:i:s.uP'),
            'diputuskan_pada' => null,
            'diputuskan_oleh' => null,
            'alasan' => null,
        ];
        $baru['status'] = 'menunggu';
        if (! $this->tulis([$baru, ...$this->baca()])) {
            throw new RuntimeException('Permohonan akun gagal dikirim — coba lagi sebentar lagi.');
        }

        return $baru;
    }

    /** @return list<array<string, mixed>> */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            return self::contoh();
        }
        $isi = json_decode((string) $this->disk()->get($this->berkas()), true);

        return is_array($isi) ? array_values($isi) : [];
    }

    /** @param  list<array<string, mixed>>  $semua */
    protected function tulis(array $semua): bool
    {
        return $this->disk()->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.permohonan_akun_file', 'stub/permohonan-akun.json');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
