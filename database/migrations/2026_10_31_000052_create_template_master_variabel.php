<?php

use App\Support\Pengaturan\UnggahMasterTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skema template master ber-variabel (PRD Fase 5 "Template KAK/TOR Variabel (Multi per Leading)"; melengkapi
 * document_templates migrasi 000020/000021/000050):
 * - document_templates: jejak pengunggah (`dibuat_oleh` FK users — kosong bila akun dihapus — + nama `pengunggah` saat
 *   itu), CHECK `document_templates_default_aktif_check` (default hanya template aktif), CHECK `document_templates_nama_check`
 *   (nama satu baris berspasi tunggal ≤ UnggahMasterTemplate::MAKS_NAMA), indeks `document_templates_daftar` (tahun ×
 *   pemilik × jenis — daftar Template Master & pilihan template kegiatan);
 * - document_template_variabel: satu baris per penanda `${…}` tiap template — variabel terdaftar (FK template_variabel),
 *   urutan kemunculan pertama, dan jumlah kemunculan di berkas; ikut terhapus bersama templatenya. Bersama
 *   document_templates.variabel (daftar nama) diisi repositori dalam satu transaksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        $maks = UnggahMasterTemplate::MAKS_NAMA;
        Schema::table('document_templates', function (Blueprint $table) {
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pengunggah', 150)->nullable();
            $table->index(['tahun_anggaran', 'kode_leading', 'jenis'], 'document_templates_daftar');
        });
        DB::unprepared(<<<SQL
            ALTER TABLE document_templates ADD CONSTRAINT document_templates_default_aktif_check CHECK (NOT is_default OR aktif);
            ALTER TABLE document_templates ADD CONSTRAINT document_templates_nama_check
                CHECK (nama = btrim(regexp_replace(nama, '\s+', ' ', 'g')) AND char_length(nama) BETWEEN 1 AND {$maks});
            SQL);

        Schema::create('document_template_variabel', function (Blueprint $table) {
            $table->uuid('template_id');
            $table->string('variabel', 40);
            $table->unsignedSmallInteger('urutan');
            $table->unsignedSmallInteger('jumlah')->default(1);
            $table->primary(['template_id', 'variabel']);
            $table->unique(['template_id', 'urutan']);
            $table->foreign('template_id')->references('id')->on('document_templates')->cascadeOnDelete();
            $table->foreign('variabel')->references('kunci')->on('template_variabel')->cascadeOnUpdate();
        });
        DB::statement('ALTER TABLE document_template_variabel ADD CONSTRAINT document_template_variabel_angka_check CHECK (urutan >= 1 AND jumlah >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_template_variabel');
        DB::unprepared(<<<'SQL'
            ALTER TABLE document_templates DROP CONSTRAINT IF EXISTS document_templates_nama_check;
            ALTER TABLE document_templates DROP CONSTRAINT IF EXISTS document_templates_default_aktif_check;
            SQL);
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropIndex('document_templates_daftar');
            $table->dropConstrainedForeignId('dibuat_oleh');
            $table->dropColumn('pengunggah');
        });
    }
};
