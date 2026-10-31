<?php

namespace App\Support\Basisdata;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pengaman deploy & redeploy (docs/deploy.md bagian "Redeploy aman"; dipanggil entrypoint kontainer lewat
 * `sigenrabtor:siapkan-deploy` di dalam kunci berkas sehingga dua kontainer tidak bermigrasi bersamaan):
 * - `periksa()` — sebelum apa pun ditulis: APP_KEY sama dengan saat data volume ini dibuat (kunci API AI terenkripsi
 *   dengannya; sidiknya dicatat, bukan kuncinya), dan volume storage ↔ database berasal dari instalasi yang sama (id di
 *   `PENANDA` volume dan tabel `instalasi`) — volume baru/kosong di atas database lama, database kosong di bawah volume
 *   lama, atau pasangan yang tertukar dihentikan dengan pesan, kecuali config `deploy.izinkan_*` (pemulihan disengaja);
 * - `perluCadangan()` — ada migrasi yang belum dijalankan atau rilis image berganti (`deploy.berkas_rilis`);
 * - `cadangan($alasan)` — set cadangan `cadangan/{Ymd-His}-{alasan}/`: `database.dump` (pg_dump -Fc, hanya bila database
 *   sudah berisi) + `data.tgz` (storage/app tanpa folder cadangan) + `info.json`; gagal → RuntimeException (migrasi tidak
 *   dijalankan); set terlama melebihi `deploy.cadangan_simpan` dibuang; `daftar()` set yang ada;
 * - `tandai()` — sesudah migrasi: id instalasi di database & volume, sidik APP_KEY, rilis.
 */
final class PengamanDeploy
{
    public const PENANDA = 'deploy/instalasi.json';

    public const FOLDER_CADANGAN = 'cadangan';

    /** Nama folder set cadangan: waktu UTC-lokal + alasan. */
    private const POLA_SET = '/^\d{8}-\d{6}-[a-z0-9-]+$/';

    /**
     * Masalah yang menghentikan deploy (kosong = aman dilanjutkan).
     *
     * @return list<string>
     */
    public static function periksa(): array
    {
        $penanda = self::penanda();
        $idDb = self::idDatabase();
        $izinkanData = (bool) config('sigenrabtor.deploy.izinkan_data_baru');
        $masalah = [];

        if ($penanda !== null && ($penanda['sidik_app_key'] ?? null) !== self::sidikAppKey() && ! config('sigenrabtor.deploy.izinkan_ganti_app_key')) {
            $masalah[] = 'APP_KEY berbeda dengan saat data di volume ini dibuat — kunci API AI & sesi tidak akan terbaca. '
                .'Kembalikan APP_KEY lama. Bila memang sengaja diganti, jalankan sekali dengan SIRABTOR_IZINKAN_GANTI_APP_KEY=true lalu isi ulang kunci API di Pengaturan AI.';
        }
        if ($izinkanData) {
            return $masalah;
        }
        if ($penanda === null && $idDb !== null) {
            $masalah[] = 'Volume storage kosong/baru, padahal database sudah dipakai instalasi '.$idDb.' — kemungkinan volume /var/www/html/storage tidak terpasang atau namanya berubah. '
                .'Pasang volume lama. Bila memang memulihkan storage dari cadangan, jalankan sekali dengan SIRABTOR_IZINKAN_DATA_BARU=true.';
        } elseif ($penanda !== null && $idDb !== null && $penanda['id'] !== $idDb) {
            $masalah[] = 'Volume storage (instalasi '.$penanda['id'].') dan database (instalasi '.$idDb.') berasal dari instalasi berbeda — periksa DB_HOST/DB_DATABASE dan volume yang terpasang.';
        } elseif ($penanda !== null && $idDb === null && self::databaseKosong()) {
            $masalah[] = 'Database kosong/baru, padahal volume storage berisi data instalasi '.$penanda['id'].' — periksa DB_HOST/DB_DATABASE atau volume database. '
                .'Bila memang memulihkan database dari cadangan, jalankan sekali dengan SIRABTOR_IZINKAN_DATA_BARU=true.';
        }

        return $masalah;
    }

    /**
     * Alasan membuat cadangan sebelum migrasi (null = tidak perlu).
     */
    public static function perluCadangan(): ?string
    {
        if (! config('sigenrabtor.deploy.cadangan_pra_migrasi') || (self::databaseKosong() && ! self::adaData())) {
            return null;
        }
        if (self::migrasiTertunda() > 0) {
            return 'pra-migrasi';
        }
        $rilis = self::rilis();
        $penanda = self::penanda();

        return $rilis !== null && $penanda !== null && ($penanda['rilis'] ?? null) !== $rilis ? 'rilis-baru' : null;
    }

    /**
     * @return array{folder: string, berkas: array<string, int>}
     *
     * @throws RuntimeException pg_dump/tar gagal.
     */
    public static function cadangan(string $alasan): array
    {
        $alasan = Str::slug($alasan) ?: 'manual';
        $disk = Storage::disk('local');
        $folder = self::FOLDER_CADANGAN.'/'.now()->format('Ymd-His').'-'.$alasan;
        $disk->makeDirectory($folder);
        $berkas = [];

        try {
            if (! self::databaseKosong()) {
                $db = config('database.connections.'.config('database.default'));
                self::jalankan([
                    (string) config('sigenrabtor.deploy.pg_dump'), '--format=custom', '--no-owner', '--no-privileges',
                    '--host='.$db['host'], '--port='.$db['port'], '--username='.$db['username'], '--dbname='.$db['database'],
                    '--file='.$disk->path("{$folder}/database.dump"),
                ], ['PGPASSWORD' => (string) $db['password']], 'pg_dump');
                $berkas['database.dump'] = self::ukuran("{$folder}/database.dump", 'pg_dump');
            }
            if (self::adaData()) {
                self::jalankan([
                    (string) config('sigenrabtor.deploy.tar'), '--create', '--gzip', '--file='.$disk->path("{$folder}/data.tgz"),
                    '--directory='.storage_path('app'), '--exclude=./private/'.self::FOLDER_CADANGAN, '.',
                ], [], 'tar');
                $berkas['data.tgz'] = self::ukuran("{$folder}/data.tgz", 'tar');
            }
        } catch (RuntimeException $e) {
            $disk->deleteDirectory($folder);

            throw $e;
        }

        $disk->put("{$folder}/info.json", json_encode([
            'alasan' => $alasan,
            'dibuat_pada' => now()->toIso8601String(),
            'rilis' => self::rilis(),
            'versi' => config('sigenrabtor.aplikasi.versi'),
            'migrasi_tertunda' => self::migrasiTertunda(),
            'berkas' => $berkas,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        self::rapikanCadangan();

        return ['folder' => $folder, 'berkas' => $berkas];
    }

    /**
     * Set cadangan yang ada, terbaru dulu.
     *
     * @return list<array{folder: string, info: array<string, mixed>|null}>
     */
    public static function daftar(): array
    {
        $disk = Storage::disk('local');
        $folder = array_values(array_filter($disk->directories(self::FOLDER_CADANGAN), fn (string $f) => preg_match(self::POLA_SET, basename($f))));
        rsort($folder);

        return array_map(fn (string $f) => ['folder' => $f, 'info' => json_decode((string) $disk->get("{$f}/info.json"), true) ?: null], $folder);
    }

    /** Catat id instalasi di database & volume, sidik APP_KEY, dan rilis (sesudah migrasi). */
    public static function tandai(): string
    {
        $penanda = self::penanda();
        $id = self::idDatabase() ?? $penanda['id'] ?? (string) Str::uuid();
        if (Schema::hasTable('instalasi') && self::idDatabase() === null) {
            DB::table('instalasi')->insert(['id' => $id]);
        }
        Storage::disk('local')->put(self::PENANDA, json_encode([
            'id' => $id,
            'sidik_app_key' => self::sidikAppKey(),
            'rilis' => self::rilis(),
            'dibuat_pada' => $penanda['dibuat_pada'] ?? now()->toIso8601String(),
            'diperbarui_pada' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $id;
    }

    public static function migrasiTertunda(): int
    {
        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            return count($migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths())));
        }
        $berkas = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));

        return count(array_diff(array_keys($berkas), $migrator->getRepository()->getRan()));
    }

    /** Sidik APP_KEY (sha256, bukan kuncinya) — penanda volume membandingkannya saat redeploy. */
    public static function sidikAppKey(): string
    {
        return substr(hash('sha256', 'sirabtor-app-key|'.config('app.key')), 0, 32);
    }

    public static function rilis(): ?string
    {
        $berkas = (string) config('sigenrabtor.deploy.berkas_rilis');

        return $berkas !== '' && is_file($berkas) ? (trim((string) file_get_contents($berkas)) ?: null) : null;
    }

    /** @return array<string, mixed>|null */
    public static function penanda(): ?array
    {
        $isi = Storage::disk('local')->exists(self::PENANDA) ? json_decode((string) Storage::disk('local')->get(self::PENANDA), true) : null;

        return is_array($isi) && filled($isi['id'] ?? null) ? $isi : null;
    }

    private static function idDatabase(): ?string
    {
        return Schema::hasTable('instalasi') ? (DB::table('instalasi')->orderBy('dibuat_pada')->value('id') ?: null) : null;
    }

    /** Database belum pernah dimigrasi (instalasi baru). */
    private static function databaseKosong(): bool
    {
        return ! app('migrator')->repositoryExists();
    }

    /** Volume storage sudah berisi data aplikasi (berkas data, unggahan) selain cadangan & penanda. */
    private static function adaData(): bool
    {
        $disk = Storage::disk('local');

        return collect($disk->directories())->reject(fn (string $f) => in_array($f, [self::FOLDER_CADANGAN, 'deploy'], true))
            ->contains(fn (string $f) => $disk->allFiles($f) !== []);
    }

    /**
     * @param  list<string>  $perintah
     * @param  array<string, string>  $env
     */
    private static function jalankan(array $perintah, array $env, string $nama): void
    {
        $hasil = Process::timeout(max(60, (int) config('sigenrabtor.deploy.batas_detik')))->env($env)->run($perintah);
        if (! $hasil->successful()) {
            throw new RuntimeException("Cadangan gagal ({$nama}): ".trim(Str::limit($hasil->errorOutput() ?: $hasil->output(), 400)));
        }
    }

    private static function ukuran(string $path, string $nama): int
    {
        if (! Storage::disk('local')->exists($path)) {
            throw new RuntimeException("Cadangan gagal ({$nama}): berkas {$path} tidak terbentuk.");
        }

        return (int) Storage::disk('local')->size($path);
    }

    private static function rapikanCadangan(): void
    {
        $simpan = max(1, (int) config('sigenrabtor.deploy.cadangan_simpan'));
        foreach (array_slice(self::daftar(), $simpan) as $lama) {
            Storage::disk('local')->deleteDirectory($lama['folder']);
        }
    }
}
