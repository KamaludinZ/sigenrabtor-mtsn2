<?php

use App\Support\Kegiatan\KegagalanAi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Simulasi kegagalan Pengaturan AI menerima jenis kegagalan baru KegagalanAi::JENIS (`penyedia_sibuk` — galat 5xx/529
 * penyedia, `permintaan_ditolak` — galat 400 & penolakan model): CHECK `ai_settings_simulasi_check` (migrasi 000037)
 * dibangun ulang dari daftar jenis saat ini. Rollback mengembalikan daftar sebelumnya (simulasi berjenis baru dimatikan).
 */
return new class extends Migration
{
    /** Jenis yang ditambahkan migrasi ini. */
    private const BARU = ['penyedia_sibuk', 'permintaan_ditolak'];

    public function up(): void
    {
        $this->pasang(array_keys(KegagalanAi::JENIS));
    }

    public function down(): void
    {
        $lama = array_values(array_diff(array_keys(KegagalanAi::JENIS), self::BARU));
        DB::table('ai_settings')->whereIn(DB::raw("simulasi_gagal->>'jenis'"), self::BARU)->update(['simulasi_gagal' => null]);
        $this->pasang($lama);
    }

    /** @param  list<string>  $jenis */
    private function pasang(array $jenis): void
    {
        $daftar = implode(', ', array_map(fn (string $j) => "'{$j}'", $jenis));
        DB::statement('ALTER TABLE ai_settings DROP CONSTRAINT IF EXISTS ai_settings_simulasi_check');
        DB::statement(<<<SQL
            ALTER TABLE ai_settings ADD CONSTRAINT ai_settings_simulasi_check CHECK (
                simulasi_gagal IS NULL OR (
                    jsonb_typeof(simulasi_gagal) = 'object' AND simulasi_gagal - ARRAY['jenis', 'cakupan'] = '{}'::jsonb
                    AND COALESCE(simulasi_gagal->>'jenis', '') IN ({$daftar}) AND COALESCE(simulasi_gagal->>'cakupan', '') IN ('aktif', 'semua')
                )
            )
            SQL);
    }
};
