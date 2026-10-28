<?php

namespace App\Support\Rab;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Support\Format;
use App\Support\Kegiatan\TahunKegiatan;
use Illuminate\Validation\ValidationException;

/**
 * Simpan termin pembayaran satu kegiatan × sumber dana di server (PRD F11, aturan 6 "Termin harus pas"): baris dirapikan
 * (baris kosong sama sekali diabaikan, nominal rupiah berformat → angka), diperiksa ValidasiTermin untuk tahun anggaran
 * kegiatan, lalu ditolak bila jumlahnya tidak sama dengan total RAB sumber dana itu (dihitung dari rincian tersimpan,
 * `pesanTidakPas()`); daftar kosong = dibayar sekaligus. Kegiatan terkunci/tidak ada/sumber dana tak dikenal ditolak
 * lewat RincianRab::pastikanDapatDiubah(). Dipakai halaman termin.
 */
final class SimpanTermin
{
    /**
     * Baris yang diisi (tanpa baris kosong sama sekali) dalam bentuk yang divalidasi: {tanggal: string, nominal: mixed}.
     *
     * @param  list<array{tanggal?: string|null, nominal?: mixed}>  $baris
     * @return list<array{tanggal: string, nominal: mixed}>
     */
    public static function terisi(array $baris): array
    {
        return array_values(array_map(
            fn (array $b) => ['tanggal' => (string) ($b['tanggal'] ?? ''), 'nominal' => $b['nominal'] ?? ''],
            array_filter($baris, fn (array $b) => filled($b['tanggal'] ?? null) || Format::parseRupiah($b['nominal'] ?? null) !== null),
        ));
    }

    /**
     * @param  list<array{tanggal?: string|null, nominal?: mixed}>  $baris
     * @return array{mekanisme: MekanismePembayaran, diabaikan: int}
     *
     * @throws ValidationException Baris tidak sah (`baris.*`) atau jumlah tidak pas (`termin`).
     */
    public static function simpan(int $kegiatanId, string $sumber, array $baris): array
    {
        RincianRab::pastikanDapatDiubah($kegiatanId, $sumber);
        $kegiatan = app(KegiatanRepository::class)->temukan($kegiatanId);
        $terisi = self::terisi($baris);

        ValidasiTermin::periksa($terisi, TahunKegiatan::dari($kegiatan));

        $total = KalkulatorRab::hitung(app(RabRepository::class)->rincian($kegiatanId, $sumber))->total;
        $mekanisme = new MekanismePembayaran($total, array_map(fn (array $b) => ['tanggal' => $b['tanggal'], 'nominal' => (int) Format::parseRupiah($b['nominal'])], $terisi));

        if ($pesan = self::pesanTidakPas($mekanisme, $sumber)) {
            throw ValidationException::withMessages(['termin' => $pesan]);
        }

        app(RabRepository::class)->simpanTermin($kegiatanId, $sumber, $mekanisme->termin);

        return ['mekanisme' => $mekanisme, 'diabaikan' => count($baris) - count($terisi)];
    }

    /** Alasan termin belum boleh disimpan/diajukan (null bila sekaligus atau pas). */
    public static function pesanTidakPas(MekanismePembayaran $mekanisme, string $sumber): ?string
    {
        return match (true) {
            ! $mekanisme->bertermin() || $mekanisme->status() === 'pas' => null,
            $mekanisme->total === 0 => "RAB {$sumber} belum berisi rincian, jadi termin belum bisa disusun. Isi rincian belanja dulu atau pilih Sekaligus.",
            default => "Jumlah termin harus sama dengan total RAB {$sumber} (".Format::rupiah($mekanisme->total).'); '.$mekanisme->teksStatusKalimat().'.',
        };
    }
}
