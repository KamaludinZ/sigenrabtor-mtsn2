<?php

namespace App\Support\Dokumen;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\HasilDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Rab\AnggaranKegiatan;

/**
 * Status dokumen tersimpan mengikuti status kegiatannya (PRD §6 `documents.status`, F16–F18): hasil susunan
 * (HasilDokumenRepository) dan draf dokumen (DrafDokumenRepository) mencatat status kegiatan & cap DRAF saat disusun/
 * disimpan, sehingga setiap perpindahan status (AlurStatus::terapkan) memanggil `sinkronkan()`:
 * - hasil susunan disusun ulang dari data saat itu (PembuatDokumen::rekam) — dokumen yang diajukan/disetujui/final
 *   memuat data pada perpindahan itu, cap DRAF-nya mengikuti status baru (StatusDraf);
 * - draf dokumen hanya diperbarui status & cap DRAF-nya (`perbaruiStatus`); sidik titik lanjutnya tetap;
 * - dokumen bersumber dana yang tidak lagi dipilih tetap bercap DRAF dengan alasannya.
 * `untuk($kegiatan)` = ringkasan dokumen tersimpan per jenis × sumber dana beserta `sinkron` (status & cap sama dengan
 * keadaan sekarang) untuk endpoint `kegiatan.status`. Di PostgreSQL status & cap minimal dijaga trigger migrasi 000040.
 */
final class StatusDokumen
{
    /** Arti status kegiatan bagi dokumennya → [label, keterangan]. */
    public const ARTI = [
        'draf' => ['Draf', 'Masih disusun — dicetak bercap DRAF.'],
        'diperiksa' => ['Menunggu pemeriksaan', 'Diajukan ke admin — dicetak bercap DRAF dan isian terkunci sampai diputuskan.'],
        'dikembalikan' => ['Perlu perbaikan', 'Perbaiki data sesuai catatan pemeriksa, lalu ajukan ulang — dicetak bercap DRAF.'],
        'disetujui' => ['Siap ditandatangani', 'Cetak tanpa cap DRAF (bila lengkap & dalam pagu), tanda tangani, lalu unggah arsip final.'],
        'final' => ['Final', 'Arsip bertanda tangan sudah diunggah — dokumen terkunci sampai admin membuka kunci.'],
    ];

    /** Alasan cap DRAF dokumen yang sumber dananya tidak lagi dipilih kegiatan. */
    public const ALASAN_TERLEPAS = 'Sumber dana %s tidak lagi dipilih untuk kegiatan ini.';

    /**
     * Cap DRAF dokumen jenis × sumber dari data kegiatan saat ini (sumber yang tidak dipilih lagi selalu DRAF).
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function cap(array $kegiatan, string $jenis, ?string $sumber): StatusDraf
    {
        if ($sumber !== null && ! array_key_exists($sumber, AnggaranKegiatan::perSumber($kegiatan))) {
            return new StatusDraf(true, [['kunci' => 'sumber', 'teks' => sprintf(self::ALASAN_TERLEPAS, $sumber)]]);
        }

        return JenisDokumen::statusDraf($jenis, StatusCetak::dari($kegiatan, $sumber));
    }

    /**
     * Ringkasan dokumen tersimpan kegiatan: status kegiatan + artinya, dan tiap jenis × sumber dana yang punya hasil
     * susunan dan/atau draf — status & cap tersimpan, cap sekarang, `sinkron`.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array{status: string, label_status: string, arti: array{label: string, keterangan: string}, dokumen: list<array<string, mixed>>, sinkron: bool}
     */
    public static function untuk(array $kegiatan): array
    {
        $status = self::status($kegiatan);
        $id = (int) ($kegiatan['id'] ?? 0);
        $tersimpan = [];
        foreach (['hasil' => app(HasilDokumenRepository::class)->untukKegiatan($id), 'draf' => app(DrafDokumenRepository::class)->untukKegiatan($id)] as $asal => $daftar) {
            foreach ($daftar as $d) {
                $tersimpan[$d['jenis'].'|'.$d['sumber']][$asal] = $d;
            }
        }

        $dokumen = [];
        foreach ($tersimpan as $catatan) {
            $satu = $catatan['hasil'] ?? $catatan['draf'];
            $cap = self::cap($kegiatan, $satu['jenis'], $satu['sumber']);
            $ringkas = fn (?array $d, string $waktu) => $d === null ? null : [
                'status' => $d['status'],
                'label_status' => StatusKegiatan::tryFrom((string) $d['status'])?->label(),
                'bertanda_draf' => (bool) $d['bertanda_draf'],
                $waktu => $d[$waktu],
                'sinkron' => $d['status'] === $status->value && (bool) $d['bertanda_draf'] === $cap->draf,
            ];
            $dokumen[] = [
                'jenis' => $satu['jenis'],
                'sumber' => $satu['sumber'],
                'nama' => DrafDokumen::nama($satu['jenis'], $satu['sumber']),
                'bertanda_draf' => $cap->draf,
                'alasan_draf' => array_column($cap->alasan, 'teks'),
                'hasil' => $ringkas($catatan['hasil'] ?? null, 'disusun_pada'),
                'draf' => $ringkas($catatan['draf'] ?? null, 'disimpan_pada'),
            ];
        }
        usort($dokumen, fn (array $a, array $b) => [array_search($a['jenis'], array_keys(DokumenKegiatan::JENIS), true), (string) $a['sumber']]
            <=> [array_search($b['jenis'], array_keys(DokumenKegiatan::JENIS), true), (string) $b['sumber']]);

        return [
            'status' => $status->value,
            'label_status' => $status->label(),
            'arti' => ['label' => self::ARTI[$status->value][0], 'keterangan' => self::ARTI[$status->value][1]],
            'dokumen' => $dokumen,
            'sinkron' => collect($dokumen)->every(fn (array $d) => ($d['hasil']['sinkron'] ?? true) && ($d['draf']['sinkron'] ?? true)),
        ];
    }

    /**
     * Samakan dokumen tersimpan kegiatan $id dengan status & data tersimpannya (dipanggil sesudah perpindahan status).
     *
     * @param  array{id?: int|null, name: string, role?: string}  $pengguna  Pelaku perpindahan (penyusun hasil yang disusun ulang).
     * @return array{hasil: int, draf: int} Jumlah hasil yang disusun ulang & draf yang diperbarui.
     */
    public static function sinkronkan(int $id, array $pengguna): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($id);
        if ($kegiatan === null) {
            return ['hasil' => 0, 'draf' => 0];
        }
        $status = self::status($kegiatan)->value;
        $dipilih = AnggaranKegiatan::perSumber($kegiatan);
        $jumlah = ['hasil' => 0, 'draf' => 0];

        $hasilRepo = app(HasilDokumenRepository::class);
        foreach ($hasilRepo->untukKegiatan($id) as $hasil) {
            if ($hasil['sumber'] === null || array_key_exists($hasil['sumber'], $dipilih)) {
                $rekaman = PembuatDokumen::rekam($kegiatan, $hasil['jenis'], $hasil['sumber'], $pengguna);
                if ($rekaman['sumber'] === $hasil['sumber']) {
                    $hasilRepo->simpan($id, $rekaman);
                    $jumlah['hasil']++;

                    continue;
                }
            }
            $cap = self::cap($kegiatan, $hasil['jenis'], $hasil['sumber']);
            if ($hasil['status'] !== $status || (bool) $hasil['bertanda_draf'] !== $cap->draf) {
                $hasilRepo->perbaruiStatus($id, $hasil['jenis'], $hasil['sumber'], $status, $cap->draf, array_column($cap->alasan, 'teks'));
                $jumlah['hasil']++;
            }
        }

        $drafRepo = app(DrafDokumenRepository::class);
        foreach ($drafRepo->untukKegiatan($id) as $draf) {
            $cap = self::cap($kegiatan, $draf['jenis'], $draf['sumber']);
            $alasan = array_column($cap->alasan, 'teks');
            if ($draf['status'] !== $status || (bool) $draf['bertanda_draf'] !== $cap->draf || $draf['alasan_draf'] !== $alasan) {
                $drafRepo->perbaruiStatus($id, $draf['jenis'], $draf['sumber'], $status, $cap->draf, $alasan);
                $jumlah['draf']++;
            }
        }

        return $jumlah;
    }

    /** @param  array<string, mixed>  $kegiatan */
    private static function status(array $kegiatan): StatusKegiatan
    {
        return StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
    }
}
