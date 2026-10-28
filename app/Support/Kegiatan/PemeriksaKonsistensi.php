<?php

namespace App\Support\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\RabRepository;
use App\Support\Stub\StubData;
use Illuminate\Support\Str;

/**
 * Pengecekan konsistensi isi kegiatan di server (PRD F10 & masalah "Isi tersalin dari kegiatan lain": jadwal KAK Workshop
 * Kurikulum menyebut "Iuran Ujian Madrasah"; RAB Workshop Soal memakai rincian ESQ). Dua pemeriksaan — keduanya hanya
 * temuan, tidak memblokir simpan/cetak (PRD aturan 8):
 * - `bulan()`: KonsistensiBulan dari data kegiatan (bulan pelaksanaan & batas laporan tahun aktif) dan matrik/jadwal —
 *   tersimpan (MatrikJadwalRepository) atau isian yang sedang disunting. Satu-satunya tempat menyusunnya di server.
 * - `tersalin()`: uraian baris jadwal, butir matrik, dan rincian RAB yang memuat kata khas nama kegiatan lain di tahun
 *   anggaran yang sama (`kataKhas()`: kata ≥ 4 huruf di luar KATA_UMUM, dan akronim kapital ≥ 3 huruf) tetapi tidak ada di
 *   nama kegiatan sendiri — dua kata khas, atau satu akronim, sudah cukup. Tingkat "saran", pesan menyebut kegiatan asal.
 */
final class PemeriksaKonsistensi
{
    /** Kata yang lazim di nama kegiatan & rincian madrasah mana pun — tidak dipakai sebagai penanda kegiatan lain. */
    public const KATA_UMUM = [
        'kegiatan', 'pelaksanaan', 'persiapan', 'penyusunan', 'penyerahan', 'pembentukan', 'pembagian', 'penyelesaian',
        'pengadaan', 'penggandaan', 'penjilidan', 'pembuatan', 'pembelian', 'pengawasan', 'peningkatan', 'pengembangan',
        'pembinaan', 'penguatan', 'rapat', 'koordinasi', 'panitia', 'kepanitiaan', 'evaluasi', 'laporan', 'pelaporan',
        'sarana', 'bahan', 'madrasah', 'mtsn', 'kota', 'malang', 'tahun', 'anggaran', 'program', 'kerja', 'honorarium',
        'honor', 'narasumber', 'pemateri', 'peserta', 'konsumsi', 'transport', 'paket', 'biaya', 'belanja', 'dokumen',
        'siswa', 'guru', 'tenaga', 'pendidik', 'kependidikan', 'kepala', 'orang', 'hari', 'kali', 'untuk', 'dengan',
        'dalam', 'rangka', 'serta', 'yang', 'dari', 'pada', 'atau', 'oleh', 'bagi', 'tentang', 'antar', 'tingkat',
        'kotak', 'nasi', 'kue', 'snack', 'makan', 'minum', 'mineral', 'fotokopi', 'cetak', 'jilid', 'sewa', 'jasa',
    ];

    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  MatrikTahapan|null  $matrik  null = matrik tersimpan.
     * @param  JadwalKegiatan|null  $jadwal  null = jadwal tersimpan.
     */
    public static function bulan(array $kegiatan, ?MatrikTahapan $matrik = null, ?JadwalKegiatan $jadwal = null): KonsistensiBulan
    {
        $repo = app(MatrikJadwalRepository::class);
        $id = (int) ($kegiatan['id'] ?? 0);
        $matrik ??= $repo->matrik($id);
        $jadwal ??= $repo->jadwal($id);

        return new KonsistensiBulan(
            DataPelaksanaan::dari($kegiatan)->bulan,
            DataMatrikJadwal::bulanLaporan($kegiatan, StubData::tahunAktif()['tahun']),
            $matrik->butir,
            $jadwal->baris,
        );
    }

    /**
     * Uraian yang mungkin tersalin dari kegiatan lain di tahun anggaran yang sama.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  list<array<string, mixed>>|null  $semuaKegiatan  Bawaan: semua kegiatan (KegiatanRepository).
     * @param  bool  $rab  Ikut memeriksa rincian RAB sumber dana yang dipilih.
     * @return list<array{kunci: string, tautan: string, tingkat: 'saran', pesan: string, kegiatan: array{id: int, nama: string}, kata: list<string>}>
     */
    public static function tersalin(array $kegiatan, ?MatrikTahapan $matrik = null, ?JadwalKegiatan $jadwal = null, ?array $semuaKegiatan = null, bool $rab = true): array
    {
        $id = (int) ($kegiatan['id'] ?? 0);
        $tahun = TahunKegiatan::dari($kegiatan);
        $sendiri = array_merge(
            self::kata((string) ($kegiatan['nama_kegiatan'] ?? '')),
            self::kata((string) ($kegiatan['nama_kegiatan_cover'] ?? '')),
        );
        $lain = [];
        foreach ($semuaKegiatan ?? app(KegiatanRepository::class)->semua() as $k) {
            if ((int) $k['id'] !== $id && TahunKegiatan::dari($k) === $tahun && filled($k['nama_kegiatan'] ?? null)) {
                $lain[] = ['id' => (int) $k['id'], 'nama' => trim((string) $k['nama_kegiatan']), 'khas' => array_diff_key(self::kataKhas($k['nama_kegiatan']), array_flip($sendiri))];
            }
        }
        if ($lain === []) {
            return [];
        }

        $repo = app(MatrikJadwalRepository::class);
        $matrik ??= $repo->matrik($id);
        $jadwal ??= $repo->jadwal($id);

        $hasil = [];
        foreach ($jadwal->baris as $i => $b) {
            $hasil[] = self::cocokkan($lain, $b['uraian'], 'jadwal-'.($i + 1), 'baris-jadwal-'.($i + 1), 'Baris '.($i + 1).' jadwal');
        }
        foreach ($matrik->butir as $tahapan => $daftar) {
            foreach ($daftar as $i => $b) {
                $hasil[] = self::cocokkan($lain, $b['uraian'], "matrik-{$tahapan}-".($i + 1), 'matrik', 'Butir '.MatrikTahapan::TAHAPAN[$tahapan].' matrik');
            }
        }
        if ($rab && $id) {
            foreach (array_column($kegiatan['sumber_dana'] ?? [], 'sumber') as $sumber) {
                foreach (array_values(app(RabRepository::class)->rincian($id, $sumber)) as $i => $item) {
                    $hasil[] = self::cocokkan($lain, (string) ($item['uraian'] ?? ''), "rab-{$sumber}-".($i + 1), "rab-{$sumber}", "Rincian {$sumber} nomor ".($i + 1));
                }
            }
        }

        return array_values(array_filter($hasil));
    }

    /**
     * Ringkasan pemeriksaan konsistensi kegiatan tersimpan.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array{bulan: list<array<string, string>>, tersalin: list<array<string, mixed>>, jumlah_peringatan: int, jumlah_saran: int}
     */
    public static function periksa(array $kegiatan): array
    {
        $bulan = self::bulan($kegiatan)->temuan();
        $tersalin = self::tersalin($kegiatan);
        $semua = [...$bulan, ...$tersalin];

        return [
            'bulan' => $bulan,
            'tersalin' => $tersalin,
            'jumlah_peringatan' => count(array_filter($semua, fn (array $t) => $t['tingkat'] === 'peringatan')),
            'jumlah_saran' => count(array_filter($semua, fn (array $t) => $t['tingkat'] === 'saran')),
        ];
    }

    /**
     * Kata khas sebuah nama kegiatan: kata ≥ 4 huruf di luar KATA_UMUM dan akronim kapital ≥ 3 huruf (mis. RKTM, ESQ).
     *
     * @return array<string, bool> Kata (huruf kecil) → apakah akronim.
     */
    public static function kataKhas(string $nama): array
    {
        $khas = [];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', $nama, -1, PREG_SPLIT_NO_EMPTY) as $kata) {
            $kecil = mb_strtolower($kata);
            $akronim = (bool) preg_match('/^\p{Lu}{3,}\p{N}*$/u', $kata);
            if ($akronim || (mb_strlen($kecil) >= 4 && ! in_array($kecil, self::KATA_UMUM, true))) {
                $khas[$kecil] = ($khas[$kecil] ?? false) || $akronim;
            }
        }

        return $khas;
    }

    /** @return list<string> Semua kata (huruf kecil). */
    private static function kata(string $teks): array
    {
        return array_map(mb_strtolower(...), preg_split('/[^\p{L}\p{N}]+/u', $teks, -1, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * @param  list<array{id: int, nama: string, khas: array<string, bool>}>  $lain
     * @return array<string, mixed>|null
     */
    private static function cocokkan(array $lain, string $uraian, string $kunci, string $tautan, string $tempat): ?array
    {
        $kata = array_flip(self::kata($uraian));
        $terbaik = null;
        foreach ($lain as $k) {
            $sama = array_intersect_key($k['khas'], $kata);
            if ((count($sama) >= 2 || in_array(true, $sama, true)) && count($sama) > count($terbaik['kata'] ?? [])) {
                $terbaik = ['kegiatan' => ['id' => $k['id'], 'nama' => $k['nama']], 'kata' => array_keys($sama)];
            }
        }
        if ($terbaik === null) {
            return null;
        }

        return [
            'kunci' => "tersalin-{$kunci}",
            'tautan' => $tautan,
            'tingkat' => 'saran',
            'pesan' => "{$tempat} “".Str::limit(trim($uraian), 80)."” memuat kata khas kegiatan lain “{$terbaik['kegiatan']['nama']}” ("
                .implode(', ', $terbaik['kata']).') — periksa apakah tersalin.',
            ...$terbaik,
        ];
    }
}
