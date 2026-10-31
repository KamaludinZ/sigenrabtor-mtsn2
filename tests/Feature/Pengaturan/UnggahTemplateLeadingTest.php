<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Repositories\EloquentTemplateDokumenRepository;
use App\Support\Dokumen\JenisDokumen;
use App\Support\Pengaturan\UnggahMasterTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Docx;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class UnggahTemplateLeadingTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function unggah(string $nama, array $atribut = [], string $jenis = 'KAK+TOR'): array
    {
        return UnggahMasterTemplate::simpan(2026, UploadedFile::fake()->createWithContent("{$nama}.docx", Docx::isi(['Kegiatan ${nama_kegiatan}'])), $nama, $jenis, self::ADMIN, $atribut);
    }

    /** @return array<string, bool> nama → default */
    private function defaultPerNama(): array
    {
        return array_column(app(TemplateDokumenRepository::class)->semua(2026), 'is_default', 'nama');
    }

    #[DataProvider('penyimpanan')]
    public function test_default_per_pemilik_dan_jenis(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(TemplateDokumenRepository::class, EloquentTemplateDokumenRepository::class);
        }

        $umum = $this->unggah('Umum reguler');
        $tpm = $this->unggah('TPM reguler', ['leading' => '1.6', 'catatan' => "  Versi 1\n   uji  "]);
        $this->assertSame([null, true, '1.6', true, 'Versi 1 uji', true], [$umum['leading'], $umum['is_default'], $tpm['leading'], $tpm['is_default'], $tpm['catatan'], $tpm['aktif']], 'Template pertama tiap pemilik × jenis menjadi default.');

        $this->unggah('TPM narasumber', ['leading' => '1.6']);
        $this->assertEquals(['Umum reguler' => true, 'TPM reguler' => true, 'TPM narasumber' => false], array_intersect_key($this->defaultPerNama(), array_flip(['Umum reguler', 'TPM reguler', 'TPM narasumber'])));
        $baru = $this->unggah('TPM versi 2', ['leading' => '1.6', 'default' => true]);
        $this->assertTrue($baru['is_default']);
        $this->assertEquals(['Umum reguler' => true, 'TPM reguler' => false, 'TPM versi 2' => true], array_intersect_key($this->defaultPerNama(), array_flip(['Umum reguler', 'TPM reguler', 'TPM versi 2'])));
        $this->assertStringContainsString('untuk leading 1.6 diunggah — 1 variabel, menjadi default leading itu untuk jenis ini.', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);

        // Susunan baku dokumen memakai default umum, bukan default khusus leading.
        $this->assertSame($umum['id'], JenisDokumen::template('KAK+TOR', 2026)['kak']['id']);

        // Menghapus default leading memindahkan default ke template leading itu juga.
        UnggahMasterTemplate::hapus($baru['id'], self::ADMIN);
        $sisa = $this->defaultPerNama();
        $this->assertTrue($sisa['Umum reguler']);
        $this->assertSame(1, count(array_filter(array_intersect_key($sisa, array_flip(['TPM reguler', 'TPM narasumber'])))));

        if ($database) {
            $this->assertSame('Versi 1 uji', DB::table('document_templates')->where('id', $tpm['id'])->value('catatan'));
            try {
                DB::transaction(fn () => DB::table('document_templates')->where('id', $tpm['id'])->update(['catatan' => ' spasi ']));
                $this->fail('Catatan harus rapi.');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_aturan_pemilik_dan_catatan(): void
    {
        $galat = function (array $atribut): array {
            try {
                $this->unggah('Uji', $atribut);
            } catch (ValidationException $e) {
                return array_map(fn (array $p) => $p[0], $e->errors());
            }
            $this->fail('Seharusnya ditolak.');
        };

        $this->assertSame(['leading' => 'Pilih pemilik template: umum atau leading yang aktif.'], $galat(['leading' => '9.9']));
        $this->assertSame(['leading' => 'Pilih pemilik template: umum atau leading yang aktif.'], $galat(['leading' => '1.9']), 'Leading nonaktif tidak bisa menjadi pemilik baru.');
        $this->assertSame(['catatan' => 'Catatan versi maksimal '.UnggahMasterTemplate::MAKS_CATATAN.' karakter.'], $galat(['catatan' => str_repeat('a', UnggahMasterTemplate::MAKS_CATATAN + 1)]));
        $this->assertSame(['leading' => null, 'catatan' => null, 'default' => false], UnggahMasterTemplate::rapikanAtribut(['leading' => '', 'catatan' => '   ']));
    }
}
