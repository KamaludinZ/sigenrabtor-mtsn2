<?php

namespace App\Repositories;

use App\Contracts\ArsipFinalRepository;
use App\Enums\SumberDana;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Arsip final di PostgreSQL (tabel arsip_final, satu baris per anggaran_kegiatan), padanan StubArsipFinalRepository:
 * bentuk data sama (urut SumberDana, waktu ISO 8601 zona aplikasi); PDF di disk local `arsip-final/{kegiatan}/{sumber}-
 * {acak}.pdf` — berkas lama dihapus setelah baris diganti/dihapus, berkas baru dibuang bila baris gagal disimpan.
 * Sumber dana tanpa baris anggaran_kegiatan → RuntimeException.
 */
class EloquentArsipFinalRepository implements ArsipFinalRepository
{
    public const FOLDER = 'arsip-final';

    public function untukKegiatan(int $kegiatanId): array
    {
        $urut = array_flip(SumberDana::urutan());

        return DB::table('arsip_final')->where('activity_id', $kegiatanId)->get()
            ->sortBy(fn ($r) => $urut[$r->sumber_dana] ?? PHP_INT_MAX)
            ->mapWithKeys(fn ($r) => [$r->sumber_dana => [
                'sumber' => $r->sumber_dana,
                'nama_berkas' => $r->nama_berkas,
                'ukuran' => (int) $r->ukuran,
                'path' => $r->file_path,
                'diunggah_oleh' => $r->nama_pengunggah,
                'diunggah_oleh_id' => $r->diunggah_oleh === null ? null : (int) $r->diunggah_oleh,
                'diunggah_pada' => CarbonImmutable::parse($r->diunggah_pada)->setTimezone(config('app.timezone'))->toIso8601String(),
            ]])->all();
    }

    public function simpan(int $kegiatanId, string $sumber, UploadedFile $berkas, array $pengguna): array
    {
        $anggaran = DB::table('anggaran_kegiatan')->where('activity_id', $kegiatanId)->where('sumber_dana', $sumber)->value('id')
            ?? throw new RuntimeException("Kegiatan {$kegiatanId} tidak punya anggaran sumber dana {$sumber}.");
        $lama = DB::table('arsip_final')->where('anggaran_kegiatan_id', $anggaran)->value('file_path');
        $path = $berkas->storeAs(self::FOLDER."/{$kegiatanId}", Str::slug($sumber).'-'.Str::random(8).'.pdf', 'local');

        try {
            DB::table('arsip_final')->upsert([
                'anggaran_kegiatan_id' => $anggaran,
                'activity_id' => $kegiatanId,
                'sumber_dana' => $sumber,
                'file_path' => $path,
                'nama_berkas' => $berkas->getClientOriginalName(),
                'ukuran' => (int) $berkas->getSize(),
                'diunggah_oleh' => $pengguna['id'] ?? null,
                'nama_pengunggah' => $pengguna['name'],
                'diunggah_pada' => now()->format('Y-m-d H:i:s.uP'),
            ], ['anggaran_kegiatan_id'], ['file_path', 'nama_berkas', 'ukuran', 'diunggah_oleh', 'nama_pengunggah', 'diunggah_pada']);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);

            throw $e;
        }

        if ($lama !== null) {
            Storage::disk('local')->delete($lama);
        }

        return $this->untukKegiatan($kegiatanId)[$sumber];
    }

    public function hapus(int $kegiatanId, string $sumber): void
    {
        $path = DB::table('arsip_final')->where('activity_id', $kegiatanId)->where('sumber_dana', $sumber)->value('file_path');
        if ($path === null) {
            return;
        }

        DB::table('arsip_final')->where('activity_id', $kegiatanId)->where('sumber_dana', $sumber)->delete();
        Storage::disk('local')->delete($path);
    }
}
