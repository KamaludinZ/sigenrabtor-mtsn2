<?php

namespace App\Support\Stub;

use App\Contracts\ProfilMadrasahRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Profil madrasah tiruan di berkas JSON (disk "local"); logo di folder senama berkas tanpa `.json`. Saat berkas belum
 * ada, profil contoh dipakai: nama, nama singkat, dan kota terisi; alamat, telepon, visi, dan logo masih kosong.
 */
class StubProfilMadrasahRepository implements ProfilMadrasahRepository
{
    public const ISIAN = ['nama', 'nama_singkat', 'kota', 'alamat', 'telepon', 'visi'];

    public function ambil(): array
    {
        $isi = $this->disk()->exists($this->berkas()) ? json_decode((string) $this->disk()->get($this->berkas()), true) : null;

        return array_merge(self::contoh(), is_array($isi) ? array_intersect_key($isi, self::contoh()) : []);
    }

    public function simpan(array $isian): void
    {
        $bersih = array_map(fn ($v) => blank($v) ? null : (string) $v, array_intersect_key($isian, array_flip(self::ISIAN)));

        $this->tulis(array_merge($this->ambil(), $bersih));
    }

    public function simpanLogo(UploadedFile $logo): void
    {
        $profil = $this->ambil();
        $ekstensi = strtolower($logo->guessExtension() ?: $logo->getClientOriginalExtension() ?: 'png');
        $path = $logo->storeAs(Str::beforeLast($this->berkas(), '.json'), 'logo-'.Str::random(8).'.'.$ekstensi, 'local');

        if ($profil['logo_path']) {
            $this->disk()->delete($profil['logo_path']);
        }
        $this->tulis(['logo_path' => $path] + $profil);
    }

    public function hapusLogo(): void
    {
        $profil = $this->ambil();
        if ($profil['logo_path']) {
            $this->disk()->delete($profil['logo_path']);
        }
        $this->tulis(['logo_path' => null] + $profil);
    }

    /**
     * @return array{nama: string, nama_singkat: string, kota: string, alamat: string|null, telepon: string|null, visi: string|null, logo_path: string|null, diperbarui_pada: string|null}
     */
    public static function contoh(): array
    {
        return [
            'nama' => 'Madrasah Tsanawiyah Negeri 2 Kota Malang',
            'nama_singkat' => 'MTsN 2 Kota Malang',
            'kota' => 'Malang',
            'alamat' => null,
            'telepon' => null,
            'visi' => null,
            'logo_path' => null,
            'diperbarui_pada' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $profil
     */
    private function tulis(array $profil): void
    {
        $profil['diperbarui_pada'] = now()->toIso8601String();
        $this->disk()->put($this->berkas(), json_encode(array_intersect_key($profil, self::contoh()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.profil_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
