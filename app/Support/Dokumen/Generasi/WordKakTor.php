<?php

namespace App\Support\Dokumen\Generasi;

use App\Support\Dokumen\Generasi\DocxSederhana as D;
use App\Support\Dokumen\SusunanKak;

/**
 * Penyusun Word (.docx) KAK/TOR di server — kembaran `resources/js/word-kak.js` (penyusun di peramban) dengan masukan yang
 * sama, data pratinjau DataPratinjau::untuk (`dokumen.cover|kak|rab`, `cetak.nama_berkas`): cover (kotak teks urut posisi,
 * jarak dari persen tinggi A4), bagian KAK/TOR menurut formatnya (9 bagian / 5 bab, judul ber-`label`; paragraf rata kanan-kiri berinden, subjudul, daftar bernomor,
 * penanda isian kosong merah miring, tabel matrik tahapan "v" per bulan, tabel jadwal, lembar pengesahan), dan RAB bila
 * lembarnya ada (kepala, tabel rincian, total & terbilang, mekanisme pembayaran, pengesahan). A4, margin 2 cm, Times New
 * Roman 12 pt, kepala "DRAF" & kaki "DRAF — alasan … Halaman x dari y" selama dokumen belum siap. Lewat DocxSederhana.
 */
final class WordKakTor
{
    public const JUDUL = 'KERANGKA ACUAN KERJA (KAK) / TERM OF REFERENCE (TOR)';

    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    private const MERAH = 'B91C1C';

    /** Lebar isi A4 dikurangi margin kiri & kanan 2 cm (twip). */
    private const LEBAR_ISI = D::A4[0] - 4 * D::CM;

    private const RATA = ['left' => 'left', 'center' => 'center', 'right' => 'right', 'kiri' => 'left', 'tengah' => 'center', 'kanan' => 'right'];

    /** @param  array<string, mixed>  $data  DataPratinjau::untuk. */
    public static function susun(array $data): string
    {
        $bagian = [];
        if (! empty($data['dokumen']['cover'])) {
            $bagian[] = self::cover($data['dokumen']['cover']);
        }
        if (! empty($data['dokumen']['kak'])) {
            $bagian[] = self::kak($data['dokumen']['kak']);
        }
        if (! empty($data['dokumen']['rab'])) {
            $bagian[] = self::rab($data['dokumen']['rab']);
        }

        return D::tulis($bagian, [
            'judul' => (string) preg_replace('/\.pdf$/', '', (string) ($data['cetak']['nama_berkas'] ?? $data['nama_kegiatan'] ?? 'KAK-TOR')),
            'deskripsi' => trim('KAK/TOR '.($data['nama_kegiatan'] ?? '')),
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private static function cover(array $data): array
    {
        $kotak = $data['teks'] ?? [];
        usort($kotak, fn (array $a, array $b) => $a['y'] <=> $b['y']);
        $sebelum = 6;
        $isi = [];
        foreach ($kotak as $k) {
            $teks = $data['nilai'][$k['field']] ?? null;
            if (blank($teks)) {
                continue;
            }
            // Persen tinggi A4 (29,7 cm) → twip, dikurangi tinggi baris sebelumnya agar cover tetap satu halaman.
            $jarak = max(0, ($k['y'] - $sebelum) * 0.297 * D::CM - 2 * $k['font_size'] * 20);
            $sebelum = $k['y'];
            $isi[] = D::paragraf([D::run((string) $teks, ['tebal' => ($k['weight'] ?? 'bold') !== 'normal', 'ukuran' => (int) round($k['font_size'] * 2)])], [
                'rata' => self::RATA[$k['align'] ?? 'center'] ?? 'center', 'sebelum' => (int) round($jarak), 'sesudah' => 0, 'baris' => 240,
            ]);
        }

        return ['isi' => $isi ?: [D::paragraf('')], 'kepala' => self::kepala($data)];
    }

    /** @param  array<string, mixed>  $data */
    private static function kak(array $data): array
    {
        $isi = [D::paragraf([D::run(self::JUDUL, ['tebal' => true, 'ukuran' => 26])], ['rata' => 'center', 'sesudah' => 240])];
        foreach ($data['bagian'] ?? [] as $bagian) {
            $isi[] = D::paragraf([D::run(($bagian['label'] ?? $bagian['nomor']).'. '.mb_strtoupper((string) $bagian['judul']), ['tebal' => true])], ['sebelum' => 240, 'sesudah' => 120, 'tetap_bersama' => true]);
            foreach ($bagian['isi'] ?? [] as $blok) {
                array_push($isi, ...self::blok($blok));
            }
        }

        return ['isi' => $isi, 'kepala' => self::kepala($data), 'kaki' => self::kaki($data)];
    }

    /**
     * @param  array<string, mixed>  $blok
     * @return list<string>
     */
    private static function blok(array $blok): array
    {
        return match ($blok['jenis'] ?? null) {
            'paragraf' => [D::paragraf((string) ($blok['teks'] ?? ''), ['rata' => 'both', 'pertama' => 709])],
            'subjudul' => [D::paragraf([D::run((string) ($blok['teks'] ?? ''), ['tebal' => true])], ['sebelum' => 120, 'sesudah' => 80, 'tetap_bersama' => true] + (($blok['tingkat'] ?? 1) > 1 ? ['kiri' => 360] : []))],
            'daftar' => self::daftar($blok['butir'] ?? [], ($blok['bernomor'] ?? true) !== false),
            'kosong', 'menunggu' => [D::paragraf([D::run('['.($blok['pesan'] ?? '').']', ['miring' => true, 'warna' => self::MERAH])])],
            'matrik' => self::matrik($blok),
            'jadwal' => self::jadwal($blok),
            'pengesahan' => self::pengesahan($blok['kolom'] ?? []),
            default => [], // pagu & blok layar lain tidak tercetak
        };
    }

    /**
     * @param  list<string>  $butir
     * @return list<string>
     */
    private static function daftar(array $butir, bool $bernomor): array
    {
        return array_map(fn (string $teks, int $i) => D::paragraf([D::run(($bernomor ? ($i + 1).'.' : '•')."\t".$teks)], [
            'rata' => 'both', 'kiri' => 720, 'gantung' => 360, 'tab' => [['left', 720]], 'sesudah' => 60,
        ]), $butir, array_keys($butir));
    }

    /**
     * Lebar kolom (twip) dari persen; kolom null mengambil sisanya.
     *
     * @param  list<float|int|null>  $persen
     * @return list<int>
     */
    private static function lebar(array $persen): array
    {
        $tetap = array_sum(array_map(fn ($p) => $p ?? 0, $persen));
        $sisa = max(1, count(array_filter($persen, fn ($p) => $p === null)));

        return array_map(fn ($p) => (int) round(self::LEBAR_ISI * ($p ?? (100 - $tetap) / $sisa) / 100), $persen);
    }

    private static function teksSel(string $teks, int $ukuran, array $opsi = []): string
    {
        return D::paragraf([D::run($teks, ['ukuran' => $ukuran] + $opsi)], ['rata' => $opsi['rata'] ?? 'left', 'sesudah' => 0, 'baris' => 240]);
    }

    /**
     * @param  array<string, mixed>  $blok
     * @return list<string>
     */
    private static function matrik(array $blok): array
    {
        $bulan = [40, 40, 15, 15];
        $baris = [D::baris([
            D::sel(self::teksSel('No', 18, ['tebal' => true, 'rata' => 'center'])),
            D::sel(self::teksSel('Tahapan / Uraian', 18, ['tebal' => true])),
            ...array_map(fn (string $b) => D::sel(self::teksSel($b, 14, ['tebal' => true, 'rata' => 'center']), ['margin' => $bulan]), self::BULAN),
        ], ['kepala' => true])];
        foreach ($blok['tahapan'] ?? [] as $tahap) {
            $baris[] = D::baris([
                D::sel(self::teksSel((string) $tahap['nomor'], 18, ['tebal' => true, 'rata' => 'center'])),
                D::sel(self::teksSel((string) $tahap['judul'], 18, ['tebal' => true]), ['span' => 13]),
            ]);
            foreach ($tahap['butir'] ?? [] as $butir) {
                $baris[] = D::baris([
                    D::sel(''),
                    D::sel(self::teksSel((string) ($butir['uraian'] ?? ''), 18)),
                    ...array_map(fn (int $n) => D::sel(self::teksSel(in_array($n, array_map('intval', $butir['bulan'] ?? []), true) ? 'v' : '', 18, ['rata' => 'center']), ['margin' => $bulan]), range(1, 12)),
                ], ['utuh' => true]);
            }
        }

        return [D::tabel($baris, self::lebar([6, null, ...array_fill(0, 12, 4.6)])), D::paragraf('')];
    }

    /**
     * @param  array<string, mixed>  $blok
     * @return list<string>
     */
    private static function jadwal(array $blok): array
    {
        $baris = [D::baris(array_map(fn (string $j) => D::sel(self::teksSel($j, 20, ['tebal' => true, 'rata' => 'center'])), ['No', 'Uraian Kegiatan', 'Waktu Pelaksanaan']), ['kepala' => true])];
        foreach ($blok['baris'] ?? [] as $b) {
            $baris[] = D::baris([D::sel(self::teksSel((string) $b['nomor'], 20, ['rata' => 'center'])), D::sel((string) ($b['uraian'] ?? '')), D::sel((string) ($b['waktu'] ?? ''))], ['utuh' => true]);
        }

        return [D::tabel($baris, self::lebar([8, null, 32])), D::paragraf('')];
    }

    /**
     * @param  list<array<string, mixed>>  $kolom
     * @return list<string>
     */
    private static function pengesahan(array $kolom): array
    {
        if ($kolom === []) {
            return [];
        }
        $tengah = fn (string $teks, array $opsi = [], int $sesudah = 0) => D::paragraf([D::run($teks, $opsi)], ['rata' => 'center', 'sesudah' => $sesudah, 'baris' => 240]);
        $isiSel = fn (array $t) => array_values(array_filter([
            $tengah((string) ($t['tempat_tanggal'] ?? '')),
            $tengah((string) ($t['peran'] ?? '')),
            $tengah((string) ($t['jabatan'] ?? ''), ['tebal' => true], 1200),
            filled($t['nama'] ?? null) ? $tengah((string) $t['nama'], ['tebal' => true, 'garis_bawah' => true]) : $tengah('( …………………………… )', ['warna' => '78716C']),
            ($t['tanpa_nip'] ?? false) ? null : $tengah('NIP. '.($t['nip'] ?? '……………………')),
        ]));
        // Format 9 bagian: satu baris sejajar. Format 5 bab: baris 1 dua kolom, baris 2 satu kolom di tengah (SusunanKak::barisPengesahan).
        $daftarBaris = SusunanKak::barisPengesahan($kolom);
        $jumlahKolom = max(array_map('count', $daftarBaris));
        $lebar = (int) round(self::LEBAR_ISI / $jumlahKolom);
        $baris = [];
        foreach ($daftarBaris as $i => $isiBaris) {
            if ($i > 0) {
                $baris[] = D::baris([D::sel([D::paragraf('')], ['tanpa_garis' => true, 'span' => $jumlahKolom])], ['utuh' => true]);
            }
            $sel = array_map(fn (array $t) => D::sel($isiSel($t), ['tanpa_garis' => true] + (count($isiBaris) === 1 && $jumlahKolom > 1 ? ['span' => $jumlahKolom] : [])), $isiBaris);
            $baris[] = D::baris($sel, ['utuh' => true]);
        }

        return [D::paragraf(''), D::tabel($baris, array_fill(0, $jumlahKolom, $lebar), ['tanpa_garis' => true])];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{isi: list<string>, kepala: list<string>, kaki: list<string>}
     */
    private static function rab(array $data): array
    {
        $t = $data['tabel'];
        $persen = ['No' => 6, 'Kode' => 11, 'Vol' => 7, 'Satuan' => 12, 'Harga Satuan (Rp)' => 14, 'Jumlah (Rp)' => 15];
        $lebar = self::lebar(array_map(fn (string $k) => $k === 'Uraian' ? null : ($persen[$k] ?? 10), $t['kolom']));
        $kanan = fn (string $k) => in_array($k, ['Vol', 'Harga Satuan (Rp)', 'Jumlah (Rp)'], true);
        $baris = [D::baris(array_map(fn (string $k) => D::sel(self::teksSel($k, 18, ['tebal' => true, 'rata' => 'center']), ['isian' => 'F5F5F4']), $t['kolom']), ['kepala' => true])];
        foreach ($t['baris'] ?? [] as $b) {
            $baris[] = D::baris(array_map(fn ($isi, int $i) => D::sel(self::teksSel((string) $isi, 18, ['rata' => $kanan($t['kolom'][$i]) ? 'right' : 'left'])), $b, array_keys($b)), ['utuh' => true]);
        }
        $baris[] = D::baris([
            D::sel(self::teksSel('TOTAL KESELURUHAN', 18, ['tebal' => true, 'rata' => 'right']), ['span' => count($t['kolom']) - 1]),
            D::sel(self::teksSel((string) ($t['total_teks'] ?? ''), 18, ['tebal' => true, 'rata' => 'right'])),
        ], ['utuh' => true]);
        if (filled($t['terbilang'] ?? null)) {
            $baris[] = D::baris([D::sel(self::teksSel('Terbilang: '.$t['terbilang'], 18, ['miring' => true, 'rata' => 'right']), ['span' => count($t['kolom'])])], ['utuh' => true]);
        }

        $isi = [D::paragraf([D::run(mb_strtoupper((string) $data['judul']), ['tebal' => true])], ['rata' => 'center', 'sesudah' => 240])];
        foreach ($data['kepala'] ?? [] as $k) {
            $isi[] = D::paragraf([D::run($k['label']."\t: ".($k['nilai'] ?? '…').(filled($k['uraian'] ?? null) ? ' — '.$k['uraian'] : ''))], ['tab' => [['left', 3 * D::CM + 1000]], 'sesudah' => 40, 'kiri' => 3 * D::CM + 1000, 'gantung' => 3 * D::CM + 1000]);
        }
        $isi[] = D::paragraf('');
        $isi[] = D::tabel($baris, $lebar);
        $isi[] = D::paragraf('');
        foreach ($data['mekanisme'] ?? [] as $m) {
            $isi[] = D::paragraf($m['label'].' : '.$m['nilai'], ['sesudah' => 40]);
        }
        array_push($isi, ...self::pengesahan($data['pengesahan'] ?? []));

        return ['isi' => $isi, 'kepala' => self::kepala($data), 'kaki' => self::kaki($data)];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function kepala(array $data): array
    {
        return ($data['draf'] ?? false) ? [D::paragraf([D::run('DRAF', ['tebal' => true, 'warna' => self::MERAH, 'ukuran' => 20])], ['rata' => 'right', 'sesudah' => 0])] : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function kaki(array $data): array
    {
        $alasan = $data['alasan'][0] ?? null;
        $alasan = is_array($alasan) ? ($alasan['teks'] ?? null) : $alasan;

        return [D::paragraf([
            ...(($data['draf'] ?? false) ? [D::run('DRAF — '.($alasan ?? 'belum siap dicetak'), ['ukuran' => 16, 'warna' => self::MERAH])] : []),
            D::run("\tHalaman ", ['ukuran' => 16]), D::nomorHalaman('PAGE', ['ukuran' => 16]),
            D::run(' dari ', ['ukuran' => 16]), D::nomorHalaman('NUMPAGES', ['ukuran' => 16]),
        ], ['tab' => [['right', self::LEBAR_ISI]], 'sesudah' => 0, 'baris' => 240])];
    }
}
