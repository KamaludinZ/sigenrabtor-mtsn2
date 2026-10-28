<?php

namespace App\Support\Basisdata;

use App\Repositories\EloquentDataPaguRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentMatrikJadwalRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\MatrikTahapan;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Rab\KalkulatorRab;
use App\Support\Stub\StubDataPaguRepository;
use App\Support\Stub\StubKegiatanRepository;
use App\Support\Stub\StubLeadingRepository;
use App\Support\Stub\StubMatrikJadwalRepository;
use App\Support\Stub\StubPenggunaRepository;
use App\Support\Stub\StubRabRepository;
use App\Support\Stub\StubTahunAnggaranRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pindahkan struktur anggaran awal dari penyimpanan tiruan (berkas JSON) ke PostgreSQL dalam satu transaksi — langkah
 * sebelum binding repositori diganti ke Eloquent (perintah `php artisan sigenrabtor:pindah-anggaran`):
 * tahun anggaran → leading → akun (id sama, sandi acak — diatur ulang saat autentikasi nyata dipasang) + tautan leading →
 * Data Pagu (id sama) → kegiatan (id sama, beserta sumber dana & pagu, panitia, riwayat status) → RAB per sumber dana
 * (rincian & termin, termasuk RAB sumber yang tidak dipilih lagi) → matrik & jadwal yang pernah disimpan. Sesudahnya tiap
 * kegiatan × sumber dana diverifikasi (pagu, total RAB, jumlah item, total termin sama); selisih apa pun membatalkan
 * seluruh pemindahan. Hanya untuk database kosong. `uji` = jalankan & verifikasi lalu batalkan (tidak ada yang tersimpan).
 */
final class PindahStrukturAnggaran
{
    /**
     * @return array{tahun: int, leading: int, akun: int, data_pagu: int, kegiatan: int, rab: int, item: int, termin: int, matrik_jadwal: int}
     *
     * @throws RuntimeException Database tidak kosong, atau hasil verifikasi berselisih (semua dibatalkan).
     */
    public static function jalankan(bool $uji = false): array
    {
        if (DB::table('tahun_anggaran')->exists() || DB::table('activities')->exists()) {
            throw new RuntimeException('Database sudah berisi tahun anggaran atau kegiatan — pemindahan hanya untuk database kosong.');
        }

        $ringkasan = null;
        try {
            DB::transaction(function () use ($uji, &$ringkasan) {
                $ringkasan = self::pindahkan();
                $selisih = self::verifikasi();
                if ($selisih !== []) {
                    throw new RuntimeException("Verifikasi gagal, pemindahan dibatalkan:\n- ".implode("\n- ", $selisih));
                }
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                if ($uji) {
                    throw new UjiPindahSelesai;
                }
            });
        } catch (UjiPindahSelesai) {
            // Mode uji: transaksi sengaja dibatalkan sesudah verifikasi lolos.
        }

        return $ringkasan;
    }

    /** @return array{tahun: int, leading: int, akun: int, data_pagu: int, kegiatan: int, rab: int, item: int, termin: int, matrik_jadwal: int} */
    private static function pindahkan(): array
    {
        $hitung = array_fill_keys(['tahun', 'leading', 'akun', 'data_pagu', 'kegiatan', 'rab', 'item', 'termin', 'matrik_jadwal'], 0);
        $waktu = now()->format('Y-m-d H:i:s.uP');

        $tahun = (new StubTahunAnggaranRepository)->semua();
        foreach ($tahun as $t) {
            DB::table('tahun_anggaran')->insert(Arr::except($t, ['id']) + ['created_at' => $waktu, 'updated_at' => $waktu]);
            $hitung['tahun']++;
        }

        $leading = (new StubLeadingRepository)->semua();
        foreach ($leading as $l) {
            DB::table('leadings')->insert($l + ['created_at' => $waktu, 'updated_at' => $waktu]);
            $hitung['leading']++;
        }
        $kodeLeading = array_column($leading, 'kode');

        foreach ((new StubPenggunaRepository)->semua() as $a) {
            DB::table('users')->insert([
                'id' => $a['id'], 'name' => $a['name'], 'email' => mb_strtolower($a['email']), 'password' => Hash::make(Str::random(40)),
                'role' => $a['role'], 'nip' => $a['nip'], 'jabatan' => $a['jabatan'], 'is_active' => $a['is_active'],
                'terakhir_masuk' => $a['terakhir_masuk'], 'created_at' => $a['dibuat_pada'] ?? $waktu, 'updated_at' => $waktu,
            ]);
            if ($a['role'] === 'leading') {
                DB::table('user_leading')->insert(array_map(fn (string $k) => ['user_id' => $a['id'], 'kode_leading' => $k], array_values(array_intersect($a['leadings'], $kodeLeading))));
            }
            $hitung['akun']++;
        }
        self::lanjutkanUrutan('users');

        $dataPagu = new EloquentDataPaguRepository;
        foreach (array_column($tahun, 'tahun') as $t) {
            foreach ((new StubDataPaguRepository)->semua($t) as $p) {
                DB::table('data_pagu')->insert(['id' => $p['id'], 'tahun_anggaran' => $p['tahun'], 'nama_kegiatan' => $p['nama_kegiatan'], 'kode_leading' => $p['leading'], 'asal' => $p['asal']]);
                $dataPagu->simpan($p, $p['id']);
                $hitung['data_pagu']++;
            }
        }

        $kegiatanTiruan = (new StubKegiatanRepository)->semua();
        usort($kegiatanTiruan, fn (array $a, array $b) => $a['id'] <=> $b['id']);
        $kegiatan = new EloquentKegiatanRepository;
        $rabTiruan = new StubRabRepository;
        $rab = new EloquentRabRepository;
        $mjTiruan = new StubMatrikJadwalRepository(new StubKegiatanRepository);
        $mj = new EloquentMatrikJadwalRepository;
        foreach ($kegiatanTiruan as $k) {
            $id = (int) $k['id'];
            // Baris awal ber-id sama (tautan, arsip, notifikasi tetap sah), lalu diisi lewat repositori Eloquent.
            DB::table('activities')->insert(['id' => $id, 'tahun_anggaran' => TahunKegiatan::dari($k), 'nama_kegiatan' => '', 'user_id' => $k['user_id'] ?? null, 'created_at' => $k['dibuat_pada'] ?? $waktu, 'updated_at' => $waktu]);
            $kegiatan->simpan(Arr::except($k, ['id']), $id);
            $hitung['kegiatan']++;

            $sumber = array_values(array_unique([...array_column($k['sumber_dana'] ?? [], 'sumber'), ...$rabTiruan->sumberTersimpan($id)]));
            foreach ($sumber as $s) {
                $rincian = $rabTiruan->rincian($id, $s);
                foreach ($rincian as $item) {
                    $rab->tambahItem($id, $s, Arr::except($item, ['id']));
                }
                $termin = $rabTiruan->termin($id, $s);
                if ($termin !== []) {
                    $rab->simpanTermin($id, $s, $termin);
                }
                $hitung['rab'] += $rincian !== [] || $termin !== [] ? 1 : 0;
                $hitung['item'] += count($rincian);
                $hitung['termin'] += count($termin);
            }

            $matrik = $mjTiruan->matrik($id);
            $jadwal = $mjTiruan->jadwal($id);
            if ($matrik->butir !== MatrikTahapan::dari([])->butir || $jadwal->baris !== JadwalKegiatan::dari([])->baris) {
                $mj->simpan($id, $matrik, $jadwal);
                $hitung['matrik_jadwal']++;
            }
        }
        self::lanjutkanUrutan('activities');

        return $hitung;
    }

    /**
     * Bandingkan pagu, total RAB, jumlah item, dan total termin tiap kegiatan × sumber dana tiruan vs database.
     *
     * @return list<string> Uraian selisih (kosong = sama).
     */
    private static function verifikasi(): array
    {
        $kegiatanTiruan = (new StubKegiatanRepository)->semua();
        $kegiatanDb = new EloquentKegiatanRepository;
        $rabTiruan = new StubRabRepository;
        $rabDb = new EloquentRabRepository;
        $selisih = [];

        foreach ($kegiatanTiruan as $k) {
            $id = (int) $k['id'];
            $db = $kegiatanDb->temukan($id);
            if ($db === null) {
                $selisih[] = "Kegiatan {$id} tidak ada di database.";

                continue;
            }
            if (array_column($k['sumber_dana'] ?? [], 'pagu', 'sumber') != array_column($db['sumber_dana'], 'pagu', 'sumber')) {
                $selisih[] = "Kegiatan {$id}: sumber dana/pagu berbeda.";
            }
            foreach (array_unique([...array_column($k['sumber_dana'] ?? [], 'sumber'), ...$rabTiruan->sumberTersimpan($id)]) as $s) {
                $a = KalkulatorRab::hitung($rabTiruan->rincian($id, $s));
                $b = KalkulatorRab::hitung($rabDb->rincian($id, $s));
                $terminA = array_sum(array_column($rabTiruan->termin($id, $s), 'nominal'));
                $terminB = array_sum(array_column($rabDb->termin($id, $s), 'nominal'));
                if ([$a->total, count($a->rincian), $terminA] !== [$b->total, count($b->rincian), $terminB]) {
                    $selisih[] = "Kegiatan {$id} {$s}: total RAB {$a->total} vs {$b->total}, item ".count($a->rincian).' vs '.count($b->rincian).", termin {$terminA} vs {$terminB}.";
                }
            }
        }

        return $selisih;
    }

    /** Urutan id lanjut sesudah id terbesar yang dipindahkan (baris baru tidak bentrok). */
    private static function lanjutkanUrutan(string $tabel): void
    {
        DB::statement("SELECT setval(pg_get_serial_sequence('{$tabel}', 'id'), GREATEST((SELECT COALESCE(max(id), 0) FROM {$tabel}), 1), (SELECT max(id) IS NOT NULL FROM {$tabel}))");
    }
}
