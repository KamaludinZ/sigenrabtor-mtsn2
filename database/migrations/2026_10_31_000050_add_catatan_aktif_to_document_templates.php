<?php

use App\Support\Pengaturan\UnggahMasterTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Template master multi per leading (PRD Fase 5 "Template KAK/TOR Variabel (Multi per Leading)"): document_templates
 * mendapat `catatan` versi (satu baris berspasi tunggal, ≤ UnggahMasterTemplate::MAKS_CATATAN; kosong = NULL) dan `aktif`
 * (template nonaktif tidak muncul di pilihan baru, dokumen lama tetap utuh). Pemilik `kode_leading` & default per tahun ×
 * jenis × pemilik sudah ada sejak migrasi 000020.
 */
return new class extends Migration
{
    public function up(): void
    {
        $maks = UnggahMasterTemplate::MAKS_CATATAN;
        DB::unprepared(<<<SQL
            ALTER TABLE document_templates ADD COLUMN catatan varchar({$maks}) NULL;
            ALTER TABLE document_templates ADD COLUMN aktif boolean NOT NULL DEFAULT true;
            ALTER TABLE document_templates ADD CONSTRAINT document_templates_catatan_check
                CHECK (catatan IS NULL OR (catatan = btrim(regexp_replace(catatan, '\s+', ' ', 'g')) AND catatan <> ''));
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE document_templates DROP CONSTRAINT IF EXISTS document_templates_catatan_check;
            ALTER TABLE document_templates DROP COLUMN IF EXISTS aktif;
            ALTER TABLE document_templates DROP COLUMN IF EXISTS catatan;
            SQL);
    }
};
