<?php

use App\Support\Master\Impor\BerkasMaster;
use App\Support\Master\JenisKode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master kode tahunan (PRD §14, F7) — aturan isian disamakan dengan pembaca Excel (App\Support\Master\Impor\Pemeriksa*):
 * kode SK `SK.n`, IKSK `IKSK n.m` (SK induk `SK.n`), RKTM `n.n.n`, EDM huruf kapital + nomor dengan komponen = huruf
 * awal kodenya, huruf komponen EDM satu huruf kapital; uraian/SNP/kegiatan/nama tidak kosong; `urutan` (urutan berkas)
 * mulai 1 dan unik per tahun di tiap tabel.
 *
 * master_kode_unggahan: jejak tiap unggahan Excel yang disimpan menjadi master (siapa, kapan, berkas, cakupan
 * `semua`/jenis JenisKode, ringkasan per jenis {baru, berubah, tetap, hilang, gagal}); nama pengguna saat itu tetap
 * tercatat walau akunnya dihapus. Berkas asli boleh diarsipkan (`berkas_path`, disk local).
 */
return new class extends Migration
{
    /** Tabel → [kolom kode → pola, kolom teks wajib]. */
    private const ATURAN = [
        'kode_sk' => [['kode' => '^SK\.[0-9]+$'], ['uraian']],
        'kode_iksk' => [['kode' => '^IKSK [0-9]+\.[0-9]+$', 'kode_sk' => '^SK\.[0-9]+$'], ['uraian']],
        'kode_rktm' => [['kode' => '^[0-9]+\.[0-9]+\.[0-9]+$'], ['snp', 'kegiatan', 'sub_kegiatan']],
        'kode_edm' => [['kode' => '^[A-Z][0-9]+$', 'komponen' => '^[A-Z]$'], ['uraian']],
    ];

    public function up(): void
    {
        foreach (self::ATURAN as $tabel => [$pola, $wajib]) {
            $syarat = [
                ...array_map(fn (string $kolom, string $p) => "{$kolom} ~ '{$p}'", array_keys($pola), $pola),
                ...array_map(fn (string $kolom) => "btrim({$kolom}) <> ''", $wajib),
                'urutan >= 1',
            ];
            DB::statement("ALTER TABLE {$tabel} ADD CONSTRAINT {$tabel}_isian_check CHECK (".implode(' AND ', $syarat).')');
            DB::statement("ALTER TABLE {$tabel} ADD CONSTRAINT {$tabel}_urutan_unik UNIQUE (tahun_anggaran, urutan)");
        }
        DB::statement('ALTER TABLE kode_edm ADD CONSTRAINT kode_edm_komponen_check CHECK (komponen = left(kode, 1))');
        DB::statement("ALTER TABLE komponen_edm ADD CONSTRAINT komponen_edm_isian_check CHECK (huruf ~ '^[A-Z]$' AND btrim(nama) <> '')");
        DB::statement("ALTER TABLE budaya_mutu ADD CONSTRAINT budaya_mutu_nama_check CHECK (btrim(nama) <> '')");

        Schema::create('master_kode_unggahan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('cakupan', 10);
            $table->string('nama_berkas');
            $table->string('berkas_path')->nullable();
            $table->jsonb('ringkasan')->default('{}');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pengguna', 150);
            $table->timestampTz('created_at', 6)->useCurrent();

            $table->foreign('tahun_anggaran')->references('tahun')->on('tahun_anggaran')->cascadeOnUpdate();
            $table->index(['tahun_anggaran', 'created_at']);
        });
        $cakupan = implode(', ', array_map(fn (string $c) => "'{$c}'", [BerkasMaster::SEMUA, ...array_keys(JenisKode::DAFTAR)]));
        DB::statement("ALTER TABLE master_kode_unggahan ADD CONSTRAINT master_kode_unggahan_isian_check CHECK (cakupan IN ({$cakupan}) AND btrim(nama_berkas) <> '' AND btrim(pengguna) <> '' AND jsonb_typeof(ringkasan) = 'object')");
    }

    public function down(): void
    {
        Schema::dropIfExists('master_kode_unggahan');
        DB::statement('ALTER TABLE budaya_mutu DROP CONSTRAINT budaya_mutu_nama_check');
        DB::statement('ALTER TABLE komponen_edm DROP CONSTRAINT komponen_edm_isian_check');
        DB::statement('ALTER TABLE kode_edm DROP CONSTRAINT kode_edm_komponen_check');
        foreach (array_keys(self::ATURAN) as $tabel) {
            DB::statement("ALTER TABLE {$tabel} DROP CONSTRAINT {$tabel}_urutan_unik");
            DB::statement("ALTER TABLE {$tabel} DROP CONSTRAINT {$tabel}_isian_check");
        }
    }
};
