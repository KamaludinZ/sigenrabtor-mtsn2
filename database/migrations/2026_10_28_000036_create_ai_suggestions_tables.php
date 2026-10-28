<?php

use App\Support\Kegiatan\BagianAi;
use App\Support\Kegiatan\SaranJadwalAi;
use App\Support\Pengaturan\PenyediaAi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat saran AI per kegiatan & pemakaian AI (PRD §6 `ai_suggestions`, F25, §21 "Batas Pemakaian"; bentuk
 * RiwayatSaranAiRepository & PemakaianAiRepository):
 * - `ai_suggestions`: satu baris per permintaan "Buat dengan AI"/usulan jadwal — kegiatan (ikut terhapus), bagian
 *   (BagianAi::BAGIAN), bahan terkirim (`prompt_terkirim`), instruksi perbaikan (≤ SaranJadwalAi::MAKS_INSTRUKSI), penyedia
 *   yang menjawab (PenyediaAi::DAFTAR atau `tiruan`; kosong = tiruan), peminta (FK users, dihapus → kosong), waktu
 *   diminta & diputuskan; id teks (uuid; contoh tiruan berid tetap);
 * - `ai_suggestion_versions`: versi saran (urutan 1–3 unik per permintaan; judul, ciri, teks) berstatus
 *   menunggu/diterima/diedit/ditolak — `teks_dipakai` hanya untuk diterima (= teks saran) & diedit (≠ teks saran),
 *   paling banyak satu versi dipakai per permintaan (indeks unik parsial), ikut terhapus bersama permintaannya;
 * - `ai_usage`: hitungan permintaan AI per akun × tanggal × fitur (kunci BagianAi atau `usul_jadwal`), jumlah ≥ 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_suggestions', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->string('bagian', 30);
            $table->text('prompt_terkirim');
            $table->text('instruksi')->default('');
            $table->string('provider', 20)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('dibuat_pada', 6)->useCurrent();
            $table->timestampTz('diputuskan_pada', 6)->nullable();
            $table->index(['activity_id', 'dibuat_pada']);
            $table->index(['user_id', 'dibuat_pada']);
        });

        $bagian = implode(', ', array_map(fn (string $b) => "'{$b}'", array_keys(BagianAi::BAGIAN)));
        $penyedia = implode(', ', array_map(fn (string $p) => "'{$p}'", [...array_keys(PenyediaAi::DAFTAR), 'tiruan']));
        DB::statement("ALTER TABLE ai_suggestions ADD CONSTRAINT ai_suggestions_isian_check CHECK (
            bagian IN ({$bagian}) AND (provider IS NULL OR provider IN ({$penyedia}))
            AND btrim(prompt_terkirim) <> '' AND char_length(instruksi) <= ".SaranJadwalAi::MAKS_INSTRUKSI.'
            AND (diputuskan_pada IS NULL OR diputuskan_pada >= dibuat_pada))');

        Schema::create('ai_suggestion_versions', function (Blueprint $table) {
            $table->id();
            $table->string('suggestion_id', 64);
            $table->unsignedSmallInteger('urutan');
            $table->string('judul', 100);
            $table->string('ciri', 255)->default('');
            $table->text('teks');
            $table->enum('status', ['menunggu', 'diterima', 'diedit', 'ditolak'])->default('menunggu');
            $table->text('teks_dipakai')->nullable();
            $table->unique(['suggestion_id', 'urutan']);
            $table->foreign('suggestion_id')->references('id')->on('ai_suggestions')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE ai_suggestion_versions ADD CONSTRAINT ai_suggestion_versions_isian_check CHECK (
            urutan BETWEEN 1 AND 3 AND btrim(teks) <> ''
            AND (status IN ('diterima', 'diedit')) = (teks_dipakai IS NOT NULL)
            AND (status <> 'diterima' OR teks_dipakai = teks) AND (status <> 'diedit' OR teks_dipakai <> teks))");
        DB::statement("CREATE UNIQUE INDEX ai_suggestion_versions_satu_dipakai ON ai_suggestion_versions (suggestion_id) WHERE status IN ('diterima', 'diedit')");

        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('fitur', 30);
            $table->unsignedInteger('jumlah')->default(1);
            $table->unique(['user_id', 'tanggal', 'fitur']);
        });
        DB::statement("ALTER TABLE ai_usage ADD CONSTRAINT ai_usage_isian_check CHECK (jumlah >= 1 AND fitur IN ({$bagian}, 'usul_jadwal'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage');
        Schema::dropIfExists('ai_suggestion_versions');
        Schema::dropIfExists('ai_suggestions');
    }
};
