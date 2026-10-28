<?php

namespace App\Repositories;

use App\Contracts\MatrikJadwalRepository;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Matrik tahapan & jadwal di PostgreSQL (tabel matrik_tahapan & schedules), padanan StubMatrikJadwalRepository.
 * Selama activities.matrik_jadwal_disimpan_pada kosong, kegiatan mendapat butir baku & 6 baris baku; sesudah disimpan,
 * isi dibaca apa adanya — termasuk jadwal atau tahapan yang sengaja dikosongkan. schedules.is_baku dicatat saat simpan
 * (JadwalKegiatan::uraianBaku); bulan_mulai/bulan_selesai dihitung database.
 */
class EloquentMatrikJadwalRepository implements MatrikJadwalRepository
{
    public function matrik(int $kegiatanId): MatrikTahapan
    {
        if (! $this->pernahDisimpan($kegiatanId)) {
            return MatrikTahapan::dari([]);
        }

        $butir = array_fill_keys(array_keys(MatrikTahapan::TAHAPAN), []);
        foreach (DB::table('matrik_tahapan')->where('activity_id', $kegiatanId)->orderBy('urutan')->get() as $baris) {
            $butir[$baris->tahapan][] = ['uraian' => $baris->uraian, 'bulan' => json_decode($baris->bulan, true)];
        }

        return MatrikTahapan::dari(['matrik' => $butir]);
    }

    public function jadwal(int $kegiatanId): JadwalKegiatan
    {
        if (! $this->pernahDisimpan($kegiatanId)) {
            return JadwalKegiatan::dari([]);
        }

        $baris = DB::table('schedules')->where('activity_id', $kegiatanId)->orderBy('urutan')->get()
            ->map(fn ($b) => ['uraian' => $b->uraian, 'bulan' => json_decode($b->bulan, true)])->all();

        return JadwalKegiatan::dari(['jadwal' => $baris]);
    }

    public function simpan(int $kegiatanId, MatrikTahapan $matrik, JadwalKegiatan $jadwal): void
    {
        DB::transaction(function () use ($kegiatanId, $matrik, $jadwal) {
            // UPDATE mengunci baris kegiatan sampai transaksi selesai; updated_at kegiatan sengaja tidak disentuh
            // (matrik & jadwal terpisah dari versi Data Kegiatan).
            $ditandai = DB::table('activities')->where('id', $kegiatanId)
                ->update(['matrik_jadwal_disimpan_pada' => now()->format('Y-m-d H:i:s.uP')]);
            if ($ditandai === 0) {
                throw new RuntimeException("Kegiatan {$kegiatanId} tidak ditemukan.");
            }

            DB::table('matrik_tahapan')->where('activity_id', $kegiatanId)->delete();
            DB::table('schedules')->where('activity_id', $kegiatanId)->delete();

            $baris = [];
            foreach ($matrik->butir as $tahapan => $daftar) {
                foreach (array_values($daftar) as $i => $b) {
                    $baris[] = ['activity_id' => $kegiatanId, 'tahapan' => $tahapan, 'urutan' => $i + 1, 'uraian' => $b['uraian'], 'bulan' => json_encode($b['bulan'])];
                }
            }
            DB::table('matrik_tahapan')->insert($baris);

            DB::table('schedules')->insert(array_map(
                fn (array $b, int $i) => ['activity_id' => $kegiatanId, 'urutan' => $i + 1, 'uraian' => $b['uraian'], 'bulan' => json_encode($b['bulan']), 'is_baku' => JadwalKegiatan::uraianBaku($b['uraian'])],
                $jadwal->baris,
                array_keys($jadwal->baris),
            ));
        });
    }

    private function pernahDisimpan(int $kegiatanId): bool
    {
        return DB::table('activities')->where('id', $kegiatanId)->whereNotNull('matrik_jadwal_disimpan_pada')->exists();
    }
}
