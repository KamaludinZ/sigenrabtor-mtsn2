<?php

namespace App\Support\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Support\Teks;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Penerima data pelaksanaan kegiatan di server (PRD F4): hanya isian `ISIAN` yang dirapikan, diperiksa, dan disimpan —
 * isian kegiatan lain tidak disentuh. Perapian sama dengan form Data Kegiatan: tanggal Y-m-d atau kosong, lama & jumlah
 * peserta angka bulat (bukan angka dibiarkan agar ditolak validasi), bulan unik & urut, teks dirapikan spasinya; bila
 * bulan belum dipilih diisi bulan yang dilalui jadwal, dan selama tanggal pelaporan otomatis diisi batas laporan baku
 * (selesai + JadwalPelaksanaan::HARI_PELAPORAN hari). Aturan isian lewat ValidasiKegiatan (antarkolom dari data utuh).
 */
final class SimpanPelaksanaan
{
    /** Isian pelaksanaan yang diterima. */
    public const ISIAN = [
        'tanggal_mulai', 'lama_hari', 'hari_kerja', 'bulan_pelaksanaan', 'tanggal_laporan', 'laporan_otomatis',
        'tempat', 'peserta_jumlah', 'peserta_jenis', 'uraian_pelaksanaan',
    ];

    /**
     * @param  array<string, mixed>  $isian  Isian pelaksanaan (kunci lain diabaikan); yang tidak dikirim memakai nilai tersimpan.
     * @param  array<string, mixed>  $tersimpan  Data kegiatan tersimpan (dasar isian yang tidak dikirim).
     * @return array<string, mixed> Isian pelaksanaan lengkap yang sudah dirapikan.
     */
    public static function rapikan(array $isian, array $tersimpan = []): array
    {
        $data = array_intersect_key($isian, array_flip(self::ISIAN)) + array_intersect_key($tersimpan, array_flip(self::ISIAN));
        $angka = fn (mixed $v) => blank($v) ? null : (filter_var($v, FILTER_VALIDATE_INT) === false ? $v : (int) $v);
        $teks = fn (mixed $v) => trim(preg_replace('/\s+/u', ' ', (string) $v));

        $hasil = [
            'tanggal_mulai' => filled($data['tanggal_mulai'] ?? null) ? (string) $data['tanggal_mulai'] : null,
            'lama_hari' => $angka($data['lama_hari'] ?? null),
            'hari_kerja' => (bool) ($data['hari_kerja'] ?? false),
            'bulan_pelaksanaan' => self::bulan($data['bulan_pelaksanaan'] ?? []),
            'tanggal_laporan' => filled($data['tanggal_laporan'] ?? null) ? (string) $data['tanggal_laporan'] : null,
            'laporan_otomatis' => (bool) ($data['laporan_otomatis'] ?? true),
            'tempat' => $teks($data['tempat'] ?? ''),
            'peserta_jumlah' => $angka($data['peserta_jumlah'] ?? null),
            'peserta_jenis' => $teks($data['peserta_jenis'] ?? ''),
            'uraian_pelaksanaan' => Teks::rapikan((string) ($data['uraian_pelaksanaan'] ?? '')),
        ];

        $jadwal = DataPelaksanaan::dari($hasil);
        if ($hasil['bulan_pelaksanaan'] === [] && ($bulan = $jadwal->bulanDariTanggal()) !== null) {
            $hasil['bulan_pelaksanaan'] = $bulan;
        }
        if ($hasil['laporan_otomatis'] && ($batas = $jadwal->batasLaporanBaku()) !== null) {
            $hasil['tanggal_laporan'] = $batas->toDateString();
        }

        return $hasil;
    }

    /**
     * Rapikan, periksa, lalu simpan isian pelaksanaan satu kegiatan; mengembalikan isian yang tersimpan.
     *
     * @param  array<string, mixed>  $isian
     * @param  array<string, mixed>|null  $pengguna
     * @return array<string, mixed>
     *
     * @throws ValidationException Isian tidak sah, berkunci isian.
     * @throws RuntimeException Kegiatan tidak ditemukan.
     */
    public static function simpan(int $kegiatanId, array $isian, ?array $pengguna = null): array
    {
        $repo = app(KegiatanRepository::class);
        $tersimpan = $repo->temukan($kegiatanId) ?? throw new RuntimeException("Kegiatan {$kegiatanId} tidak ditemukan.");
        $pelaksanaan = self::rapikan($isian, $tersimpan);
        $data = array_merge($tersimpan, $pelaksanaan);

        ValidasiKegiatan::periksa($data, self::ISIAN, $pengguna, $tersimpan['leading'] ?? null);
        $repo->simpan($data, $kegiatanId);

        return $pelaksanaan;
    }

    /**
     * Bulan unik & urut; nilai bukan angka dibiarkan agar ditolak validasi (sama dengan KegiatanForm::rapikanBulan).
     *
     * @return list<int|mixed>
     */
    private static function bulan(mixed $bulan): array
    {
        $bulan = array_map(fn ($b) => is_numeric($b) ? (int) $b : $b, array_values((array) $bulan));
        $bulan = array_values(array_unique($bulan, SORT_REGULAR));
        sort($bulan);

        return $bulan;
    }
}
