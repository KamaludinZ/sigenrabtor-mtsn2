<?php

namespace App\Support\Dokumen\Generasi;

use RuntimeException;
use Throwable;

/**
 * Berkas dokumen gagal dibuat di server (kontrak GeneratorBerkasDokumen): `jenis` menentukan status HTTP endpoint
 * (`JENIS`: tidak_didukung 501 — format disusun di peramban selama generator server belum ada; gagal 503 — perender/pengubah
 * berkas gagal), pesan siap tampil, dan `tautanCetak` halaman cetak sebagai jalan lain (bila ada).
 */
class GagalBuatBerkas extends RuntimeException
{
    /** Jenis kegagalan → status HTTP. */
    public const JENIS = ['tidak_didukung' => 501, 'gagal' => 503];

    public function __construct(public readonly string $jenis, string $pesan, public readonly ?string $tautanCetak = null, ?Throwable $sebelumnya = null)
    {
        parent::__construct($pesan, 0, $sebelumnya);
    }

    public function status(): int
    {
        return self::JENIS[$this->jenis] ?? 503;
    }
}
