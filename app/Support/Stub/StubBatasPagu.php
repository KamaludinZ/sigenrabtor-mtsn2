<?php

namespace App\Support\Stub;

use App\Contracts\KegiatanRepository;
use App\Support\Rab\AnggaranKegiatan;

/**
 * Data tiruan Batas Pagu (PRD F12, aturan 5): total RAB tiap kegiatan × sumber dana terhadap pagunya sendiri,
 * dan kasus batas yang harus dibedakan layar peringatan (sesuai, sisa/berbeda, melebihi, pagu belum diisi).
 */
class StubBatasPagu
{
    /**
     * Kondisi pagu setiap RAB pada kegiatan tersimpan; selisih = pagu − total (negatif = melebihi, null = pagu belum diisi).
     *
     * @return list<array{kegiatan_id: int, kegiatan: string, sumber: string, pagu: int|null, total: int, selisih: int|null, jumlah_item: int, status: string, teks: string}>
     */
    public static function kondisiKegiatan(): array
    {
        $kondisi = [];

        foreach (app(KegiatanRepository::class)->semua() as $kegiatan) {
            foreach (AnggaranKegiatan::untuk($kegiatan) as $anggaran) {
                $status = $anggaran->keteranganPagu();

                $kondisi[] = [
                    'kegiatan_id' => (int) $kegiatan['id'],
                    'kegiatan' => $kegiatan['nama_kegiatan'] ?? '',
                    'sumber' => $anggaran->sumber,
                    'pagu' => $anggaran->pagu(),
                    'total' => $anggaran->total(),
                    'selisih' => $status->selisih,
                    'jumlah_item' => $anggaran->jumlahItem(),
                    'status' => $status->status,
                    'teks' => $status->teks(),
                ];
            }
        }

        return $kondisi;
    }

    /**
     * Kasus batas total RAB vs pagu. Setiap kasus juga ada di tests/fixtures/status-pagu.json agar kembaran JS
     * ($statusPagu) diuji dengan angka yang sama.
     *
     * @return list<array{pagu: int|null, total: int, status: string, teks: string, catatan: string}>
     */
    public static function kasusUji(): array
    {
        return [
            ['pagu' => null, 'total' => 900_000, 'status' => 'tanpa_pagu', 'teks' => 'Pagu belum diisi', 'catatan' => 'pagu DIPA kosong pada draf dari Cascade Perkin'],
            ['pagu' => 6_000_000, 'total' => 0, 'status' => 'sisa', 'teks' => 'Sisa pagu Rp6.000.000', 'catatan' => 'RAB belum berisi rincian'],
            ['pagu' => 10_000_000, 'total' => 10_000_000, 'status' => 'sesuai', 'teks' => 'Sesuai pagu', 'catatan' => 'Form RAB Workshop: total tepat pagu'],
            ['pagu' => 6_000_000, 'total' => 5_400_000, 'status' => 'sisa', 'teks' => 'Sisa pagu Rp600.000', 'catatan' => 'total berbeda (di bawah) pagu'],
            ['pagu' => 6_000_000, 'total' => 5_999_999, 'status' => 'sisa', 'teks' => 'Sisa pagu Rp1', 'catatan' => 'selisih terkecil di bawah pagu'],
            ['pagu' => 9_450_000, 'total' => 9_540_000, 'status' => 'melebihi', 'teks' => 'Melebihi pagu Rp90.000', 'catatan' => 'Paket Meeting: KAK Rp9.450.000, RAB Rp9.540.000 (PRD)'],
            ['pagu' => 7_500_000, 'total' => 7_950_000, 'status' => 'melebihi', 'teks' => 'Melebihi pagu Rp450.000', 'catatan' => 'contoh kegiatan Review RKJM, RAB Komite'],
            ['pagu' => 1_000_000_000_000, 'total' => 1_000_000_000_001, 'status' => 'melebihi', 'teks' => 'Melebihi pagu Rp1', 'catatan' => 'melebihi Rp1 pada angka triliun'],
        ];
    }
}
