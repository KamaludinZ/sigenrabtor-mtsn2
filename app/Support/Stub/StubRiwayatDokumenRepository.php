<?php

namespace App\Support\Stub;

use App\Contracts\RiwayatDokumenRepository;
use App\Support\Dokumen\RiwayatDokumen;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Riwayat dokumen tiruan: berkas JSON di disk "local" berisi daftar catatan. Selama berkas belum ada, isinya `contoh()`:
 * dokumen fiktif TA 2025 yang kegiatannya tidak tersimpan (final, bernomor, satu berupa arsip bertanda tangan) dan dokumen
 * contoh kegiatan 1–3 TA 2026 (PDF set, Word KAK/TOR, Excel/PDF RAB; bercap DRAF selama belum disetujui). Pembuatnya akun
 * contoh StubPenggunaRepository: dokumen leading lain dibuat akun lain sehingga cakupan akun leading 1.6 terlihat.
 */
class StubRiwayatDokumenRepository implements RiwayatDokumenRepository
{
    public function semua(): array
    {
        $semua = $this->baca();
        usort($semua, fn (array $a, array $b) => [$b['dibuat_pada'], $b['id']] <=> [$a['dibuat_pada'], $a['id']]);

        return $semua;
    }

    public function temukan(string $id): ?array
    {
        return collect($this->baca())->firstWhere('id', $id);
    }

    public function catat(array $entri): array
    {
        $semua = $this->baca();
        $kunci = RiwayatDokumen::kunciVersi($entri);
        $versi = max([0, ...array_map(fn (array $c) => (int) $c['versi'], array_filter($semua, fn (array $c) => RiwayatDokumen::kunciVersi($c) === $kunci))]) + 1;
        $catatan = self::bentuk((string) Str::uuid(), $entri, $versi, now()->format('Y-m-d\TH:i:s.uP'));
        $semua[] = $catatan;
        if (! $this->tulis($semua)) {
            throw new RuntimeException('Riwayat dokumen gagal disimpan — coba lagi.');
        }

        return $catatan;
    }

    /**
     * Contoh riwayat (urutan bebas; `semua()` mengurutkan terbaru dulu). rd-2026-6 diunduh saat total RAB Komite kegiatan 1
     * masih Rp9.250.000 (kini Rp10.000.000) — contoh dokumen yang isinya berbeda bila dibuka ulang (konfirmasi buka ulang).
     *
     * @return list<array<string, mixed>>
     */
    public static function contoh(): array
    {
        $kegiatan = StubKegiatanRepository::contohSemua();
        $leading = ['id' => 1, 'name' => 'Muslimin, M.Pd.'];
        $admin = ['id' => 2, 'name' => 'Koordinator PUSKOM'];
        $wakaKesiswaan = ['id' => 4, 'name' => 'Ahmad Fauzan, S.Pd.'];
        $penjaminMutu = ['id' => 5, 'name' => 'Rina Kusumawati, M.Pd.'];
        $lalu = fn (string $nama, string $kode) => ['nama_kegiatan' => $nama, 'leading' => $kode, 'tahun_anggaran' => 2025, 'status' => 'final'];
        $baris = [
            // [id, kegiatan, jenis, sumber, format, asal, ukuran, status, draf, nomor, waktu, pengguna, ringkas]
            ['rd-2025-1', $lalu('Studi Banding Madrasah Unggulan', '1.2') + ['status' => 'draf'], 'KAK+TOR', 'DIPA', 'docx', 'unduh', 48_210, 'draf', true, null, '2025-03-11 09:20', $wakaKesiswaan, [null, 6, 9]],
            ['rd-2025-2', $lalu('Pelatihan Penulisan Karya Ilmiah Guru', '1.3'), 'RAB', 'DIPA', 'xlsx', 'unduh', 12_480, 'disetujui', false, null, '2025-05-02 13:05', $penjaminMutu, [14_250_000, null, null]],
            ['rd-2025-3', $lalu('Pelatihan Penulisan Karya Ilmiah Guru', '1.3'), 'lengkap', 'DIPA', 'pdf', 'arsip', 1_842_000, 'final', false, 'KAK/012/2025', '2025-06-20 10:40', $penjaminMutu, [14_250_000, 9, 9]],
            ['rd-2025-4', $lalu('Lomba Kreativitas Siswa Akhir Tahun', '1.6'), 'lengkap', 'Komite', 'pdf', 'cetak', 486_300, 'diperiksa', true, null, '2025-09-01 08:15', $leading, [8_600_000, 8, 9]],
            ['rd-2025-5', $lalu('Lomba Kreativitas Siswa Akhir Tahun', '1.6'), 'lengkap', 'Komite', 'pdf', 'arsip', 2_105_600, 'final', false, 'KAK/031/2025', '2025-10-15 14:30', $leading, [8_600_000, 9, 9]],
            ['rd-2026-1', $kegiatan[2], 'RAB', 'DIPA', 'pdf', 'unduh', 64_900, 'draf', true, null, '2026-02-10 10:12', $leading, [5_400_000, null, null]],
            ['rd-2026-2', $kegiatan[2], 'lengkap', 'DIPA', 'pdf', 'cetak', 512_400, 'draf', true, null, '2026-02-12 09:01', $leading, [5_400_000, 7, 9]],
            ['rd-2026-3', $kegiatan[2], 'lengkap', 'Komite', 'pdf', 'cetak', 498_700, 'draf', true, null, '2026-02-12 09:03', $leading, [2_500_000, 7, 9]],
            ['rd-2026-4', $kegiatan[1], 'lengkap', 'Komite', 'pdf', 'cetak', 534_100, 'draf', true, null, '2026-03-02 11:45', $leading, [10_000_000, 8, 9]],
            ['rd-2026-5', $kegiatan[1], 'KAK+TOR', 'Komite', 'docx', 'unduh', 51_330, 'draf', true, null, '2026-03-02 11:47', $leading, [null, 8, 9]],
            ['rd-2026-6', $kegiatan[1], 'RAB', 'Komite', 'xlsx', 'unduh', 13_020, 'draf', true, null, '2026-03-05 08:30', $admin, [9_250_000, null, null]],
            ['rd-2026-7', $kegiatan[3], 'lengkap', 'Komite', 'pdf', 'cetak', 541_800, 'draf', true, null, '2026-04-01 15:20', $leading, [7_950_000, 8, 9]],
        ];

        $versi = [];
        $hasil = [];
        foreach ($baris as [$id, $k, $jenis, $sumber, $format, $asal, $ukuran, $status, $draf, $nomor, $waktu, $pengguna, [$total, $lengkap, $jumlah]]) {
            $k = ['status' => $status] + $k;
            $tahun = (int) ($k['tahun_anggaran'] ?? 2026);
            $nama = RiwayatDokumen::namaBerkas($jenis, (string) $k['nama_kegiatan'], $sumber, $tahun, $draf, $format);
            $entri = RiwayatDokumen::entri($k, $jenis, $sumber, $format, $asal, $nama, $ukuran, $draf, $pengguna, ['total_rab' => $total, 'bagian_lengkap' => $lengkap, 'jumlah_bagian' => $jumlah], $nomor);
            $entri['tahun_anggaran'] = $tahun;
            $kunci = RiwayatDokumen::kunciVersi($entri);
            $versi[$kunci] = ($versi[$kunci] ?? 0) + 1;
            $hasil[] = self::bentuk($id, $entri, $versi[$kunci], CarbonImmutable::parse($waktu, config('app.timezone'))->format('Y-m-d\TH:i:s.uP'));
        }

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $entri
     * @return array<string, mixed>
     */
    private static function bentuk(string $id, array $entri, int $versi, string $waktu): array
    {
        $catatan = ['id' => $id];
        foreach (['kegiatan_id', 'tahun_anggaran', 'kode_leading', 'nama_kegiatan', 'jenis', 'sumber', 'format', 'asal', 'nama_berkas', 'ukuran', 'status', 'bertanda_draf', 'nomor_dokumen'] as $kunci) {
            $catatan[$kunci] = $entri[$kunci] ?? null;
        }

        return $catatan + ['versi' => $versi, 'ringkas' => $entri['ringkas'] ?? ['total_rab' => null, 'bagian_lengkap' => null, 'jumlah_bagian' => null], 'user_id' => $entri['user_id'] ?? null, 'dibuat_oleh' => $entri['dibuat_oleh'] ?? '', 'dibuat_pada' => $waktu, 'isi' => $entri['isi'] ?? null];
    }

    /** @return list<array<string, mixed>> */
    private function baca(): array
    {
        if (! Storage::disk('local')->exists($this->berkas())) {
            return self::contoh();
        }
        $isi = json_decode((string) Storage::disk('local')->get($this->berkas()), true);

        return is_array($isi) ? array_values($isi) : [];
    }

    /** @param  list<array<string, mixed>>  $semua */
    protected function tulis(array $semua): bool
    {
        return Storage::disk('local')->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.riwayat_dokumen_file');
    }
}
