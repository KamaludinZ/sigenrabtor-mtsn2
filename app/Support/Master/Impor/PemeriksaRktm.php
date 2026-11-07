<?php

namespace App\Support\Master\Impor;

use App\Support\Impor\LembarExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pembacaan & pemeriksaan kode RKTM dari sheet SNP (atau sheet/CSV khusus RKTM, sheet gabungan 5BM_SNP, atau daftar RKTM
 * per leading seperti 8SNP): Kode (n.n.n), Sub kegiatan, serta Standar (SNP) & Kegiatan yang opsional — satu baris
 * pratinjau per kode. Kode RKTM berubah tiap tahun, jadi daftar baru diterima apa adanya; isian yang tidak ditulis
 * dilengkapi `lengkapi()`:
 * - SNP: sel kosong mengikuti baris di atasnya / baris judul standar ("PENGEMBANGAN STANDAR ISI"); SNP turunan itu
 *   diganti SNP terbanyak kode berstandar sama (bagian pertama kode) di berkas, lalu master rujukan (SNP yang ditulis di
 *   barisnya sendiri tidak pernah diganti);
 * - Kegiatan: mengikuti baris di atasnya hanya untuk kode n.n yang sama, lalu kegiatan terbanyak kode n.n sama di
 *   berkas, lalu master rujukan (tahun itu & tahun sebelumnya), selain itu "Kegiatan n.n" + catatan.
 * `asal` per baris mencatat dari mana SNP & kegiatan diambil (sel|atas|berkas|rujukan|tebak). SNP yang ditulis berbeda
 * untuk nomor standar yang sama hanya diberi peringatan.
 */
final class PemeriksaRktm
{
    public const JUDUL = [
        'kode' => ['kode rktm', 'kode', 'kode sub kegiatan'],
        'snp' => ['snp', 'standar snp', 'standar nasional pendidikan', 'standar'],
        'kegiatan' => ['kegiatan', 'program'],
        'sub_kegiatan' => ['sub kegiatan', 'subkegiatan', 'uraian sub kegiatan', 'uraian', 'program kegiatan', 'nama sub kegiatan'],
    ];

    /** "6.17.12", " 06 . 17 . 12", "6,17,12" → "6.17.12"; null bila bukan tiga bagian angka. */
    public static function normalkanKode(string $kode): ?string
    {
        $kode = str_replace([' ', ','], ['', '.'], trim($kode));

        return preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $kode, $cocok) ? ((int) $cocok[1]).'.'.((int) $cocok[2]).'.'.((int) $cocok[3]) : null;
    }

    /** "6.17.12" → "6.17" (kelompok kegiatan). */
    public static function awalan(string $kode): string
    {
        return implode('.', array_slice(explode('.', $kode), 0, 2));
    }

    /**
     * @param  list<array{kode: string, snp: string, kegiatan: string, sub_kegiatan: string}>  $rujukan  Master RKTM (tahun itu,
     *                                                                                                   lalu tahun sebelumnya) untuk melengkapi SNP & kegiatan yang tidak ditulis.
     * @return list<array{baris: list<int>, kode: string, tampil: array{sub_kegiatan: string, kegiatan: string, snp: string}, status: string|null, pesan: list<string>, asal: array{snp: string, kegiatan: string}}>
     *
     * @throws GagalPratinjauMaster Judul kolom tidak ditemukan.
     */
    public static function baca(Worksheet $lembar, array $rujukan = []): array
    {
        $judul = KolomMaster::judul($lembar, 'rktm');
        $sinonimKode = array_map([LembarExcel::class, 'normalkanJudul'], self::JUDUL['kode']);

        $hasil = [];
        $posisi = [];
        $sebelum = ['snp' => '', 'kegiatan' => '', 'awalan' => null];
        for ($baris = $judul['baris'] + 1; $baris <= $lembar->getHighestDataRow(); $baris++) {
            $sel = fn (string $kunci) => isset($judul['kolom'][$kunci]) ? trim((string) LembarExcel::nilaiSel($lembar, $judul['kolom'][$kunci].$baris)) : '';
            // Sel rumus kosong yang tampil "0" dan sel galat rumus (#REF!, #NAME?, …) dianggap kosong.
            [$mentah, $snp, $kegiatan, $sub] = array_map(fn (string $v) => $v === '0' || preg_match('/^#[A-Z\/0!?]+[!?]?$/', $v) ? '' : $v, [$sel('kode'), $sel('snp'), $sel('kegiatan'), $sel('sub_kegiatan')]);
            if ($mentah === '' && $sub === '') {
                // Baris judul kelompok (mis. hanya SNP/kegiatan) menjadi isian untuk baris di bawahnya.
                $sebelum = ['snp' => $snp !== '' ? $snp : $sebelum['snp'], 'kegiatan' => $kegiatan !== '' ? $kegiatan : $sebelum['kegiatan'], 'awalan' => $kegiatan !== '' ? null : $sebelum['awalan']];

                continue;
            }
            if (in_array(LembarExcel::normalkanJudul($mentah), $sinonimKode, true)) {
                continue; // Baris judul tabel yang diulang di tengah sheet.
            }

            $kode = self::normalkanKode($mentah);
            if ($kode === null && mb_strlen($mentah) <= 2 && $mentah === $sub && $sub === $kegiatan) {
                continue; // Baris contoh/penanda (mis. "n" di semua kolom).
            }
            if ($kode === null && KolomMaster::judulKelompok($mentah, [$sub])) {
                $sebelum = ['snp' => $mentah, 'kegiatan' => '', 'awalan' => null]; // Judul standar, mis. "PENGEMBANGAN STANDAR ISI".

                continue;
            }

            $asal = ['snp' => $snp !== '' ? 'sel' : 'atas', 'kegiatan' => $kegiatan !== '' ? 'sel' : 'atas'];
            $snp = $snp !== '' ? $snp : $sebelum['snp'];
            // Kegiatan dari baris di atasnya hanya untuk kode sekelompok (n.n sama) atau sesudah baris judul kegiatan.
            if ($kegiatan === '' && $kode !== null && ($sebelum['awalan'] === null || $sebelum['awalan'] === self::awalan($kode))) {
                $kegiatan = $sebelum['kegiatan'];
            }
            $tampil = ['sub_kegiatan' => $sub, 'kegiatan' => $kegiatan, 'snp' => $snp];
            $pesan = array_values(array_filter([
                $mentah === '' ? 'Kode RKTM kosong.' : ($kode === null ? "Kode \"{$mentah}\" tidak berformat n.n.n (mis. 6.17.12) — format sel Teks agar tidak terbaca angka." : null),
                $sub === '' ? 'Sub kegiatan kosong.' : null,
                ...KolomMaster::galatPanjang('rktm', $tampil),
            ]));
            if ($pesan !== []) {
                $hasil[] = ['baris' => [$baris], 'kode' => $kode ?? $mentah, 'tampil' => $tampil, 'status' => 'gagal', 'pesan' => $pesan, 'asal' => $asal];

                continue;
            }
            $sebelum = ['snp' => $snp, 'kegiatan' => $kegiatan, 'awalan' => self::awalan($kode)];

            if (isset($posisi[$kode])) {
                $awal = &$hasil[$posisi[$kode]];
                if ($awal['tampil'] === $tampil) {
                    $awal['baris'][] = $baris;
                } else {
                    $hasil[] = ['baris' => [$baris], 'kode' => $kode, 'tampil' => $tampil, 'status' => 'gagal',
                        'pesan' => ["Isi berbeda dengan baris {$awal['baris'][0]} untuk kode yang sama."], 'asal' => $asal];
                }
                unset($awal);

                continue;
            }

            $posisi[$kode] = count($hasil);
            $hasil[] = ['baris' => [$baris], 'kode' => $kode, 'tampil' => $tampil, 'status' => null, 'pesan' => [], 'asal' => $asal];
        }

        return self::lengkapi($hasil, $rujukan);
    }

    /**
     * Lengkapi SNP & kegiatan yang tidak ditulis (asal bukan `sel`) lalu periksa SNP per standar. Dipanggil ulang
     * PratinjauMaster sesudah baris dari sheet RKTM lain digabung (kode yang sama tidak dobel).
     *
     * @param  list<array<string, mixed>>  $baris
     * @param  list<array{kode: string, snp: string, kegiatan: string, sub_kegiatan: string}>  $rujukan
     * @return list<array<string, mixed>>
     */
    public static function lengkapi(array $baris, array $rujukan = []): array
    {
        $terbanyak = function (array $nilai): ?string {
            $nilai = array_filter($nilai, fn ($v) => $v !== '');
            if ($nilai === []) {
                return null;
            }
            $hitung = array_count_values($nilai);
            arsort($hitung);

            return (string) array_key_first($hitung);
        };
        $kodeSah = fn (array $b) => is_string($b['kode']) && self::normalkanKode($b['kode']) === $b['kode'];

        $snpSemua = $kegiatanSel = [];
        foreach ($baris as $b) {
            if (! $kodeSah($b) || $b['status'] === 'gagal') {
                continue;
            }
            $standar = strtok($b['kode'], '.');
            $snpSemua[$standar][] = $b['tampil']['snp'];
            if (($b['asal']['kegiatan'] ?? 'sel') === 'sel') {
                $kegiatanSel[self::awalan($b['kode'])][] = $b['tampil']['kegiatan'];
            }
        }
        $snpRujukan = $kegiatanRujukan = [];
        foreach ($rujukan as $r) {
            $snpRujukan[strtok($r['kode'], '.')] ??= $r['snp'];
            $kegiatanRujukan[self::awalan($r['kode'])] ??= $r['kegiatan'];
        }

        $snpPerStandar = [];
        foreach ($baris as $i => $b) {
            if (! $kodeSah($b) || $b['status'] === 'gagal') {
                continue;
            }
            $standar = strtok($b['kode'], '.');
            $awalan = self::awalan($b['kode']);
            $b['pesan'] = array_values(array_filter($b['pesan'], fn (string $p) => ! str_starts_with($p, 'Nama kegiatan ') && ! str_starts_with($p, 'SNP berbeda')));

            if (($b['asal']['snp'] ?? 'sel') !== 'sel') {
                $dariBerkas = $terbanyak($snpSemua[$standar] ?? []);
                $pilih = $dariBerkas ?? ($snpRujukan[$standar] ?? null);
                if ($pilih !== null && $pilih !== $b['tampil']['snp']) {
                    $b['tampil']['snp'] = $pilih;
                    $b['asal']['snp'] = $dariBerkas !== null ? 'berkas' : 'rujukan';
                }
            }
            if (($b['asal']['kegiatan'] ?? 'sel') !== 'sel') {
                $dariBerkas = $terbanyak($kegiatanSel[$awalan] ?? []);
                $berasal = $b['asal']['kegiatan'] === 'atas' && $b['tampil']['kegiatan'] !== '' ? 'atas' : null;
                if ($berasal === null) {
                    [$isi, $berasal] = match (true) {
                        $dariBerkas !== null => [$dariBerkas, 'berkas'],
                        isset($kegiatanRujukan[$awalan]) => [$kegiatanRujukan[$awalan], 'rujukan'],
                        default => ["Kegiatan {$awalan}", 'tebak'],
                    };
                    $b['tampil']['kegiatan'] = $isi;
                }
                $b['asal']['kegiatan'] = $berasal;
                if ($berasal === 'tebak') {
                    $b['pesan'][] = "Nama kegiatan {$awalan} belum ada di berkas maupun master — tercetak \"Kegiatan {$awalan}\"; isi kolom Kegiatan bila perlu.";
                }
            }

            if ($b['tampil']['snp'] === '') {
                $b['status'] = 'gagal';
                $b['pesan'][] = 'Standar (SNP) kosong.';
            } else {
                $snpPerStandar[$standar] ??= ['snp' => $b['tampil']['snp'], 'baris' => $b['baris'][0] ?? 0];
                if ($snpPerStandar[$standar]['snp'] !== $b['tampil']['snp']) {
                    $b['pesan'][] = "SNP berbeda dengan kode {$standar}.x di baris {$snpPerStandar[$standar]['baris']} ({$snpPerStandar[$standar]['snp']}).";
                }
            }
            $baris[$i] = $b;
        }

        return $baris;
    }
}
