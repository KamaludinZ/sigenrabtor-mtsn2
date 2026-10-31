<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pilihan template master per kegiatan (PRD §9 "Pilihan Template per Kegiatan"): activities.kak_template_id = master
 * KAK/TOR yang dipilih untuk kegiatan itu (NULL = otomatis — aturan Format Seragam & default template leading,
 * PilihanTemplate::untukKegiatan). Template dihapus → pilihan kembali otomatis (ON DELETE SET NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->uuid('kak_template_id')->nullable();
            $table->foreign('kak_template_id')->references('id')->on('document_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['kak_template_id']);
            $table->dropColumn('kak_template_id');
        });
    }
};
