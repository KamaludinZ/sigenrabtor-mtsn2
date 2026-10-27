<?php

namespace App\Support\Stub;

use App\Contracts\RiwayatSaranAiRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Riwayat saran AI tiruan di berkas JSON (disk "local"). Saat berkas belum ada, contoh fiktif untuk tiga contoh kegiatan
 * disiapkan (contoh()) agar halaman riwayat bisa dicoba sebelum AI tersambung.
 */
class StubRiwayatSaranAiRepository implements RiwayatSaranAiRepository
{
    public function catat(array $permintaan): string
    {
        $semua = $this->baca();
        $id = (string) Str::uuid();
        $semua[] = [
            'id' => $id,
            'kegiatan_id' => (int) $permintaan['kegiatan_id'],
            'bagian' => $permintaan['bagian'],
            'prompt' => $permintaan['prompt'],
            'instruksi' => $permintaan['instruksi'],
            'penyedia' => $permintaan['penyedia'],
            'user_id' => (int) $permintaan['user_id'],
            'pada' => now()->format('Y-m-d\TH:i:s.uP'),
            'diputuskan_pada' => null,
            'versi' => array_map(fn (array $v) => ['judul' => $v['judul'], 'ciri' => $v['ciri'], 'teks' => $v['teks'], 'status' => 'menunggu', 'teks_dipakai' => null], $permintaan['versi']),
        ];
        $this->tulis($semua);

        return $id;
    }

    public function putuskan(string $id, ?int $versi, ?string $teksDipakai = null): void
    {
        $semua = $this->baca();
        foreach ($semua as &$p) {
            if ($p['id'] !== $id) {
                continue;
            }
            foreach ($p['versi'] as $i => &$v) {
                $dipakai = $i === $versi;
                $v['status'] = $dipakai ? ($teksDipakai !== null && $teksDipakai !== $v['teks'] ? 'diedit' : 'diterima') : 'ditolak';
                $v['teks_dipakai'] = $dipakai && $teksDipakai !== null && $teksDipakai !== $v['teks'] ? $teksDipakai : null;
            }
            unset($v);
            $p['diputuskan_pada'] = now()->format('Y-m-d\TH:i:s.uP');
        }
        unset($p);
        $this->tulis($semua);
    }

    public function semua(): array
    {
        $semua = $this->baca();
        usort($semua, fn (array $a, array $b) => strcmp($b['pada'], $a['pada']));

        return $semua;
    }

    /**
     * Contoh riwayat fiktif tahun aktif: kegiatan 1 (tujuan diterima, sasaran diedit), 2 (latar ditolak), 3 (jadwal
     * menunggu, oleh admin).
     *
     * @return list<array<string, mixed>>
     */
    public static function contoh(): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $versi = fn (array $teks, array $status, array $dipakai = []) => array_map(fn (int $i) => [
            'judul' => 'Versi '.($i + 1), 'ciri' => ['Formal', 'Ringkas', 'Menekankan capaian'][$i], 'teks' => $teks[$i], 'status' => $status[$i], 'teks_dipakai' => $dipakai[$i] ?? null,
        ], array_keys($teks));
        $prompt = fn (string $bagian, string $nama) => "Bagian: {$bagian}\nNama kegiatan: {$nama}\nLeading: Tim Penjamin Mutu\nCatatan konteks: (contoh fiktif)";

        return [
            [
                'id' => 'contoh-saran-1', 'kegiatan_id' => 1, 'bagian' => 'tujuan', 'prompt' => $prompt('Tujuan', 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran'),
                'instruksi' => '', 'penyedia' => null, 'user_id' => 1, 'pada' => "{$tahun}-01-05T09:10:00.000000+07:00", 'diputuskan_pada' => "{$tahun}-01-05T09:12:00.000000+07:00",
                'versi' => $versi([
                    'menghasilkan dokumen kurikulum madrasah yang tersusun lengkap, tepat waktu, dan sesuai kebutuhan madrasah.',
                    'meningkatkan mutu perencanaan pembelajaran melalui penyusunan kurikulum yang partisipatif.',
                    'memastikan perangkat pembelajaran tersusun dan siap dipakai guru.',
                ], ['diterima', 'ditolak', 'ditolak']),
            ],
            [
                'id' => 'contoh-saran-2', 'kegiatan_id' => 1, 'bagian' => 'sasaran', 'prompt' => $prompt('Sasaran', 'Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran'),
                'instruksi' => 'lebih singkat', 'penyedia' => null, 'user_id' => 1, 'pada' => "{$tahun}-01-05T09:20:00.000000+07:00", 'diputuskan_pada' => "{$tahun}-01-05T09:24:00.000000+07:00",
                'versi' => $versi([
                    'guru yang memahami dan menerapkan kurikulum madrasah dalam pembelajaran.',
                    'dokumen kurikulum yang lengkap dan siap disahkan.',
                ], ['ditolak', 'diedit'], [1 => 'dokumen kurikulum madrasah yang lengkap, disahkan, dan siap diterapkan.']),
            ],
            [
                'id' => 'contoh-saran-3', 'kegiatan_id' => 2, 'bagian' => 'latar_belakang', 'prompt' => $prompt('Latar belakang', 'Supervisi Akademik dan Refleksi Pembelajaran'),
                'instruksi' => '', 'penyedia' => null, 'user_id' => 1, 'pada' => "{$tahun}-01-06T10:00:00.000000+07:00", 'diputuskan_pada' => "{$tahun}-01-06T10:03:00.000000+07:00",
                'versi' => $versi([
                    'Supervisi akademik diselenggarakan untuk memastikan pembelajaran berjalan sesuai standar.',
                    'Refleksi pembelajaran menjadi kebutuhan agar guru terus memperbaiki praktik mengajarnya.',
                    'Peningkatan mutu pembelajaran menuntut pendampingan guru secara berkala.',
                ], ['ditolak', 'ditolak', 'ditolak']),
            ],
            [
                'id' => 'contoh-saran-4', 'kegiatan_id' => 3, 'bagian' => 'jadwal', 'prompt' => $prompt('Jadwal kegiatan', 'Review RKJM dan Penyusunan RKTM'),
                'instruksi' => '', 'penyedia' => null, 'user_id' => 2, 'pada' => "{$tahun}-01-07T13:30:00.000000+07:00", 'diputuskan_pada' => null,
                'versi' => $versi([
                    "Pembentukan kepanitiaan — Januari\nRapat koordinasi panitia — Januari\nPelaksanaan kegiatan — Februari\nPenyusunan dan penyerahan laporan — Maret",
                    "Rapat koordinasi panitia — Januari\nPelaksanaan kegiatan — Februari\nPenyusunan dan penyerahan laporan — Maret",
                ], ['menunggu', 'menunggu']),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function baca(): array
    {
        if (! Storage::disk('local')->exists($this->berkas())) {
            return self::contoh();
        }
        $isi = json_decode((string) Storage::disk('local')->get($this->berkas()), true);

        return is_array($isi) ? $isi : [];
    }

    /** @param  list<array<string, mixed>>  $semua */
    private function tulis(array $semua): void
    {
        Storage::disk('local')->put($this->berkas(), json_encode(array_values($semua), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.riwayat_saran_ai_file');
    }
}
