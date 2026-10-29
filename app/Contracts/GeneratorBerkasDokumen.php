<?php

namespace App\Contracts;

use App\Support\Dokumen\Generasi\BerkasDokumen;
use App\Support\Dokumen\Generasi\GagalBuatBerkas;
use App\Support\Dokumen\Generasi\PermintaanBerkas;

/**
 * Kontrak generasi berkas dokumen kegiatan di server (PRD F12, F16, F21; dokumen API `docs/api/generasi-dokumen.md`):
 * permintaan yang sudah divalidasi (PermintaanBerkas: kegiatan, jenis DokumenKegiatan::JENIS, sumber dana, format
 * FormatBerkas::FORMAT, lembar) → BerkasDokumen (isi biner, nama berkas baku, MIME, ukuran, cap DRAF). `mendukung($format)`
 * = format yang bisa dibuat server saat ini; format lain (mis. Word selama generatornya belum ada) dilayani peramban
 * (FormatBerkas::klien) dan `buat()` melempar GagalBuatBerkas `tidak_didukung`. Implementasi server
 * `App\Support\Dokumen\Generasi\GeneratorBerkasServer`.
 */
interface GeneratorBerkasDokumen
{
    public function mendukung(string $format): bool;

    /**
     * @throws GagalBuatBerkas
     */
    public function buat(PermintaanBerkas $permintaan): BerkasDokumen;
}
