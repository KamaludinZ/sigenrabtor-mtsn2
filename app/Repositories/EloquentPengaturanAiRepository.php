<?php

namespace App\Repositories;

use App\Contracts\PengaturanAiRepository;
use App\Models\PengaturanAi;
use App\Models\PengaturanPenyediaAi;
use App\Support\Pengaturan\ParameterAi;
use App\Support\Pengaturan\PenyediaAi;
use App\Support\Stub\StubPengaturanAiRepository;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pengaturan AI di PostgreSQL (ai_settings satu baris + ai_provider_settings per penyedia; aturan migrasi 000037),
 * padanan StubPengaturanAiRepository: tanpa baris = StubPengaturanAiRepository::bawaan(); penyedia = Ollama bawaan
 * ditimpa baris tersimpan, tiap penyedia berbentuk {base_url, model ('' bila kosong), batas_waktu, ada_kunci (= kunci
 * terenkripsi ada), kunci_akhir, kunci_terenkripsi}; parameter dilengkapi ParameterAi::BAWAAN; `diperbarui_pada` =
 * updated_at ai_settings (ISO bermikrodetik zona aplikasi), diperbarui setiap simpan. simpan() satu transaksi: baris
 * pengaturan di-upsert, penyedia yang dikirim di-upsert ('' → null, ada_kunci false → kunci dikosongkan), penyedia
 * yang tidak dikirim dihapus (seperti tiruan yang mengganti seluruh isi), penyedia tak dikenal diabaikan; ditolak
 * database (mis. penyedia aktif belum siap saat commit) → RuntimeException.
 */
class EloquentPengaturanAiRepository implements PengaturanAiRepository
{
    public function ambil(): array
    {
        $bawaan = StubPengaturanAiRepository::bawaan();
        $baris = PengaturanAi::find(1);
        if ($baris === null) {
            return $bawaan;
        }

        $penyedia = $bawaan['penyedia'];
        foreach (PengaturanPenyediaAi::all() as $p) {
            $penyedia[$p->penyedia] = [
                'base_url' => $p->base_url ?? '',
                'model' => $p->model ?? '',
                'batas_waktu' => $p->batas_waktu,
                'ada_kunci' => $p->kunci_terenkripsi !== null,
                'kunci_akhir' => $p->kunci_akhir,
                'kunci_terenkripsi' => $p->kunci_terenkripsi,
            ];
        }

        return [
            'provider_aktif' => $baris->provider_aktif,
            'provider_cadangan' => $baris->provider_cadangan,
            'penyedia' => $penyedia,
            'parameter' => ($baris->parameter ?? []) + ParameterAi::BAWAAN,
            'batas_per_user_per_hari' => $baris->batas_per_user_per_hari,
            'batas_khusus' => $baris->batas_khusus ?? [],
            'simulasi_gagal' => $baris->simulasi_gagal,
            'diperbarui_pada' => CarbonImmutable::parse($baris->getRawOriginal('updated_at'))->setTimezone(config('app.timezone'))->format('Y-m-d\TH:i:s.uP'),
        ];
    }

    public function simpan(array $data): void
    {
        $bawaan = StubPengaturanAiRepository::bawaan();

        try {
            DB::transaction(function () use ($data, $bawaan) {
                // Kesiapan penyedia aktif & cadangan diperiksa sekali di akhir transaksi, apa pun urutan tulisnya.
                DB::statement('SET CONSTRAINTS ai_settings_penyedia_siap, ai_provider_settings_penyedia_siap DEFERRED');
                $sekarang = now()->format('Y-m-d H:i:s.uP');
                DB::table('ai_settings')->upsert([[
                    'id' => 1,
                    'provider_aktif' => $data['provider_aktif'] ?? null,
                    'provider_cadangan' => $data['provider_cadangan'] ?? null,
                    'parameter' => json_encode((object) ($data['parameter'] ?? $bawaan['parameter']), JSON_PRESERVE_ZERO_FRACTION),
                    'batas_per_user_per_hari' => (int) ($data['batas_per_user_per_hari'] ?? $bawaan['batas_per_user_per_hari']),
                    'batas_khusus' => json_encode((object) ($data['batas_khusus'] ?? [])),
                    'simulasi_gagal' => ($data['simulasi_gagal'] ?? null) === null ? null : json_encode((object) $data['simulasi_gagal']),
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ]], ['id'], ['provider_aktif', 'provider_cadangan', 'parameter', 'batas_per_user_per_hari', 'batas_khusus', 'simulasi_gagal', 'updated_at']);

                $penyedia = array_intersect_key((array) ($data['penyedia'] ?? []), PenyediaAi::DAFTAR);
                DB::table('ai_provider_settings')->whereNotIn('penyedia', array_keys($penyedia))->delete();
                foreach ($penyedia as $kunci => $p) {
                    $adaKunci = (bool) ($p['ada_kunci'] ?? false) && filled($p['kunci_terenkripsi'] ?? null);
                    DB::table('ai_provider_settings')->upsert([[
                        'penyedia' => $kunci,
                        'base_url' => filled($p['base_url'] ?? null) ? (string) $p['base_url'] : null,
                        'model' => filled($p['model'] ?? null) ? (string) $p['model'] : null,
                        'batas_waktu' => isset($p['batas_waktu']) ? (int) $p['batas_waktu'] : null,
                        'kunci_terenkripsi' => $adaKunci ? (string) $p['kunci_terenkripsi'] : null,
                        'kunci_akhir' => $adaKunci ? (string) ($p['kunci_akhir'] ?? '') : null,
                        'created_at' => $sekarang,
                        'updated_at' => $sekarang,
                    ]], ['penyedia'], ['base_url', 'model', 'batas_waktu', 'kunci_terenkripsi', 'kunci_akhir', 'updated_at']);
                }
            });
        } catch (QueryException $e) {
            throw new RuntimeException('Pengaturan AI gagal disimpan. Coba lagi.', previous: $e);
        }
    }
}
