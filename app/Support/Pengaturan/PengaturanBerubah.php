<?php

namespace App\Support\Pengaturan;

use RuntimeException;

/**
 * Pengaturan yang hendak diubah sudah berubah di tab/pengguna lain sejak dimuat (versi `diperbarui_pada` berbeda) —
 * aksi dibatalkan agar perubahan orang lain tidak tertimpa diam-diam; layar memuat ulang lalu pengguna mengulanginya.
 */
class PengaturanBerubah extends RuntimeException {}
