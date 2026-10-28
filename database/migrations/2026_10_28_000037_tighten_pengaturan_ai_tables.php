<?php

use App\Support\Kegiatan\BatasAi;
use App\Support\Kegiatan\KegagalanAi;
use App\Support\Pengaturan\ParameterAi;
use App\Support\Pengaturan\PromptAi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Skema Pengaturan AI (PRD §21, F24; tabel 000020 ai_settings, ai_provider_settings, prompt_templates) diperketat agar
 * sama dengan aturan isian aplikasi:
 * - ai_settings: `parameter` hanya suhu 0–1, maks_token ParameterAi::MAKS_TOKEN, jumlah_versi ParameterAi::JUMLAH_VERSI;
 *   `batas_khusus` {id akun: batas bulat BatasAi::RENTANG} (fungsi batas_khusus_ai_sah); `simulasi_gagal` null atau
 *   {jenis KegagalanAi::JENIS, cakupan aktif|semua};
 * - ai_provider_settings: alamat Ollama http(s)://host[:port][/jalur] dan alamat API cloud https (tanpa query/fragmen,
 *   tanpa garis miring akhir), model berpola AturanPenyediaAi (tanpa spasi), kunci API hanya untuk penyedia cloud dan
 *   4 karakter akhirnya tepat 4;
 * - penyedia aktif & cadangan wajib siap (AturanPenyediaAi::siap: Ollama alamat & model, cloud model & kunci API) —
 *   constraint trigger tertunda periksa_penyedia_ai_siap pada ai_settings & ai_provider_settings (diperiksa saat commit);
 * - prompt_templates: gaya bahasa tidak kosong (baris `umum` wajib bergaya bahasa), instruksi hanya berpenanda
 *   PromptAi::PENANDA dan tanpa NIP (PromptAi::galat).
 */
return new class extends Migration
{
    public function up(): void
    {
        $token = implode(', ', array_map(fn (int $t) => "'{$t}'", array_keys(ParameterAi::MAKS_TOKEN)));
        $versi = implode(', ', array_map(fn (int $v) => "'{$v}'", ParameterAi::JUMLAH_VERSI));
        $jenis = implode(', ', array_map(fn (string $j) => "'{$j}'", array_keys(KegagalanAi::JENIS)));
        [$min, $maks] = BatasAi::RENTANG;
        $penanda = implode('|', array_map(fn (string $p) => trim($p, '{}'), array_keys(PromptAi::PENANDA)));

        DB::statement(<<<SQL
            ALTER TABLE ai_settings ADD CONSTRAINT ai_settings_parameter_check CHECK (
                parameter - ARRAY['suhu', 'maks_token', 'jumlah_versi'] = '{}'::jsonb
                AND (parameter->'suhu' IS NULL OR (jsonb_typeof(parameter->'suhu') = 'number' AND (parameter->>'suhu')::numeric BETWEEN 0 AND 1))
                AND (parameter->'maks_token' IS NULL OR (jsonb_typeof(parameter->'maks_token') = 'number' AND parameter->>'maks_token' IN ({$token})))
                AND (parameter->'jumlah_versi' IS NULL OR (jsonb_typeof(parameter->'jumlah_versi') = 'number' AND parameter->>'jumlah_versi' IN ({$versi})))
            )
            SQL);

        DB::statement(<<<SQL
            CREATE OR REPLACE FUNCTION batas_khusus_ai_sah(batas jsonb) RETURNS boolean LANGUAGE sql IMMUTABLE AS \$\$
                SELECT jsonb_typeof(batas) = 'object' AND NOT EXISTS (
                    SELECT 1 FROM jsonb_each(batas) AS e
                    WHERE e.key !~ '^[1-9][0-9]*$' OR jsonb_typeof(e.value) <> 'number' OR e.value::text !~ '^[0-9]{1,3}$'
                        OR e.value::text::int NOT BETWEEN {$min} AND {$maks}
                )
            \$\$
            SQL);
        DB::statement('ALTER TABLE ai_settings ADD CONSTRAINT ai_settings_batas_khusus_check CHECK (batas_khusus_ai_sah(batas_khusus))');

        DB::statement(<<<SQL
            ALTER TABLE ai_settings ADD CONSTRAINT ai_settings_simulasi_check CHECK (
                simulasi_gagal IS NULL OR (
                    jsonb_typeof(simulasi_gagal) = 'object' AND simulasi_gagal - ARRAY['jenis', 'cakupan'] = '{}'::jsonb
                    AND COALESCE(simulasi_gagal->>'jenis', '') IN ({$jenis}) AND COALESCE(simulasi_gagal->>'cakupan', '') IN ('aktif', 'semua')
                )
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE ai_provider_settings ADD CONSTRAINT ai_provider_settings_isian_check CHECK (
                (base_url IS NULL OR (
                    base_url ~ (CASE WHEN penyedia = 'ollama' THEN '^https?://' ELSE '^https://' END || '[^/?#[:space:]]+(/[^?#[:space:]]*)?$')
                    AND right(base_url, 1) <> '/'
                ))
                AND (model IS NULL OR (char_length(model) <= 100 AND model ~* '^[a-z0-9][a-z0-9._:/-]*$'))
                AND (penyedia <> 'ollama' OR kunci_terenkripsi IS NULL)
                AND (kunci_akhir IS NULL OR char_length(kunci_akhir) = 4)
            )
            SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION periksa_penyedia_ai_siap() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                pilihan text;
            BEGIN
                FOR pilihan IN SELECT unnest(ARRAY[provider_aktif, provider_cadangan]) FROM ai_settings LOOP
                    CONTINUE WHEN pilihan IS NULL;
                    IF NOT EXISTS (
                        SELECT 1 FROM ai_provider_settings p
                        WHERE p.penyedia = pilihan AND p.model IS NOT NULL
                            AND (CASE WHEN p.penyedia = 'ollama' THEN p.base_url IS NOT NULL ELSE p.kunci_terenkripsi IS NOT NULL END)
                    ) THEN
                        RAISE EXCEPTION 'Penyedia AI % belum siap dipakai: % belum diatur.', pilihan,
                            CASE WHEN pilihan = 'ollama' THEN 'alamat server & model' ELSE 'model & kunci API' END
                            USING ERRCODE = 'check_violation';
                    END IF;
                END LOOP;
                RETURN NULL;
            END
            $$
            SQL);
        DB::statement('CREATE CONSTRAINT TRIGGER ai_settings_penyedia_siap AFTER INSERT OR UPDATE ON ai_settings DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION periksa_penyedia_ai_siap()');
        DB::statement('CREATE CONSTRAINT TRIGGER ai_provider_settings_penyedia_siap AFTER UPDATE OR DELETE ON ai_provider_settings DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION periksa_penyedia_ai_siap()');

        DB::statement(<<<SQL
            ALTER TABLE prompt_templates ADD CONSTRAINT prompt_templates_isian_check CHECK (
                (gaya_bahasa IS NULL OR btrim(gaya_bahasa) <> '')
                AND (bagian <> 'umum' OR gaya_bahasa IS NOT NULL)
                AND (instruksi IS NULL OR (
                    regexp_replace(instruksi, '\\{({$penanda})\\}', '', 'g') !~ '\\{[^{}]*\\}'
                    AND instruksi !~ '(^|[^0-9])[0-9]{18}([^0-9]|$)' AND instruksi !~ '[0-9]{8}[[:space:]][0-9]{6}[[:space:]][0-9][[:space:]][0-9]{3}'
                    AND instruksi !~* '\\mNIP\\M'
                ))
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE prompt_templates DROP CONSTRAINT IF EXISTS prompt_templates_isian_check');
        DB::statement('DROP TRIGGER IF EXISTS ai_provider_settings_penyedia_siap ON ai_provider_settings');
        DB::statement('DROP TRIGGER IF EXISTS ai_settings_penyedia_siap ON ai_settings');
        DB::statement('DROP FUNCTION IF EXISTS periksa_penyedia_ai_siap()');
        DB::statement('ALTER TABLE ai_provider_settings DROP CONSTRAINT IF EXISTS ai_provider_settings_isian_check');
        DB::statement('ALTER TABLE ai_settings DROP CONSTRAINT IF EXISTS ai_settings_simulasi_check');
        DB::statement('ALTER TABLE ai_settings DROP CONSTRAINT IF EXISTS ai_settings_batas_khusus_check');
        DB::statement('DROP FUNCTION IF EXISTS batas_khusus_ai_sah(jsonb)');
        DB::statement('ALTER TABLE ai_settings DROP CONSTRAINT IF EXISTS ai_settings_parameter_check');
    }
};
