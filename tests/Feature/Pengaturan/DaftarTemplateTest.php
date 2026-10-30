<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PilihanTemplateRepository;
use App\Contracts\TemplateCoverRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Support\Akun\SesiAkun;
use App\Support\Dokumen\DaftarTemplate;
use App\Support\Dokumen\PilihanTemplate;
use App\Support\Dokumen\TeksCover;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\Docx;
use Tests\TestCase;

class DaftarTemplateTest extends TestCase
{
    /** @return array{kak: string, rab: string, cover: string, cover2: string} */
    private function siapkan(): array
    {
        $docx = fn (string $n) => UploadedFile::fake()->createWithContent($n, Docx::isi(['${nama_kegiatan}']));
        $png = fn (string $n) => new UploadedFile(base_path('tests/fixtures/cover-contoh.png'), $n, 'image/png', null, true);
        $dokumen = app(TemplateDokumenRepository::class);
        $cover = app(TemplateCoverRepository::class);
        $id = [
            'kak' => $dokumen->tambah(2026, 'Master Workshop', 'KAK+TOR', $docx('m.docx'), ['nama_kegiatan']),
            'rab' => $dokumen->tambah(2026, 'Master RAB', 'RAB', $docx('r.docx'), ['nama_kegiatan']),
            'cover' => $cover->tambah(2026, 'Cover Default', $png('a.png'), TeksCover::bawaan()),
            'cover2' => $cover->tambah(2026, 'Cover Leading', $png('b.png'), TeksCover::bawaan()),
        ];
        app(PilihanTemplateRepository::class)->simpan(2026, [
            'jenis' => ['workshop' => ['kak' => $id['kak'], 'cover' => null]],
            'leading' => [['leading' => '1.6', 'jenis' => 'workshop', 'kak' => null, 'cover' => $id['cover2']]],
        ]);

        return $id;
    }

    public function test_daftar_template_beserta_pemakaian_dan_template_berlaku(): void
    {
        $id = $this->siapkan();
        $data = DaftarTemplate::untuk(2026, ['jenis' => 'workshop', 'leading' => '1.6']);

        $this->assertSame([$id['kak']], array_column($data['kak'], 'id'), 'Master RAB bukan pilihan KAK/TOR.');
        $this->assertTrue($data['kak'][0]['ada_berkas']);
        $this->assertSame([['asal' => 'jenis', 'jenis' => 'workshop', 'label_jenis' => $data['kak'][0]['dipakai'][0]['label_jenis'], 'leading' => null]], $data['kak'][0]['dipakai']);
        $cover = array_column($data['cover'], null, 'id');
        $this->assertSame([], $cover[$id['cover']]['dipakai']);
        $this->assertSame('leading', $cover[$id['cover2']]['dipakai'][0]['asal']);
        $this->assertSame(['kak' => 1, 'cover' => 2, 'kak_dipakai' => 1, 'cover_dipakai' => 1, 'tanpa_berkas' => 0], $data['ringkasan']);
        $this->assertSame(['leading', $id['kak'], $id['cover2'], false], [$data['berlaku']['asal'], $data['berlaku']['kak']['id'], $data['berlaku']['cover']['id'], $data['berlaku']['susunan_baku']]);
        $this->assertFalse($data['terkunci']);

        // Leading lain memakai pilihan per jenis; jenis tanpa pilihan = bawaan.
        $this->assertSame('jenis', DaftarTemplate::untuk(2026, ['jenis' => 'workshop', 'leading' => '1.1'])['berlaku']['asal']);
        $bawaan = DaftarTemplate::untuk(2026, ['jenis' => 'lomba'])['berlaku'];
        $this->assertSame(['bawaan', null, null, true], [$bawaan['asal'], $bawaan['kak'], $bawaan['cover'], $bawaan['susunan_baku']]);
        $this->assertNull(DaftarTemplate::untuk(2026)['berlaku']);

        // Berkas master hilang dari disk ditandai.
        Storage::disk('local')->delete(app(TemplateDokumenRepository::class)->temukan($id['kak'])['file_path']);
        $this->assertFalse(DaftarTemplate::untuk(2026)['kak'][0]['ada_berkas']);

        $this->expectException(ValidationException::class);
        DaftarTemplate::untuk(2026, ['jenis' => 'rapat']);
    }

    public function test_endpoint_daftar_template_khusus_admin(): void
    {
        $id = $this->siapkan();
        $this->getJson(route('pengaturan.format.template'))->assertForbidden();

        session(['stub_role' => 'admin']);
        $this->getJson(route('pengaturan.format.template', ['jenis' => 'workshop']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('tahun', 2026)
            ->assertJsonPath('kak.0.id', $id['kak'])
            ->assertJsonPath('kak.0.tautan_unduh', route('pengaturan.template.master.unduh', $id['kak']))
            ->assertJsonPath('berlaku.asal', 'jenis')
            ->assertJsonPath('pilihan.leading.0.leading', '1.6');
        $this->getJson(route('pengaturan.format.template', ['ta' => 2025]))->assertOk()->assertJsonPath('kak', [])->assertJsonPath('terkunci', true);
        $this->getJson(route('pengaturan.format.template', ['ta' => 1999]))->assertNotFound();
        $this->getJson(route('pengaturan.format.template', ['jenis' => 'rapat', 'leading' => '9.9']))
            ->assertStatus(422)->assertJsonStructure(['pesan', 'galat' => ['jenis', 'leading']]);
    }

    public function test_endpoint_template_kegiatan(): void
    {
        $id = $this->siapkan();
        $repo = app(KegiatanRepository::class);
        $k = $repo->temukan(1);
        $this->assertSame('workshop', PilihanTemplate::untukKegiatan($k)['jenis'], 'Contoh 1 = workshop.');

        $this->getJson(route('kegiatan.template_dokumen', 1))
            ->assertOk()
            ->assertJsonPath('kegiatan_id', 1)
            ->assertJsonPath('asal', $k['leading'] === '1.6' ? 'leading' : 'jenis')
            ->assertJsonPath('kak.id', $id['kak'])
            ->assertJsonPath('kak.ada_berkas', true);
        $this->getJson(route('kegiatan.template_dokumen', 999))->assertNotFound();

        // Akun leading lain tidak boleh membaca kegiatan di luar cakupannya.
        SesiAkun::masuk(['id' => 3, 'role' => 'leading']);
        $this->getJson(route('kegiatan.template_dokumen', 1))->assertForbidden();
    }
}
