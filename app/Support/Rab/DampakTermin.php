<?php

namespace App\Support\Rab;

use App\Contracts\RabRepository;
use App\Support\Format;

/**
 * Dampak perubahan rincian RAB pada termin pembayaran tersimpan (PRD aturan 6 "Termin harus pas"): termin tidak diubah
 * otomatis, tetapi setiap kali total RAB satu sumber dana berubah statusnya dihitung ulang — kini tidak pas (peringatan
 * dengan selisihnya dan arahan ke halaman Termin) atau kembali pas. Tidak ada dampak bila sumber dana itu dibayar
 * sekaligus atau totalnya tidak berubah. Dipakai RincianRab (tambah, ubah, hapus, kembalikan).
 */
final class DampakTermin
{
    /** Total RAB sumber dana itu saat ini (dari rincian tersimpan). */
    public static function total(int $kegiatanId, string $sumber): int
    {
        return KalkulatorRab::hitung(app(RabRepository::class)->rincian($kegiatanId, $sumber))->total;
    }

    /**
     * @return array{status: string, pesan: string, tipe: string}|null `tipe` = jenis notifikasi (warning/success).
     */
    public static function sesudahPerubahan(int $kegiatanId, string $sumber, int $totalSebelum): ?array
    {
        $termin = app(RabRepository::class)->termin($kegiatanId, $sumber);
        $totalSesudah = self::total($kegiatanId, $sumber);

        if ($termin === [] || $totalSesudah === $totalSebelum) {
            return null;
        }

        $sesudah = new MekanismePembayaran($totalSesudah, $termin);
        if ($sesudah->status() === 'pas') {
            return ['status' => 'pas', 'pesan' => "Termin RAB {$sumber} kini sama dengan total RAB.", 'tipe' => 'success'];
        }

        return [
            'status' => $sesudah->status(),
            'pesan' => "Total RAB {$sumber} kini ".Format::rupiah($totalSesudah).'; '.$sesudah->teksStatusKalimat().' — sesuaikan di Termin pembayaran.',
            'tipe' => 'warning',
        ];
    }
}
