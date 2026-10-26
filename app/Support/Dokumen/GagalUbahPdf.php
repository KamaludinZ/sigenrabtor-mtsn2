<?php

namespace App\Support\Dokumen;

use RuntimeException;

/**
 * Konversi ke PDF gagal; pesannya aman ditampilkan ke pengguna (tanpa path/keluaran perintah).
 */
class GagalUbahPdf extends RuntimeException {}
