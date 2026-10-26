<?php

namespace App\Support\Stub;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Matrik & jadwal tiruan di berkas JSON (disk "local"), satu entri per kegiatan: { "<kegiatanId>": { "matrik": …, "jadwal": … } }.
 *
 * Kegiatan yang belum punya entri dibaca dari kunci "matrik"/"jadwal" di data kegiatan (format lama, sebelum ada
 * penyimpanan terpisah) dan pindah ke sini saat disimpan. Saat berkas belum ada, contoh kegiatan 3 disiapkan.
 */
class StubMatrikJadwalRepository implements MatrikJadwalRepository
{
    public function __construct(
        private readonly KegiatanRepository $kegiatan,
    ) {}

    public function matrik(int $kegiatanId): MatrikTahapan
    {
        return MatrikTahapan::dari(['matrik' => $this->entri($kegiatanId)['matrik'] ?? null]);
    }

    public function jadwal(int $kegiatanId): JadwalKegiatan
    {
        return JadwalKegiatan::dari(['jadwal' => $this->entri($kegiatanId)['jadwal'] ?? null]);
    }

    public function simpan(int $kegiatanId, MatrikTahapan $matrik, JadwalKegiatan $jadwal): void
    {
        $semua = $this->baca();
        $semua[$kegiatanId] = ['matrik' => $matrik->butir, 'jadwal' => $jadwal->baris];
        $this->tulis($semua);
    }

    /**
     * Contoh konsistensi bulan (PRD "isi tersalin dari kegiatan lain"): kegiatan 3 berlangsung November dengan batas
     * laporan 27 November, tetapi sebagian matrik & jadwalnya tersalin dari workshop bulan Juli.
     *
     * @return array<int, array{matrik: array<string, list<array{uraian: string, bulan: list<int>}>>, jadwal: list<array{uraian: string, bulan: list<int>}>}>
     */
    public static function contoh(): array
    {
        return [3 => [
            'matrik' => [
                'persiapan' => [['uraian' => 'Rapat dan pembagian job deskripsi', 'bulan' => [10]], ['uraian' => 'Penyerahan anggaran per seksi', 'bulan' => [10]]],
                'pelaksanaan' => [['uraian' => 'Persiapan pelaksanaan', 'bulan' => [7]], ['uraian' => 'Pengawasan kegiatan', 'bulan' => [7]]],
                'evaluasi_pelaporan' => [['uraian' => 'Penyusunan laporan', 'bulan' => [11]], ['uraian' => 'Penyerahan laporan', 'bulan' => [12]]],
            ],
            'jadwal' => [
                ['uraian' => 'Pembentukan kepanitiaan', 'bulan' => [10]],
                ['uraian' => 'Rapat koordinasi panitia', 'bulan' => [10]],
                ['uraian' => 'Persiapan sarana dan bahan kegiatan', 'bulan' => [10, 11]],
                ['uraian' => 'Pelaksanaan workshop kurikulum', 'bulan' => [7]],
                ['uraian' => 'Evaluasi kegiatan', 'bulan' => [11]],
                ['uraian' => 'Penyusunan dan penyerahan laporan', 'bulan' => [12]],
            ],
        ]];
    }

    /**
     * @return array{matrik?: mixed, jadwal?: mixed}
     */
    private function entri(int $kegiatanId): array
    {
        $semua = $this->baca();

        if (isset($semua[$kegiatanId])) {
            return $semua[$kegiatanId];
        }

        $lama = $this->kegiatan->temukan($kegiatanId) ?? [];

        return array_intersect_key($lama, ['matrik' => true, 'jadwal' => true]);
    }

    /**
     * @return array<int|string, array{matrik: mixed, jadwal: mixed}>
     */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            $this->tulis(self::contoh());
        }

        $isi = json_decode((string) $this->disk()->get($this->berkas()), true);

        return is_array($isi) ? $isi : [];
    }

    /**
     * @param  array<int|string, array{matrik: mixed, jadwal: mixed}>  $semua
     */
    private function tulis(array $semua): void
    {
        $this->disk()->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.matrik_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
