<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Support\Dokumen\VariabelTemplate;
use Illuminate\Http\UploadedFile;
use Tests\Support\Docx;
use Tests\TestCase;

class ApiVariabelTemplateTest extends TestCase
{
    /** @return array<string, mixed> Satu variabel dari jawaban. */
    private function variabel(array $jawaban, string $nama): array
    {
        return collect($jawaban['kelompok'])->flatMap(fn (array $k) => $k['variabel'])->firstWhere('nama', $nama);
    }

    public function test_daftar_variabel_terdaftar(): void
    {
        $this->withSession(['stub_role' => 'admin']);
        $jawaban = $this->getJson(route('pengaturan.template.variabel'))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('tahun', 2026)
            ->assertJsonPath('jumlah', count(VariabelTemplate::DAFTAR))
            ->assertJsonPath('contoh_kegiatan', null)
            ->json();
        $this->assertSame(array_keys(VariabelTemplate::KELOMPOK), array_column($jawaban['kelompok'], 'kunci'));
        $this->assertSame(count(VariabelTemplate::DAFTAR), collect($jawaban['kelompok'])->sum(fn (array $k) => count($k['variabel'])));
        $this->assertSame(['nama' => 'tahun_anggaran', 'penanda' => '${tahun_anggaran}', 'keterangan' => 'Tahun anggaran aktif', 'jenis' => 'teks', 'berdiri_sendiri' => false, 'dianjurkan' => true],
            array_intersect_key($this->variabel($jawaban, 'tahun_anggaran'), array_flip(['nama', 'penanda', 'keterangan', 'jenis', 'berdiri_sendiri', 'dianjurkan'])));
        $this->assertSame([true, 'tabel'], [$this->variabel($jawaban, 'rab')['berdiri_sendiri'], $this->variabel($jawaban, 'rab')['jenis']]);
        $this->assertSame(['jumlah' => 0, 'template' => []], $this->variabel($jawaban, 'nama_kegiatan')['dipakai']);

        app(TemplateDokumenRepository::class)->tambah(2026, 'Master reguler', 'KAK+TOR', UploadedFile::fake()->createWithContent('m.docx', Docx::isi(['${nama_kegiatan}'])), ['nama_kegiatan']);
        $dipakai = $this->getJson(route('pengaturan.template.variabel'))->json();
        $this->assertSame(['jumlah' => 1, 'template' => ['Master reguler']], $this->variabel($dipakai, 'nama_kegiatan')['dipakai']);

        // Contoh nilai untuk kegiatan contoh.
        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $contoh = $this->getJson(route('pengaturan.template.variabel', ['kegiatan' => 1]))->assertOk()->json();
        $this->assertSame(1, $contoh['contoh_kegiatan']['id']);
        $this->assertSame($kegiatan['nama_kegiatan'], $this->variabel($contoh, 'nama_kegiatan')['contoh']);
        $this->assertStringStartsWith('[Tabel rincian RAB', $this->variabel($contoh, 'rab')['contoh'], 'Variabel tabel = ringkasan (NilaiVariabel).');

        $this->getJson(route('pengaturan.template.variabel', ['kegiatan' => 1, 'sumber' => 'BOS']))->assertStatus(422)->assertJsonStructure(['galat' => ['sumber']]);
        $this->getJson(route('pengaturan.template.variabel', ['kegiatan' => 999]))->assertNotFound();
        $this->getJson(route('pengaturan.template.variabel', ['ta' => 2099]))->assertNotFound();

        $this->withSession(['stub_role' => 'leading'])->getJson(route('pengaturan.template.variabel'))->assertForbidden();
    }
}
