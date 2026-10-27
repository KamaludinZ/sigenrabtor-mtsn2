<?php

namespace App\Support\Master\Impor;

use RuntimeException;

/** Sheet master tidak bisa dipratinjau (mis. judul kolom tidak ditemukan); pesannya tampil di tab jenisnya. */
class GagalPratinjauMaster extends RuntimeException {}
