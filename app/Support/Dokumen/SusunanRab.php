<?php

namespace App\Support\Dokumen;

use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Kegiatan\KunciDokumen;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\AnggaranSumber;
use App\Support\Rab\MekanismePembayaran;
use App\Support\Rab\PeringatanPagu;
use App\Support\Stub\StubData;

/**
 * Susunan dokumen RAB satu kegiatan × sumber dana (PRD 7B.3) untuk <x-dokumen.rab>: judul, header (Kementerian, Satker,
 * kegiatan & kode RKTM, SK, IKSK, EDM, 5 Budaya Mutu, volume, jumlah yang diajukan), tabel rincian (`tabel()` = TabelRab), blok Mekanisme
 * Pembayaran, dan lembar pengesahan yang sama dengan KAK. Kode dari NilaiVariabel, angka dari AngkaDokumen. Isian yang
 * belum lengkap tercatat di $kurang; cap DRAF lewat StatusDraf seperti KAK (belum disetujui, belum lengkap, atau melebihi pagu).
 */
final readonly class SusunanRab
{
    public const KEMENTERIAN = 'Kementerian Agama';

    /** Satu RAB untuk satu kegiatan. */
    public const VOLUME = '1 Kegiatan';

    /**
     * @param  list<array{label: string, nilai: string|null, uraian: string|null, asal: string|null, tempat?: string}>  $kepala  nilai null = belum diisi; tempat = kunci AngkaDokumen::tempatCetak().
     * @param  list<array{label: string, nilai: string}>  $mekanisme  Kosong bila dibayar sekaligus.
     * @param  list<array{peran: string, jabatan: string, nama: string|null, nip: string|null, tempat_tanggal: string|null, baris?: int, sebutan?: string, tanpa_nip?: bool}>  $pengesahan  Tata letak menurut $format (SusunanKak::pengesahan).
     * @param  list<string>  $kurang
     * @param  list<string>  $daftarSumber
     */
    public function __construct(
        public string $judul,
        public array $kepala,
        public ?AnggaranSumber $anggaran,
        public array $mekanisme,
        public ?string $statusTermin,
        public array $pengesahan,
        public array $kurang,
        public bool $draf,
        public ?string $sumber,
        public array $daftarSumber,
        public ?PeringatanPagu $peringatanPagu = null,
        public ?KunciDokumen $kunci = null,
        public ?StatusDraf $statusDraf = null,
        public string $format = FormatKakTor::SEMBILAN_BAGIAN,
    ) {}

    /**
     * @param  array<string, mixed>  $kegiatan
     */
    public static function dari(array $kegiatan, ?string $sumber = null): self
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $madrasah = StubData::madrasah();
        $semua = AnggaranKegiatan::perSumber($kegiatan);
        $daftarSumber = array_keys($semua);
        $sumber = in_array($sumber, $daftarSumber, true) ? $sumber : ($daftarSumber[0] ?? null);
        $anggaran = $sumber ? $semua[$sumber] : null;
        $nilai = NilaiVariabel::untuk($kegiatan, $sumber);
        $kurang = [];

        $baris = function (string $label, ?string $isi, ?string $uraian = null, ?string $variabel = null) use (&$kurang): array {
            if ($isi === null && $variabel !== null) {
                $kurang[] = mb_strtolower($label);
            }

            return ['label' => $label, 'nilai' => $isi, 'uraian' => $uraian, 'asal' => $variabel ? (NilaiVariabel::ASAL[$variabel] ?? null) : null];
        };
        $kode = fn (string $label, string $kunci) => $baris($label, $nilai["kode_{$kunci}"], $nilai["uraian_{$kunci}"], "kode_{$kunci}");

        $angka = $anggaran ? AngkaDokumen::dari($anggaran->hasil, $sumber, $tahun, $madrasah['nama_singkat']) : null;
        $kepala = [
            $baris('Kementerian', self::KEMENTERIAN),
            $baris('Satker', $madrasah['nama']),
            $baris('Nama kegiatan', $nilai['nama_kegiatan'], null, 'nama_kegiatan'),
            $kode('Kode RKTM', 'rktm'),
            $kode('Kode SK', 'sk'),
            $kode('IKSK', 'iksk'),
            $kode('Kode EDM', 'edm'),
            $baris('5 Budaya Mutu', $nilai['budaya_mutu'], null, 'budaya_mutu'),
            $baris('Volume', self::VOLUME),
            // Kosong selama rincian belum ada; kekurangannya dicatat sebagai rincian belanja di bawah.
            ['label' => 'Jumlah yang diajukan', 'nilai' => $angka?->nominalDanTerbilang(), 'uraian' => $angka?->sebutanSumber(), 'asal' => NilaiVariabel::ASAL['total'], 'tempat' => 'rab-diajukan'],
        ];

        if ($anggaran === null) {
            $kurang[] = 'sumber dana';
        }
        if ($anggaran && $anggaran->jumlahItem() === 0) {
            $kurang[] = 'rincian belanja RAB '.$sumber;
        }

        $mekanisme = $anggaran ? MekanismePembayaran::dari($anggaran) : null;
        $statusTermin = $mekanisme && in_array($mekanisme->status(), ['kurang', 'lebih'], true) ? $mekanisme->teksStatus() : null;
        if ($statusTermin) {
            $kurang[] = 'termin pembayaran (jumlahnya belum sama dengan total RAB)';
        }

        $format = FormatKakTor::untuk($kegiatan, $tahun);
        $pengesahan = SusunanKak::pengesahan($kegiatan, $sumber, $madrasah['kota'], function (string $apa) use (&$kurang): void {
            $kurang[] = $apa;
        }, $tahun, $format, 'rab');

        $peringatanPagu = $anggaran ? PeringatanPagu::untuk($sumber, $anggaran->hasil) : null;
        $status = StatusKegiatan::tryFrom($kegiatan['status'] ?? '') ?? StatusKegiatan::Draf;
        $nama = trim((string) ($kegiatan['nama_kegiatan'] ?? '')) ?: '…';
        $statusDraf = StatusDraf::hitung($status, array_values(array_unique($kurang)), $peringatanPagu);

        return new self(
            'Rencana Anggaran Biaya Kegiatan '.KalimatKak::tanpaAwalanKegiatan($nama)." {$madrasah['nama_singkat']} Tahun {$tahun}",
            $kepala,
            $anggaran,
            $mekanisme?->barisDokumen() ?? [],
            $statusTermin,
            $pengesahan['kolom'],
            array_values(array_unique($kurang)),
            $statusDraf->draf,
            $sumber,
            $daftarSumber,
            $peringatanPagu,
            KunciDokumen::dari($kegiatan),
            $statusDraf,
            $format,
        );
    }

    /** Struktur tabel rincian RAB dokumen ini (TabelRab; kosong bila belum ada sumber dana). */
    public function tabel(): TabelRab
    {
        return TabelRab::dari($this->anggaran);
    }
}
