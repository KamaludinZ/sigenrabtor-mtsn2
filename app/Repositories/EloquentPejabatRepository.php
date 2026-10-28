<?php

namespace App\Repositories;

use App\Contracts\PejabatRepository;
use App\Enums\SumberDana;
use App\Support\Stub\StubPejabatRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pejabat penanda tangan per tahun di PostgreSQL (tabel pejabat_penanda_tangan & aturan_mengetahui), padanan
 * StubPejabatRepository: jabatan bawaan (StubPejabatRepository::bawaan — nama singkat madrasah & master leading aktif)
 * ditimpa baris tersimpan tahun itu; simpan = upsert satu peran (kepala/komite/leading per kode); blok "Mengetahui"
 * bawaan tampil untuk setiap sumber dana sampai diatur.
 */
class EloquentPejabatRepository implements PejabatRepository
{
    public function untukTahun(int $tahun): array
    {
        $bawaan = StubPejabatRepository::bawaan();
        $hasil = $bawaan;
        foreach (DB::table('pejabat_penanda_tangan')->where('tahun_anggaran', $tahun)->get() as $b) {
            $isi = ['jabatan' => $b->jabatan, 'nama' => $b->nama, 'nip' => $b->nip];
            if ($b->peran === 'leading') {
                if (isset($bawaan['leading'][$b->kode_leading])) {
                    $hasil['leading'][$b->kode_leading] = $isi;
                }
            } else {
                $hasil[$b->peran] = $isi;
            }
        }

        return $hasil;
    }

    public function simpan(int $tahun, string $peran, ?string $leading, array $pejabat): void
    {
        if (! in_array($peran, ['kepala', 'komite', 'leading'], true) || ($peran === 'leading' && ! isset(StubPejabatRepository::bawaan()['leading'][$leading]))) {
            throw new InvalidArgumentException("Peran pejabat [{$peran}] tidak dikenal.");
        }

        $kode = $peran === 'leading' ? $leading : null;
        $waktu = now()->format('Y-m-d H:i:s.uP');
        $isi = ['jabatan' => (string) $pejabat['jabatan'], 'nama' => $pejabat['nama'] ?: null, 'nip' => $pejabat['nip'] ?: null, 'updated_at' => $waktu];
        $kunci = fn ($q) => $q->where('tahun_anggaran', $tahun)->where('peran', $peran)
            ->when($kode === null, fn ($q) => $q->whereNull('kode_leading'), fn ($q) => $q->where('kode_leading', $kode));

        DB::transaction(function () use ($kunci, $isi, $tahun, $peran, $kode, $waktu) {
            if ($kunci(DB::table('pejabat_penanda_tangan'))->update($isi) === 0) {
                DB::table('pejabat_penanda_tangan')->insert(['tahun_anggaran' => $tahun, 'peran' => $peran, 'kode_leading' => $kode, 'created_at' => $waktu] + $isi);
            }
        });
    }

    public function leadingTersimpan(int $tahun, string $kode): ?array
    {
        $b = DB::table('pejabat_penanda_tangan')->where('tahun_anggaran', $tahun)->where('peran', 'leading')->where('kode_leading', $kode)->first();

        return $b ? ['jabatan' => $b->jabatan, 'nama' => $b->nama, 'nip' => $b->nip] : null;
    }

    public function aturanMengetahui(int $tahun): array
    {
        $tersimpan = DB::table('aturan_mengetahui')->where('tahun_anggaran', $tahun)->pluck('tampil', 'sumber_dana')->map(fn ($v) => (bool) $v)->all();

        return array_merge(array_fill_keys(SumberDana::urutan(), true), array_intersect_key($tersimpan, array_flip(SumberDana::urutan())));
    }

    public function simpanAturanMengetahui(int $tahun, array $aturan): void
    {
        $baris = [];
        foreach (array_intersect_key($aturan, array_flip(SumberDana::urutan())) as $sumber => $tampil) {
            $baris[] = ['tahun_anggaran' => $tahun, 'sumber_dana' => $sumber, 'tampil' => (bool) $tampil];
        }
        DB::table('aturan_mengetahui')->upsert($baris, ['tahun_anggaran', 'sumber_dana'], ['tampil']);
    }
}
