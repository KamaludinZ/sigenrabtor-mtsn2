<?php

namespace App\Support\Kegiatan;

use App\Contracts\MatrikJadwalRepository;
use App\Enums\StatusKegiatan;
use App\Support\Stub\StubData;
use Carbon\CarbonImmutable;

/**
 * Data Matrik & Jadwal satu kegiatan (PRD F10) untuk endpoint `kegiatan.matrik_jadwal` dan halaman Matrik & Jadwal:
 * acuan bulan dari Data Kegiatan (bulan pelaksanaan, persiapan sebulan sebelumnya, bulan batas laporan — `acuan()`,
 * `bulanLaporan()`), matrik tahapan per tahapan (butir, bulan bertanda, jumlah tanda, lengkap) & jadwal (nomor, uraian,
 * bulan, mulai–sampai, waktu terbaca tahun aktif, baris baku) dari MatrikJadwalRepository, serta temuan KonsistensiBulan.
 * Saat ini dilayani repositori tiruan (respons tiruan); backend cukup mengganti binding repositori.
 */
final class DataMatrikJadwal
{
    /**
     * Bulan batas laporan di tahun anggaran $tahun; laporan yang jatuh di tahun berikutnya ditandai Desember, tanggal
     * kosong/tidak sah → null.
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function bulanLaporan(array $kegiatan, int $tahun): ?int
    {
        $tanggal = $kegiatan['tanggal_laporan'] ?? null;
        if (! is_string($tanggal) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return null;
        }
        $laporan = CarbonImmutable::parse($tanggal);

        return $laporan->year > $tahun ? 12 : $laporan->month;
    }

    /**
     * Bulan acuan isi otomatis & usulan jadwal: persiapan sebulan sebelum pelaksanaan, bulan pelaksanaan, laporan (bulan
     * batas laporan atau bulan pelaksanaan terakhir). Null bila bulan pelaksanaan belum diisi.
     *
     * @param  list<int>  $pelaksanaan  Bulan pelaksanaan urut naik (DataPelaksanaan::$bulan).
     * @return array{persiapan: int, pelaksanaan: list<int>, laporan: int}|null
     */
    public static function acuan(array $pelaksanaan, ?int $laporan): ?array
    {
        if ($pelaksanaan === []) {
            return null;
        }

        return ['persiapan' => max(1, $pelaksanaan[0] - 1), 'pelaksanaan' => $pelaksanaan, 'laporan' => $laporan ?? end($pelaksanaan)];
    }

    /**
     * @param  array<string, mixed>  $kegiatan  Data KegiatanRepository.
     * @return array<string, mixed>
     */
    public static function untuk(array $kegiatan): array
    {
        $id = (int) $kegiatan['id'];
        $tahun = StubData::tahunAktif()['tahun'];
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
        $pelaksanaan = DataPelaksanaan::dari($kegiatan);
        $laporan = self::bulanLaporan($kegiatan, $tahun);
        $repo = app(MatrikJadwalRepository::class);
        $matrik = $repo->matrik($id);
        $jadwal = $repo->jadwal($id);
        $konsistensi = new KonsistensiBulan($pelaksanaan->bulan, $laporan, $matrik->butir, $jadwal->baris);

        return [
            'kegiatan_id' => $id,
            'nama_kegiatan' => (string) ($kegiatan['nama_kegiatan'] ?? ''),
            'tahun' => $tahun,
            'status' => $status->value,
            'dapat_diubah' => $status->dapatDiedit(),
            'pelaksanaan' => [
                'bulan' => $pelaksanaan->bulan,
                'teks_bulan' => $pelaksanaan->teksBulan($tahun),
                'tanggal_mulai' => $pelaksanaan->mulai?->toDateString(),
                'tanggal_selesai' => $pelaksanaan->selesai()?->toDateString(),
                'tanggal_laporan' => is_string($kegiatan['tanggal_laporan'] ?? null) ? $kegiatan['tanggal_laporan'] : null,
                'bulan_laporan' => $laporan,
            ],
            'acuan' => self::acuan($pelaksanaan->bulan, $laporan),
            'matrik' => [
                'tahapan' => array_map(fn (string $kunci, string $label) => [
                    'kunci' => $kunci,
                    'label' => $label,
                    'butir' => $matrik->butir[$kunci],
                    'bulan' => $matrik->bulanTahapan($kunci),
                ], array_keys(MatrikTahapan::TAHAPAN), MatrikTahapan::TAHAPAN),
                'jumlah_tanda' => $matrik->jumlahTanda(),
                'lengkap' => $matrik->lengkap(),
            ],
            'jadwal' => [
                'baris' => array_map(fn (array $b, int $i) => [
                    'nomor' => $i + 1,
                    'uraian' => $b['uraian'],
                    'bulan' => $b['bulan'],
                    'mulai' => $b['bulan'][0] ?? null,
                    'sampai' => $b['bulan'] === [] ? null : end($b['bulan']),
                    'waktu' => $jadwal->waktu($i, $tahun),
                    'baku' => JadwalKegiatan::uraianBaku($b['uraian']),
                ], $jadwal->baris, array_keys($jadwal->baris)),
                'jumlah_terisi' => $jadwal->jumlahTerisi(),
                'lengkap' => $jadwal->lengkap(),
                'maks_baris' => JadwalKegiatan::MAKS_BARIS,
            ],
            'konsistensi' => [
                'temuan' => $konsistensi->temuan(),
                'jumlah_peringatan' => $konsistensi->jumlahPeringatan(),
            ],
        ];
    }
}
