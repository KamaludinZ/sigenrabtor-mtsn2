<?php

namespace App\Support\Basisdata;

use RuntimeException;

/** Penanda mode uji PindahStrukturAnggaran: dilempar sesudah verifikasi lolos agar transaksi dibatalkan. */
final class UjiPindahSelesai extends RuntimeException {}
