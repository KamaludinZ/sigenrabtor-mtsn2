<?php

namespace App\Support\Pengaturan;

use App\Contracts\RiwayatPengaturanRepository;
use Carbon\CarbonImmutable;

/**
 * Catatan audit perubahan pengaturan (PRD N7, tabel activity_logs tanpa activity_id; hanya bisa ditambah) — pembacaan &
 * penyaringan di server, dipakai halaman Riwayat Perubahan dan endpoint `pengaturan.riwayat.data`. Saringan (`SARINGAN`):
 * bagian (BagianPengaturan lewat RiwayatPengaturan::AKSI), pengguna (nama), aksi, tahun anggaran, rentang tanggal
 * `dari`/`sampai` (Y-m-d, zona aplikasi; terbalik → diabaikan), dan kata kunci pada keterangan & label aksi.
 */
final class CatatanAudit
{
    public const SARINGAN = ['bagian', 'pengguna', 'aksi', 'tahun', 'dari', 'sampai', 'cari'];

    /**
     * Saringan yang sah (kunci SARINGAN, teks dipangkas; tanggal Y-m-d; rentang terbalik dibuang).
     *
     * @param  array<string, mixed>  $isian
     * @return array<string, string>
     */
    public static function rapikanSaringan(array $isian): array
    {
        $hasil = [];
        foreach (self::SARINGAN as $kunci) {
            $nilai = trim((string) ($isian[$kunci] ?? ''));
            if (in_array($kunci, ['dari', 'sampai'], true) && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai)) {
                $nilai = '';
            }
            if ($kunci === 'tahun' && ! ctype_digit($nilai)) {
                $nilai = '';
            }
            $hasil[$kunci] = $nilai;
        }
        if ($hasil['dari'] !== '' && $hasil['sampai'] !== '' && $hasil['dari'] > $hasil['sampai']) {
            [$hasil['dari'], $hasil['sampai']] = ['', ''];
        }

        return $hasil;
    }

    /**
     * Entri (terbaru dulu) yang cocok dengan saringan, masing-masing diberi `label_aksi` dan `bagian`.
     *
     * @param  array<string, mixed>  $saringan
     * @param  list<array<string, mixed>>|null  $entri  Bawaan: semua entri RiwayatPengaturanRepository.
     * @return list<array<string, mixed>>
     */
    public static function saring(array $saringan, ?array $entri = null): array
    {
        $s = self::rapikanSaringan($saringan);
        $cari = mb_strtolower($s['cari']);
        $zona = config('app.timezone');

        $hasil = [];
        foreach ($entri ?? app(RiwayatPengaturanRepository::class)->semua() as $r) {
            [$label, $bagian] = RiwayatPengaturan::AKSI[$r['aksi']] ?? [$r['aksi'], null];
            $tanggal = CarbonImmutable::parse($r['waktu'])->setTimezone($zona)->toDateString();
            $cocok = ($s['bagian'] === '' || $bagian === $s['bagian'])
                && ($s['pengguna'] === '' || $r['pengguna'] === $s['pengguna'])
                && ($s['aksi'] === '' || $r['aksi'] === $s['aksi'])
                && ($s['tahun'] === '' || (int) $r['tahun'] === (int) $s['tahun'])
                && ($s['dari'] === '' || $tanggal >= $s['dari'])
                && ($s['sampai'] === '' || $tanggal <= $s['sampai'])
                && ($cari === '' || str_contains(mb_strtolower($r['keterangan'].' '.$label), $cari));
            if ($cocok) {
                $hasil[] = $r + ['label_aksi' => $label, 'bagian' => $bagian];
            }
        }

        return $hasil;
    }

    /**
     * Satu halaman hasil saringan.
     *
     * @param  array<string, mixed>  $saringan
     * @return array{saringan: array<string, string>, data: list<array<string, mixed>>, jumlah: int, halaman: int, jumlah_halaman: int, per_halaman: int}
     */
    public static function halaman(array $saringan, int $halaman = 1, int $perHalaman = 20): array
    {
        $semua = self::saring($saringan);
        $jumlahHalaman = max(1, (int) ceil(count($semua) / $perHalaman));
        $halaman = max(1, min($halaman, $jumlahHalaman));

        return [
            'saringan' => self::rapikanSaringan($saringan),
            'data' => array_slice($semua, ($halaman - 1) * $perHalaman, $perHalaman),
            'jumlah' => count($semua),
            'halaman' => $halaman,
            'jumlah_halaman' => $jumlahHalaman,
            'per_halaman' => $perHalaman,
        ];
    }
}
