<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data Pagu (PRD F12 "Batas pagu": pagu kegiatan dari cascade Perkin) dengan bentuk DataPaguRepository: `data_pagu` (id
 * uuid seperti tiruan, tahun anggaran, nama kegiatan, kode leading, asal manual/impor) dan pagunya per sumber dana di
 * `data_pagu_sumber` (FK ke sumber_dana; hanya sumber yang berpagu, pagu > 0). Nama kegiatan unik per tahun dengan
 * pembanding yang sama dengan aplikasi (PemeriksaImporKegiatan::kunciNama: tanpa beda huruf besar/kecil, spasi ganda,
 * dan spasi tepi). activities.data_pagu_id kini FK ke data_pagu (tautan dilepas bila data pagu dihapus).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_pagu', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('nama_kegiatan');
            $table->string('kode_leading', 10)->nullable();
            $table->enum('asal', ['manual', 'impor'])->default('manual');
            $table->timestampsTz(6);

            $table->index(['tahun_anggaran', 'kode_leading']);
        });
        DB::statement("CREATE UNIQUE INDEX data_pagu_nama_unik ON data_pagu (tahun_anggaran, lower(regexp_replace(btrim(nama_kegiatan), '\\s+', ' ', 'g')))");

        Schema::create('data_pagu_sumber', function (Blueprint $table) {
            $table->foreignUuid('data_pagu_id')->constrained('data_pagu')->cascadeOnDelete();
            $table->string('sumber_dana', 10);
            $table->unsignedBigInteger('pagu');

            $table->primary(['data_pagu_id', 'sumber_dana']);
            $table->foreign('sumber_dana')->references('kode')->on('sumber_dana')->cascadeOnUpdate()->restrictOnDelete();
        });
        DB::statement('ALTER TABLE data_pagu_sumber ADD CONSTRAINT data_pagu_sumber_pagu_check CHECK (pagu > 0)');

        Schema::table('activities', function (Blueprint $table) {
            $table->foreign('data_pagu_id')->references('id')->on('data_pagu')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['data_pagu_id']);
        });
        Schema::dropIfExists('data_pagu_sumber');
        Schema::dropIfExists('data_pagu');
    }
};
