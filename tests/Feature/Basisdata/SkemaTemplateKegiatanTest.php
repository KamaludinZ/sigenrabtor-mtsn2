<?php

namespace Tests\Feature\Basisdata;

use App\Contracts\KegiatanRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentTemplateDokumenRepository;
use App\Support\Dokumen\PilihanTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\Docx;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SkemaTemplateKegiatanTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilihan_template_kegiatan_tersimpan_dan_kembali_otomatis_saat_template_dihapus(): void
    {
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(TemplateDokumenRepository::class, EloquentTemplateDokumenRepository::class);
        $master = app(TemplateDokumenRepository::class)->tambah(2026, 'TPM reguler', 'KAK+TOR', UploadedFile::fake()->createWithContent('tpm.docx', Docx::isi(['${nama_kegiatan}'])), ['nama_kegiatan'], ['leading' => '1.6']);
        $kegiatan = app(KegiatanRepository::class);
        $id = $kegiatan->simpan(['nama_kegiatan' => 'Rapat Evaluasi Mutu', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        $this->assertArrayNotHasKey('template_kak', $kegiatan->temukan($id));

        $kegiatan->simpan(['template_kak' => $master], $id);
        $this->assertSame($master, $kegiatan->temukan($id)['template_kak']);
        $this->assertSame('kegiatan', PilihanTemplate::untukKegiatan($kegiatan->temukan($id))['asal']);

        app(TemplateDokumenRepository::class)->hapus($master);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        $this->assertNull(DB::table('activities')->where('id', $id)->value('kak_template_id'));
        $this->assertArrayNotHasKey('template_kak', $kegiatan->temukan($id));
    }
}
