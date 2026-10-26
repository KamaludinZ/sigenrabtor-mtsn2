<?php

namespace App\Support\Kegiatan;

use RuntimeException;

/** Usulan AI tidak bisa dibuat; pesannya ditampilkan apa adanya ke pengguna (bahasa Indonesia, tanpa detail teknis). */
class GagalUsulanAi extends RuntimeException {}
