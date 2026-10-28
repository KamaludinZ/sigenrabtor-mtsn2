<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\PratinjauDokumen;
use App\Support\Dokumen\KelengkapanDokumen;
use App\Support\Dokumen\PenampilDokumen;
use App\Support\Dokumen\PenandaKosong;
use App\Support\Dokumen\SusunanKak;
use App\Support\Dokumen\SusunanOtomatis;
use App\Support\Dokumen\SusunanRab;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class KelengkapanDokumenTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private function kelengkapan(array $kegiatan, ?string $sumber = null): KelengkapanDokumen
    {
        return KelengkapanDokumen::dari(SusunanKak::dari($kegiatan, $sumber), SusunanRab::dari($kegiatan, $sumber), $kegiatan, self::LEADING);
    }

    public function test_status_tiap_bagian_cover_kak_rab(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $kak = SusunanKak::dari($kegiatan);
        $rab = SusunanRab::dari($kegiatan);
        $hasil = KelengkapanDokumen::dari($kak, $rab, $kegiatan, self::LEADING);

        // Kunci & jangkar sama dengan daftar isi penampil.
        $isi = PenampilDokumen::isi($kak, $rab);
        $this->assertSame(array_column($isi, 'kunci'), array_column($hasil->bagian, 'kunci'));
        $this->assertSame(array_column($isi, 'jangkar'), array_column($hasil->bagian, 'jangkar'));
        $this->assertSame(array_column(SusunanOtomatis::dari($kak, $rab, $kegiatan, self::LEADING)->bagian, 'status'), array_column($hasil->dokumen('kak'), 'status'));
        $this->assertSame(['cover' => 'lengkap', 'rab' => 'lengkap'], [$hasil->bagian[0]['kunci'] => $hasil->bagian[0]['status'], 'rab' => $hasil->dokumen('rab')[0]['status']]);
        $this->assertSame('RAB DIPA', $hasil->dokumen('rab')[0]['label']);
        $this->assertSame(11, $hasil->jumlah());
        $this->assertSame($hasil->jumlah(), $hasil->jumlah('lengkap') + $hasil->jumlah('kurang') + $hasil->jumlah('periksa'));
        $this->assertSame((int) floor($hasil->jumlah('lengkap') / 11 * 100), $hasil->persen());
        $this->assertSame($kak->statusDraf->draf || $rab->statusDraf->draf, $hasil->draf->draf);

        // RAB melebihi pagu (contoh 3 Komite) = perlu diperiksa.
        $tiga = $this->kelengkapan(app(KegiatanRepository::class)->temukan(3), 'Komite');
        $this->assertSame('periksa', $tiga->dokumen('rab')[0]['status']);
        $this->assertStringContainsString('melebihi pagu', $tiga->dokumen('rab')[0]['periksa'][0]);

        // Kegiatan baru tanpa nama & sumber dana: cover & RAB kurang, isian wajib dihitung.
        $id = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => '', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        $baru = app(KegiatanRepository::class)->temukan($id);
        $kosong = $this->kelengkapan($baru);
        $this->assertSame(['kurang', ['nama kegiatan']], [$kosong->bagian[0]['status'], $kosong->bagian[0]['kurang']]);
        $this->assertSame('kurang', $kosong->dokumen('rab')[0]['status']);
        $this->assertContains('sumber dana', $kosong->dokumen('rab')[0]['kurang']);
        $this->assertSame(PenandaKosong::ringkasan(PenandaKosong::daftar(SusunanKak::dari($baru), SusunanRab::dari($baru)))['wajib'], $kosong->wajib);
        $this->assertGreaterThan(0, $kosong->wajib);
        $this->assertLessThan($hasil->persen(), $kosong->persen());
    }

    public function test_komponen_ringkasan_kelengkapan(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(2);
        $kelengkapan = $this->kelengkapan($kegiatan);
        $html = Blade::render('<x-dokumen.ringkasan-kelengkapan :kelengkapan="$k" />', ['k' => $kelengkapan]);

        $this->assertStringContainsString('data-testid="ringkasan-kelengkapan" data-persen="'.$kelengkapan->persen().'"', $html);
        $this->assertStringContainsString('data-testid="persen-kelengkapan">'.$kelengkapan->persen().'%</span>', $html);
        $this->assertSame(11, substr_count($html, 'data-kelengkapan="'));
        $this->assertStringContainsString('href="#cetak-kak-bagian-7" data-kelengkapan="kak-7" data-status="kurang"', $html);
        $this->assertStringContainsString('href="#dok-rab" data-kelengkapan="rab"', $html);
        $this->assertMatchesRegularExpression('/data-hitung="kurang">.*?>'.$kelengkapan->jumlah('kurang').'<\/span> perlu dilengkapi/s', $html);
        $this->assertStringNotContainsString('bagianAktif', $html, 'Di luar penampil tanpa Alpine.');
        $this->assertStringNotContainsString('ke-isian-kosong', $html);
        $this->assertStringContainsString('Tercetak sebagai DRAF', $html);

        // Di Pratinjau: paling atas panel samping, terhubung ke penampil (bagian aktif & isian kosong berikutnya).
        $pratinjau = Livewire::test(PratinjauDokumen::class, ['kegiatan' => 2])->html();
        $this->assertStringContainsString('data-testid="ringkasan-kelengkapan"', $pratinjau);
        $this->assertLessThan(strpos($pratinjau, 'id="judul-kaidah"'), strpos($pratinjau, 'data-testid="ringkasan-kelengkapan"'));
        $this->assertStringContainsString("bagianAktif === 'kak-7' ? 'location' : null", $pratinjau);
        $this->assertStringContainsString('data-testid="ke-isian-kosong"', $pratinjau);
    }
}
