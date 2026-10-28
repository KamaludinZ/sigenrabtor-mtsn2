<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/** Profil madrasah (tabel profil_madrasah, tepat satu baris ber-id 1): identitas kop & dokumen beserta logo. */
#[Table('profil_madrasah', dateFormat: 'Y-m-d H:i:s.uP')]
#[Unguarded]
class ProfilMadrasah extends Model
{
    public $incrementing = false;
}
