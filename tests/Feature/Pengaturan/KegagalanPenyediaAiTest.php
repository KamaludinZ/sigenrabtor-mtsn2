<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\RiwayatSaranAiRepository;
use App\Livewire\Kegiatan\KalimatAi;
use App\Livewire\Kegiatan\MatrikJadwal;
use App\Livewire\Pengaturan\PengaturanAi;
use App\Support\Kegiatan\GagalPenyediaAi;
use App\Support\Kegiatan\GagalUsulanAi;
use App\Support\Kegiatan\KegagalanAi;
use App\Support\Kegiatan\RantaiPenyediaAi;
use Livewire\Livewire;
use Tests\TestCase;

class KegagalanPenyediaAiTest extends TestCase
{
    /** @param  array<string, mixed>  $ubah */
    private function atur(array $ubah): void
    {
        $data = array_diff_key(app(PengaturanAiRepository::class)->ambil(), ['diperbarui_pada' => 1]);
        app(PengaturanAiRepository::class)->simpan(array_replace_recursive($data, $ubah));
    }

    private function denganCadangan(): void
    {
        $this->atur(['provider_aktif' => 'ollama', 'provider_cadangan' => 'claude', 'penyedia' => ['ollama' => ['model' => 'qwen2.5:7b'], 'claude' => ['model' => 'claude-sonnet-5-5', 'ada_kunci' => true]]]);
    }

    private function gagal(\Closure $jalan): GagalPenyediaAi
    {
        try {
            $jalan();
        } catch (GagalPenyediaAi $e) {
            return $e;
        }
        $this->fail('Seharusnya gagal.');
    }

    public function test_rantai_penyedia_aktif_lalu_cadangan(): void
    {
        $this->assertSame(['hasil' => 'ok', 'penyedia' => 'tiruan', 'gagal' => []], RantaiPenyediaAi::jalankan(fn () => 'ok'));

        $this->atur(['simulasi_gagal' => ['jenis' => 'waktu_habis', 'cakupan' => 'aktif']]);
        $e = $this->gagal(fn () => RantaiPenyediaAi::jalankan(fn () => 'ok'));
        $this->assertSame(['waktu_habis', true, false, KegagalanAi::pesan('waktu_habis')], [$e->jenis, $e->simulasi, $e->cadanganGagal, $e->getMessage()]);

        $this->denganCadangan();
        $hasil = RantaiPenyediaAi::jalankan(fn (string $p) => "jawaban {$p}");
        $this->assertSame(['jawaban claude', 'claude'], [$hasil['hasil'], $hasil['penyedia']]);
        $this->assertSame('Disusun penyedia cadangan (Claude (Anthropic)) karena penyedia aktif gagal: waktu tunggu habis.', RantaiPenyediaAi::catatan($hasil));

        $this->atur(['simulasi_gagal' => ['jenis' => 'kunci_ditolak', 'cakupan' => 'semua']]);
        $e = $this->gagal(fn () => RantaiPenyediaAi::jalankan(fn () => 'ok'));
        $this->assertSame('Penyedia AI menolak permintaan ini. Hubungi admin madrasah. Penyedia cadangan juga gagal.', $e->getMessage());

        // Kegagalan nyata tanpa jenis: pesan asli dipertahankan, cadangan mengambil alih.
        $this->atur(['simulasi_gagal' => null]);
        $hasil = RantaiPenyediaAi::jalankan(fn (string $p) => $p === 'ollama' ? throw new GagalUsulanAi('Server tidak menjawab.') : 'dari cadangan');
        $this->assertSame(['dari cadangan', 'jawaban_rusak', 'Server tidak menjawab.'], [$hasil['hasil'], $hasil['gagal'][0]->jenis, $hasil['gagal'][0]->getMessage()]);
        $this->assertSame([false, false, true], [KegagalanAi::bisaCobaLagi('kunci_ditolak'), KegagalanAi::bisaCobaLagi('model_tidak_ada'), KegagalanAi::bisaCobaLagi('waktu_habis')]);
    }

    public function test_pesan_kegagalan_di_kalimat_kak_dan_usulan_jadwal(): void
    {
        $this->atur(['simulasi_gagal' => ['jenis' => 'tidak_terhubung', 'cakupan' => 'semua']]);
        Livewire::test(KalimatAi::class, ['kegiatan' => 1])
            ->call('mintaSaran', 'tujuan')
            ->assertSet('galatAi', ['tujuan' => KegagalanAi::pesan('tidak_terhubung')])
            ->assertSeeHtml('data-tombol-ai="tujuan" data-keadaan="gagal"')
            ->assertDontSee('Buka Pengaturan AI');
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])->set('usulJenis', 'umum')->call('buatUsulJadwal')
            ->assertSet('usulGagal', KegagalanAi::pesan('tidak_terhubung'));

        session(['stub_role' => 'admin']);
        Livewire::test(KalimatAi::class, ['kegiatan' => 1])
            ->call('mintaSaran', 'tujuan')
            ->assertSee('(simulasi kegagalan dari Pengaturan AI)')
            ->assertSeeHtml('href="'.route('pengaturan.ai.index').'"');
        session(['stub_role' => 'leading']);

        // Hanya penyedia aktif yang gagal → cadangan menjawab, catatan tampil di modal, riwayat mencatat cadangan.
        $this->denganCadangan();
        $this->atur(['simulasi_gagal' => ['jenis' => 'waktu_habis', 'cakupan' => 'aktif']]);
        Livewire::test(KalimatAi::class, ['kegiatan' => 1])
            ->call('mintaSaran', 'tujuan')
            ->assertSet('saranTerbuka', 'tujuan')
            ->assertSeeHtml('data-testid="catatan-penyedia"')
            ->assertSee('Disusun penyedia cadangan (Claude (Anthropic)) karena penyedia aktif gagal: waktu tunggu habis.');
        $this->assertSame('claude', app(RiwayatSaranAiRepository::class)->semua()[0]['penyedia']);
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])->call('bukaUsulJadwal')->set('usulJenis', 'umum')->call('buatUsulJadwal')
            ->assertSeeHtml('data-testid="catatan-penyedia-jadwal"');
    }

    public function test_admin_mengatur_dan_mematikan_simulasi(): void
    {
        session(['stub_role' => 'admin']);
        Livewire::test(PengaturanAi::class)
            ->assertSeeHtml('data-testid="status-simulasi">Mati')
            ->assertDontSeeHtml('data-testid="banner-simulasi"')
            ->set('isianSimulasi.jenis', 'asing')
            ->call('simpanSimulasi')
            ->assertHasErrors('isianSimulasi.jenis')
            ->set('isianSimulasi.jenis', 'waktu_habis')
            ->assertSeeHtml('data-testid="pratinjau-pesan-gagal"')
            ->assertSee(KegagalanAi::saranAdmin('waktu_habis'))
            ->call('simpanSimulasi')
            ->assertSeeHtml('data-testid="banner-simulasi"')
            ->assertSee('Simulasi kegagalan aktif: Waktu tunggu habis (penyedia aktif).')
            ->assertSeeHtml('data-testid="status-simulasi">Aktif');
        $this->assertSame(['jenis' => 'waktu_habis', 'cakupan' => 'aktif'], app(PengaturanAiRepository::class)->ambil()['simulasi_gagal']);
        $this->assertSame('Simulasi kegagalan: mati → Waktu tunggu habis (penyedia aktif)', collect(app(RiwayatPengaturanRepository::class)->semua())->firstWhere('aksi', 'ubah_simulasi_ai')['keterangan']);

        Livewire::test(PengaturanAi::class)
            ->assertSet('isianSimulasi', ['jenis' => 'waktu_habis', 'cakupan' => 'aktif'])
            ->call('matikanSimulasi')
            ->assertDispatched('notify', message: 'Simulasi kegagalan dimatikan — permintaan AI kembali normal.', type: 'success')
            ->assertDontSeeHtml('data-testid="banner-simulasi"');
        $this->assertNull(app(PengaturanAiRepository::class)->ambil()['simulasi_gagal']);
    }
}
