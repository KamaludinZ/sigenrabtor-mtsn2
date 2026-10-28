<?php

namespace App\Repositories;

use App\Contracts\DaftarIsiRepository;
use App\Support\Dokumen\DaftarIsi;
use Illuminate\Support\Facades\DB;

/**
 * Konfigurasi daftar isi di PostgreSQL (tabel daftar_isi_templates), padanan StubDaftarIsiRepository: baris tanpa
 * leading & sumber = bawaan tahun itu (DaftarIsi::BAWAAN bila belum ada), baris berleading × sumber = pengaturan khusus
 * berkunci "leading|sumber". Konfigurasi dibaca selalu lewat DaftarIsi::rapikan (urutan kunci & isinya baku).
 */
class EloquentDaftarIsiRepository implements DaftarIsiRepository
{
    public function bawaan(int $tahun): array
    {
        $konfigurasi = DB::table('daftar_isi_templates')->where('tahun_anggaran', $tahun)->whereNull('kode_leading')->value('konfigurasi');

        return DaftarIsi::rapikan($konfigurasi === null ? DaftarIsi::BAWAAN : json_decode($konfigurasi, true));
    }

    public function khusus(int $tahun): array
    {
        return DB::table('daftar_isi_templates')->where('tahun_anggaran', $tahun)->whereNotNull('kode_leading')->orderBy('id')->get()
            ->mapWithKeys(fn ($b) => ["{$b->kode_leading}|{$b->sumber_dana}" => DaftarIsi::rapikan(json_decode($b->konfigurasi, true))])
            ->all();
    }

    public function simpanBawaan(int $tahun, array $konfigurasi): void
    {
        $this->simpan($tahun, null, null, $konfigurasi);
    }

    public function simpanKhusus(int $tahun, string $leading, string $sumber, array $konfigurasi): void
    {
        $this->simpan($tahun, $leading, $sumber, $konfigurasi);
    }

    public function hapusKhusus(int $tahun, string $leading, string $sumber): void
    {
        DB::table('daftar_isi_templates')->where('tahun_anggaran', $tahun)->where('kode_leading', $leading)->where('sumber_dana', $sumber)->delete();
    }

    /** @param  array<string, mixed>  $konfigurasi */
    private function simpan(int $tahun, ?string $leading, ?string $sumber, array $konfigurasi): void
    {
        $waktu = now()->format('Y-m-d H:i:s.uP');
        $kunci = fn ($q) => $q->where('tahun_anggaran', $tahun)
            ->when($leading === null, fn ($q) => $q->whereNull('kode_leading')->whereNull('sumber_dana'), fn ($q) => $q->where('kode_leading', $leading)->where('sumber_dana', $sumber));

        DB::transaction(function () use ($tahun, $leading, $sumber, $konfigurasi, $waktu, $kunci) {
            $diubah = $kunci(DB::table('daftar_isi_templates'))->update(['konfigurasi' => json_encode($konfigurasi), 'updated_at' => $waktu]);
            if ($diubah === 0) {
                DB::table('daftar_isi_templates')->insert([
                    'tahun_anggaran' => $tahun, 'kode_leading' => $leading, 'sumber_dana' => $sumber,
                    'konfigurasi' => json_encode($konfigurasi), 'created_at' => $waktu, 'updated_at' => $waktu,
                ]);
            }
        });
    }
}
