<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\HasilDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Contracts\PenomoranDokumenRepository;
use App\Contracts\RabRepository;
use App\Enums\StatusKegiatan;
use App\Repositories\EloquentDrafDokumenRepository;
use App\Repositories\EloquentHasilDokumenRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Repositories\EloquentMatrikJadwalRepository;
use App\Repositories\EloquentRabRepository;
use App\Support\Akun\SesiAkun;
use App\Support\Dokumen\RiwayatDokumen;
use App\Support\Dokumen\SimpanDokumenLengkap;
use App\Support\Kegiatan\KelolaArsipFinal;
use App\Support\Kegiatan\RiwayatStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RujukanDb;
use Tests\TestCase;

class SimpanDokumenLengkapTest extends TestCase
{
    use RefreshDatabase;

    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, array{bool}> */
    public static function penyimpanan(): array
    {
        return ['tiruan' => [false], 'database' => [true]];
    }

    private function pakaiDatabase(): void
    {
        RujukanDb::siapkan();
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $this->app->singleton(HasilDokumenRepository::class, EloquentHasilDokumenRepository::class);
        $this->app->singleton(DrafDokumenRepository::class, EloquentDrafDokumenRepository::class);
        $this->app->singleton(RabRepository::class, EloquentRabRepository::class);
        $this->app->singleton(MatrikJadwalRepository::class, EloquentMatrikJadwalRepository::class);
    }

    private function kegiatanBaru(string $status = 'draf'): int
    {
        $riwayat = $status === 'draf' ? [] : [RiwayatStatus::entri('ajukan', StatusKegiatan::Diperiksa, self::LEADING), RiwayatStatus::entri('setujui', StatusKegiatan::Disetujui, self::ADMIN)];

        return app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Workshop Penguatan Literasi', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026,
            'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 2_000_000], ['sumber' => 'Komite', 'pagu' => 3_000_000]],
            'status' => $status, 'riwayat_status' => $riwayat,
        ]);
    }

    #[DataProvider('penyimpanan')]
    public function test_simpan_dokumen_lengkap_dengan_format_dan_nomor(bool $database): void
    {
        if ($database) {
            $this->pakaiDatabase();
        }
        $draf = $this->kegiatanBaru();
        $hasil = SimpanDokumenLengkap::simpan($draf, null, self::LEADING);
        $this->assertTrue($hasil['baru']);
        $this->assertSame(['lengkap', 'DIPA', null], [$hasil['dokumen']['jenis'], $hasil['dokumen']['sumber'], $hasil['dokumen']['nomor']]);
        $this->assertSame(['nomor' => null, 'keadaan' => 'menunggu', 'label' => SimpanDokumenLengkap::NOMOR['menunggu']], $hasil['nomor']);
        $format = $hasil['dokumen']['format'];
        $this->assertSame(['workshop', 'bawaan', true], [$format['template']['jenis'], $format['template']['asal'], $format['template']['susunan_baku']]);
        $this->assertSame(['pola' => 'DOK/{urut}/{leading}/{tahun}', 'digit' => 3, 'aktif' => true], $format['aturan_nomor']);
        $this->assertIsBool($format['kop_lengkap']);

        // Nomor manual pada draf; disusun ulang tanpa nomor → nomor tetap; nomor lain ditolak.
        $manual = SimpanDokumenLengkap::simpan($draf, 'Komite', self::LEADING, ' DOK/LIT/01 ');
        $this->assertSame(['DOK/LIT/01', 'manual'], [$manual['nomor']['nomor'], $manual['nomor']['keadaan']]);
        $ulang = SimpanDokumenLengkap::simpan($draf, 'Komite', self::LEADING);
        $this->assertSame(['DOK/LIT/01', 'tetap', false], [$ulang['nomor']['nomor'], $ulang['nomor']['keadaan'], $ulang['baru']]);
        $this->assertSame('DOK/LIT/01', app(HasilDokumenRepository::class)->temukan($draf, 'lengkap', 'Komite')['nomor']);
        try {
            SimpanDokumenLengkap::simpan($draf, 'Komite', self::LEADING, 'DOK/LAIN/02');
            $this->fail('Nomor tersimpan tidak boleh diganti.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('nomor_dokumen', $e->errors());
        }

        // Kegiatan disetujui → nomor terbit otomatis dan tidak berubah saat disimpan lagi.
        $setuju = $this->kegiatanBaru('disetujui');
        $terbit = SimpanDokumenLengkap::simpan($setuju, 'DIPA', self::LEADING);
        $this->assertSame('terbit', $terbit['nomor']['keadaan']);
        $this->assertMatchesRegularExpression('#^DOK/\d{3}/1\.6/2026$#', $terbit['nomor']['nomor']);
        $this->assertStringContainsString('Nomor dokumen '.$terbit['nomor']['nomor'], $terbit['pesan']);
        $terakhir = app(PenomoranDokumenRepository::class)->terakhir(2026)['lengkap'];
        $this->assertSame($terbit['nomor']['nomor'], SimpanDokumenLengkap::simpan($setuju, 'DIPA', self::LEADING)['nomor']['nomor']);
        $this->assertSame($terakhir, app(PenomoranDokumenRepository::class)->terakhir(2026)['lengkap']);
    }

    public function test_arsip_final_memakai_nomor_dokumen_lengkap(): void
    {
        $id = $this->kegiatanBaru('disetujui');
        $nomor = SimpanDokumenLengkap::simpan($id, 'DIPA', self::LEADING)['nomor']['nomor'];
        $unggah = KelolaArsipFinal::unggah($id, 'DIPA', UploadedFile::fake()->createWithContent('dipa.pdf', '%PDF-1.4 ttd'), self::LEADING);
        $this->assertSame($nomor, $unggah['nomor_dokumen']);
        $this->assertSame($nomor, collect(RiwayatDokumen::semua())->firstWhere('id', $unggah['riwayat_dokumen'])['nomor_dokumen']);

        // Sebaliknya: arsip dulu, dokumen lengkap mengikuti nomor arsip.
        $lain = $this->kegiatanBaru('disetujui');
        $arsip = KelolaArsipFinal::unggah($lain, 'Komite', UploadedFile::fake()->createWithContent('komite.pdf', '%PDF-1.4 ttd'), self::LEADING);
        $simpan = SimpanDokumenLengkap::simpan($lain, 'Komite', self::LEADING);
        $this->assertSame([$arsip['nomor_dokumen'], 'arsip'], [$simpan['nomor']['nomor'], $simpan['nomor']['keadaan']]);
    }

    public function test_endpoint_simpan_dokumen_lengkap(): void
    {
        $id = $this->kegiatanBaru();
        $this->postJson(route('kegiatan.dokumen.lengkap', $id), ['sumber' => 'Komite'])
            ->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('dokumen.jenis', 'lengkap')
            ->assertJsonPath('dokumen.sumber', 'Komite')
            ->assertJsonPath('dokumen.format.template.asal', 'bawaan')
            ->assertJsonPath('nomor.keadaan', 'menunggu');
        $this->postJson(route('kegiatan.dokumen.lengkap', $id), ['sumber' => 'Komite'])->assertOk()->assertJsonPath('baru', false);
        $this->postJson(route('kegiatan.dokumen.lengkap', $id), ['sumber' => 'BOS'])->assertStatus(422)->assertJsonPath('galat.sumber', 'Sumber dana BOS tidak dipilih untuk kegiatan ini.');
        $this->postJson(route('kegiatan.dokumen.lengkap', $id), ['nomor_dokumen' => '#salah'])->assertStatus(422)->assertJsonStructure(['pesan', 'galat' => ['nomor_dokumen']]);
        $this->postJson(route('kegiatan.dokumen.lengkap', 9999))->assertNotFound();

        $tanpaSumber = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Rapat Tanpa Dana', 'leading' => '1.6', 'user_id' => 1, 'tahun_anggaran' => 2026]);
        $this->postJson(route('kegiatan.dokumen.lengkap', $tanpaSumber))->assertStatus(422)->assertJsonStructure(['galat' => ['sumber']]);

        SesiAkun::masuk(['id' => 3, 'role' => 'leading']);
        $this->postJson(route('kegiatan.dokumen.lengkap', $id))->assertForbidden();
    }
}
