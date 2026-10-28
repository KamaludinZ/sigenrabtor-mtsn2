<?php

namespace App\Support\Basisdata;

use App\Repositories\EloquentPengaturanAiRepository;
use App\Support\Pengaturan\ParameterAi;
use App\Support\Stub\StubPengaturanAiRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sinkronkan Pengaturan AI tiruan (StubPengaturanAiRepository: penyedia aktif & cadangan, konfigurasi & kunci API
 * terenkripsi tiap penyedia, parameter jawaban, batas harian umum & per akun, simulasi kegagalan) ke PostgreSQL (perintah
 * `php artisan sigenrabtor:pindah-pengaturan-ai`, boleh pada database berisi): bila berbeda, seluruh pengaturan ditulis
 * lewat EloquentPengaturanAiRepository::simpan; satu transaksi, kesiapan penyedia diperiksa seketika, lalu diverifikasi —
 * pengaturan harus terbaca sama (`bentuk()`, tanpa diperbarui_pada) lewat repositori database, selisih atau penolakan
 * database membatalkan semuanya. Kunci API disalin apa adanya (terenkripsi dengan APP_KEY yang sama). `uji` = jalankan &
 * verifikasi lalu batalkan.
 */
final class PindahPengaturanAi
{
    /**
     * @return array{status: 'diganti'|'tetap', provider_aktif: string|null, provider_cadangan: string|null, penyedia: list<string>}
     *
     * @throws RuntimeException
     */
    public static function jalankan(bool $uji = false): array
    {
        $tiruan = self::bentuk((new StubPengaturanAiRepository)->ambil());
        $db = new EloquentPengaturanAiRepository;
        $hasil = null;

        try {
            DB::transaction(function () use ($uji, $tiruan, $db, &$hasil) {
                $sama = DB::table('ai_settings')->exists() && self::bentuk($db->ambil()) === $tiruan;
                if (! $sama) {
                    $db->simpan($tiruan);
                }
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

                $tersimpan = self::bentuk($db->ambil());
                if ($tersimpan !== $tiruan) {
                    $beda = array_keys(array_filter($tiruan, fn ($nilai, string $kunci) => $tersimpan[$kunci] !== $nilai, ARRAY_FILTER_USE_BOTH));
                    throw new RuntimeException('Verifikasi gagal, pemindahan pengaturan AI dibatalkan: '.implode(', ', $beda).' berbeda.');
                }
                $hasil = ['status' => $sama ? 'tetap' : 'diganti', 'provider_aktif' => $tiruan['provider_aktif'], 'provider_cadangan' => $tiruan['provider_cadangan'], 'penyedia' => array_keys($tiruan['penyedia'])];
                if ($uji) {
                    throw new UjiPindahSelesai;
                }
            });
        } catch (UjiPindahSelesai) {
            // Mode uji: transaksi sengaja dibatalkan sesudah verifikasi lolos.
        } catch (QueryException $e) {
            throw new RuntimeException('Database menolak pengaturan AI tiruan — periksa penyedia aktif & cadangan di Pengaturan AI. '.$e->getPrevious()?->getMessage(), previous: $e);
        }

        return $hasil;
    }

    /**
     * Bentuk pembanding pengaturan (dibaca seperti EloquentPengaturanAiRepository): tanpa diperbarui_pada, penyedia &
     * kunci terurut, kosong = '' / null, kunci hanya bila ada kunci terenkripsi.
     *
     * @param  array<string, mixed>  $ai
     * @return array<string, mixed>
     */
    public static function bentuk(array $ai): array
    {
        $penyedia = [];
        foreach ((array) ($ai['penyedia'] ?? []) as $kunci => $p) {
            $ada = (bool) ($p['ada_kunci'] ?? false) && filled($p['kunci_terenkripsi'] ?? null);
            $penyedia[$kunci] = [
                'base_url' => (string) ($p['base_url'] ?? ''),
                'model' => (string) ($p['model'] ?? ''),
                'batas_waktu' => isset($p['batas_waktu']) ? (int) $p['batas_waktu'] : null,
                'ada_kunci' => $ada,
                'kunci_akhir' => $ada ? ($p['kunci_akhir'] ?? null) : null,
                'kunci_terenkripsi' => $ada ? $p['kunci_terenkripsi'] : null,
            ];
        }
        ksort($penyedia);
        $parameter = ParameterAi::dari($ai);
        $batas = array_map('intval', (array) ($ai['batas_khusus'] ?? []));
        ksort($batas);
        $simulasi = $ai['simulasi_gagal'] ?? null;

        return [
            'provider_aktif' => $ai['provider_aktif'] ?? null,
            'provider_cadangan' => $ai['provider_cadangan'] ?? null,
            'penyedia' => $penyedia,
            'parameter' => $parameter,
            'batas_per_user_per_hari' => (int) ($ai['batas_per_user_per_hari'] ?? StubPengaturanAiRepository::bawaan()['batas_per_user_per_hari']),
            'batas_khusus' => $batas,
            'simulasi_gagal' => $simulasi === null ? null : ['jenis' => $simulasi['jenis'] ?? null, 'cakupan' => $simulasi['cakupan'] ?? null],
        ];
    }
}
