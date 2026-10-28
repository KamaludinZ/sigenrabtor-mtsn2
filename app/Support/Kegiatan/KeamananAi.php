<?php

namespace App\Support\Kegiatan;

/**
 * Sanitasi data & pemeriksaan keamanan AI di server (PRD §20 "Batas Aman", §21 "data yang tidak pernah dikirim") —
 * lapisan penjaga di sekitar PayloadAi (muatan keluar) dan penyusun AI (keluaran), dipakai SaranKalimatAi & SaranJadwalAi:
 * - `bersihkanKeluaran($teks)`: teks dari model AI dibersihkan sebelum ditampilkan/disimpan — tag & entitas HTML, tanda
 *   markdown (penebalan, kode, judul `#`, pagar kode), karakter kendali & lebar-nol (baris baru tetap), dan NIP yang
 *   muncul disamarkan (`PENANDA_NIP`);
 * - `piiMuatan($teks)` / `pastikanMuatanAman(...$teks)`: muatan yang akan dikirim tidak boleh lagi memuat data pribadi
 *   (SanitasiPii::jenis + NIP) — bila ada (mis. jalur yang melewati PayloadAi), permintaan dibatalkan dengan GagalUsulanAi
 *   sebelum menyentuh penyedia;
 * - `peringatan($versi, $kegiatan)`: peringatan Batas Aman tiap versi saran (BatasAmanSaran — angka/kode/nama pejabat yang
 *   tidak ada di data kegiatan); hanya peringatan, saran tidak diubah diam-diam.
 */
final class KeamananAi
{
    public const PENANDA_NIP = '[NIP disembunyikan]';

    /** Jenis data pribadi → sebutan di pesan. */
    public const LABEL_PII = ['surel' => 'alamat surel', 'rekening' => 'nomor rekening', 'nik' => 'NIK', 'telepon' => 'nomor telepon', 'nip' => 'NIP'];

    private const POLA_NIP = '/(?<!\d)\d{18}(?!\d)|(?<!\d)\d{8}\s\d{6}\s\d\s\d{3}(?!\d)/';

    public static function bersihkanKeluaran(string $teks): string
    {
        $teks = strip_tags(html_entity_decode($teks, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $teks = (string) preg_replace('/```[\w-]*/u', '', $teks);
        $teks = (string) preg_replace('/\*\*|__|`/u', '', $teks);
        $teks = (string) preg_replace('/^[ \t]{0,3}#{1,6}[ \t]+/mu', '', $teks);
        // Karakter kendali & format (termasuk lebar-nol) dibuang; baris baru & tab tetap.
        $teks = (string) preg_replace('/(?![\n\t])\p{C}/u', '', str_replace("\r\n", "\n", $teks));

        return (string) preg_replace(self::POLA_NIP, self::PENANDA_NIP, $teks);
    }

    /** @return list<string> Jenis data pribadi yang masih ada (kunci LABEL_PII). */
    public static function piiMuatan(string $teks): array
    {
        $jenis = SanitasiPii::jenis($teks);
        if (preg_match(self::POLA_NIP, $teks)) {
            $jenis[] = 'nip';
        }

        return array_values(array_unique($jenis));
    }

    /** @throws GagalUsulanAi Muatan masih memuat data pribadi. */
    public static function pastikanMuatanAman(string ...$teks): void
    {
        $jenis = self::piiMuatan(implode("\n", $teks));
        if ($jenis !== []) {
            $label = array_map(fn (string $j) => self::LABEL_PII[$j] ?? $j, $jenis);
            throw new GagalUsulanAi('Bahan AI masih memuat '.implode(', ', $label).' — permintaan dibatalkan agar data pribadi tidak terkirim. Hapus data itu dari catatan konteks atau isian kegiatan.');
        }
    }

    /**
     * @param  list<array{teks: string}>  $versi
     * @param  array<string, mixed>  $kegiatan
     * @return list<list<string>> Peringatan per versi (urutan sama).
     */
    public static function peringatan(array $versi, array $kegiatan): array
    {
        return array_map(fn (array $v) => BatasAmanSaran::peringatan((string) $v['teks'], $kegiatan), array_values($versi));
    }
}
