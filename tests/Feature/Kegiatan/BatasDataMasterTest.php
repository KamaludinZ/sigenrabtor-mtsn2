<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PengaturanAiRepository;
use App\Contracts\PenyusunSaranKalimat;
use App\Livewire\Kegiatan\KalimatAi;
use App\Support\Kegiatan\BatasDataMaster;
use App\Support\Kegiatan\PermintaanSaranKalimat;
use App\Support\Kegiatan\SaranKalimatAi;
use App\Support\Rab\RincianRab;
use App\Support\Stub\StubData;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class BatasDataMasterTest extends TestCase
{
    /** @param  array<string, mixed>  $isi */
    private function kegiatan(array $isi = []): array
    {
        $id = app(KegiatanRepository::class)->simpan($isi + [
            'nama_kegiatan' => 'Supervisi Akademik', 'nama_kegiatan_cover' => 'SUPERVISI AKADEMIK', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026,
            'kode_rktm' => '6.19.1', 'kode_sk' => 'SK.19', 'kode_iksk' => 'IKSK 19.2', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 2_000_000]],
        ]);

        return app(KegiatanRepository::class)->temukan($id);
    }

    /** Penyusun palsu dengan versi tetap. */
    private function penyusun(string ...$teks): void
    {
        $this->app->instance(PenyusunSaranKalimat::class, new class($teks) implements PenyusunSaranKalimat
        {
            /** @param  list<string>  $teks */
            public function __construct(private array $teks) {}

            public function sarankan(PermintaanSaranKalimat $permintaan, string $penyedia = 'tiruan'): array
            {
                return array_map(fn (string $t, int $i) => ['judul' => 'Versi '.($i + 1), 'ciri' => 'Formal', 'teks' => $t], $this->teks, array_keys($this->teks));
            }
        });
    }

    public function test_isian_ai_hanya_kalimat_kak(): void
    {
        $this->assertSame([], BatasDataMaster::galatIsian(['tujuan' => 'a', 'cover_manual' => true, 'nama_kegiatan_cover' => 'A', 'uraian_pelaksanaan' => 'b']));
        $this->assertSame([
            'kode_sk' => 'Kode SK tidak bisa diubah lewat Kalimat KAK atau saran AI — ubah di halaman Kode & Klasifikasi.',
            'sumber_dana' => 'Sumber dana & pagu tidak bisa diubah lewat Kalimat KAK atau saran AI — ubah di Data Kegiatan › Sumber dana.',
            'nama_kegiatan' => 'Isian nama_kegiatan bukan kalimat KAK — di sini hanya nama cover, latar belakang, tujuan, sasaran, dan pelaksanaan yang bisa diubah.',
        ], BatasDataMaster::galatIsian(['tujuan' => 'a', 'kode_sk' => 'SK.1', 'sumber_dana' => [], 'nama_kegiatan' => 'X']));

        $this->expectException(ValidationException::class);
        BatasDataMaster::periksaIsian(['leading' => '1.2']);
    }

    public function test_kalimat_tidak_boleh_menyelisihi_kode_dan_angka_anggaran(): void
    {
        $k = $this->kegiatan();
        RincianRab::tambah($k['id'], 'DIPA', ['uraian' => 'Konsumsi', 'kategori' => 'bahan', 'volume' => '20', 'satuan' => 'kotak', 'harga_satuan' => '25000']);

        foreach ([
            'mendukung SK.19, SK 19, IKSK 19.2, dan IKSK 19 melalui program 6.19.1.',
            'dibiayai Rp2.000.000,- dari pagu dengan RAB Rp500.000,00 atau Rp 0,5 juta dan Rp2 juta.',
            'menyelesaikan 3 tahap dalam 2 hari untuk 30 guru kelas 7.',
            'anggaran Rp10.000.000 tidak dibaca sebagai kode 10.000.000.',
        ] as $i => $aman) {
            $this->assertSame($i === 3 ? ['Kalimat menyebut Rp10.000.000 yang tidak sama dengan pagu, total RAB, atau termin kegiatan ini — angka anggaran diubah di Data Kegiatan atau halaman RAB.'] : [], BatasDataMaster::galatTeks($aman, $k), $aman);
        }

        $this->assertSame([
            'Kalimat memuat NIP — NIP hanya tercetak di lembar pengesahan.',
            'Kalimat menyebut SK.20, IKSK 19.3, kode RKTM 6.19.2 yang bukan kode kegiatan ini — kode dipilih di halaman Kode & Klasifikasi, bukan diubah lewat kalimat.',
            'Kalimat menyebut Rp5.000.000, Rp1.500.000 yang tidak sama dengan pagu, total RAB, atau termin kegiatan ini — angka anggaran diubah di Data Kegiatan atau halaman RAB.',
        ], BatasDataMaster::galatTeks('Mendukung SK.20 dan IKSK 19.3 (6.19.2) senilai Rp5.000.000,00 atau Rp1,5 juta; NIP 198001102006041001.', $k));

        $tanpaKode = $this->kegiatan(['kode_rktm' => null, 'kode_sk' => null, 'kode_iksk' => null]);
        $this->assertCount(1, BatasDataMaster::galatTeks('mendukung SK.19.', $tanpaKode), 'Kode yang belum dipilih tidak bisa ditetapkan lewat kalimat.');

        try {
            BatasDataMaster::pastikanTeks($k, ['tujuan' => 'mendukung SK.20.', 'sasaran' => 'guru yang terampil.'], 'form.');
            $this->fail('Kalimat yang menyelisihi kode harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(['form.tujuan'], array_keys($e->errors()));
        }
    }

    public function test_kalimat_kak_menolak_simpan_dan_pakai_saran_yang_menyelisihi_data_master(): void
    {
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $tujuanLama = $kegiatan['tujuan'];
        $this->penyusun('mendukung capaian IKSK 99.1 melalui kegiatan ini.', 'meningkatkan mutu pembelajaran madrasah.');

        $halaman = Livewire::test(KalimatAi::class, ['kegiatan' => 1])
            ->set('form.tujuan', 'mendukung capaian SK.99 madrasah.')
            ->call('simpan')
            ->assertHasErrors(['form.tujuan']);
        $this->assertSame($tujuanLama, app(KegiatanRepository::class)->temukan(1)['tujuan'], 'Tidak tersimpan.');

        $halaman->set('form.tujuan', $tujuanLama)
            ->call('mintaSaran', 'tujuan')
            ->assertSet('saranTerbuka', 'tujuan')
            ->assertSeeHtml('data-testid="galat-batas-aman"')
            ->assertSee('IKSK 99.1 yang bukan kode kegiatan ini')
            ->call('pakaiSaran')
            ->assertHasErrors(['suntingSaran'])
            ->assertSet('form.tujuan', $tujuanLama)
            ->assertSet('saranTerbuka', 'tujuan')
            ->set('suntingSaran', 'mendukung capaian indikator kinerja melalui kegiatan ini.')
            ->call('pakaiSaran')
            ->assertHasNoErrors()
            ->assertSet('form.tujuan', 'mendukung capaian indikator kinerja melalui kegiatan ini.')
            ->call('mintaSaran', 'tujuan')
            ->call('pilihVersi', 1)
            ->assertDontSeeHtml('data-testid="galat-batas-aman"')
            ->set('form.kode_sk', 'SK.1')
            ->assertForbidden();
    }

    public function test_api_saran_dan_keputusan_menolak_teks_yang_menyelisihi_data_master(): void
    {
        $this->penyusun('mendukung capaian IKSK 99.1 melalui kegiatan ini.', 'meningkatkan mutu pembelajaran madrasah.');

        $minta = $this->postJson(route('kegiatan.saran_ai.minta', ['kegiatan' => 1, 'bagian' => 'tujuan']))->assertCreated()
            ->assertJsonPath('bertentangan.1', [])
            ->json();
        $this->assertStringContainsString('IKSK 99.1', $minta['bertentangan'][0][0]);

        $putuskan = fn (array $isi) => $this->putJson(route('saran_ai.putuskan', ['permintaan' => $minta['permintaan']]), $isi);
        $putuskan(['versi' => 1])->assertUnprocessable()->assertJsonPath('galat.versi.0', $minta['bertentangan'][0][0]);
        $putuskan(['versi' => 2, 'teks_dipakai' => 'meningkatkan mutu dengan anggaran Rp99.000.000.'])->assertUnprocessable()
            ->assertJsonPath('galat.teks_dipakai.0', 'Kalimat menyebut Rp99.000.000 yang tidak sama dengan pagu, total RAB, atau termin kegiatan ini — angka anggaran diubah di Data Kegiatan atau halaman RAB.');
        $putuskan(['versi' => 1, 'teks_dipakai' => 'mendukung capaian indikator kinerja madrasah.'])->assertOk()->assertJsonPath('permintaan.versi.0.status', 'diedit');
    }

    public function test_jawaban_llm_tidak_bisa_mengubah_data_master(): void
    {
        $k = $this->kegiatan();
        app(PengaturanAiRepository::class)->simpan(['provider_aktif' => 'ollama', 'penyedia' => ['ollama' => ['base_url' => 'http://192.168.1.10:11434', 'model' => 'qwen2.5:7b', 'batas_waktu' => 30]]] + array_diff_key(app(PengaturanAiRepository::class)->ambil(), ['diperbarui_pada' => 1, 'provider_aktif' => 1, 'penyedia' => 1]));
        Http::fake(['192.168.1.10:11434/*' => Http::response(['message' => ['content' => json_encode([
            'versi' => [['judul' => 'Versi 1', 'ciri' => 'Formal', 'teks' => 'meningkatkan mutu supervisi.', 'kode_sk' => 'SK.99', 'pagu' => 99_000_000]],
            'kode_sk' => 'SK.99', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 99_000_000]], 'status' => 'final',
        ])]])]);

        $hasil = SaranKalimatAi::minta($k, 'tujuan', StubData::currentUser());

        $this->assertSame([['judul' => 'Versi 1', 'ciri' => 'Formal', 'teks' => 'meningkatkan mutu supervisi.']], $hasil['versi'], 'Hanya teks saran yang dibaca dari jawaban model.');
        $sesudah = app(KegiatanRepository::class)->temukan($k['id']);
        $this->assertSame(['SK.19', [['sumber' => 'DIPA', 'pagu' => 2_000_000]], 'draf'], [$sesudah['kode_sk'], $sesudah['sumber_dana'], $sesudah['status']]);
    }
}
