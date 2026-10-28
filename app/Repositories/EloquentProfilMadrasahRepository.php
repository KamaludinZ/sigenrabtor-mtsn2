<?php

namespace App\Repositories;

use App\Contracts\ProfilMadrasahRepository;
use App\Models\ProfilMadrasah;
use App\Support\Stub\StubProfilMadrasahRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Profil madrasah di PostgreSQL (tabel profil_madrasah, satu baris id 1) + logo di disk "local" (folder FOLDER, dilayani
 * rute madrasah.logo), padanan StubProfilMadrasahRepository: selama baris belum ada, profil contoh dipakai; simpan hanya
 * mengubah isian ISIAN yang dikirim (kosong = null); mengganti/menghapus logo menghapus berkas lama.
 */
class EloquentProfilMadrasahRepository implements ProfilMadrasahRepository
{
    public const FOLDER = 'profil';

    public function ambil(): array
    {
        $profil = ProfilMadrasah::query()->find(1);
        if ($profil === null) {
            return StubProfilMadrasahRepository::contoh();
        }

        return [
            ...$profil->only(['nama', 'nama_singkat', 'kota', 'alamat', 'telepon', 'visi', 'logo_path']),
            'diperbarui_pada' => $profil->updated_at?->setTimezone(config('app.timezone'))->toIso8601String(),
        ];
    }

    public function simpan(array $isian): void
    {
        $bersih = array_map(fn ($v) => blank($v) ? null : (string) $v, array_intersect_key($isian, array_flip(StubProfilMadrasahRepository::ISIAN)));
        $this->tulis($bersih);
    }

    public function simpanLogo(UploadedFile $logo): void
    {
        $lama = $this->ambil()['logo_path'];
        $ekstensi = strtolower($logo->guessExtension() ?: $logo->getClientOriginalExtension() ?: 'png');
        $path = $logo->storeAs(self::FOLDER, 'logo-'.Str::random(8).'.'.$ekstensi, 'local');

        $this->tulis(['logo_path' => $path]);
        if ($lama) {
            Storage::disk('local')->delete($lama);
        }
    }

    public function hapusLogo(): void
    {
        $lama = $this->ambil()['logo_path'];
        $this->tulis(['logo_path' => null]);
        if ($lama) {
            Storage::disk('local')->delete($lama);
        }
    }

    /** @param  array<string, string|null>  $ubah */
    private function tulis(array $ubah): void
    {
        $profil = ProfilMadrasah::query()->find(1)
            ?? new ProfilMadrasah(['id' => 1] + array_intersect_key(StubProfilMadrasahRepository::contoh(), array_flip([...StubProfilMadrasahRepository::ISIAN, 'logo_path'])));
        $profil->fill($ubah);
        // Seperti tiruan: setiap simpan memperbarui waktu walau isinya sama.
        $profil->updated_at = $profil->freshTimestamp();
        $profil->save();
    }
}
