<?php

namespace App\Support\Kegiatan;

/**
 * Kegagalan penyedia AI berjenis (KegagalanAi::JENIS). Pesan untuk pengguna = pesan asli penyusun (bila ada) atau pesan
 * katalog jenisnya; $cadanganGagal = penyedia cadangan juga sudah dicoba dan gagal.
 */
class GagalPenyediaAi extends GagalUsulanAi
{
    public readonly string $pesanDasar;

    public function __construct(
        public readonly string $jenis,
        public readonly string $penyedia,
        public readonly bool $cadanganGagal = false,
        public readonly bool $simulasi = false,
        ?string $pesan = null,
    ) {
        $this->pesanDasar = $pesan ?? KegagalanAi::pesan($jenis);
        parent::__construct($this->pesanDasar.($cadanganGagal ? ' Penyedia cadangan juga gagal.' : ''));
    }
}
