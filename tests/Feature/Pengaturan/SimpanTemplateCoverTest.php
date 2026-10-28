<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\TemplateCoverRepository;
use App\Repositories\EloquentTemplateCoverRepository;
use App\Support\Dokumen\TeksCover;
use App\Support\Pengaturan\SimpanTemplateCover;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SimpanTemplateCoverTest extends TestCase
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
            $this->app->singleton(TemplateCoverRepository::class, EloquentTemplateCoverRepository::class);
        }
    }

    private function gambar(int $lebar = 1240, int $tinggi = 1754): UploadedFile
    {
        return UploadedFile::fake()->image('cover.png', $lebar, $tinggi);
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

    private function riwayatTerakhir(): string
    {
        return app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan'];
    }

    #[DataProvider('penyimpanan')]
    public function test_unggah_atur_tata_letak_lalu_hapus(bool $database): void
    {
        $this->penyimpananDipakai($database);
        $repo = app(TemplateCoverRepository::class);

        $a = SimpanTemplateCover::tambah(2026, $this->gambar(), ' Cover A ', self::ADMIN);
        $this->assertSame(['Cover A', 1240, 1754, true, TeksCover::bawaan()], [$a['nama'], $a['lebar'], $a['tinggi'], $a['is_default'], TeksCover::rapikan($a['teks_items'])]);
        Storage::disk('local')->assertExists($a['gambar_path']);
        $this->assertSame('Cover “Cover A” diunggah (1240 × 1754 piksel), menjadi cover default.', $this->riwayatTerakhir());

        $this->travel(1)->seconds();
        $b = SimpanTemplateCover::tambah(2026, $this->gambar(800, 1131), 'Cover B', self::ADMIN);
        $this->assertFalse($b['is_default']);
        $this->assertSame(['Cover A', 'Cover B'], array_column($repo->semua(2026), 'nama'));

        $kotak = TeksCover::bawaan();
        $kotak[1] = ['y' => 30.456, 'align' => 'left', 'color' => '#0F5E42'] + $kotak[1];
        unset($kotak[4]);
        $sesudah = SimpanTemplateCover::simpanTataLetak($a['id'], array_values($kotak), self::ADMIN);
        $this->assertSame([30.46, 'left', '#0f5e42'], [(float) $sesudah['teks_items'][1]['y'], $sesudah['teks_items'][1]['align'], $sesudah['teks_items'][1]['color']], 'Selalu dirapikan.');
        $this->assertNotContains('disusun_oleh', array_column($sesudah['teks_items'], 'field'));
        $this->assertSame('Tata letak “Cover A” diubah — 1 kotak diubah, disembunyikan: “Disusun oleh”.', $this->riwayatTerakhir());

        $hapus = SimpanTemplateCover::hapus($a['id'], self::ADMIN);
        $this->assertSame(['Cover A', 'Cover B'], [$hapus['cover']['nama'], $hapus['pengganti']['nama']]);
        Storage::disk('local')->assertMissing($a['gambar_path']);
        $this->assertTrue($repo->temukan($b['id'])['is_default']);
        $this->assertSame('Cover “Cover A” dihapus; “Cover B” menjadi cover default.', $this->riwayatTerakhir());
        $this->assertNull(SimpanTemplateCover::hapus('bukan-id', self::ADMIN));
    }

    public function test_aturan_gambar_tata_letak_dan_hak(): void
    {
        $this->assertSame(['gambar' => 'Gambar terlalu kecil — minimal 600 piksel di setiap sisi agar tajam saat dicetak.'], $this->galat(fn () => SimpanTemplateCover::tambah(2026, $this->gambar(500, 700), 'Kecil', self::ADMIN)));
        $this->assertSame(['gambar' => 'Berkas harus berupa gambar JPG atau PNG.'], $this->galat(fn () => SimpanTemplateCover::tambah(2026, UploadedFile::fake()->create('cover.pdf', 10), 'PDF', self::ADMIN)));
        $this->assertSame(['gambar' => 'Pilih gambar desain cover.', 'nama' => 'Beri nama cover, mis. “Cover default 2026”.'], $this->galat(fn () => SimpanTemplateCover::tambah(2026, null, ' ', self::ADMIN)));
        $this->assertSame([], app(TemplateCoverRepository::class)->semua(2026));

        $cover = SimpanTemplateCover::tambah(2026, $this->gambar(), 'Cover', self::ADMIN);
        $this->assertSame(['kotak' => 'Tampilkan minimal satu kotak teks di cover.'], $this->galat(fn () => SimpanTemplateCover::simpanTataLetak($cover['id'], [['field' => 'asing']], self::ADMIN)));
        $this->assertSame(['kotak' => 'Cover tidak ditemukan — muat ulang halaman.'], $this->galat(fn () => SimpanTemplateCover::simpanTataLetak('bukan-id', TeksCover::bawaan(), self::ADMIN)));
        $this->assertSame(TeksCover::bawaan(), TeksCover::rapikan(app(TemplateCoverRepository::class)->temukan($cover['id'])['teks_items']));

        foreach ([
            fn () => SimpanTemplateCover::tambah(2026, $this->gambar(), 'Cover', ['role' => 'leading']),
            fn () => SimpanTemplateCover::simpanTataLetak($cover['id'], TeksCover::bawaan(), ['role' => 'leading']),
            fn () => SimpanTemplateCover::tambah(2025, $this->gambar(), 'Cover 2025', self::ADMIN),
        ] as $aksi) {
            try {
                $aksi();
                $this->fail('Seharusnya ditolak.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_bentuk_database_sama_dengan_tiruan(): void
    {
        $tiruan = SimpanTemplateCover::tambah(2026, $this->gambar(), 'Cover', self::ADMIN);
        $this->penyimpananDipakai(true);
        $database = SimpanTemplateCover::tambah(2026, $this->gambar(), 'Cover', self::ADMIN);

        $this->assertSame(array_keys($tiruan), array_keys($database));
        $this->assertSame(Arr::except($tiruan, ['id', 'gambar_path', 'diperbarui_pada']), Arr::except($database, ['id', 'gambar_path', 'diperbarui_pada']));
        $this->assertStringStartsWith(EloquentTemplateCoverRepository::FOLDER.'/', $database['gambar_path']);
    }
}
