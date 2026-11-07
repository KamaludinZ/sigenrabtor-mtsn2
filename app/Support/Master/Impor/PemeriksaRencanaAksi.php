<?php

namespace App\Support\Master\Impor;

use App\Support\Format;
use App\Support\Impor\LembarExcel;
use App\Support\Kegiatan\Impor\PemeriksaImporKegiatan;
use App\Support\Master\FormatKode;
use App\Support\Master\RencanaAksi;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Pembacaan & pemeriksaan Rencana Aksi dari sheet Perkin (cascade Perjanjian Kinerja) atau template master: satu baris
 * pratinjau per rencana aksi beserta tag kodenya — SK & IKSK (sel kosong mengikuti kode di atasnya; SK baru membuka
 * kelompok IKSK baru), kode RKTM (sub kegiatan), unit/tim penerima mandat → leading (RencanaAksi::cocokkanLeading),
 * anggaran DIPA & Komite, dan bulan pelaksanaan (kolom bulan 1–12 bertanda X/v/✓/angka; "-" atau kosong = tidak).
 * Kode tampil "RA.n" menurut urutan berkas. Rencana aksi ganda (nama sama di IKSK yang sama) gagal; IKSK yang tidak sejalan
 * dengan SK-nya dan unit yang belum cocok dengan leading hanya peringatan.
 */
final class PemeriksaRencanaAksi
{
    public const JUDUL = [
        'uraian' => ['rencana aksi', 'uraian rencana aksi', 'nama rencana aksi'],
        'kode_sk' => ['kode sk', 'kode sasaran kegiatan'],
        'kode_iksk' => ['kode iksk', 'kode indikator kinerja', 'kode indikator'],
        'kode_rktm' => ['kode rktm', 'kode sub kegiatan', 'kode rktm sub kegiatan'],
        'unit' => ['unit tim penerima mandat', 'unit penerima mandat', 'tim penerima mandat', 'unit', 'penanggung jawab', 'leading'],
        'pagu_dipa' => ['anggaran dipa', 'anggaran kegiatan dipa rp', 'anggaran kegiatan dipa', 'pagu dipa', 'dipa', 'bos'],
        'pagu_komite' => ['anggaran komite', 'anggaran kegiatan komite rp', 'anggaran kegiatan komite', 'pagu komite', 'komite'],
    ];

    /** Isi sel bulan yang berarti "tidak dilaksanakan". */
    private const BUKAN_TANDA = ['', '-', '0', 'tidak', 'x-', '—'];

    /**
     * @return list<array{baris: list<int>, kode: string, tampil: array<string, string>, status: string|null, pesan: list<string>}>
     *
     * @throws GagalPratinjauMaster Judul kolom tidak ditemukan.
     */
    public static function baca(Worksheet $lembar): array
    {
        $judul = KolomMaster::judul($lembar, 'rencana_aksi');
        $bulan = $judul['bulan'] ?? [];

        $hasil = [];
        $posisi = [];
        [$skTerakhir, $ikskTerakhir] = ['', ''];
        $nomor = 0;
        for ($baris = $judul['baris'] + 1; $baris <= $lembar->getHighestDataRow(); $baris++) {
            $sel = fn (string $kunci) => isset($judul['kolom'][$kunci]) ? LembarExcel::nilaiSel($lembar, $judul['kolom'][$kunci].$baris) : null;
            $teks = fn (string $kunci) => trim((string) $sel($kunci));

            // Cascade: kode SK baru membuka kelompok IKSK baru; sel kosong mengikuti kode di atasnya.
            $skMentah = $teks('kode_sk');
            if ($skMentah !== '' && ! KolomMaster::judulKelompok($skMentah, [])) {
                $ikskTerakhir = $skMentah !== $skTerakhir ? '' : $ikskTerakhir;
                $skTerakhir = $skMentah;
            }
            if (($ikskMentah = $teks('kode_iksk')) !== '') {
                $ikskTerakhir = $ikskMentah;
            }
            $uraian = $teks('uraian');
            if ($uraian === '') {
                continue;
            }
            $nomor++;

            $sk = $skTerakhir === '' ? null : FormatKode::sk($skTerakhir);
            $iksk = $ikskTerakhir === '' ? null : FormatKode::iksk($ikskTerakhir);
            $rktmMentah = $teks('kode_rktm');
            $rktm = $rktmMentah === '' ? null : PemeriksaRktm::normalkanKode($rktmMentah);
            $unit = $teks('unit');
            $leading = $unit === '' ? null : RencanaAksi::cocokkanLeading($unit);
            [$dipa, $galatDipa] = self::angka($sel('pagu_dipa'));
            [$komite, $galatKomite] = self::angka($sel('pagu_komite'));
            $bulanTerpilih = [];
            foreach ($bulan as $n => $huruf) {
                $tanda = mb_strtolower(trim((string) LembarExcel::nilaiSel($lembar, $huruf.$baris)));
                if (! in_array($tanda, self::BUKAN_TANDA, true)) {
                    $bulanTerpilih[] = $n;
                }
            }

            $tampil = [
                'uraian' => $uraian,
                'kode_sk' => $sk ?? $skTerakhir,
                'kode_iksk' => $iksk ?? $ikskTerakhir,
                'kode_rktm' => $rktm ?? $rktmMentah,
                'unit' => $unit,
                'leading' => $leading ?? '',
                'pagu_dipa' => (string) ($dipa ?? 0),
                'pagu_komite' => (string) ($komite ?? 0),
                'bulan' => implode(', ', $bulanTerpilih),
            ];
            $gagal = array_values(array_filter([
                $skTerakhir !== '' && $sk === null ? "Kode SK \"{$skTerakhir}\" tidak berformat kode SK (mis. ".FormatKode::CONTOH_SK.').' : null,
                $ikskTerakhir !== '' && $iksk === null ? "Kode IKSK \"{$ikskTerakhir}\" tidak berformat kode IKSK (mis. ".FormatKode::CONTOH_IKSK.').' : null,
                $rktmMentah !== '' && $rktm === null ? "Kode RKTM \"{$rktmMentah}\" tidak berformat n.n.n (mis. 2.3.1) — format sel Teks agar tidak terbaca angka." : null,
                $galatDipa ? 'Anggaran DIPA bukan angka rupiah yang sah.' : null,
                $galatKomite ? 'Anggaran Komite bukan angka rupiah yang sah.' : null,
                ...KolomMaster::galatPanjang('rencana_aksi', ['uraian' => $uraian, 'unit' => $unit]),
            ]));
            $kode = 'RA.'.$nomor;
            if ($gagal !== []) {
                $hasil[] = ['baris' => [$baris], 'kode' => $kode, 'tampil' => $tampil, 'status' => 'gagal', 'pesan' => $gagal];

                continue;
            }

            $kunci = RencanaAksi::kunci($uraian, $tampil['kode_iksk']);
            if (isset($posisi[$kunci])) {
                $hasil[] = ['baris' => [$baris], 'kode' => $kode, 'tampil' => $tampil, 'status' => 'gagal',
                    'pesan' => ["Rencana aksi yang sama sudah ada di baris {$hasil[$posisi[$kunci]]['baris'][0]}".($tampil['kode_iksk'] !== '' ? " untuk {$tampil['kode_iksk']}" : '').'.']];

                continue;
            }

            $posisi[$kunci] = count($hasil);
            $hasil[] = ['baris' => [$baris], 'kode' => $kode, 'tampil' => $tampil, 'status' => null, 'pesan' => array_values(array_filter([
                $sk !== null && $iksk !== null && ! FormatKode::sejalan($iksk, $sk) ? "{$iksk} tidak sejalan dengan {$sk} — periksa lagi." : null,
                $unit !== '' && $leading === null ? "Unit \"{$unit}\" belum cocok dengan leading — pilih leading-nya di tag Master Kode." : null,
                $rktm === null ? 'Tanpa kode RKTM — tag RKTM & EDM diisi manual.' : null,
            ]))];
        }

        return $hasil;
    }

    /** Kunci pembanding nama rencana aksi (sama dengan nama kegiatan). */
    public static function kunciNama(string $uraian): string
    {
        return PemeriksaImporKegiatan::kunciNama($uraian);
    }

    /**
     * Sel anggaran → [rupiah utuh|null (kosong), galat].
     *
     * @return array{0: int|null, 1: bool}
     */
    private static function angka(string|int|float|null $nilai): array
    {
        if ($nilai === null || $nilai === '' || $nilai === '-') {
            return [null, false];
        }
        if (is_int($nilai) || is_float($nilai)) {
            return $nilai >= 0 ? [(int) round($nilai), false] : [null, true];
        }
        $teks = trim($nilai);
        if (str_starts_with($teks, '-') || ! preg_match('/\d/', $teks) || preg_match('/[a-z]/i', (string) preg_replace('/^rp\.?/i', '', $teks))) {
            return [null, true];
        }

        return [Format::parseRupiah($teks), false];
    }
}
