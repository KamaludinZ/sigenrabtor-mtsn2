<?php

use App\Support\Pengaturan\DaftarDasarHukum;
use App\Support\Pengaturan\LatarBelakangBaku;
use App\Support\Pengaturan\TeksBaku;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dasar hukum & latar belakang baku per tahun (PRD §13, kontrak DasarHukumRepository) — skema disamakan dengan aturan
 * aplikasi:
 * - dasar_hukum: id butir berupa teks (id tiruan seperti "dh-2026-1" tetap utuh saat dipindahkan; butir baru UUID),
 *   unik per tahun; teks satu baris berspasi tunggal tanpa tanda baca akhir (DaftarDasarHukum::rapikan), hanya penanda
 *   DaftarDasarHukum::boleh(), dan tidak dobel dalam satu tahun (tanpa memandang huruf besar).
 * - latar_belakang_baku (satu baris per tahun yang sudah diatur; `updated_at` bermikrodetik = versi untuk mengenali
 *   perubahan dari tab lain): paragraf baku ≤ MAKS_PARAGRAF satu paragraf hanya dengan {tahun}; kalimat penutup
 *   ≤ MAKS_PENUTUP satu baris dengan penanda TeksBaku::bolehPenutup() ({nama_kegiatan} tepat sekali — constraint lama).
 * Fungsi `penanda_asing_ada(teks, boleh[])` = padanan TeksBaku::penandaAsing() !== [].
 */
return new class extends Migration
{
    /** Satu baris, tanpa spasi di tepi, tanpa spasi ganda. */
    private const SATU_BARIS = "%1\$s = btrim(%1\$s) AND %1\$s !~ '[\\n\\r\\t]' AND %1\$s !~ '[[:space:]]{2,}'";

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION penanda_asing_ada(teks text, boleh text[]) RETURNS boolean
            LANGUAGE plpgsql IMMUTABLE AS $$
            DECLARE
                p text;
            BEGIN
                FOREACH p IN ARRAY boleh LOOP
                    teks := replace(teks, p, '');
                END LOOP;
                RETURN teks ~ '\{[^{}]*\}';
            END
            $$;
            SQL);

        DB::statement('ALTER TABLE dasar_hukum DROP CONSTRAINT dasar_hukum_pkey');
        DB::statement('ALTER TABLE dasar_hukum ALTER COLUMN id TYPE varchar(64) USING id::text');
        DB::statement('ALTER TABLE dasar_hukum ADD CONSTRAINT dasar_hukum_pkey PRIMARY KEY (tahun_anggaran, id)');
        DB::statement("ALTER TABLE dasar_hukum ADD CONSTRAINT dasar_hukum_id_check CHECK (btrim(id) <> '')");
        DB::statement('ALTER TABLE dasar_hukum DROP CONSTRAINT dasar_hukum_isian_check');
        DB::statement('ALTER TABLE dasar_hukum ADD CONSTRAINT dasar_hukum_isian_check CHECK ('.sprintf(self::SATU_BARIS, 'teks')
            ." AND teks <> '' AND teks !~ '[;.,]$' AND NOT penanda_asing_ada(teks, ".self::larik(DaftarDasarHukum::boleh()).')'
            .' AND urutan BETWEEN 1 AND '.DaftarDasarHukum::MAKS_BUTIR.')');
        DB::statement('CREATE UNIQUE INDEX dasar_hukum_teks_unik ON dasar_hukum (tahun_anggaran, lower(teks))');

        DB::statement('ALTER TABLE latar_belakang_baku ADD CONSTRAINT latar_belakang_isian_check CHECK ('
            .sprintf(self::SATU_BARIS, 'latar_belakang')." AND latar_belakang <> '' AND char_length(latar_belakang) <= ".LatarBelakangBaku::MAKS_PARAGRAF
            .' AND NOT penanda_asing_ada(latar_belakang, '.self::larik([TeksBaku::TAHUN]).')'
            .' AND '.sprintf(self::SATU_BARIS, 'kalimat_penutup').' AND char_length(kalimat_penutup) <= '.LatarBelakangBaku::MAKS_PENUTUP
            .' AND NOT penanda_asing_ada(kalimat_penutup, '.self::larik(TeksBaku::bolehPenutup()).'))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE latar_belakang_baku DROP CONSTRAINT latar_belakang_isian_check');
        DB::statement('DROP INDEX dasar_hukum_teks_unik');
        DB::statement('ALTER TABLE dasar_hukum DROP CONSTRAINT dasar_hukum_isian_check');
        DB::statement("ALTER TABLE dasar_hukum ADD CONSTRAINT dasar_hukum_isian_check CHECK (btrim(teks) <> '' AND urutan BETWEEN 1 AND 30)");
        DB::statement('ALTER TABLE dasar_hukum DROP CONSTRAINT dasar_hukum_id_check');
        DB::statement('ALTER TABLE dasar_hukum DROP CONSTRAINT dasar_hukum_pkey');
        // Id tiruan yang bukan UUID diganti UUID baru agar kolom bisa kembali bertipe uuid.
        DB::statement("ALTER TABLE dasar_hukum ALTER COLUMN id TYPE uuid USING (CASE WHEN id ~* '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$' THEN id::uuid ELSE gen_random_uuid() END)");
        DB::statement('ALTER TABLE dasar_hukum ADD CONSTRAINT dasar_hukum_pkey PRIMARY KEY (id)');
        DB::statement('DROP FUNCTION IF EXISTS penanda_asing_ada(text, text[])');
    }

    /** @param  list<string>  $nilai */
    private static function larik(array $nilai): string
    {
        return 'ARRAY['.implode(', ', array_map(fn (string $v) => DB::getPdo()->quote($v), $nilai)).']::text[]';
    }
};
