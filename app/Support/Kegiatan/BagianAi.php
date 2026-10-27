<?php

namespace App\Support\Kegiatan;

use App\Support\MasterKode;
use App\Support\Stub\StubData;

/**
 * Enam bagian KAK/TOR yang bisa dibantu AI (PRD §9, F23): kunci = `prompt_templates.bagian`. Tiap bagian membawa kolom
 * data kegiatan yang diisi, tempat tercetak, nomor bagian KAK (null = cover), apa yang dihasilkan AI, dan bahan dari
 * data kegiatan yang dipakai menyusun prompt (bahan()). NIP & nama pejabat tidak pernah termasuk bahan (PRD N8).
 */
final class BagianAi
{
    public const BAGIAN = [
        'nama_cover' => [
            'judul' => 'Nama kegiatan (cover)', 'kolom' => 'nama_kegiatan_cover', 'kak' => null, 'tercetak' => 'Cover & judul KAK/TOR',
            'hasil' => 'Versi judul yang rapi dan baku, ditulis kapital.',
        ],
        'latar_belakang' => [
            'judul' => 'Latar belakang', 'kolom' => 'latar_belakang', 'kak' => 1, 'tercetak' => 'Bagian 1 · Latar Belakang (paragraf khusus)',
            'hasil' => '1–2 paragraf alasan kegiatan yang menyambung paragraf baku visi madrasah.',
        ],
        'tujuan' => [
            'judul' => 'Tujuan', 'kolom' => 'tujuan', 'kak' => 3, 'tercetak' => 'Bagian 3A · Tujuan',
            'hasil' => '1 paragraf tujuan yang spesifik.',
        ],
        'sasaran' => [
            'judul' => 'Sasaran', 'kolom' => 'sasaran', 'kak' => 3, 'tercetak' => 'Bagian 3B · Sasaran',
            'hasil' => 'Kalimat sasaran dan hasil yang terukur.',
        ],
        'pelaksanaan' => [
            'judul' => 'Pelaksanaan', 'kolom' => 'uraian_pelaksanaan', 'kak' => 4, 'tercetak' => 'Bagian 4b · Tahapan Kegiatan: Pelaksanaan',
            'hasil' => 'Kalimat bentuk pelaksanaan (peserta, lama, tempat, narasumber).',
        ],
        'jadwal' => [
            'judul' => 'Jadwal kegiatan', 'kolom' => null, 'kak' => 7, 'tercetak' => 'Bagian 7 · Jadwal Kegiatan',
            'hasil' => 'Usulan 4–6 uraian jadwal beserta bulannya.',
        ],
    ];

    /** Kolom teks yang disunting di halaman Kalimat KAK (jadwal disunting di Matrik & Jadwal). */
    public const KOLOM = ['nama_kegiatan_cover', 'latar_belakang', 'tujuan', 'sasaran', 'uraian_pelaksanaan'];

    /**
     * Bahan dari data kegiatan untuk satu bagian (PRD §9 "Konteks utama"); nilai null = belum diisi.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return list<array{label: string, nilai: string|null}>
     */
    public static function bahan(string $bagian, array $kegiatan): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $pelaksanaan = DataPelaksanaan::dari($kegiatan);
        $rktm = MasterKode::cari(MasterKode::kodeRktm(), $kegiatan['kode_rktm'] ?? null);
        $iksk = MasterKode::cari(MasterKode::kodeIksk(), $kegiatan['kode_iksk'] ?? null);
        $edm = MasterKode::cari(MasterKode::kodeEdm(), $kegiatan['kode_edm'] ?? null);
        $konteks = trim((string) ($kegiatan['konteks'] ?? ''));
        $aspek = array_keys(array_filter(KonteksAi::aspekTerisi($konteks)));
        $peserta = trim(($pelaksanaan->pesertaJumlah ? $pelaksanaan->pesertaJumlah.' orang ' : '').$pelaksanaan->pesertaJenis);
        $nama = trim((string) ($kegiatan['nama_kegiatan'] ?? ''));

        $isi = [
            'nama' => ['Nama kegiatan', $nama ?: null],
            'rktm' => ['Kode RKTM & sub kegiatan', $rktm ? $rktm['kode'].' · '.$rktm['sub_kegiatan'] : null],
            'iksk' => ['IKSK', $iksk ? $iksk['kode'].' · '.$iksk['uraian'] : null],
            'edm' => ['Indikator EDM', $edm ? $edm['kode'].' · '.$edm['uraian'] : null],
            'output' => ['Output RKTM', $rktm['kegiatan'] ?? null],
            'peserta' => ['Peserta', $peserta ?: null],
            'lama' => ['Lama', $pelaksanaan->lamaHari ? $pelaksanaan->lamaHari.' hari' : null],
            'tempat' => ['Tempat', $pelaksanaan->tempat ?: null],
            'bulan' => ['Bulan pelaksanaan', $pelaksanaan->teksBulan($tahun)],
            'jenis' => ['Jenis kegiatan', $nama !== '' ? (KonteksAi::jenisKegiatan()[KonteksAi::tebakJenis($nama)] ?? null) : null],
            'konteks' => ['Catatan konteks', $konteks === '' ? null : ($aspek === [] ? 'terisi' : implode(', ', array_map(fn ($a) => mb_strtolower(KonteksAi::ASPEK[$a][0]), $aspek)))],
        ];

        $kunci = match ($bagian) {
            'nama_cover' => ['nama'],
            'latar_belakang' => ['nama', 'rktm', 'iksk', 'konteks'],
            'tujuan' => ['nama', 'output', 'edm', 'konteks'],
            'sasaran' => ['peserta', 'output', 'iksk', 'konteks'],
            'pelaksanaan' => ['peserta', 'lama', 'tempat', 'konteks'],
            'jadwal' => ['bulan', 'jenis', 'nama'],
        };

        return array_map(fn (string $k) => ['label' => $isi[$k][0], 'nilai' => $isi[$k][1]], $kunci);
    }

    /**
     * Bagian sudah berisi teks (jadwal: ada baris berisi uraian & bulan).
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function terisi(string $bagian, array $kegiatan, ?JadwalKegiatan $jadwal = null): bool
    {
        $kolom = self::BAGIAN[$bagian]['kolom'];

        return $kolom === null
            ? collect($jadwal?->baris ?? [])->contains(fn (array $b) => $b['uraian'] !== '' && $b['bulan'] !== [])
            : filled(trim((string) ($kegiatan[$kolom] ?? '')));
    }
}
