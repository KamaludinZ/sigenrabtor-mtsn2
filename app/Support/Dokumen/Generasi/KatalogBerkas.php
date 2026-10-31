<?php

namespace App\Support\Dokumen\Generasi;

use App\Contracts\GeneratorBerkasDokumen;
use App\Support\Dokumen\CetakDokumen;
use App\Support\Dokumen\DokumenKegiatan;
use App\Support\Dokumen\JenisDokumen;
use App\Support\Dokumen\StatusCetak;
use App\Support\Dokumen\TemplateDipakai;

/**
 * Katalog berkas yang bisa dibuat untuk satu kegiatan (endpoint `kegiatan.berkas`): setiap jenis DokumenKegiatan::JENIS ×
 * sumber dana (jenis per sumber dana untuk tiap sumber terpilih; jenis lain pada sumber pertama) × format yang cocok
 * (FormatBerkas::untukJenis; format per sumber dana dilewati tanpa sumber) → nama berkas baku, cap DRAF + alasan, `server`
 * (GeneratorBerkasDokumen::mendukung), `klien` (bisa disusun peramban), dan `tautan` unduhan server `kegiatan.berkas.unduh`.
 * `format()` = daftar format beserta kemampuannya.
 */
final class KatalogBerkas
{
    /**
     * @param  array<string, mixed>  $kegiatan
     * @return list<array<string, mixed>>
     */
    public static function untuk(array $kegiatan): array
    {
        $generator = app(GeneratorBerkasDokumen::class);
        $dipilih = array_values(array_unique(array_column($kegiatan['sumber_dana'] ?? [], 'sumber')));
        $cetak = [];
        $dipakai = [];
        $katalog = [];
        foreach (array_keys(DokumenKegiatan::JENIS) as $jenis) {
            $sumberJenis = JenisDokumen::perluSumber($jenis) ? $dipilih : [$dipilih[0] ?? null];
            foreach ($sumberJenis as $sumber) {
                foreach (FormatBerkas::untukJenis($jenis) as $format) {
                    if ($sumber === null && FormatBerkas::perluSumber($format)) {
                        continue;
                    }
                    $permintaan = new PermintaanBerkas($kegiatan, $jenis, $sumber, $format, FormatBerkas::lembar($jenis, $sumber));
                    $status = $cetak[$sumber ?? ''] ??= StatusCetak::dari($kegiatan, $sumber);
                    $draf = CetakDokumen::statusDraf($status, $permintaan->lembar);
                    $katalog[] = $permintaan->toArray() + [
                        'label_jenis' => DokumenKegiatan::JENIS[$jenis],
                        'label_format' => FormatBerkas::label($format),
                        'nama_berkas' => GeneratorBerkasServer::nama($permintaan, $status),
                        'template' => TemplateDipakai::teks($dipakai[$sumber ?? ''] ??= TemplateDipakai::untuk($kegiatan, $sumber), $permintaan->lembar),
                        'draf' => $draf->draf,
                        'alasan_draf' => array_column($draf->alasan, 'teks'),
                        'server' => $generator->mendukung($format),
                        'klien' => FormatBerkas::klien($format),
                        'tautan' => route('kegiatan.berkas.unduh', array_filter(['kegiatan' => $kegiatan['id'], 'jenis' => $jenis, 'sumber' => $sumber, 'format' => $format])),
                    ];
                }
            }
        }

        return $katalog;
    }

    /** @return array<string, array{label: string, mime: string, ekstensi: string, lembar: list<string>, perlu_sumber: bool, server: bool, klien: bool}> */
    public static function format(): array
    {
        $generator = app(GeneratorBerkasDokumen::class);

        $daftar = [];
        foreach (FormatBerkas::FORMAT as $format => [$label, $mime, $ekstensi, $lembar, $perluSumber, $klien]) {
            $daftar[$format] = ['label' => $label, 'mime' => $mime, 'ekstensi' => $ekstensi, 'lembar' => $lembar, 'perlu_sumber' => $perluSumber, 'server' => $generator->mendukung($format), 'klien' => $klien];
        }

        return $daftar;
    }
}
