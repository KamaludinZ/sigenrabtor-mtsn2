<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sumber dana kegiatan (PRD §6 anggaran_kegiatan): satu baris per kegiatan × sumber dana (App\Enums\SumberDana),
 * calon induk rincian RAB & termin. Uang dalam rupiah utuh (bigint, sama dengan KalkulatorRab); pagu null = belum diisi.
 * total_rab & terbilang adalah salinan hasil KalkulatorRab/Terbilang yang diperbarui saat rincian disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anggaran_kegiatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->enum('sumber_dana', ['DIPA', 'Komite']);
            $table->unsignedBigInteger('pagu')->nullable();
            $table->unsignedBigInteger('total_rab')->default(0);
            $table->string('terbilang')->nullable();
            $table->timestampsTz(6);

            $table->unique(['activity_id', 'sumber_dana']);
        });

        DB::statement('ALTER TABLE anggaran_kegiatan ADD CONSTRAINT anggaran_kegiatan_pagu_check CHECK (pagu >= 0)');
        DB::statement('ALTER TABLE anggaran_kegiatan ADD CONSTRAINT anggaran_kegiatan_total_rab_check CHECK (total_rab >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('anggaran_kegiatan');
    }
};
