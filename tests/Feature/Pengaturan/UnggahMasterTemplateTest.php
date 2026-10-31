<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\TemplateDokumenRepository;
use App\Repositories\EloquentTemplateDokumenRepository;
use App\Support\Pengaturan\UnggahMasterTemplate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Docx;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class UnggahMasterTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function penyimpananDipakai(bool $database): void
    {
        if ($database) {
            RujukanDb::siapkan();
            $this->app->singleton(TemplateDokumenRepository::class, EloquentTemplateDokumenRepository::class);
        }
    }

    private function docx(string $nama, array $paragraf): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, Docx::isi($paragraf));
    }

    /** @return array<string, string> */
    private function galat(callable $aksi): array
    {
        try {
            $aksi();
        } catch (ValidationException $e) {
            return array_map(fn (array $p) => $p[0], $e->errors());
        }
        $this->fail('Seharusnya ditolak.');
    }

    #[DataProvider('penyimpanan')]
    public function test_unggah_default_per_jenis_lalu_hapus(bool $database): void
    {
        $this->penyimpananDipakai($database);
        $repo = app(TemplateDokumenRepository::class);

        $pertama = UnggahMasterTemplate::simpan(2026, $this->docx('kak-a.docx', ['${nama_kegiatan} ${total} ${total}']), '  KAK A ', 'KAK+TOR', self::ADMIN);
        $this->assertSame(['KAK A', 'KAK+TOR', 'kak-a.docx', ['nama_kegiatan', 'total'], true, null], [$pertama['nama'], $pertama['jenis'], $pertama['nama_berkas'], $pertama['variabel'], $pertama['is_default'], $pertama['leading']]);
        Storage::disk('local')->assertExists($pertama['file_path']);
        $this->assertSame('Master “KAK A” (KAK+TOR) diunggah — 2 variabel, menjadi default jenis ini.', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);

        $this->travel(1)->seconds();
        $kedua = UnggahMasterTemplate::simpan(2026, $this->docx('kak-b.docx', ['${tujuan}']), 'KAK B', 'KAK+TOR', self::ADMIN);
        $rab = UnggahMasterTemplate::simpan(2026, $this->docx('rab.docx', ['${rab}']), 'RAB', 'RAB', self::ADMIN);
        $this->assertSame([false, true], [$kedua['is_default'], $rab['is_default']], 'Default per jenis.');
        $this->assertSame(['RAB', 'KAK A', 'KAK B'], array_column($repo->semua(2026), 'nama'), 'Default dulu (terbaru dulu), lalu yang bukan default.');
        $this->assertSame([], $repo->semua(2025));

        $this->assertSame('KAK A', UnggahMasterTemplate::hapus($pertama['id'], self::ADMIN)['nama']);
        Storage::disk('local')->assertMissing($pertama['file_path']);
        $this->assertTrue($repo->temukan($kedua['id'])['is_default'], 'Default pindah ke template berikutnya jenis itu.');
        $this->assertSame('Master “KAK A” (KAK+TOR) dihapus.', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);
        $this->assertNull(UnggahMasterTemplate::hapus('bukan-id', self::ADMIN));
        $this->assertNull($repo->temukan('bukan-id'));
    }

    public function test_aturan_berkas_dan_variabel(): void
    {
        $this->assertSame([
            'berkas' => 'Perbaiki variabel yang tidak terdaftar di Word, lalu unggah ulang.',
            'variabel.nama_kegiaatan' => '${nama_kegiaatan} tidak terdaftar — maksud Anda ${nama_kegiatan}?',
        ], $this->galat(fn () => UnggahMasterTemplate::simpan(2026, $this->docx('salah.docx', ['${nama_kegiaatan}']), 'Salah', 'KAK', self::ADMIN)));
        $this->assertSame(['berkas' => 'Belum ada variabel ${…} di dokumen — tandai bagian yang diisi otomatis, mis. ${nama_kegiatan}.'], $this->galat(fn () => UnggahMasterTemplate::simpan(2026, $this->docx('polos.docx', ['Tanpa variabel.']), 'Polos', 'KAK', self::ADMIN)));
        $this->assertSame(['berkas' => 'Pilih dokumen Word .docx (bukan .doc atau PDF).'], $this->galat(fn () => UnggahMasterTemplate::simpan(2026, UploadedFile::fake()->create('kak.pdf', 10), 'PDF', 'KAK', self::ADMIN)));
        $this->assertStringStartsWith('Berkas bukan dokumen Word', $this->galat(fn () => UnggahMasterTemplate::simpan(2026, UploadedFile::fake()->createWithContent('rusak.docx', 'bukan zip'), 'Rusak', 'KAK', self::ADMIN))['berkas']);
        $this->assertSame(['berkas' => 'Pilih berkas master KAK/TOR (.docx).', 'nama' => 'Beri nama template, mis. “Master KAK/TOR 2026”.', 'jenis' => 'Pilih jenis template dari daftar.'], $this->galat(fn () => UnggahMasterTemplate::simpan(2026, null, '  ', 'SPJ', self::ADMIN)));
        $this->assertSame([], app(TemplateDokumenRepository::class)->semua(2026), 'Yang ditolak tidak tersimpan.');

        $analisis = UnggahMasterTemplate::periksa($this->docx('campur.docx', ['${nama_kegiatan} ${pagu}']));
        $this->assertSame(['pagu'], array_column($analisis->tidakTerdaftar(), 'nama'));
    }

    public function test_hanya_admin_dan_tahun_terbuka(): void
    {
        $berkas = fn () => $this->docx('kak.docx', ['${nama_kegiatan}']);
        foreach ([
            fn () => UnggahMasterTemplate::simpan(2026, $berkas(), 'KAK', 'KAK', ['role' => 'leading']),
            fn () => UnggahMasterTemplate::simpan(2025, $berkas(), 'KAK', 'KAK', self::ADMIN),
        ] as $aksi) {
            try {
                $aksi();
                $this->fail('Seharusnya ditolak.');
            } catch (AuthorizationException $e) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('Tahun Anggaran 2025 terkunci — templatenya hanya bisa dilihat.', (function () use ($berkas) {
            try {
                UnggahMasterTemplate::simpan(2025, $berkas(), 'KAK', 'KAK', self::ADMIN);
            } catch (AuthorizationException $e) {
                return $e->getMessage();
            }
        })());
    }

    public function test_bentuk_database_sama_dengan_tiruan(): void
    {
        $tiruan = UnggahMasterTemplate::simpan(2026, $this->docx('kak.docx', ['${nama_kegiatan}']), 'KAK', 'KAK', self::ADMIN);
        $this->penyimpananDipakai(true);
        $database = UnggahMasterTemplate::simpan(2026, $this->docx('kak.docx', ['${nama_kegiatan}']), 'KAK', 'KAK', self::ADMIN);

        $this->assertSame(array_keys($tiruan), array_keys($database));
        $this->assertSame(Arr::except($tiruan, ['id', 'file_path', 'diperbarui_pada']), Arr::except($database, ['id', 'file_path', 'diperbarui_pada']));
        $this->assertStringStartsWith(EloquentTemplateDokumenRepository::FOLDER.'/', $database['file_path']);
    }
}
