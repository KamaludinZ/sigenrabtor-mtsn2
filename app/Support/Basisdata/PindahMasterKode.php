<?php

namespace App\Support\Basisdata;

use App\Repositories\EloquentMasterKodeRepository;
use App\Support\Stub\StubMasterKodeRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

/**
 * Sinkronkan master kode tiruan (berkas data/master-{tahun}.php ditimpa master hasil unggahan di berkas JSON) ke tabel
 * master kode PostgreSQL (perintah `php artisan sigenrabtor:pindah-master`, boleh pada database berisi): per tahun yang
 * sudah ada di tabel tahun_anggaran, tiap bagian (StubMasterKodeRepository::BAGIAN) yang berbeda diganti seluruhnya
 * lewat EloquentMasterKodeRepository::ganti, yang sama dibiarkan; tahun tanpa baris tahun_anggaran dilewati
 * (`tanpa_tahun`). Jejak unggahan tiruan disalin dengan id yang sama bila belum ada. Satu transaksi, lalu diverifikasi:
 * master tiap tahun harus terbaca sama persis lewat EloquentMasterKodeRepository — selisih membatalkan semuanya.
 * `uji` = jalankan & verifikasi lalu batalkan.
 */
final class PindahMasterKode
{
    /**
     * @param  list<int>|null  $tahun  null = semua tahun master tiruan.
     * @return array{ganti: array<int, list<string>>, tetap: list<int>, tanpa_tahun: list<int>, unggahan: int}
     *
     * @throws RuntimeException Verifikasi berselisih atau database menolak (semua dibatalkan).
     */
    public static function jalankan(bool $uji = false, ?array $tahun = null): array
    {
        $tiruan = new StubMasterKodeRepository;
        $tahun ??= $tiruan->tahunTersedia();
        $hasil = null;

        try {
            DB::transaction(function () use ($uji, $tahun, $tiruan, &$hasil) {
                $hasil = self::sinkronkan($tiruan, $tahun);
                $selisih = [];
                foreach ([...array_keys($hasil['ganti']), ...$hasil['tetap']] as $t) {
                    foreach (self::beda($tiruan, $t) as $bagian) {
                        $selisih[] = "TA {$t} {$bagian}";
                    }
                }
                if ($selisih !== []) {
                    throw new RuntimeException("Verifikasi gagal, pemindahan master kode dibatalkan:\n- ".implode("\n- ", array_unique($selisih)));
                }
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                if ($uji) {
                    throw new UjiPindahSelesai;
                }
            });
        } catch (UjiPindahSelesai) {
            // Mode uji: transaksi sengaja dibatalkan sesudah verifikasi lolos.
        }

        return $hasil;
    }

    /**
     * @param  list<int>  $tahun
     * @return array{ganti: array<int, list<string>>, tetap: list<int>, tanpa_tahun: list<int>, unggahan: int}
     */
    private static function sinkronkan(StubMasterKodeRepository $tiruan, array $tahun): array
    {
        $ada = DB::table('tahun_anggaran')->pluck('tahun')->map(fn ($t) => (int) $t)->all();
        $hasil = ['ganti' => [], 'tetap' => [], 'tanpa_tahun' => [], 'unggahan' => 0];

        foreach ($tahun as $t) {
            if (! in_array($t, $ada, true)) {
                $hasil['tanpa_tahun'][] = $t;

                continue;
            }
            $beda = self::beda($tiruan, $t);
            if ($beda === []) {
                $hasil['tetap'][] = $t;
            } else {
                (new EloquentMasterKodeRepository)->ganti($t, array_intersect_key(self::master($tiruan, $t), array_flip($beda)));
                $hasil['ganti'][$t] = $beda;
            }

            $tersalin = DB::table('master_kode_unggahan')->pluck('id')->all();
            foreach (array_reverse($tiruan->unggahan($t)) as $u) {
                if (in_array($u['id'], $tersalin, true)) {
                    continue;
                }
                DB::table('master_kode_unggahan')->insert([
                    'id' => $u['id'], 'tahun_anggaran' => $t, 'cakupan' => $u['cakupan'], 'nama_berkas' => $u['nama_berkas'],
                    'ringkasan' => json_encode($u['ringkasan'] === [] ? new stdClass : $u['ringkasan']),
                    'user_id' => DB::table('users')->where('id', $u['user_id'])->exists() ? $u['user_id'] : null,
                    'pengguna' => $u['pengguna'], 'created_at' => $u['waktu'],
                ]);
                $hasil['unggahan']++;
            }
        }

        return $hasil;
    }

    /** @return list<string> Bagian master tiruan tahun $tahun yang berbeda dengan database. */
    private static function beda(StubMasterKodeRepository $tiruan, int $tahun): array
    {
        $db = self::master(new EloquentMasterKodeRepository, $tahun);

        return array_values(array_keys(array_filter(self::master($tiruan, $tahun), fn ($baris, string $bagian) => $baris != $db[$bagian], ARRAY_FILTER_USE_BOTH)));
    }

    /** @return array<string, array<mixed>> Master satu tahun berkunci StubMasterKodeRepository::BAGIAN. */
    private static function master(StubMasterKodeRepository|EloquentMasterKodeRepository $repo, int $tahun): array
    {
        return [
            'kode_sk' => $repo->kodeSk($tahun),
            'kode_iksk' => $repo->kodeIksk($tahun),
            'kode_rktm' => $repo->kodeRktm($tahun),
            'kode_edm' => $repo->kodeEdm($tahun),
            'komponen_edm' => $repo->komponenEdm($tahun),
            'budaya_mutu' => $repo->budayaMutu($tahun),
        ];
    }
}
