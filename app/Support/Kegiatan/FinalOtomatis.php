<?php

namespace App\Support\Kegiatan;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use Carbon\CarbonImmutable;

/**
 * Status Final otomatis setelah arsip final lengkap (PRD F18, alur 8 "Finalisasi & arsip", aturan 9): begitu setiap sumber
 * dana wajib (ArsipFinal::sumberWajib) punya arsip yang diunggah sesudah persetujuan terakhir, kegiatan Disetujui langsung
 * menjadi Final dan datanya terkunci — dicatat sebagai entri riwayat `finalkan` atas nama pengunggah dengan catatan
 * otomatis (jejak kunci KunciDokumen, notifikasi admin, putaran RiwayatPemeriksaan). Dipanggil KelolaArsipFinal::unggah.
 * Arsip dari putaran sebelumnya (diunggah sebelum kunci dibuka lalu disetujui ulang) tidak dihitung, agar mengganti satu
 * arsip tidak langsung mengunci kegiatan sebelum arsip lainnya diganti; "Tandai final" manual tetap bisa memakainya
 * (ArsipFinal::kurang). Tahun anggaran terkunci tidak difinalkan.
 */
final class FinalOtomatis
{
    /**
     * Waktu persetujuan terakhir (entri `setujui`); null bila tidak tercatat (semua arsip dihitung).
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function sejak(array $kegiatan): ?CarbonImmutable
    {
        $setujui = array_values(array_filter((array) ($kegiatan['riwayat_status'] ?? []), fn (array $e) => ($e['aksi'] ?? null) === 'setujui'));

        return $setujui === [] ? null : CarbonImmutable::parse(end($setujui)['pada']);
    }

    /**
     * Arsip yang diunggah sesudah persetujuan terakhir, berkunci sumber dana.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array<string, array<string, mixed>>  $arsip  ArsipFinalRepository::untukKegiatan().
     * @return array<string, array<string, mixed>>
     */
    public static function berlaku(array $kegiatan, array $arsip): array
    {
        $sejak = self::sejak($kegiatan);

        return $sejak === null ? $arsip : array_filter($arsip, fn (array $a) => CarbonImmutable::parse($a['diunggah_pada'])->gte($sejak));
    }

    /**
     * Sumber dana wajib yang belum punya arsip berlaku (syarat final otomatis).
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array<string, array<string, mixed>>  $arsip
     * @return list<string>
     */
    public static function kurang(array $kegiatan, array $arsip): array
    {
        return ArsipFinal::kurang($kegiatan, self::berlaku($kegiatan, $arsip));
    }

    /** @param  list<string>  $sumber */
    public static function catatan(array $sumber): string
    {
        $daftar = array_map(fn (string $s) => 'RAB '.$s, $sumber);
        $teks = count($daftar) > 1 ? implode(', ', array_slice($daftar, 0, -1)).' dan '.end($daftar) : $daftar[0];

        return "Ditandai final otomatis: arsip final {$teks} sudah lengkap.";
    }

    /**
     * Finalkan kegiatan $id bila syaratnya terpenuhi.
     *
     * @param  array{name: string, role: string}  $pengguna  Pengunggah arsip terakhir (pelaku entri riwayat).
     * @return array<string, mixed>|null Entri riwayat `finalkan`; null bila belum/tidak difinalkan.
     */
    public static function jalankan(int $id, array $pengguna): ?array
    {
        $repo = app(KegiatanRepository::class);
        $kegiatan = $repo->temukan($id);
        if ($kegiatan === null || ($kegiatan['status'] ?? null) !== StatusKegiatan::Disetujui->value || KunciKegiatan::tahunTerkunci($kegiatan) !== null) {
            return null;
        }
        $wajib = ArsipFinal::sumberWajib($kegiatan);
        if ($wajib === [] || self::kurang($kegiatan, app(ArsipFinalRepository::class)->untukKegiatan($id)) !== []) {
            return null;
        }

        $entri = RiwayatStatus::entri('finalkan', StatusKegiatan::Final, $pengguna, self::catatan($wajib));
        $kegiatan['status'] = StatusKegiatan::Final->value;
        $kegiatan['riwayat_status'] = [...array_values((array) ($kegiatan['riwayat_status'] ?? [])), $entri];
        $repo->simpan($kegiatan, $id);
        RiwayatKunci::catat($id, 'kunci_final', $entri['catatan'], $pengguna, $entri['pada']);

        return $entri;
    }
}
