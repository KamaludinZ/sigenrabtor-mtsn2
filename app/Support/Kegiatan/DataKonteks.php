<?php

namespace App\Support\Kegiatan;

/**
 * Data konteks satu kegiatan (PRD §9 "Konteks utama", F23, N8) untuk endpoint `kegiatan.konteks`: catatan leading apa
 * adanya beserta panjang/batasnya dan waktu terakhir berubah, empat aspek catatan (KonteksAi::ASPEK) dan mana yang sudah
 * disebut, peringatan privasi NIP, bahan dari isian lain, serta teks bahan AI persis seperti yang dikirim ke penyedia
 * (KonteksAi::teks — NIP disembunyikan).
 */
final class DataKonteks
{
    /** Panjang maksimal catatan konteks (sama dengan ValidasiKegiatan & CHECK di database). */
    public const BATAS = 3000;

    /**
     * @param  array<string, mixed>  $kegiatan  Data KegiatanRepository.
     * @return array<string, mixed>
     */
    public static function untuk(array $kegiatan): array
    {
        $konteks = (string) ($kegiatan['konteks'] ?? '');
        $terisi = KonteksAi::aspekTerisi($konteks);

        return [
            'kegiatan_id' => (int) $kegiatan['id'],
            'nama_kegiatan' => (string) ($kegiatan['nama_kegiatan'] ?? ''),
            'konteks' => $konteks,
            'panjang' => mb_strlen($konteks),
            'batas' => self::BATAS,
            'diperbarui_pada' => $kegiatan['konteks_diperbarui_pada'] ?? null,
            'aspek' => array_map(fn (string $kunci) => [
                'kunci' => $kunci,
                'label' => KonteksAi::ASPEK[$kunci][0],
                'terisi' => $terisi[$kunci],
                'contoh' => KonteksAi::CONTOH_ASPEK[$kunci],
            ], array_keys(KonteksAi::ASPEK)),
            'peringatan_privasi' => KonteksAi::peringatanPrivasi($konteks),
            'bahan_isian' => KonteksAi::dariIsian($kegiatan),
            'bahan_ai' => KonteksAi::teks($kegiatan),
        ];
    }
}
