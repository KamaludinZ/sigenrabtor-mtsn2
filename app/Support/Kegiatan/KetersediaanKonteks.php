<?php

namespace App\Support\Kegiatan;

/**
 * Ketersediaan catatan konteks untuk satu bagian yang dibantu AI (PRD §9, F23): aspek catatan (KonteksAi::ASPEK) yang
 * dipakai bagian itu, mana yang sudah ditulis leading, dan arahan untuk AI bila belum — saran tetap dibuat dari bahan
 * yang ada tanpa mengarang rincian aspek yang kosong. Bagian yang tidak bergantung pada catatan (nama cover, jadwal)
 * berstatus `tidak_perlu`.
 */
final class KetersediaanKonteks
{
    /** Bagian (kunci BagianAi::BAGIAN) → aspek catatan yang dipakai. */
    public const ASPEK_BAGIAN = [
        'nama_cover' => [],
        'latar_belakang' => ['latar'],
        'tujuan' => ['output'],
        'sasaran' => ['peserta', 'output'],
        'pelaksanaan' => ['peserta', 'narasumber'],
        'jadwal' => [],
    ];

    /**
     * Status `tidak_perlu`, `kosong`, `sebagian`, atau `lengkap`; `kurang` = aspek yang dipakai bagian tetapi belum ditulis.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array{status: string, aspek: list<string>, kurang: list<string>, arahan: string|null}
     */
    public static function untuk(string $bagian, array $kegiatan): array
    {
        $aspek = self::ASPEK_BAGIAN[$bagian] ?? [];
        $konteks = trim((string) ($kegiatan['konteks'] ?? ''));
        $terisi = KonteksAi::aspekTerisi($konteks);
        $kurang = array_values(array_filter($aspek, fn (string $a) => ! $terisi[$a]));

        $status = match (true) {
            $aspek === [] => 'tidak_perlu',
            $konteks === '' => 'kosong',
            $kurang === [] => 'lengkap',
            default => 'sebagian',
        };

        $label = fn (array $daftar) => implode(' dan ', array_map(fn (string $a) => mb_strtolower(KonteksAi::ASPEK[$a][0]), $daftar));
        $arahan = match ($status) {
            'kosong' => 'Catatan konteks masih kosong — susun kalimat umum dari bahan yang ada tanpa mengarang angka, nama, atau rincian.',
            'sebagian' => 'Catatan konteks belum menyebut '.$label($kurang).' — jangan mengarang rinciannya; tulis secara umum.',
            default => null,
        };

        return ['status' => $status, 'aspek' => $aspek, 'kurang' => $kurang, 'arahan' => $arahan];
    }
}
