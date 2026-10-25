<?php

namespace App\Support\Impor;

use RuntimeException;

/**
 * Berkas impor tidak dapat diproses sama sekali (bukan Excel/CSV, tanpa kolom wajib, kosong, terlalu besar).
 * Pesannya ditampilkan apa adanya kepada pengguna.
 */
final class GagalBacaImpor extends RuntimeException {}
