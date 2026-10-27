<?php

namespace App\Support\Kegiatan\Impor;

use App\Enums\SumberDana;
use App\Livewire\Forms\KegiatanForm;
use App\Support\Format;
use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\Kegiatan\ValidasiKegiatan;

/**
 * Memeriksa baris impor satu per satu dan menjelaskan kondisinya kepada pengguna:
 * - "siap": akan dibuat sebagai kegiatan Draf;
 * - "dilewati": nama sudah ada di aplikasi (berkas aman diimpor ulang);
 * - "gagal": ada isian yang harus diperbaiki di berkas, alasannya dicantumkan.
 * Peringatan (mis. pagu kosong) tidak menghalangi impor.
 */
final class PemeriksaImporKegiatan
{
    public const MAKS_NAMA = 255;

    /**
     * @param  list<array{baris: int, nilai: array<string, mixed>}>  $barisMentah
     * @param  list<string>  $namaAda  Nama kegiatan yang sudah tersimpan.
     * @param  list<array{kode: string, nama_unit: string}>  $leadings  Leading yang boleh dipakai pengguna ini.
     * @param  list<array{kode: string, nama_unit: string}>  $semuaLeading  Seluruh leading master (untuk pesan yang jelas).
     * @return list<array{baris: int, nama: string, leading: string|null, leading_label: string|null, sumber_dana: list<array{sumber: string, pagu: int}>, bulan: list<int>, status: string, pesan: list<string>, peringatan: list<string>}>
     */
    public static function periksa(array $barisMentah, array $namaAda, array $leadings, array $semuaLeading, ?string $leadingBawaan = null): array
    {
        $sudahAda = array_flip(array_map(self::kunciNama(...), $namaAda));
        $dalamBerkas = [];
        $hasil = [];

        foreach ($barisMentah as ['baris' => $nomor, 'nilai' => $nilai]) {
            $pesan = [];
            $peringatan = [];
            $nama = trim((string) ($nilai['nama_kegiatan'] ?? ''));

            if ($nama === '') {
                $pesan[] = 'Nama kegiatan wajib diisi.';
            } elseif (mb_strlen($nama) > self::MAKS_NAMA) {
                $pesan[] = 'Nama kegiatan lebih dari '.self::MAKS_NAMA.' karakter ('.mb_strlen($nama).').';
            }

            [$leading, $pesanLeading] = self::leading($nilai['leading'] ?? null, $leadings, $semuaLeading, $leadingBawaan);
            if ($pesanLeading) {
                $pesan[] = $pesanLeading;
            }

            $sumber = [];
            foreach (['pagu_dipa' => SumberDana::Dipa, 'pagu_komite' => SumberDana::Komite] as $kolom => $jenis) {
                [$pagu, $pesanPagu] = self::pagu($nilai[$kolom] ?? null, $jenis);
                if ($pesanPagu) {
                    $pesan[] = $pesanPagu;
                } elseif ($pagu !== null) {
                    $sumber[] = ['sumber' => $jenis->value, 'pagu' => $pagu];
                }
            }
            if ($sumber === [] && $pesan === []) {
                $peringatan[] = 'Pagu DIPA dan Komite kosong — sumber dana dipilih nanti di Data Kegiatan.';
            }

            [$bulan, $pesanBulan] = self::bulan($nilai['bulan'] ?? null);
            if ($pesanBulan) {
                $pesan[] = $pesanBulan;
            }

            // Aturan isian yang sama dengan form Data Kegiatan (mis. nama minimal 5 karakter); leading sudah diperiksa di atas.
            if ($pesan === []) {
                array_push($pesan, ...ValidasiKegiatan::galat(
                    self::dataKegiatan(['nama' => $nama, 'leading' => $leading['kode'] ?? null, 'sumber_dana' => $sumber, 'bulan' => $bulan ?? []]),
                    kecuali: ['leading'],
                ));
            }

            $status = $pesan === [] ? 'siap' : 'gagal';
            $kunci = self::kunciNama($nama);

            if ($status === 'siap' && isset($sudahAda[$kunci])) {
                $status = 'dilewati';
                $pesan[] = 'Kegiatan dengan nama ini sudah ada di aplikasi.';
            } elseif ($nama !== '' && isset($dalamBerkas[$kunci])) {
                $status = 'gagal';
                $pesan[] = "Nama sama dengan baris {$dalamBerkas[$kunci]} di berkas ini.";
            }

            if ($nama !== '') {
                $dalamBerkas[$kunci] ??= $nomor;
            }

            $hasil[] = [
                'baris' => $nomor,
                'nama' => $nama,
                'leading' => $leading['kode'] ?? null,
                'leading_label' => $leading ? TemplateImporKegiatan::labelLeading($leading) : null,
                'sumber_dana' => $sumber,
                'bulan' => $bulan ?? [],
                'status' => $status,
                'pesan' => $pesan,
                'peringatan' => $status === 'gagal' ? [] : $peringatan,
            ];
        }

        return $hasil;
    }

    /**
     * Data kegiatan Draf dari baris yang siap (bentuk sama dengan isian form Data Kegiatan).
     *
     * @param  array{nama: string, leading: string|null, sumber_dana: list<array{sumber: string, pagu: int}>, bulan: list<int>}  $baris
     * @return array<string, mixed>
     */
    public static function dataKegiatan(array $baris): array
    {
        return [
            'nama_kegiatan' => $baris['nama'],
            'nama_kegiatan_cover' => mb_strtoupper($baris['nama']),
            'cover_manual' => false,
            'leading' => $baris['leading'],
            'sumber_dana' => $baris['sumber_dana'],
            'bulan_pelaksanaan' => $baris['bulan'],
            'status' => 'draf',
            'asal' => 'impor_excel',
        ];
    }

    /** Pembanding nama: tanpa beda huruf besar/kecil dan spasi ganda. */
    public static function kunciNama(string $nama): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $nama)));
    }

    /**
     * Kolom Leading (kode, nama unit, atau label template) → leading yang boleh dipakai; juga dipakai impor Data Pagu.
     *
     * @return array{0: array{kode: string, nama_unit: string}|null, 1: string|null}
     */
    public static function leading(mixed $nilai, array $leadings, array $semuaLeading, ?string $bawaan): array
    {
        $teks = trim((string) $nilai);

        if ($teks === '') {
            $leading = $bawaan ? collect($leadings)->firstWhere('kode', '===', $bawaan) : null;

            return [$leading, null];
        }

        $cocok = fn (array $l) => in_array(mb_strtolower($teks), [
            mb_strtolower($l['kode']),
            mb_strtolower($l['nama_unit']),
            mb_strtolower(TemplateImporKegiatan::labelLeading($l)),
            mb_strtolower($l['kode'].' - '.$l['nama_unit']),
        ], true);

        if ($leading = collect($leadings)->first($cocok)) {
            return [$leading, null];
        }

        if ($lain = collect($semuaLeading)->first($cocok)) {
            return [null, 'Leading "'.TemplateImporKegiatan::labelLeading($lain).'" bukan leading akun Anda.'];
        }

        return [null, "Leading \"{$teks}\" tidak ada di master tahun anggaran aktif."];
    }

    /**
     * Kolom pagu (angka atau teks rupiah) → rupiah bulat; juga dipakai impor Data Pagu.
     *
     * @return array{0: int|null, 1: string|null}
     */
    public static function pagu(mixed $nilai, SumberDana $sumber): array
    {
        if ($nilai === null || $nilai === '') {
            return [null, null];
        }

        $label = 'Pagu '.$sumber->value;

        if (is_int($nilai) || is_float($nilai)) {
            if (floor($nilai) != $nilai) {
                return [null, "{$label} harus rupiah bulat (tertulis {$nilai})."];
            }
            $pagu = (int) $nilai;
        } elseif (preg_match('/^\s*(rp\.?)?\s*[\d.\s]+(,-?|,00)?\s*$/i', (string) $nilai)) {
            // "Rp6.000.000,-" / "6.000.000,00": akhiran sen dibuang dulu agar tidak ikut menjadi digit.
            $pagu = Format::parseRupiah(preg_replace('/,(-|00)?\s*$/', '', (string) $nilai));
        } else {
            return [null, "{$label} \"{$nilai}\" bukan angka rupiah."];
        }

        return match (true) {
            $pagu === null => [null, "{$label} \"{$nilai}\" bukan angka rupiah."],
            $pagu < 0 => [null, "{$label} tidak boleh negatif."],
            $pagu > KegiatanForm::MAKS_PAGU => [null, "{$label} ".Format::rupiah($pagu).' melebihi batas wajar '.Format::rupiah(KegiatanForm::MAKS_PAGU).'.'],
            default => [$pagu, null],
        };
    }

    /**
     * "7", "Juli", "Jul", "7-9", "Juli – September", "3, 6, 9" → nomor bulan.
     *
     * @return array{0: list<int>|null, 1: string|null}
     */
    public static function bulan(mixed $nilai): array
    {
        $teks = mb_strtolower(trim((string) $nilai));

        if ($teks === '') {
            return [[], null];
        }

        $bulan = [];
        foreach (preg_split('/\s*[,;\/]\s*|\s+dan\s+/u', $teks) as $bagian) {
            $ujung = preg_split('/\s*(?:-|–|—|s\.?d\.?|sampai)\s*/u', trim($bagian));
            $nomor = array_map(self::nomorBulan(...), $ujung);

            if (in_array(null, $nomor, true) || count($nomor) > 2) {
                return [null, "Bulan pelaksanaan \"{$nilai}\" tidak dikenali (contoh: 7, Juli, 7-9, Juli – September)."];
            }

            $dari = $nomor[0];
            $sampai = $nomor[1] ?? $nomor[0];
            if ($sampai < $dari) {
                return [null, "Rentang bulan \"{$nilai}\" terbalik."];
            }

            array_push($bulan, ...range($dari, $sampai));
        }

        $bulan = array_values(array_unique($bulan));
        sort($bulan);

        return [$bulan, null];
    }

    private static function nomorBulan(string $teks): ?int
    {
        $teks = trim($teks);

        if (preg_match('/^\d{1,2}$/', $teks)) {
            $angka = (int) $teks;

            return $angka >= 1 && $angka <= 12 ? $angka : null;
        }

        foreach (JadwalPelaksanaan::NAMA_BULAN as $nomor => $nama) {
            $nama = mb_strtolower($nama);
            if ($teks === $nama || (mb_strlen($teks) >= 3 && str_starts_with($nama, $teks))) {
                return $nomor;
            }
        }

        return null;
    }
}
