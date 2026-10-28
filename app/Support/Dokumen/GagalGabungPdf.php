<?php

namespace App\Support\Dokumen;

use RuntimeException;

/** Penggabungan PDF buku gagal; pesannya siap ditampilkan ke pengguna (status PDF buku "gagal"). */
class GagalGabungPdf extends RuntimeException {}
