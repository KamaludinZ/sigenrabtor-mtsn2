<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/** Satu anggota pelaksana & panitia kegiatan (tabel committees); urutan = urutan cetak. */
#[Table('committees', timestamps: false)]
#[Unguarded]
class Panitia extends Model {}
