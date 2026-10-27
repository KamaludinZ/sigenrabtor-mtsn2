<?php

use App\Support\Terbilang;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Terbilang otomatis (PRD F3, aturan "Total dihitung, tidak diketik"): anggaran_kegiatan.terbilang adalah salinan
 * Terbilang::tampil(total_rab) — null tepat bila total 0 (layar menampilkan keterangan kontekstual, bukan "Nol Rupiah").
 * Migrasi ini menghitung ulang salinan yang ada dari rinciannya, lalu menguncinya dengan CHECK: terbilang kosong ⟺ total
 * 0, dan total RAB maupun nominal termin (keduanya tercetak beserta terbilangnya) tidak melebihi Terbilang::MAKSIMUM
 * sehingga terbilangnya selalu bisa disusun dan muat di kolom (terpanjang 226 karakter).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('anggaran_kegiatan')->select('id')->orderBy('id')->cursor() as $anggaran) {
            $total = (int) DB::table('budget_items')->where('anggaran_kegiatan_id', $anggaran->id)->sum('jumlah');
            DB::table('anggaran_kegiatan')->where('id', $anggaran->id)->update([
                'total_rab' => $total,
                'terbilang' => $total > Terbilang::MAKSIMUM ? null : Terbilang::tampil($total),
            ]);
        }

        $maks = Terbilang::MAKSIMUM;
        DB::statement('ALTER TABLE anggaran_kegiatan ADD CONSTRAINT anggaran_kegiatan_terbilang_check CHECK ((total_rab = 0) = (terbilang IS NULL))');
        DB::statement("ALTER TABLE anggaran_kegiatan ADD CONSTRAINT anggaran_kegiatan_total_rab_maks_check CHECK (total_rab <= {$maks})");
        DB::statement("ALTER TABLE termin_pembayaran ADD CONSTRAINT termin_pembayaran_nominal_maks_check CHECK (nominal <= {$maks})");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE termin_pembayaran DROP CONSTRAINT termin_pembayaran_nominal_maks_check');
        DB::statement('ALTER TABLE anggaran_kegiatan DROP CONSTRAINT anggaran_kegiatan_total_rab_maks_check');
        DB::statement('ALTER TABLE anggaran_kegiatan DROP CONSTRAINT anggaran_kegiatan_terbilang_check');
    }
};
