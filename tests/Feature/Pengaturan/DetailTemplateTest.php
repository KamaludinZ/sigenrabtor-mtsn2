<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PilihanTemplateRepository;
use App\Contracts\TemplateCoverRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Support\Dokumen\DetailTemplate;
use App\Support\Dokumen\TeksCover;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\Docx;
use Tests\TestCase;

class DetailTemplateTest extends TestCase
{
    /** @return array{master: string, cover: string} */
    private function siapkan(): array
    {
        $docx = UploadedFile::fake()->createWithContent('m.docx', Docx::isi(['KERANGKA ACUAN ${nama_kegiatan_cover}', 'Tempat: ${tempat} — ${variabel_asing}'], header: ['${kop}']));
        $png = new UploadedFile(base_path('tests/fixtures/cover-contoh.png'), 'c.png', 'image/png', null, true);
        $id = [
            'master' => app(TemplateDokumenRepository::class)->tambah(2026, 'Master Workshop', 'KAK+TOR', $docx, ['nama_kegiatan_cover', 'tempat', 'kop']),
            'cover' => app(TemplateCoverRepository::class)->tambah(2026, 'Cover Default', $png, TeksCover::bawaan()),
        ];
        app(PilihanTemplateRepository::class)->simpan(2026, ['jenis' => ['workshop' => ['kak' => $id['master'], 'cover' => $id['cover']]], 'leading' => []]);

        return $id;
    }

    public function test_detail_master_dengan_variabel_paragraf_dan_pratinjau(): void
    {
        $id = $this->siapkan();
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $d = DetailTemplate::untuk($id['master'], $kegiatan);

        $this->assertSame(['master', 'KAK+TOR', true, true], [$d['jenis_template'], $d['jenis'], $d['default'], $d['bisa_dipilih']]);
        $this->assertTrue($d['berkas']['ada']);
        $this->assertGreaterThan(0, $d['berkas']['ukuran']);
        $this->assertSame(['nama_kegiatan_cover', 'tempat', 'variabel_asing', 'kop'], array_column($d['variabel'], 'nama'));
        $this->assertSame(1, $d['tidak_terdaftar']);
        $this->assertSame('workshop', $d['dipakai'][0]['jenis']);
        $this->assertSame(['header', 'isi', 'isi'], array_column($d['paragraf'], 'bagian'));
        $this->assertSame(1, $d['pratinjau']['kegiatan_id']);
        $segmen = collect($d['pratinjau']['paragraf'])->pluck('segmen')->flatten(1);
        $this->assertSame($kegiatan['tempat'] ?? null, $segmen->firstWhere('nama', 'tempat')['nilai'] ?? null);
        $this->assertNull(DetailTemplate::untuk($id['master'])['pratinjau']);

        // Berkas hilang: variabel tersimpan tetap, paragraf null + galat.
        Storage::disk('local')->delete(app(TemplateDokumenRepository::class)->temukan($id['master'])['file_path']);
        $hilang = DetailTemplate::untuk($id['master'], $kegiatan);
        $this->assertSame([false, null, null], [$hilang['berkas']['ada'], $hilang['paragraf'], $hilang['pratinjau']]);
        $this->assertStringContainsString('tidak ditemukan', $hilang['galat_berkas']);
        $this->assertSame(['nama_kegiatan_cover', 'tempat', 'kop'], array_column($hilang['variabel'], 'nama'));
    }

    public function test_detail_cover_dan_pratinjau_nilai(): void
    {
        $id = $this->siapkan();
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $d = DetailTemplate::untuk($id['cover'], $kegiatan, 'Komite');

        $this->assertSame(['cover', true, null, null], [$d['jenis_template'], $d['default'], $d['leading'], $d['sumber_dana']]);
        $this->assertTrue($d['gambar']['ada']);
        $this->assertSame(array_column(TeksCover::bawaan(), 'field'), array_column($d['kotak'], 'field'));
        $this->assertSame(TeksCover::FIELD['kop'], $d['kotak'][0]['label']);
        $this->assertSame('workshop', $d['dipakai'][0]['jenis']);
        $nilai = array_column($d['pratinjau']['kotak'], 'nilai', 'field');
        $this->assertSame(TeksCover::nilai($kegiatan, 'Komite')['tahun'], $nilai['tahun']);

        $this->assertNull(DetailTemplate::untuk((string) Str::uuid()));
        $this->expectException(ValidationException::class);
        DetailTemplate::untuk($id['cover'], $kegiatan, 'BOS');
    }

    public function test_endpoint_detail_template_khusus_admin(): void
    {
        $id = $this->siapkan();
        $this->getJson(route('pengaturan.format.template.detail', $id['master']))->assertForbidden();

        session(['stub_role' => 'admin']);
        $this->getJson(route('pengaturan.format.template.detail', $id['master']))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('jenis_template', 'master')
            ->assertJsonPath('tidak_terdaftar', 1)
            ->assertJsonPath('pratinjau', null);
        $this->getJson(route('pengaturan.format.template.detail', [$id['cover'], 'kegiatan' => 1]))
            ->assertOk()->assertJsonPath('jenis_template', 'cover')->assertJsonPath('pratinjau.kegiatan_id', 1);
        $this->getJson(route('pengaturan.format.template.detail', [$id['cover'], 'kegiatan' => 999]))->assertNotFound();
        $this->getJson(route('pengaturan.format.template.detail', [$id['cover'], 'kegiatan' => 1, 'sumber' => 'BOS']))->assertStatus(422)->assertJsonStructure(['galat' => ['sumber']]);
        $this->getJson(route('pengaturan.format.template.detail', (string) Str::uuid()))->assertNotFound()->assertJsonPath('pesan', 'Template tidak ditemukan.');
        $this->getJson('/pengaturan/format/template/bukan-uuid')->assertNotFound();
    }
}
