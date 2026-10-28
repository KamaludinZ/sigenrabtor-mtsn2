<?php

namespace App\Support\Dokumen;

use App\Support\Kegiatan\KelengkapanKode;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Pengaturan\ValidasiPejabat;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\MekanismePembayaran;
use App\Support\Stub\StubData;

/**
 * Kaidah dokumen otomatis (PRD §17 & §8 "Aturan Otomatis & Validasi") untuk satu kegiatan × sumber dana: tiap aturan
 * diperiksa dari sumber yang sama dengan dokumen (AngkaDokumen, SusunanKak/SusunanRab, MasterKode, StatusPagu,
 * MekanismePembayaran, PenandaTangan) sehingga panel di layar dan isi dokumen tidak pernah berbeda.
 *
 * Status: `lulus`, `peringatan` (dokumen tetap bisa dicetak dengan cap DRAF), `gagal` (angka/kode salah — harus
 * diperbaiki), `info` (dijamin sistem).
 */
final class KaidahDokumen
{
    /** Kunci aturan → [nomor aturan PRD §8, judul]. */
    public const ATURAN = [
        'total' => [1, 'Total dihitung, tidak diketik'],
        'terbilang' => [2, 'Terbilang otomatis'],
        'tahun' => [3, 'Satu tahun untuk semua'],
        'penanda_tangan' => [4, 'Penanda tangan terikat'],
        'pagu' => [5, 'Batas pagu'],
        'termin' => [6, 'Termin harus pas'],
        'kode' => [7, 'Kode wajib valid'],
        'kelengkapan' => [8, 'Cek kelengkapan saat cetak'],
        'format' => [10, 'Format angka & tanggal Indonesia'],
    ];

    /** Kolom kode kegiatan → [label, daftar master, kunci pencarian]. */
    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array{teks: list<array<string, mixed>>, nilai: array<string, string>}  $cover  Isi cover yang ditampilkan (sinkronisasi tahun).
     * @return list<array{kunci: string, nomor: int, judul: string, status: string, pesan: string}>
     */
    public static function periksa(array $kegiatan, SusunanKak $kak, SusunanRab $rab, array $cover = ['teks' => [], 'nilai' => []]): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $anggaran = $rab->anggaran ?? (AnggaranKegiatan::perSumber($kegiatan)[$kak->sumber] ?? null);
        $angka = $anggaran ? AngkaDokumen::dari($anggaran->hasil, $anggaran->sumber, $tahun, StubData::madrasah()['nama_singkat']) : null;
        $temuan = $angka ? array_merge(...array_column($angka->tempatCetak(), 'temuan')) : [];
        $hasil = [];

        $tambah = function (string $kunci, string $status, string $pesan) use (&$hasil): void {
            [$nomor, $judul] = self::ATURAN[$kunci];
            $hasil[] = compact('kunci', 'nomor', 'judul', 'status', 'pesan');
        };

        // 1–2. Nominal & terbilang di KAK Pembiayaan, header RAB, dan baris Total dari total RAB yang sama.
        if (! $angka?->adaRincian()) {
            $tambah('total', 'peringatan', $anggaran ? "Belum ada rincian RAB {$anggaran->sumber} — total & terbilang muncul setelah item ditambahkan." : 'Pilih sumber dana agar RAB dapat disusun.');
            $tambah('terbilang', 'peringatan', 'Menunggu total RAB.');
        } else {
            $salahNominal = array_filter($temuan, fn (array $t) => ! str_contains(mb_strtolower($t['masalah']), 'terbilang'));
            $salahEja = array_filter($temuan, fn (array $t) => str_contains(mb_strtolower($t['masalah']), 'terbilang'));
            $tambah('total', $salahNominal === [] ? 'lulus' : 'gagal', $salahNominal === [] ? 'Total KAK = total RAB = jumlah yang diajukan: '.$angka->nominal().'.' : count($salahNominal).' tempat cetak berbeda dengan total RAB.');
            $tambah('terbilang', $salahEja === [] ? 'lulus' : 'gagal', $salahEja === [] ? '“'.$angka->terbilang().'”.' : count($salahEja).' terbilang tidak sesuai nominalnya.');
        }

        // 3. Tahun dokumen = tahun anggaran aktif; kegiatan milik tahun lain atau tanggal di luar TA diberi peringatan.
        $tanggal = TanggalDokumen::dari($kegiatan, $kak->sumber, $tahun);
        $tahunKegiatan = TahunKegiatan::dari($kegiatan);
        $luarTahun = array_values(array_unique(array_map(fn (array $t) => "{$t['dokumen']} · {$t['bagian']}", array_filter($tanggal, fn (array $t) => collect($t['temuan'])->contains('jenis', 'tahun')))));
        $tahunBeda = TahunDokumen::beda(TahunDokumen::dari($kegiatan, $kak, $rab, $cover, $tahun), $tahun);
        $tambah('tahun', $tahunKegiatan === $tahun && $luarTahun === [] && $tahunBeda === [] ? 'lulus' : 'peringatan', match (true) {
            $tahunKegiatan !== $tahun => "Kegiatan tercatat di TA {$tahunKegiatan}, tetapi dokumen dicetak dengan TA aktif {$tahun}.",
            $luarTahun !== [] => "Tanggal di luar TA {$tahun}: ".implode(', ', $luarTahun).'.',
            $tahunBeda !== [] => 'Teks menyebut tahun lain: '.implode(', ', $tahunBeda).' — pastikan bukan sisa dokumen tahun lalu.',
            default => "Cover, judul, dasar hukum, jadwal, tanggal, dan RAB memakai TA {$tahun}.",
        });

        // 4. Penanda tangan dari pejabat tahun aktif: nama terisi, NIP tercetak sah, tidak bentrok antar-kolom.
        $ttd = ValidasiPejabat::pengesahan($rab->pengesahan);
        $tambah('penanda_tangan', $ttd['status'], $ttd['temuan'] === []
            ? 'Nama & NIP dari pejabat leading dan pimpinan TA '.$tahun.'.'
            : implode('; ', $ttd['temuan']).' (Pengaturan › Profil & Pejabat).');

        // 5. Batas pagu.
        if ($anggaran) {
            $pagu = $anggaran->keteranganPagu();
            $tambah('pagu', match ($pagu->status) {
                'melebihi' => 'gagal',
                'tanpa_pagu' => 'peringatan',
                default => 'lulus',
            }, $pagu->teks().'.');

            // 6. Termin = total RAB.
            $mekanisme = MekanismePembayaran::dari($anggaran);
            $tambah('termin', in_array($mekanisme->status(), ['kurang', 'lebih'], true) ? 'gagal' : 'lulus', $mekanisme->teksStatus().'.');
        }

        // 7. Kode dipilih dari master tahun aktif (KelengkapanKode: kosong, tidak ada di master, IKSK bukan indikator SK).
        $belum = [];
        $asing = [];
        $tidakSesuai = [];
        foreach (KelengkapanKode::periksa($kegiatan, $tahun) as $kode) {
            match ($kode['status']) {
                'kosong' => $belum[] = $kode['label'],
                'asing' => $asing[] = "{$kode['label']} {$kode['kode']}",
                'tidak_sesuai' => $tidakSesuai[] = $kode['pesan'],
                default => null,
            };
        }
        $tambah('kode', $asing !== [] || $tidakSesuai !== [] ? 'gagal' : ($belum !== [] ? 'peringatan' : 'lulus'), match (true) {
            $asing !== [] || $tidakSesuai !== [] => trim(($asing !== [] ? 'Tidak ada di master TA '.$tahun.': '.implode(', ', $asing).'. ' : '').implode(' ', $tidakSesuai)),
            $belum !== [] => 'Belum dipilih: '.implode(', ', $belum).'.',
            default => 'Semua kode dipilih dari master TA '.$tahun.'.',
        });

        // 8. Kelengkapan KAK/TOR & RAB (dokumen tetap bisa dicetak dengan cap DRAF).
        $kurang = array_values(array_unique([...$kak->kurang, ...$rab->kurang]));
        $tambah('kelengkapan', $kurang === [] ? 'lulus' : 'peringatan', $kurang === []
            ? 'Semua isian KAK/TOR & RAB lengkap.'
            : count($kurang).' isian belum lengkap — dokumen dicetak dengan cap DRAF.');

        // 10. Format Indonesia dijamin Format/Terbilang; urutan tanggal yang janggal diberi peringatan.
        $tercetak = array_values(array_filter(array_column($tanggal, 'teks')));
        $urutan = TanggalDokumen::temuan($tanggal, 'urutan');
        $tambah('format', $urutan !== [] ? 'peringatan' : ($tercetak === [] ? 'info' : 'lulus'), match (true) {
            $urutan !== [] => implode(' ', $urutan),
            $tercetak === [] => 'Rupiah dengan titik ribuan (Rp10.000.000,-) dan tanggal seperti “23 Desember '.$tahun.'”.',
            default => 'Rupiah dengan titik ribuan; '.count($tercetak).' tanggal ditulis lengkap, mis. “'.$tercetak[0].'”.',
        });

        return $hasil;
    }

    /**
     * Jumlah aturan per status.
     *
     * @param  list<array{status: string}>  $kaidah
     * @return array{lulus: int, peringatan: int, gagal: int, info: int}
     */
    public static function ringkasan(array $kaidah): array
    {
        return array_merge(['lulus' => 0, 'peringatan' => 0, 'gagal' => 0, 'info' => 0], array_count_values(array_column($kaidah, 'status')));
    }
}
