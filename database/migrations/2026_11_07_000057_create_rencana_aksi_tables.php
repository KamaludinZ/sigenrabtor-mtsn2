<?php

use App\Support\Master\FormatKode;
use App\Support\Master\Impor\BerkasMaster;
use App\Support\Master\JenisKode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rencana aksi cascade Perkin & relasi EDM ↔ RKTM per tahun anggaran (Master Kode), serta format kode SK/IKSK gaya
 * Kemenag (FormatKode: `SK.1.2133`, `IKSK.4422.1.1.a`) di samping gaya lama (`SK.10`, `IKSK 10.2`).
 *
 * rencana_aksi: bentuk MasterKodeRepository::rencanaAksi — kode `RA.n` & urutan unik per tahun; tag SK/IKSK/RKTM berpola
 * FormatKode/RKTM (tanpa FK ke master: master diganti tiap unggahan dan kode yang hilang tetap terbaca); leading FK
 * leadings (kode ikut berganti, leading dihapus → kosong); anggaran rupiah utuh ≥ 0; bulan larik angka 1–12; `kode_edm`
 * null = tag EDM otomatis dari relasi, selain itu larik kode EDM; `budaya_mutu` larik nomor ≥ 1.
 * relasi_edm_rktm: pasangan indikator EDM → kode sub kegiatan RKTM yang direkomendasikan, unik per tahun.
 */
return new class extends Migration
{
    /** Pola lama (migrasi 000026) untuk down(). */
    private const POLA_LAMA_SK = '^SK\.[0-9]+$';

    private const POLA_LAMA_IKSK = '^IKSK [0-9]+\.[0-9]+$';

    private const CAKUPAN_LAMA = ['semua', 'sk', 'iksk', 'rktm', 'edm', 'budaya'];

    public function up(): void
    {
        $this->polaKode(FormatKode::POLA_SK, FormatKode::POLA_IKSK);

        Schema::create('rencana_aksi', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('kode', 20);
            $table->unsignedInteger('urutan');
            $table->text('uraian');
            $table->string('kode_sk', 40)->nullable();
            $table->string('kode_iksk', 40)->nullable();
            $table->string('kode_rktm', 20)->nullable();
            $table->string('unit', 150)->nullable();
            $table->string('kode_leading', 10)->nullable();
            $table->unsignedBigInteger('pagu_dipa')->default(0);
            $table->unsignedBigInteger('pagu_komite')->default(0);
            $table->jsonb('bulan')->default('[]');
            $table->jsonb('kode_edm')->nullable();
            $table->jsonb('budaya_mutu')->default('[]');

            $table->unique(['tahun_anggaran', 'kode']);
            $table->index(['tahun_anggaran', 'kode_rktm']);
        });
        $pola = fn (string $kolom, string $p) => "({$kolom} IS NULL OR {$kolom} ~ '{$p}')";
        DB::statement('ALTER TABLE rencana_aksi ADD CONSTRAINT rencana_aksi_isian_check CHECK ('.implode(' AND ', [
            "kode ~ '^RA\\.[0-9]+$'",
            'urutan >= 1',
            "btrim(uraian) <> '' AND char_length(uraian) <= 500",
            $pola('kode_sk', FormatKode::POLA_SK),
            $pola('kode_iksk', FormatKode::POLA_IKSK),
            $pola('kode_rktm', '^[0-9]+\.[0-9]+\.[0-9]+$'),
            "(unit IS NULL OR btrim(unit) <> '')",
            'pagu_dipa >= 0 AND pagu_komite >= 0',
            "jsonb_typeof(bulan) = 'array'",
            "NOT jsonb_path_exists(bulan, '\$[*] ? (@.type() != \"number\" || @ < 1 || @ > 12 || @.floor() != @)')",
            "(kode_edm IS NULL OR (jsonb_typeof(kode_edm) = 'array' AND NOT jsonb_path_exists(kode_edm, '\$[*] ? (@.type() != \"string\" || !(@ like_regex \"^[A-Z][0-9]+$\"))')))",
            "jsonb_typeof(budaya_mutu) = 'array'",
            "NOT jsonb_path_exists(budaya_mutu, '\$[*] ? (@.type() != \"number\" || @ < 1 || @.floor() != @)')",
        ]).')');
        DB::statement('ALTER TABLE rencana_aksi ADD CONSTRAINT rencana_aksi_urutan_unik UNIQUE (tahun_anggaran, urutan) DEFERRABLE INITIALLY DEFERRED');
        DB::statement('ALTER TABLE rencana_aksi ADD CONSTRAINT rencana_aksi_tahun_anggaran_rujukan FOREIGN KEY (tahun_anggaran) REFERENCES tahun_anggaran(tahun) ON UPDATE CASCADE DEFERRABLE INITIALLY DEFERRED');
        DB::statement('ALTER TABLE rencana_aksi ADD CONSTRAINT rencana_aksi_kode_leading_rujukan FOREIGN KEY (kode_leading) REFERENCES leadings(kode) ON UPDATE CASCADE ON DELETE SET NULL DEFERRABLE INITIALLY DEFERRED');

        Schema::create('relasi_edm_rktm', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun_anggaran');
            $table->string('kode_edm', 10);
            $table->string('kode_rktm', 20);
            $table->unsignedInteger('urutan');

            $table->unique(['tahun_anggaran', 'kode_edm', 'kode_rktm']);
            $table->index(['tahun_anggaran', 'kode_rktm']);
        });
        DB::statement("ALTER TABLE relasi_edm_rktm ADD CONSTRAINT relasi_edm_rktm_isian_check CHECK (kode_edm ~ '^[A-Z][0-9]+$' AND kode_rktm ~ '^[0-9]+\\.[0-9]+\\.[0-9]+$' AND urutan >= 1)");
        DB::statement('ALTER TABLE relasi_edm_rktm ADD CONSTRAINT relasi_edm_rktm_tahun_anggaran_rujukan FOREIGN KEY (tahun_anggaran) REFERENCES tahun_anggaran(tahun) ON UPDATE CASCADE DEFERRABLE INITIALLY DEFERRED');

        DB::statement('ALTER TABLE master_kode_unggahan ALTER COLUMN cakupan TYPE varchar(20)');
        $this->cakupan([BerkasMaster::SEMUA, ...array_keys(JenisKode::DAFTAR)]);
    }

    public function down(): void
    {
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::table('master_kode_unggahan')->whereNotIn('cakupan', self::CAKUPAN_LAMA)->delete();
        $this->cakupan(self::CAKUPAN_LAMA);
        DB::statement('ALTER TABLE master_kode_unggahan ALTER COLUMN cakupan TYPE varchar(10)');
        Schema::dropIfExists('relasi_edm_rktm');
        Schema::dropIfExists('rencana_aksi');
        $this->polaKode(self::POLA_LAMA_SK, self::POLA_LAMA_IKSK);
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
    }

    private function polaKode(string $sk, string $iksk): void
    {
        DB::statement('ALTER TABLE kode_sk DROP CONSTRAINT kode_sk_isian_check');
        DB::statement("ALTER TABLE kode_sk ADD CONSTRAINT kode_sk_isian_check CHECK (kode ~ '{$sk}' AND btrim(uraian) <> '' AND urutan >= 1)");
        DB::statement('ALTER TABLE kode_iksk DROP CONSTRAINT kode_iksk_isian_check');
        DB::statement("ALTER TABLE kode_iksk ADD CONSTRAINT kode_iksk_isian_check CHECK (kode ~ '{$iksk}' AND kode_sk ~ '{$sk}' AND btrim(uraian) <> '' AND urutan >= 1)");
    }

    /** @param  list<string>  $cakupan */
    private function cakupan(array $cakupan): void
    {
        $daftar = implode(', ', array_map(fn (string $c) => "'{$c}'", $cakupan));
        DB::statement('ALTER TABLE master_kode_unggahan DROP CONSTRAINT master_kode_unggahan_isian_check');
        DB::statement("ALTER TABLE master_kode_unggahan ADD CONSTRAINT master_kode_unggahan_isian_check CHECK (cakupan IN ({$daftar}) AND btrim(nama_berkas) <> '' AND btrim(pengguna) <> '' AND jsonb_typeof(ringkasan) = 'object')");
    }
};
