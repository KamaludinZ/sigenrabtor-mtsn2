<?php

use App\Support\Dokumen\TeksCover;
use App\Support\Dokumen\VariabelTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Variabel template & kotak teks cover di database (PRD §7A–C, tabel document_templates & cover_templates):
 * - `template_variabel` = daftar variabel master KAK/TOR (VariabelTemplate::DAFTAR: keterangan, kelompok, jenis
 *   teks/daftar/tabel/blok/gambar) dan `cover_field` = kotak teks cover (TeksCover::FIELD) — keduanya rujukan yang diisi
 *   dari konstanta aplikasi saat migrasi; variabel baru di aplikasi wajib ditambahkan lewat migrasi berikutnya (tes
 *   memastikan keduanya sama).
 * - document_templates.variabel hanya boleh memuat variabel terdaftar tanpa ganda (trigger, padanan AnalisisTemplate).
 * - cover_templates.teks_items = daftar kotak sesuai TeksCover::rapikan(): field terdaftar tanpa ganda, x/y 0–100,
 *   width 5–(100 − x), font_size 6–48 pt, align left/center/right, weight normal/bold, color #rrggbb huruf kecil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_variabel', function (Blueprint $table) {
            $table->string('kunci', 40)->primary();
            $table->string('keterangan');
            $table->string('kelompok', 20);
            $table->string('jenis', 10);
            $table->unsignedSmallInteger('urutan')->unique();
        });
        DB::statement("ALTER TABLE template_variabel ADD CONSTRAINT template_variabel_jenis_check CHECK (jenis IN ('teks', 'daftar', 'tabel', 'blok', 'gambar') AND kunci ~ '^[a-z][a-z0-9_]*$')");
        DB::table('template_variabel')->insert(array_map(
            fn (string $kunci, array $v, int $i) => ['kunci' => $kunci, 'keterangan' => $v[0], 'kelompok' => $v[1], 'jenis' => $v[2], 'urutan' => $i + 1],
            array_keys(VariabelTemplate::DAFTAR), VariabelTemplate::DAFTAR, array_keys(array_keys(VariabelTemplate::DAFTAR)),
        ));

        Schema::create('cover_field', function (Blueprint $table) {
            $table->string('kunci', 30)->primary();
            $table->string('label', 100);
            $table->unsignedSmallInteger('urutan')->unique();
        });
        DB::table('cover_field')->insert(array_map(
            fn (string $kunci, string $label, int $i) => ['kunci' => $kunci, 'label' => $label, 'urutan' => $i + 1],
            array_keys(TeksCover::FIELD), TeksCover::FIELD, array_keys(array_keys(TeksCover::FIELD)),
        ));

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION periksa_variabel_template() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                asing text;
            BEGIN
                IF EXISTS (SELECT 1 FROM jsonb_array_elements(NEW.variabel) e WHERE jsonb_typeof(e) <> 'string') THEN
                    RAISE EXCEPTION 'variabel template harus berupa daftar nama' USING ERRCODE = 'check_violation';
                END IF;
                IF (SELECT count(*) FROM jsonb_array_elements_text(NEW.variabel)) <> (SELECT count(DISTINCT v) FROM jsonb_array_elements_text(NEW.variabel) v) THEN
                    RAISE EXCEPTION 'variabel template ganda' USING ERRCODE = 'check_violation';
                END IF;
                SELECT v INTO asing FROM jsonb_array_elements_text(NEW.variabel) v
                    WHERE NOT EXISTS (SELECT 1 FROM template_variabel t WHERE t.kunci = v) LIMIT 1;
                IF asing IS NOT NULL THEN
                    RAISE EXCEPTION 'variabel template % tidak terdaftar', asing USING ERRCODE = 'foreign_key_violation';
                END IF;
                RETURN NEW;
            END $$
            SQL);
        DB::statement('CREATE TRIGGER document_templates_variabel BEFORE INSERT OR UPDATE OF variabel ON document_templates FOR EACH ROW EXECUTE FUNCTION periksa_variabel_template()');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION periksa_teks_cover() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                k jsonb;
                x numeric;
            BEGIN
                IF (SELECT count(*) FROM jsonb_array_elements(NEW.teks_items)) <> (SELECT count(DISTINCT e ->> 'field') FROM jsonb_array_elements(NEW.teks_items) e) THEN
                    RAISE EXCEPTION 'kotak teks cover ganda' USING ERRCODE = 'check_violation';
                END IF;
                FOR k IN SELECT * FROM jsonb_array_elements(NEW.teks_items) LOOP
                    IF jsonb_typeof(k) <> 'object'
                        OR NOT EXISTS (SELECT 1 FROM cover_field f WHERE f.kunci = k ->> 'field')
                        OR COALESCE(jsonb_typeof(k -> 'x'), '') <> 'number' OR COALESCE(jsonb_typeof(k -> 'y'), '') <> 'number'
                        OR COALESCE(jsonb_typeof(k -> 'width'), '') <> 'number' OR COALESCE(jsonb_typeof(k -> 'font_size'), '') <> 'number' THEN
                        RAISE EXCEPTION 'kotak teks cover tidak sah: %', k USING ERRCODE = 'check_violation';
                    END IF;
                    x := (k ->> 'x')::numeric;
                    IF x < 0 OR x > 100 OR (k ->> 'y')::numeric NOT BETWEEN 0 AND 100
                        OR (k ->> 'width')::numeric < 5 OR (k ->> 'width')::numeric > 100 - x
                        OR (k ->> 'font_size')::numeric NOT BETWEEN 6 AND 48
                        OR (k ->> 'align') IS DISTINCT FROM 'left' AND (k ->> 'align') IS DISTINCT FROM 'center' AND (k ->> 'align') IS DISTINCT FROM 'right'
                        OR (k ->> 'weight') IS DISTINCT FROM 'normal' AND (k ->> 'weight') IS DISTINCT FROM 'bold'
                        OR (k ->> 'color') !~ '^#[0-9a-f]{6}$' OR (k ->> 'color') IS NULL THEN
                        RAISE EXCEPTION 'kotak teks cover tidak sah: %', k USING ERRCODE = 'check_violation';
                    END IF;
                END LOOP;
                RETURN NEW;
            END $$
            SQL);
        DB::statement('CREATE TRIGGER cover_templates_teks BEFORE INSERT OR UPDATE OF teks_items ON cover_templates FOR EACH ROW EXECUTE FUNCTION periksa_teks_cover()');
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER cover_templates_teks ON cover_templates');
        DB::statement('DROP TRIGGER document_templates_variabel ON document_templates');
        DB::statement('DROP FUNCTION periksa_teks_cover()');
        DB::statement('DROP FUNCTION periksa_variabel_template()');
        Schema::dropIfExists('cover_field');
        Schema::dropIfExists('template_variabel');
    }
};
