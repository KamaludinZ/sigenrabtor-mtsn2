<?php

namespace App\Support\Dokumen;

use App\Contracts\HasilDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatDokumenRepository;

/**
 * Pemakaian template master KAK/TOR (PRD §6 `document_templates`: "template yang sudah dipakai dokumen tidak boleh dihapus,
 * hanya dinonaktifkan"; dipakai UnggahMasterTemplate::hapus & daftar Template Master):
 * - `kegiatan` = kegiatan yang memilihnya (`template_kak`);
 * - `dokumen` = dokumen yang tercatat memakainya: hasil susunan tersimpan (HasilDokumenRepository::memakaiTemplate —
 *   PembuatDokumen::rekam mencatat master yang berlaku) dan catatan Riwayat Dokumen (cetak/unduh/arsip,
 *   CatatRiwayatDokumen) — {asal hasil|riwayat, kegiatan_id, nama_kegiatan, jenis, label_jenis, sumber, keterangan};
 * - `boleh_dihapus` = keduanya kosong. Database menjaga hal yang sama untuk dokumen (FK ON DELETE RESTRICT, migrasi 000053).
 * `jumlah($ids)` = jumlah kegiatan & dokumen per template sekaligus (daftar Template Master).
 */
final class PemakaianTemplate
{
    /**
     * @return array{kegiatan: list<array{id: int, nama: string}>, dokumen: list<array{asal: string, kegiatan_id: int|null, nama_kegiatan: string, jenis: string, label_jenis: string, sumber: string|null, keterangan: string}>, boleh_dihapus: bool}
     */
    public static function untuk(string $id): array
    {
        $semuaKegiatan = app(KegiatanRepository::class)->semua();
        $nama = array_column($semuaKegiatan, 'nama_kegiatan', 'id');
        $kegiatan = array_values(array_map(fn (array $k) => ['id' => (int) $k['id'], 'nama' => (string) ($k['nama_kegiatan'] ?? '')],
            array_filter($semuaKegiatan, fn (array $k) => ($k['template_kak'] ?? null) === $id)));
        $label = fn (string $jenis) => DokumenKegiatan::JENIS[$jenis] ?? $jenis;

        $dokumen = [];
        foreach (app(HasilDokumenRepository::class)->memakaiTemplate([$id]) as $h) {
            $dokumen[] = [
                'asal' => 'hasil', 'kegiatan_id' => (int) $h['kegiatan_id'], 'nama_kegiatan' => (string) ($nama[$h['kegiatan_id']] ?? 'Kegiatan #'.$h['kegiatan_id']),
                'jenis' => $h['jenis'], 'label_jenis' => $label($h['jenis']), 'sumber' => $h['sumber'],
                'keterangan' => 'Hasil susunan '.$label($h['jenis']).($h['sumber'] ? " {$h['sumber']}" : '').($h['nomor'] ? " nomor {$h['nomor']}" : ''),
            ];
        }
        foreach (self::riwayat([$id]) as $r) {
            $dokumen[] = [
                'asal' => 'riwayat', 'kegiatan_id' => $r['kegiatan_id'], 'nama_kegiatan' => (string) $r['nama_kegiatan'],
                'jenis' => $r['jenis'], 'label_jenis' => $label($r['jenis']), 'sumber' => $r['sumber'],
                'keterangan' => (RiwayatDokumen::ASAL[$r['asal']] ?? $r['asal']).' '.$r['nama_berkas'].($r['nomor_dokumen'] ? " nomor {$r['nomor_dokumen']}" : ''),
            ];
        }

        return ['kegiatan' => $kegiatan, 'dokumen' => $dokumen, 'boleh_dihapus' => $kegiatan === [] && $dokumen === []];
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array{kegiatan: int, dokumen: int}>
     */
    public static function jumlah(array $ids): array
    {
        $hasil = array_fill_keys($ids, ['kegiatan' => 0, 'dokumen' => 0]);
        foreach (app(KegiatanRepository::class)->semua() as $k) {
            if (isset($hasil[$k['template_kak'] ?? ''])) {
                $hasil[$k['template_kak']]['kegiatan']++;
            }
        }
        foreach ([...app(HasilDokumenRepository::class)->memakaiTemplate($ids), ...self::riwayat($ids)] as $d) {
            $hasil[$d['template']]['dokumen']++;
        }

        return $hasil;
    }

    /**
     * @param  list<string>  $ids
     * @return list<array<string, mixed>>
     */
    private static function riwayat(array $ids): array
    {
        return array_values(array_filter(app(RiwayatDokumenRepository::class)->semua(), fn (array $r) => ($r['template'] ?? null) !== null && in_array($r['template'], $ids, true)));
    }
}
