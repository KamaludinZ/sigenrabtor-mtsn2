<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
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
        $nginx = (string) file_get_contents("{$akar}/docker/nginx/default.conf");
        $this->assertMatchesRegularExpression('/fastcgi_read_timeout (3[1-9]\d|[4-9]\d\d)s;/', $nginx);
        $this->assertStringContainsString('client_max_body_size 25m;', $nginx);

        // Contoh variabel lingkungan memuat semua kunci yang dibaca konfigurasi deploy.
        $contoh = (string) file_get_contents("{$akar}/docker/env.produksi.contoh");
        foreach (['APP_KEY=', 'APP_URL=', 'DB_CONNECTION=pgsql', 'SIRABTOR_PROKSI_TEPERCAYA=', 'SIRABTOR_MODE_DEMO=', 'SIRABTOR_WAJIB_MASUK=', 'SIRABTOR_PDF_PERENDER=chrome', 'SESSION_SECURE_COOKIE=true'] as $kunci) {
            $this->assertStringContainsString($kunci, $contoh);
        }
    }
}
