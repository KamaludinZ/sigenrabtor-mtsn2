<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PromptAiRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\RiwayatSaranAiRepository;
use App\Livewire\Kegiatan\KalimatAi;
use App\Livewire\Kegiatan\MatrikJadwal;
use App\Livewire\Pengaturan\PengaturanAi;
use App\Support\Pengaturan\PromptAi;
use Livewire\Livewire;
use Tests\TestCase;

class PromptAiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    private function catatanTerbaru(): string
    {
        return collect(app(RiwayatPengaturanRepository::class)->semua())->firstWhere('aksi', 'ubah_prompt_ai')['keterangan'];
    }

    public function test_aturan_template_dan_susunan_prompt(): void
    {
        $this->assertSame([], PromptAi::galat(PromptAi::BAWAAN['tujuan']));
        $this->assertStringStartsWith('Instruksi 20–1000 karakter', PromptAi::galat('terlalu pendek')['instruksi']);
        $this->assertStringContainsString('Penanda {nama_kegiatn} tidak dikenal — maksud Anda {nama_kegiatan}?', PromptAi::galat('Tulis tujuan untuk {nama_kegiatn} secara singkat.')['instruksi']);
        $this->assertSame('Instruksi tidak boleh memuat NIP.', PromptAi::galat('Sebutkan kepala madrasah NIP 197001011995031001 di akhir.')['instruksi']);
        $this->assertArrayHasKey('gaya_bahasa', PromptAi::galat(PromptAi::BAWAAN['tujuan'], str_repeat('a', 301)));

        $prompt = PromptAi::susun('tujuan', ['nama_kegiatan' => 'Kegiatan Rapat Kerja', 'konteks' => 'Peserta: guru.'], ['instruksi' => 'Tulis tujuan {nama_kegiatan} tahun {tahun} dengan kata kerja.']);
        $this->assertStringStartsWith('Gaya bahasa: '.PromptAi::GAYA_BAHASA."\nAturan: ".PromptAi::BATAS_AMAN."\nTugas: Tulis tujuan Rapat Kerja tahun 2026 dengan kata kerja.", $prompt);
        $this->assertStringContainsString("- Catatan konteks: peserta\nBahan kegiatan:", $prompt);
        $this->assertSame(['aktif' => true, 'bawaan' => true], array_intersect_key(PromptAi::untuk('sasaran'), ['aktif' => 1, 'bawaan' => 1]));
    }

    public function test_ubah_gaya_bahasa_dan_template_bagian(): void
    {
        $halaman = Livewire::withQueryParams(['tab' => 'prompt'])->test(PengaturanAi::class)
            ->call('ubahGaya')
            ->assertSet('isianGaya', PromptAi::GAYA_BAHASA)
            ->set('isianGaya', 'Bahasa Indonesia baku,   ringkas, dan santun.')
            ->call('simpanGaya')
            ->assertDispatched('notify', message: 'Gaya bahasa baku tersimpan.', type: 'success')
            ->assertSee('Bahasa Indonesia baku, ringkas, dan santun.');
        $this->assertSame(['Bahasa Indonesia baku, ringkas, dan santun.', 'Gaya bahasa baku diubah'], [PromptAi::gayaBahasa(), $this->catatanTerbaru()]);

        $halaman->call('ubahPrompt', 'tujuan')
            ->assertSet('isianPrompt.instruksi', PromptAi::BAWAAN['tujuan'])
            ->set('isianPrompt.instruksi', 'Tulis tujuan untuk {nama_kegiatn} secara singkat dan jelas.')
            ->call('simpanPromptBagian')
            ->assertHasErrors('isianPrompt.instruksi')
            ->set('isianPrompt.instruksi', 'Tulis satu frasa tujuan {nama_kegiatan} tahun {tahun} yang diawali kata kerja.')
            ->assertSeeHtml('data-testid="pratinjau-prompt"')
            ->assertSee('Tugas: Tulis satu frasa tujuan ')
            ->assertSee(' tahun 2026 yang diawali kata kerja.')
            ->assertDontSee('{nama_kegiatan} tahun')
            ->call('simpanPromptBagian')
            ->assertHasNoErrors()
            ->assertSet('promptTerbuka', null)
            ->assertSeeHtml('data-testid="status-prompt-tujuan">Diubah');
        $this->assertSame('Tujuan — Instruksi: bawaan → diubah', $this->catatanTerbaru());

        // Riwayat saran AI mencatat template yang berlaku.
        Livewire::test(KalimatAi::class, ['kegiatan' => 1])->call('mintaSaran', 'tujuan');
        $this->assertStringContainsString("Tugas: Tulis satu frasa tujuan Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran tahun 2026 yang diawali kata kerja.\nGaya bahasa: Bahasa Indonesia baku, ringkas, dan santun.", app(RiwayatSaranAiRepository::class)->semua()[0]['prompt']);

        // Kembalikan bawaan → tidak lagi tersimpan sebagai template khusus.
        $halaman->call('ubahPrompt', 'tujuan')->call('pakaiPromptBawaan')->call('simpanPromptBagian');
        $this->assertArrayNotHasKey('tujuan', app(PromptAiRepository::class)->ambil()['bagian']);
        $this->assertTrue(PromptAi::untuk('tujuan')['bawaan']);
    }

    public function test_bagian_nonaktif_mematikan_tombol_ai_dan_bentrok(): void
    {
        Livewire::withQueryParams(['tab' => 'prompt'])->test(PengaturanAi::class)
            ->call('ubahPrompt', 'sasaran')
            ->set('isianPrompt.aktif', false)
            ->call('simpanPromptBagian')
            ->assertDispatched('notify', message: 'Prompt sasaran tersimpan — tombol “Buat dengan AI” bagian ini dinonaktifkan.', type: 'success')
            ->assertSeeHtml('data-testid="nonaktif-sasaran"')
            ->call('ubahPrompt', 'jadwal')
            ->set('isianPrompt.aktif', false)
            ->set('isianPrompt.gaya_khusus', true)
            ->call('simpanPromptBagian')
            ->assertHasErrors('isianPrompt.gaya_bahasa')
            ->set('isianPrompt.gaya_khusus', false)
            ->call('simpanPromptBagian')
            ->assertHasNoErrors();
        $this->assertSame('Jadwal kegiatan — Status: aktif → nonaktif', $this->catatanTerbaru());

        Livewire::test(KalimatAi::class, ['kegiatan' => 1])
            ->assertSeeHtml('data-tombol-ai="sasaran" data-keadaan="nonaktif"')
            ->assertSee('Bagian ini dinonaktifkan admin di Pengaturan AI.')
            ->assertSeeHtml('data-tombol-ai="tujuan" data-keadaan="siap"')
            ->call('mintaSaran', 'sasaran')
            ->assertSet('saran', []);
        Livewire::test(MatrikJadwal::class, ['kegiatan' => 1])->set('usulJenis', 'umum')->call('buatUsulJadwal')
            ->assertSet('usulGagal', 'Usulan jadwal AI dinonaktifkan admin di Pengaturan AI.');

        // Bentrok: diubah di tab lain sejak penyunting dibuka → isian tetap, tidak tersimpan.
        $halaman = Livewire::test(PengaturanAi::class)->call('ubahPrompt', 'tujuan')->set('isianPrompt.instruksi', 'Tulis tujuan kegiatan secara singkat, diawali kata kerja.');
        app(PromptAiRepository::class)->simpan(['gaya_bahasa' => 'Gaya lain dari tab lain.'] + app(PromptAiRepository::class)->ambil());
        $halaman->call('simpanPromptBagian')->assertDispatched('notify', type: 'error')->assertSet('promptTerbuka', 'tujuan');
        $this->assertTrue(PromptAi::untuk('tujuan')['bawaan']);
    }
}
