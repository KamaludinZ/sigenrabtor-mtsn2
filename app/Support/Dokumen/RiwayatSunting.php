<?php

namespace App\Support\Dokumen;

use App\Contracts\RiwayatSuntingRepository;
use App\Support\Kegiatan\TeksKhusus;
use RuntimeException;

/**
 * Jejak sunting teks langsung di pratinjau (PRD F5 "Edit Teks Langsung", N7) lewat RiwayatSuntingRepository: setiap
 * penyimpanan SuntingDokumen yang mengubah teks satu bidang dicatat — bidang, nomor bagian, asal, teks lama & baru
 * (`teks()`: isian tersimpan atau teks khusus tersimpan satu butir per baris; null = kosong / teks baku), penyunting, waktu.
 * Gagal menulis jejak tidak membatalkan perubahan data yang sudah tersimpan (dilaporkan ke log). `untuk()` = catatan
 * siap tampil (label bidang, keterangan perubahan `KETERANGAN`).
 */
final class RiwayatSunting
{
    public const BATAS = 50;

    /** Asal → [teks lama kosong, teks baru kosong, keduanya terisi] → keterangan perubahan. */
    public const KETERANGAN = [
        'isian' => ['diisi', 'dikosongkan', 'diubah'],
        'teks_khusus' => ['disesuaikan', 'kembali ke teks baku', 'diubah'],
    ];

    /**
     * Teks tersimpan bidang $kunci; null = kosong (isian) / teks baku (teks khusus).
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function teks(array $kegiatan, string $kunci): ?string
    {
        if (SuntingDokumen::BIDANG[$kunci][1] === 'isian') {
            $teks = is_scalar($kegiatan[$kunci] ?? null) ? trim((string) $kegiatan[$kunci]) : '';

            return $teks === '' ? null : $teks;
        }
        $teks = TeksKhusus::dari($kegiatan)[$kunci] ?? null;

        return $teks === null ? null : (is_array($teks) ? implode("\n", $teks) : $teks);
    }

    /**
     * Entri jejak perubahan bidang $kunci dari $sebelum ke $sesudah; null bila teksnya sama.
     *
     * @param  array<string, mixed>  $sebelum
     * @param  array<string, mixed>  $sesudah
     * @param  array{id?: int|null, name: string}  $pengguna
     * @return array{bidang: string, nomor_bagian: int, asal: string, teks_lama: string|null, teks_baru: string|null, user_id: int|null, pengguna: string}|null
     */
    public static function entri(array $sebelum, array $sesudah, string $kunci, array $pengguna): ?array
    {
        [$lama, $baru] = [self::teks($sebelum, $kunci), self::teks($sesudah, $kunci)];
        if ($lama === $baru) {
            return null;
        }

        return [
            'bidang' => $kunci,
            'nomor_bagian' => SuntingDokumen::BIDANG[$kunci][0],
            'asal' => SuntingDokumen::BIDANG[$kunci][1],
            'teks_lama' => $lama,
            'teks_baru' => $baru,
            'user_id' => isset($pengguna['id']) ? (int) $pengguna['id'] : null,
            'pengguna' => $pengguna['name'],
        ];
    }

    /**
     * Catat perubahan bidang $kunci kegiatan $id (bila ada).
     *
     * @param  array<string, mixed>  $sebelum
     * @param  array<string, mixed>  $sesudah
     * @param  array{id?: int|null, name: string}  $pengguna
     * @return array<string, mixed>|null Catatan tersimpan; null bila tidak ada perubahan atau gagal ditulis.
     */
    public static function catat(int $id, array $sebelum, array $sesudah, string $kunci, array $pengguna): ?array
    {
        $entri = self::entri($sebelum, $sesudah, $kunci, $pengguna);
        if ($entri === null) {
            return null;
        }
        try {
            return app(RiwayatSuntingRepository::class)->catat($id, $entri);
        } catch (RuntimeException $e) {
            report($e);

            return null;
        }
    }

    /**
     * Catatan kegiatan $id siap tampil, terbaru dulu.
     *
     * @return list<array<string, mixed>>
     */
    public static function untuk(int $id, ?string $bidang = null, int $batas = self::BATAS): array
    {
        return array_map(fn (array $c) => $c + [
            'label' => isset(SuntingDokumen::BIDANG[$c['bidang']]) ? SuntingDokumen::label($c['bidang']) : $c['bidang'],
            'keterangan' => self::KETERANGAN[$c['asal']][match (true) {
                $c['teks_lama'] === null => 0,
                $c['teks_baru'] === null => 1,
                default => 2,
            }] ?? 'diubah',
        ], app(RiwayatSuntingRepository::class)->untukKegiatan($id, $bidang, $batas));
    }
}
