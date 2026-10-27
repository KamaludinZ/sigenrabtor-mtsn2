<?php

namespace App\Support\Dokumen;

use App\Support\Format;
use App\Support\Kegiatan\DataPelaksanaan;
use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\MekanismePembayaran;
use App\Support\Stub\StubData;
use Carbon\CarbonImmutable;

/**
 * Tempat tanggal tercetak di KAK/TOR & RAB satu kegiatan × sumber dana (PRD aturan 3 & 10): teksnya persis seperti di
 * dokumen — tanggal Indonesia lengkap ("5 Januari 2026", rentang "31 Agustus – 4 September 2026"; termin bergaya Form RAB
 * "17/06/2026") — beserta temuan: di luar tahun anggaran, batas laporan sebelum kegiatan selesai, dan tanggal dokumen
 * sesudah kegiatan dimulai. Kunci = jangkar `#cetak-{kunci}` di pratinjau.
 */
final class TanggalDokumen
{
    /**
     * @param  array<string, mixed>  $kegiatan
     * @return list<array{kunci: string, dokumen: string, bagian: string, teks: string|null, temuan: list<array{jenis: string, pesan: string}>}>
     */
    public static function dari(array $kegiatan, ?string $sumber = null, ?int $tahun = null): array
    {
        $tahun ??= StubData::tahunAktif()['tahun'];
        $pelaksanaan = DataPelaksanaan::dari($kegiatan);
        $mulai = $pelaksanaan->mulai;
        $selesai = $pelaksanaan->selesai();
        $laporan = self::tanggal($kegiatan['tanggal_laporan'] ?? null);
        $dokumen = self::tanggal($kegiatan['tanggal_dokumen'] ?? null);
        $luarTahun = fn (?CarbonImmutable ...$t) => collect($t)->filter()->contains(fn (CarbonImmutable $x) => $x->year !== $tahun)
            ? [['jenis' => 'tahun', 'pesan' => "Di luar Tahun Anggaran {$tahun}."]] : [];

        $tempat = [
            [
                'kunci' => 'kak-pelaksanaan', 'dokumen' => 'KAK', 'bagian' => '4b. Pelaksanaan',
                'teks' => $mulai && $selesai ? JadwalPelaksanaan::teksRentang($mulai, $selesai) : null,
                'temuan' => $luarTahun($mulai, $selesai),
            ],
            [
                'kunci' => 'kak-pelaporan', 'dokumen' => 'KAK', 'bagian' => '4c. Pelaporan',
                'teks' => $laporan ? Format::tanggal($laporan->toDateString()) : null,
                'temuan' => [...$luarTahun($laporan), ...($laporan && $selesai && $laporan->lt($selesai)
                    ? [['jenis' => 'urutan', 'pesan' => 'Batas laporan sebelum kegiatan selesai ('.Format::tanggal($selesai->toDateString()).').']] : [])],
            ],
        ];

        $kota = StubData::madrasah()['kota'];
        foreach (['kak' => 'KAK', 'rab' => 'RAB'] as $kunci => $nama) {
            $tempat[] = [
                'kunci' => "{$kunci}-pengesahan", 'dokumen' => $nama, 'bagian' => 'Lembar pengesahan',
                'teks' => $dokumen ? $kota.', '.Format::tanggal($dokumen->toDateString()) : null,
                'temuan' => [...$luarTahun($dokumen), ...($dokumen && $mulai && $dokumen->gt($mulai)
                    ? [['jenis' => 'urutan', 'pesan' => 'Tanggal dokumen sesudah kegiatan dimulai ('.Format::tanggal($mulai->toDateString()).').']] : [])],
            ];
        }

        $anggaran = $sumber ? (AnggaranKegiatan::perSumber($kegiatan)[$sumber] ?? null) : null;
        foreach ($anggaran ? MekanismePembayaran::dari($anggaran)->barisTabel() : [] as $termin) {
            $tempat[] = [
                'kunci' => 'rab-mekanisme', 'dokumen' => 'RAB', 'bagian' => "Termin {$termin['nomor']}",
                'teks' => $termin['tanggal']?->format('d/m/Y'),
                'temuan' => $luarTahun($termin['tanggal']),
            ];
        }

        return $tempat;
    }

    /**
     * Semua temuan berjenis $jenis (tahun|urutan) beserta tempatnya: "KAK · 4c. Pelaporan: …".
     *
     * @param  list<array{dokumen: string, bagian: string, temuan: list<array{jenis: string, pesan: string}>}>  $tempat
     * @return list<string>
     */
    public static function temuan(array $tempat, string $jenis): array
    {
        $hasil = [];
        foreach ($tempat as $t) {
            foreach ($t['temuan'] as $temuan) {
                if ($temuan['jenis'] === $jenis) {
                    $hasil[] = "{$t['dokumen']} · {$t['bagian']}: {$temuan['pesan']}";
                }
            }
        }

        return $hasil;
    }

    private static function tanggal(?string $nilai): ?CarbonImmutable
    {
        return filled($nilai) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $nilai) ? rescue(fn () => CarbonImmutable::parse($nilai)->startOfDay(), null, false) : null;
    }
}
