<?php

namespace App\Support\Pagu;

use App\Contracts\DataPaguRepository;
use App\Contracts\KegiatanRepository;
use App\Support\Format;
use App\Support\Kegiatan\Impor\PemeriksaImporKegiatan;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Stub\StubData;

/**
 * Aturan bersama Data Pagu: data yang boleh dilihat/dipilih pengguna (admin semua; akun leading hanya leading-nya dan
 * yang belum berleading), pemakaian oleh kegiatan (tertaut lewat `data_pagu_id` atau nama sama pada tahun yang sama —
 * pembanding nama sama dengan impor kegiatan), dan sumber dana untuk mengisi formulir kegiatan.
 */
final class DataPagu
{
    /** Pembanding nama: tanpa beda huruf besar/kecil dan spasi ganda (sama dengan impor kegiatan). */
    public static function kunci(string $nama): string
    {
        return PemeriksaImporKegiatan::kunciNama($nama);
    }

    /**
     * Data pagu tahun aktif yang terlihat oleh $pengguna.
     *
     * @param  array{role: string, leadings: list<string>}  $pengguna
     * @return list<array<string, mixed>>
     */
    public static function untuk(array $pengguna, ?int $tahun = null): array
    {
        $semua = app(DataPaguRepository::class)->semua($tahun ?? StubData::tahunAktif()['tahun']);

        return $pengguna['role'] === 'admin' ? $semua : array_values(array_filter($semua, fn (array $p) => $p['leading'] === null || in_array($p['leading'], $pengguna['leadings'], true)));
    }

    /**
     * Kegiatan yang memakai tiap data pagu: id data pagu → {id, nama} (null = belum dipakai).
     *
     * @param  list<array<string, mixed>>  $dataPagu
     * @param  list<array<string, mixed>>|null  $kegiatan  Bawaan: semua kegiatan.
     * @return array<string, array{id: int, nama: string}|null>
     */
    public static function pemakaian(array $dataPagu, ?array $kegiatan = null): array
    {
        $kegiatan ??= app(KegiatanRepository::class)->semua();
        $tertaut = [];
        $perNama = [];
        foreach ($kegiatan as $k) {
            $ringkas = ['id' => (int) $k['id'], 'nama' => (string) ($k['nama_kegiatan'] ?? '')];
            if (filled($k['data_pagu_id'] ?? null)) {
                $tertaut[$k['data_pagu_id']] ??= $ringkas;
            }
            if (filled($k['nama_kegiatan'] ?? null)) {
                $perNama[TahunKegiatan::dari($k).'|'.self::kunci($k['nama_kegiatan'])] ??= $ringkas;
            }
        }

        $hasil = [];
        foreach ($dataPagu as $p) {
            $hasil[$p['id']] = $tertaut[$p['id']] ?? $perNama[$p['tahun'].'|'.self::kunci($p['nama_kegiatan'])] ?? null;
        }

        return $hasil;
    }

    /**
     * Sumber dana untuk formulir/kegiatan: hanya sumber yang pagunya diisi.
     *
     * @param  array{pagu: array<string, int|null>}  $dataPagu
     * @return list<array{sumber: string, pagu: int}>
     */
    public static function sumberDana(array $dataPagu): array
    {
        return array_values(array_map(fn (string $s) => ['sumber' => $s, 'pagu' => (int) $dataPagu['pagu'][$s]], array_keys(array_filter($dataPagu['pagu'], fn ($v) => $v !== null))));
    }

    /** "1.6 · DIPA Rp6.000.000 · Komite Rp2.500.000" untuk pilihan & ringkasan. */
    public static function keterangan(array $dataPagu): string
    {
        $leading = $dataPagu['leading'] ? MasterKode::leading($dataPagu['leading']) : null;

        return implode(' · ', array_filter([
            $leading ? $leading['kode'].' '.$leading['nama_unit'] : 'Tanpa leading',
            ...array_map(fn (array $s) => $s['sumber'].' '.Format::rupiah($s['pagu']), self::sumberDana($dataPagu)),
        ]));
    }
}
