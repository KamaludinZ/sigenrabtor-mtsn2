<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal dokumen lembar pengesahan per leading per tahun anggaran (tab Pejabat, PejabatRepository::tanggalDokumen) —
 * dipakai kegiatan leading itu yang tanggal dokumennya sendiri kosong (TanggalPengesahan). Tanggal dalam rentang yang
 * sama dengan tanggal dokumen kegiatan: 1 Januari tahun sebelumnya s.d. 31 Desember tahun anggaran itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tanggal_dokumen_leading', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('kode_leading', 10);
            $table->date('tanggal');
            $table->timestampTz('updated_at', 6)->useCurrent();

            $table->primary(['tahun_anggaran', 'kode_leading']);
        });
        DB::statement('ALTER TABLE tanggal_dokumen_leading ADD CONSTRAINT tanggal_dokumen_leading_rentang_check CHECK (tanggal >= make_date(tahun_anggaran - 1, 1, 1) AND tanggal <= make_date(tahun_anggaran, 12, 31))');
        DB::statement('ALTER TABLE tanggal_dokumen_leading ADD CONSTRAINT tanggal_dokumen_leading_tahun_rujukan FOREIGN KEY (tahun_anggaran) REFERENCES tahun_anggaran(tahun) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED');
        DB::statement('ALTER TABLE tanggal_dokumen_leading ADD CONSTRAINT tanggal_dokumen_leading_leading_rujukan FOREIGN KEY (kode_leading) REFERENCES leadings(kode) ON UPDATE CASCADE ON DELETE CASCADE DEFERRABLE INITIALLY DEFERRED');
    }

    public function down(): void
    {
        Schema::dropIfExists('tanggal_dokumen_leading');
    }
};
