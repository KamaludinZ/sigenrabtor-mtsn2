<?php

namespace App\Support\Dokumen;

use App\Support\Format;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Cetak dokumen dari pratinjau (PRD F12 & F16: dokumen belum lengkap/belum disetujui tetap boleh dicetak dengan tanda
 * "DRAF"): halaman cetak `kegiatan.cetak` (`/kegiatan/{id}/cetak?sumber=&dokumen=cover,kak,rab[&otomatis=1]`) memuat
 * lembar terpilih (`pilihan()`, urut `LEMBAR`; kosong = semua) seperti pratinjau — lembar A4, cap DRAF di setiap halaman
 * selama StatusCetak DRAF, kaki halaman `kakiHalaman()` + nomor halaman — tanpa bingkai aplikasi, sehingga "Simpan
 * sebagai PDF" di dialog cetak peramban menghasilkan PDF bernama `namaBerkas()` (judul halaman). Unduh Word & PDF dari
 * server menyusul (Fase 3); RAB sudah bisa diunduh sebagai Excel/PDF (`kegiatan.rab.excel`/`kegiatan.rab.pdf`).
 */
final class CetakDokumen
{
    /** Lembar yang bisa dicetak → label, urut cetak. */
    public const LEMBAR = PenampilDokumen::LEMBAR;

    /**
     * Lembar terpilih dari parameter (daftar atau teks dipisah koma), urut cetak; tak dikenal diabaikan; kosong = semua.
     *
     * @param  string|list<string>|null  $dokumen
     * @return list<string>
     */
    public static function pilihan(string|array|null $dokumen): array
    {
        $diminta = is_array($dokumen) ? $dokumen : explode(',', (string) $dokumen);
        $diminta = array_map(fn ($d) => trim((string) $d), $diminta);
        $pilihan = array_values(array_filter(array_keys(self::LEMBAR), fn (string $l) => in_array($l, $diminta, true)));

        return $pilihan === [] ? array_keys(self::LEMBAR) : $pilihan;
    }

    /** Jenis nama berkas StatusCetak::namaBerkas untuk lembar terpilih. */
    public static function jenisBerkas(array $lembar): string
    {
        return match (true) {
            $lembar === ['rab'] => 'RAB',
            ! in_array('rab', $lembar, true) => 'KAK-TOR',
            default => 'KAK-TOR_RAB',
        };
    }

    /**
     * Cap DRAF lembar terpilih: cover & set mengikuti `set`, KAK/TOR `kak`, RAB `rab` (aturan PemeriksaanCetak).
     *
     * @param  list<string>  $lembar
     */
    public static function statusDraf(StatusCetak $cetak, array $lembar): StatusDraf
    {
        return match (true) {
            $lembar === ['rab'] => $cetak->rab,
            $lembar === ['kak'] => $cetak->kak,
            default => $cetak->set,
        };
    }

    /**
     * Isi halaman cetak satu kegiatan × sumber dana.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  list<string>  $lembar
     * @return array{kak: SusunanKak, rab: SusunanRab, cover: array{gambar: string|null, teks: list<array<string, mixed>>, nilai: array<string, string>}, cetak: StatusCetak, draf: StatusDraf, lembar: list<string>, nama_berkas: string, judul: string, kaki: string|null}
     */
    public static function untuk(array $kegiatan, ?string $sumber, array $lembar, ?DateTimeInterface $waktu = null): array
    {
        $kak = SusunanKak::dari($kegiatan, $sumber);
        $rab = SusunanRab::dari($kegiatan, $sumber);
        $cetak = StatusCetak::dari($kegiatan, $kak->sumber, $kak, $rab);
        $draf = self::statusDraf($cetak, $lembar);
        $nama = self::namaBerkas($cetak, $lembar);

        return [
            'kak' => $kak,
            'rab' => $rab,
            'cover' => CoverDokumen::untukKegiatan($kegiatan, $kak->sumber) + ['nilai' => TeksCover::nilai($kegiatan, $kak->sumber)],
            'cetak' => $cetak,
            'draf' => $draf,
            'lembar' => $lembar,
            'nama_berkas' => $nama,
            'judul' => preg_replace('/\.pdf$/', '', $nama),
            'kaki' => $draf->draf ? self::kakiHalaman($draf, $waktu) : null,
            'template' => TemplateDipakai::cetak($kegiatan, $kak->sumber, $lembar, $kak->format),
        ];
    }

    /** Nama berkas PDF lembar terpilih (akhiran _DRAF hanya bila lembar itu DRAF). */
    public static function namaBerkas(StatusCetak $cetak, array $lembar): string
    {
        $nama = $cetak->namaBerkas(self::jenisBerkas($lembar), 'pdf');
        $draf = self::statusDraf($cetak, $lembar)->draf;

        return $cetak->draf() && ! $draf ? str_replace('_'.StatusCetak::TANDA_AIR.'.', '.', $nama) : $nama;
    }

    /** Kaki halaman lembar DRAF: "DRAF — dicetak … pukul … · alasan pertama". */
    public static function kakiHalaman(StatusDraf $draf, ?DateTimeInterface $waktu = null): string
    {
        $waktu = CarbonImmutable::instance($waktu ?? now())->setTimezone(config('app.timezone'));

        return StatusCetak::TANDA_AIR.' — dicetak '.Format::tanggalPanjang($waktu->toDateString()).' pukul '.$waktu->format('H.i')
            .' · '.($draf->alasan[0]['teks'] ?? 'belum siap dicetak');
    }

    /**
     * Tautan halaman cetak; `$otomatis` membuka dialog cetak begitu halaman dimuat.
     *
     * @param  list<string>  $lembar
     */
    public static function tautan(int $kegiatanId, ?string $sumber, array $lembar, bool $otomatis = false): string
    {
        return route('kegiatan.cetak', array_filter([
            'kegiatan' => $kegiatanId,
            'sumber' => $sumber,
            'dokumen' => self::pilihan($lembar) === array_keys(self::LEMBAR) ? null : implode(',', self::pilihan($lembar)),
            'otomatis' => $otomatis ? 1 : null,
        ]));
    }

    /**
     * Tautan unduhan PDF langsung (`kegiatan.cetak.pdf`, PembuatPdfDokumen) lembar terpilih.
     *
     * @param  list<string>  $lembar
     */
    public static function tautanPdf(int $kegiatanId, ?string $sumber, array $lembar): string
    {
        return route('kegiatan.cetak.pdf', array_filter([
            'kegiatan' => $kegiatanId,
            'sumber' => $sumber,
            'dokumen' => self::pilihan($lembar) === array_keys(self::LEMBAR) ? null : implode(',', self::pilihan($lembar)),
        ]));
    }
}
