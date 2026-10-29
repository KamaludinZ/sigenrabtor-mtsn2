<?php

namespace App\Support\Dokumen\Generasi;

use App\Contracts\GeneratorBerkasDokumen;
use App\Contracts\PembuatPdfDokumen;
use App\Support\Dokumen\CetakDokumen;
use App\Support\Dokumen\DataPratinjau;
use App\Support\Dokumen\DokumenKegiatan;
use App\Support\Dokumen\GagalUbahPdf;
use App\Support\Dokumen\StatusCetak;
use App\Support\Rab\EksporRabExcel;
use App\Support\Stub\StubData;
use PhpOffice\PhpSpreadsheet\Exception as GalatSpreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Implementasi server GeneratorBerkasDokumen (`SERVER`): PDF lewat PembuatPdfDokumen (lembar permintaan, GagalUbahPdf →
 * `gagal`), Excel RAB lewat EksporRabExcel (lembar satu sumber dana), Word KAK/TOR lewat WordKakTor dari data pratinjau
 * (DataPratinjau::untuk lembar cover + KAK/TOR — sama dengan penyusun Word di peramban); format lain → GagalBuatBerkas
 * `tidak_didukung`. Nama berkas & cap DRAF dari StatusCetak kegiatan × sumber dana
 * (CetakDokumen::statusDraf lembar itu); tautan cetak = halaman cetak lembar yang sama sebagai jalan lain.
 */
final class GeneratorBerkasServer implements GeneratorBerkasDokumen
{
    /** Format yang dibuat server saat ini. */
    public const SERVER = ['pdf', 'docx', 'xlsx'];

    public function mendukung(string $format): bool
    {
        return in_array($format, self::SERVER, true);
    }

    public function buat(PermintaanBerkas $permintaan): BerkasDokumen
    {
        $tautan = isset($permintaan->kegiatan['id']) ? CetakDokumen::tautan((int) $permintaan->kegiatan['id'], $permintaan->sumber, $permintaan->lembar) : null;
        if (! $this->mendukung($permintaan->format)) {
            throw new GagalBuatBerkas('tidak_didukung', FormatBerkas::label($permintaan->format).' '.(DokumenKegiatan::JENIS[$permintaan->jenis] ?? $permintaan->jenis).' disusun di peramban — gunakan tombol unduh '.FormatBerkas::label($permintaan->format).' di Cetak & Unduh atau Pratinjau.', $tautan);
        }

        $cetak = StatusCetak::dari($permintaan->kegiatan, $permintaan->sumber);
        $draf = CetakDokumen::statusDraf($cetak, $permintaan->lembar);
        try {
            $isi = match ($permintaan->format) {
                'pdf' => app(PembuatPdfDokumen::class)->buat($permintaan->kegiatan, $permintaan->sumber, $permintaan->lembar),
                'xlsx' => $this->excel($permintaan),
                'docx' => WordKakTor::susun(DataPratinjau::untuk($permintaan->kegiatan, $permintaan->sumber, $permintaan->pengguna ?: StubData::currentUser(), $permintaan->lembar)),
            };
        } catch (GagalUbahPdf $e) {
            throw new GagalBuatBerkas('gagal', $e->getMessage(), $tautan, $e);
        } catch (GalatSpreadsheet $e) {
            throw new GagalBuatBerkas('gagal', 'Berkas Excel RAB gagal disusun di server — coba lagi atau gunakan unduhan Excel di peramban.', $tautan, $e);
        } catch (RuntimeException $e) {
            throw new GagalBuatBerkas('gagal', FormatBerkas::label($permintaan->format).' gagal disusun di server — coba lagi.', $tautan, $e);
        }

        return new BerkasDokumen($isi, self::nama($permintaan, $cetak), $permintaan->format, $draf->draf, array_column($draf->alasan, 'teks'), $permintaan);
    }

    /** Nama berkas baku permintaan itu (sama dengan nama unduhan peramban). */
    public static function nama(PermintaanBerkas $permintaan, ?StatusCetak $cetak = null): string
    {
        $cetak ??= StatusCetak::dari($permintaan->kegiatan, $permintaan->sumber);

        return match ($permintaan->format) {
            'xlsx' => $cetak->namaBerkas('RAB', 'xlsx'),
            'docx' => (string) preg_replace('/\.pdf$/', '.docx', CetakDokumen::namaBerkas($cetak, $permintaan->lembar)),
            default => CetakDokumen::namaBerkas($cetak, $permintaan->lembar),
        };
    }

    private function excel(PermintaanBerkas $permintaan): string
    {
        ['buku' => $buku] = EksporRabExcel::buat($permintaan->kegiatan, $permintaan->sumber);
        ob_start();
        try {
            (new Xlsx($buku))->save('php://output');
        } finally {
            $isi = (string) ob_get_clean();
        }

        return $isi;
    }
}
