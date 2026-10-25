<?php

namespace App\Support\Kegiatan\Impor;

use RuntimeException;

/**
 * Berkas impor tidak dapat diproses sama sekali (bukan Excel/CSV, tanpa kolom Nama Kegiatan, kosong, terlalu besar).
 * Pesannya ditampilkan apa adanya kepada pengguna.
 */
final class GagalBacaImpor extends RuntimeException {}
