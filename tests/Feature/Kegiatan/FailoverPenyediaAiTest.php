<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\PengaturanAiRepository;
use App\Support\Kegiatan\GagalPenyediaAi;
use App\Support\Kegiatan\KesehatanPenyediaAi;
use App\Support\Kegiatan\RantaiPenyediaAi;
use App\Support\Pengaturan\KelolaPenyediaAi;
use Tests\TestCase;

class FailoverPenyediaAiTest extends TestCase
{
    /** @var list<string> Penyedia yang dicoba, berurutan. */
    private array $dicoba = [];

    /** @param  array<string, mixed>  $ubah */
    private function atur(array $ubah): void
    {
        $data = array_diff_key(app(PengaturanAiRepository::class)->ambil(), ['diperbarui_pada' => 1]);
        app(PengaturanAiRepository::class)->simpan(array_replace_recursive($data, $ubah));
    }

    /**
     * Penyusun palsu: penyedia di $gagal melempar jenisnya, lainnya menjawab namanya.
     *
     * @param  array<string, string>  $gagal
     */
    private function jalankan(array $gagal = []): array
    {
        return RantaiPenyediaAi::jalankan(function (string $penyedia) use ($gagal) {
            $this->dicoba[] = $penyedia;

            return isset($gagal[$penyedia]) ? throw new GagalPenyediaAi($gagal[$penyedia], $penyedia) : "jawaban {$penyedia}";
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->atur(['provider_aktif' => 'ollama', 'provider_cadangan' => 'claude']);
    }

    public function test_penyedia_aktif_yang_baru_gagal_dilewati_selama_jeda_lalu_pulih(): void
    {
        $pertama = $this->jalankan(['ollama' => 'tidak_terhubung']);
        $this->assertSame(['claude', ['ollama', 'claude']], [$pertama['penyedia'], $this->dicoba]);
        $this->assertSame('Disusun penyedia cadangan (Claude (Anthropic)) karena penyedia aktif gagal: server ai tidak bisa dihubungi.', RantaiPenyediaAi::catatan($pertama));
        $this->assertSame(['tidak_terhubung', 'Server AI tidak bisa dihubungi'], [KesehatanPenyediaAi::jeda('ollama')['jenis'], KesehatanPenyediaAi::jeda('ollama')['judul']]);
        $this->assertSame(1, collect(KelolaPenyediaAi::data()['penyedia'])->firstWhere('kunci', 'ollama')['kesehatan']['beruntun']);

        // Selama jeda: langsung cadangan, penyedia aktif tidak ditunggu.
        $this->dicoba = [];
        $kedua = $this->jalankan();
        $this->assertSame(['claude', ['claude']], [$kedua['penyedia'], $this->dicoba]);
        $this->assertSame('ollama', $kedua['dilewati']['penyedia']);
        $this->assertSame('Disusun penyedia cadangan (Claude (Anthropic)) karena penyedia aktif sedang bermasalah: server ai tidak bisa dihubungi — dicoba lagi otomatis setelah jeda.', RantaiPenyediaAi::catatan($kedua));

        // Cadangan juga gagal selama jeda: penyedia aktif tetap dicoba terakhir; pesan utama = kegagalan penyedia aktif.
        $this->dicoba = [];
        try {
            $this->jalankan(['claude' => 'kunci_ditolak', 'ollama' => 'waktu_habis']);
            $this->fail('Keduanya gagal.');
        } catch (GagalPenyediaAi $e) {
            $this->assertSame([['claude', 'ollama'], 'waktu_habis', true], [$this->dicoba, $e->jenis, $e->cadanganGagal]);
            $this->assertSame('AI terlalu lama menjawab. Coba lagi, atau persingkat instruksi. Penyedia cadangan juga gagal.', $e->getMessage());
        }
        $this->assertSame(2, KesehatanPenyediaAi::status('ollama')['beruntun']);

        // Keduanya dijeda: urutan baku kembali (aktif dulu); sesudah jeda lewat penyedia aktif pulih.
        $this->dicoba = [];
        $this->assertSame('ollama', $this->jalankan()['penyedia']);
        $this->assertSame(['ollama'], $this->dicoba);
        $this->assertNull(KesehatanPenyediaAi::status('ollama'), 'Jawaban berhasil memulihkan penyedia.');
        $this->assertNotNull(KesehatanPenyediaAi::jeda('claude'));

        KesehatanPenyediaAi::catatGagal('ollama', 'kuota_penyedia');
        $this->travel(KesehatanPenyediaAi::JEDA['kuota_penyedia'] + 1)->seconds();
        $this->dicoba = [];
        $this->assertSame(['ollama', null], [$this->jalankan()['penyedia'], KesehatanPenyediaAi::jeda('ollama')]);
    }

    public function test_jeda_dipulihkan_perubahan_pengaturan_dan_tidak_untuk_simulasi_atau_jawaban_rusak(): void
    {
        $this->jalankan(['ollama' => 'model_tidak_ada']);
        $this->assertNotNull(KesehatanPenyediaAi::jeda('ollama'));
        $this->atur(['penyedia' => ['ollama' => ['model' => 'qwen2.5:3b']]]);
        $this->assertSame([null, null], [KesehatanPenyediaAi::jeda('ollama'), KesehatanPenyediaAi::status('ollama')], 'Admin mengubah pengaturan: catatan lama tidak berlaku.');

        $this->jalankan(['ollama' => 'jawaban_rusak']);
        $this->assertSame(['jawaban_rusak', null], [KesehatanPenyediaAi::status('ollama')['jenis'], KesehatanPenyediaAi::jeda('ollama')], 'Jawaban rusak tidak menjeda penyedia.');

        KesehatanPenyediaAi::catatBerhasil('ollama');
        $this->atur(['simulasi_gagal' => ['jenis' => 'tidak_terhubung', 'cakupan' => 'aktif']]);
        $this->assertSame('claude', $this->jalankan()['penyedia']);
        $this->assertNull(KesehatanPenyediaAi::status('ollama'), 'Kegagalan simulasi tidak dicatat sebagai kegagalan nyata.');

        // Tanpa cadangan, penyedia aktif yang dijeda tetap dicoba.
        $this->atur(['simulasi_gagal' => null]);
        $this->atur(['provider_cadangan' => null]);
        KesehatanPenyediaAi::catatGagal('ollama', 'tidak_terhubung');
        $this->dicoba = [];
        $hasil = $this->jalankan();
        $this->assertSame([['ollama'], 'ollama', null], [$this->dicoba, $hasil['penyedia'], $hasil['dilewati']]);
    }
}
