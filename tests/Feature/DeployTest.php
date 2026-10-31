<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use App\Support\Pengaturan\AturanPenyediaAi;
use Illuminate\Http\Middleware\TrustProxies;
use Tests\TestCase;

class DeployTest extends TestCase
{
    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    public function test_tanpa_proksi_tepercaya_header_forwarded_diabaikan(): void
    {
        $this->get('/up', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9'])->assertOk();

        $this->assertFalse(request()->isSecure());
        $this->assertSame('127.0.0.1', request()->ip());
    }

    public function test_proksi_tepercaya_membuat_url_https_dan_ip_asli(): void
    {
        AppServiceProvider::pasangProksiTepercaya(' * ');

        $this->get('/up', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Host' => 'sirabtor.example.com'])->assertOk();

        $this->assertTrue(request()->isSecure());
        $this->assertSame('203.0.113.9', request()->ip());
        $this->assertStringStartsWith('https://sirabtor.example.com/', url('/masuk'));
    }

    public function test_daftar_proksi_hanya_alamat_yang_disebut(): void
    {
        AppServiceProvider::pasangProksiTepercaya('10.0.0.0/8, 172.16.0.0/12 ,');

        $this->get('/up', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9'])->assertOk();
        // Permintaan tes datang dari 127.0.0.1 — bukan proksi yang dipercaya.
        $this->assertFalse(request()->isSecure());

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])
            ->get('/up', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9'])->assertOk();
        $this->assertTrue(request()->isSecure());
        $this->assertSame('203.0.113.9', request()->ip());
    }

    public function test_berkas_deploy_saling_cocok(): void
    {
        $akar = base_path();
        $dockerfile = (string) file_get_contents("{$akar}/Dockerfile");
        foreach (['docker/php/php.ini', 'docker/php/www.conf', 'docker/nginx/default.conf', 'docker/supervisord.conf', 'docker/entrypoint.sh'] as $berkas) {
            $this->assertFileExists("{$akar}/{$berkas}");
            $this->assertStringContainsString("COPY {$berkas} ", $dockerfile, "{$berkas} tidak disalin Dockerfile");
        }
        $this->assertTrue(is_executable("{$akar}/docker/entrypoint.sh"));

        // Mesin dokumen yang dipanggil aplikasi terpasang di image.
        foreach (['chromium', 'libreoffice-calc-nogui', 'poppler-utils', 'locales'] as $paket) {
            $this->assertStringContainsString($paket, $dockerfile);
        }
        $this->assertStringContainsString('id_ID.UTF-8', $dockerfile);

        // Batas unggahan & waktu cukup untuk berkas terbesar (10 MB) dan batas waktu AI terpanjang (300 detik).
        $ini = parse_ini_file("{$akar}/docker/php/php.ini");
        $this->assertGreaterThanOrEqual(10, (int) $ini['upload_max_filesize']);
        $this->assertGreaterThan(300, (int) $ini['max_execution_time']);
        // Seluruh permintaan AI (aktif + cadangan + coba ulang) selesai sebelum PHP/nginx memutus, dan tetap memberi
        // penyedia batas waktu terpanjangnya.
        $this->assertLessThan((int) $ini['max_execution_time'], config('sigenrabtor.ai.batas_permintaan_detik'));
        $this->assertGreaterThan(AturanPenyediaAi::BATAS_WAKTU[1], config('sigenrabtor.ai.batas_permintaan_detik'));
        $nginx = (string) file_get_contents("{$akar}/docker/nginx/default.conf");
        $this->assertMatchesRegularExpression('/fastcgi_read_timeout (3[1-9]\d|[4-9]\d\d)s;/', $nginx);
        $this->assertStringContainsString('client_max_body_size 25m;', $nginx);

        // Contoh variabel lingkungan memuat semua kunci yang dibaca konfigurasi deploy.
        $contoh = (string) file_get_contents("{$akar}/docker/env.produksi.contoh");
        foreach (['APP_KEY=', 'APP_URL=', 'DB_CONNECTION=pgsql', 'SIRABTOR_AI_BATAS_PERMINTAAN_DETIK=', 'SIRABTOR_PROKSI_TEPERCAYA=', 'SIRABTOR_MODE_DEMO=', 'SIRABTOR_WAJIB_MASUK=', 'SIRABTOR_PDF_PERENDER=chrome', 'SESSION_SECURE_COOKIE=true'] as $kunci) {
            $this->assertStringContainsString($kunci, $contoh);
        }
    }

    public function test_redeploy_aman(): void
    {
        $akar = base_path();
        $dockerfile = (string) file_get_contents("{$akar}/Dockerfile");
        $entrypoint = (string) file_get_contents("{$akar}/docker/entrypoint.sh");
        $compose = (string) file_get_contents("{$akar}/docker-compose.prod.yml");
        $supervisor = (string) file_get_contents("{$akar}/docker/supervisord.conf");

        // pg_dump versi server (18) & penanda rilis untuk cadangan otomatis.
        $this->assertStringContainsString('postgresql-client-18', $dockerfile);
        $this->assertStringContainsString('postgres:18-alpine', $compose);
        $this->assertStringContainsString('> /etc/sirabtor-rilis', $dockerfile);
        $this->assertSame('/etc/sirabtor-rilis', config('sigenrabtor.deploy.berkas_rilis'));

        // Entrypoint: tunggu database, lalu pengaman + cadangan + migrasi dalam satu kunci berkas di volume bersama.
        $this->assertStringContainsString('pg_isready', $entrypoint);
        $this->assertStringContainsString('flock -w 900 storage/app/private/deploy/.kunci php artisan sigenrabtor:siapkan-deploy', $entrypoint);
        $this->assertStringNotContainsString('migrate --force', $entrypoint, 'Migrasi hanya lewat sigenrabtor:siapkan-deploy.');

        // Berhenti anggun: waktu tenggang kontainer > waktu tunggu pekerja antrean > --timeout antrean.
        preg_match('/stop_grace_period: (\d+)m(\d+)s/', $compose, $tenggang);
        preg_match('/\[program:antrean\][^\[]*stopwaitsecs=(\d+)/s', $supervisor, $tungguAntrean);
        $this->assertGreaterThan((int) $tungguAntrean[1], (int) $tenggang[1] * 60 + (int) $tenggang[2]);
        $this->assertGreaterThan(360, (int) $tungguAntrean[1]);
        $this->assertSame(2, substr_count($supervisor, 'stopsignal=QUIT'), 'nginx & PHP-FPM berhenti anggun.');

        // Variabel pengaman tercantum di compose & contoh; skrip redeploy server biasa siap dijalankan.
        $contoh = (string) file_get_contents("{$akar}/docker/env.produksi.contoh");
        foreach (['SIRABTOR_CADANGAN_PRA_MIGRASI', 'SIRABTOR_CADANGAN_HARIAN', 'SIRABTOR_CADANGAN_SIMPAN', 'SIRABTOR_IZINKAN_GANTI_APP_KEY', 'SIRABTOR_IZINKAN_DATA_BARU'] as $kunci) {
            $this->assertStringContainsString("{$kunci}:", $compose);
            $this->assertStringContainsString("{$kunci}=", $contoh);
        }
        $this->assertStringContainsString('name: sirabtor', (string) file_get_contents("{$akar}/docker-compose.vps.yml"));
        $this->assertTrue(is_executable("{$akar}/docker/redeploy.sh"));
        $this->assertTrue(is_executable("{$akar}/docker/pulihkan.sh"));
        $this->assertStringContainsString('COPY docker/pulihkan.sh /usr/local/bin/sirabtor-pulihkan', $dockerfile);
        $pulihkan = (string) file_get_contents("{$akar}/docker/pulihkan.sh");
        $this->assertLessThan(strpos($pulihkan, 'pg_restore --clean'), strpos($pulihkan, 'www "php artisan down'), 'Mode pemeliharaan sebelum memulihkan.');
        $this->assertLessThan(strpos($pulihkan, 'www "php artisan down'), strpos($pulihkan, 'www "php artisan sigenrabtor:cadangan --alasan=pra-pemulihan'), 'Keadaan sekarang dicadangkan dulu.');
        $this->assertStringContainsString('sigenrabtor:cadangan --alasan=pra-redeploy', (string) file_get_contents("{$akar}/docker/redeploy.sh"));
    }

    public function test_image_membawa_bawaan_aman_produksi_untuk_build_pack_dockerfile(): void
    {
        $akar = base_path();
        $dockerfile = (string) file_get_contents("{$akar}/Dockerfile");
        $compose = (string) file_get_contents("{$akar}/docker-compose.prod.yml");
        preg_match_all('/^(?:ENV)?\s+([A-Z_]+)=(\S+?)(?: \\\\)?$/m', $dockerfile, $env);
        $image = array_combine($env[1], $env[2]);
        preg_match_all('/^\s+([A-Z_]+): \$\{\1:-([^}]*)\}$/m', $compose, $bawaan);
        $composeBawaan = array_combine($bawaan[1], $bawaan[2]);

        // Deploy lewat Dockerfile saja tidak membawa bawaan compose: nilai penting keamanan & keandalan ada di image juga.
        foreach (['APP_ENV', 'APP_DEBUG', 'SESSION_DRIVER', 'SESSION_SECURE_COOKIE', 'CACHE_STORE', 'QUEUE_CONNECTION', 'DB_QUEUE_RETRY_AFTER', 'LOG_CHANNEL',
            'SIRABTOR_PROKSI_TEPERCAYA', 'SIRABTOR_WAJIB_MASUK', 'SIRABTOR_MODE_DEMO', 'SIRABTOR_CADANGAN_PRA_MIGRASI', 'SIRABTOR_CADANGAN_HARIAN', 'SIRABTOR_CADANGAN_SIMPAN'] as $kunci) {
            $this->assertArrayHasKey($kunci, $image, "{$kunci} tidak punya nilai bawaan di image");
            $this->assertSame($composeBawaan[$kunci], $image[$kunci], "{$kunci} di image berbeda dengan bawaan compose");
        }
        $this->assertSame(['pgsql', 'false', 'false'], [$image['DB_CONNECTION'], $image['SIRABTOR_MODE_DEMO'], $image['APP_DEBUG']]);

        // Yang tidak punya bawaan aman wajib diisi; entrypoint menolak berjalan tanpanya.
        $entrypoint = (string) file_get_contents("{$akar}/docker/entrypoint.sh");
        $this->assertStringContainsString('for wajib in APP_URL DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD', $entrypoint);
        $this->assertArrayNotHasKey('APP_KEY', $image);
        $this->assertArrayNotHasKey('DB_PASSWORD', $image);
    }
}
