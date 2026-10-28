<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PenyusunSaranKalimat;
use App\Contracts\RiwayatSaranAiRepository;
use App\Support\Kegiatan\BatasAi;
use App\Support\Kegiatan\GagalPenyediaAi;
use App\Support\Kegiatan\GagalUsulanAi;
use App\Support\Kegiatan\PermintaanSaranKalimat;
use App\Support\Kegiatan\SaranKalimatAi;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaranKalimatAiTest extends TestCase
{
    private function kegiatan(int $id = 2): array
    {
        return app(KegiatanRepository::class)->temukan($id);
    }

    public function test_saran_tersusun_tercatat_dan_menghitung_kuota(): void
    {
        $pengguna = StubData::currentUser();
        $sebelum = BatasAi::sisa($pengguna);
        $hasil = SaranKalimatAi::minta($this->kegiatan(), 'tujuan', $pengguna);

        $this->assertGreaterThanOrEqual(2, count($hasil['versi']));
        $this->assertSame(['judul', 'ciri', 'teks'], array_keys($hasil['versi'][0]));
        $this->assertSame($sebelum - 1, BatasAi::sisa($pengguna));
        $riwayat = collect(app(RiwayatSaranAiRepository::class)->semua())->firstWhere('id', $hasil['permintaan']);
        $this->assertSame([2, 'tujuan', array_column($hasil['versi'], 'teks')], [$riwayat['kegiatan_id'], $riwayat['bagian'], array_column($riwayat['versi'], 'teks')]);
        $this->assertStringNotContainsString('197', $riwayat['prompt'], 'Tanpa NIP.');

        $this->assertSame([['judul' => 'Versi 1', 'ciri' => '', 'teks' => 'RAPAT KERJA']], SaranKalimatAi::rapikan('nama_cover', [['teks' => " rapat \n kerja "], ['teks' => 'RAPAT KERJA'], ['teks' => ''], 'x']));
    }

    public function test_aturan_permintaan(): void
    {
        $pengguna = StubData::currentUser();
        $galat = function (callable $aksi): array {
            try {
                $aksi();
            } catch (ValidationException $e) {
                return array_map(fn ($p) => $p[0], $e->errors());
            }
            $this->fail('Seharusnya ditolak.');
        };

        $this->assertSame(['bagian' => 'Usulan jadwal dibuat di halaman Matrik & Jadwal.'], $galat(fn () => SaranKalimatAi::minta($this->kegiatan(), 'jadwal', $pengguna)));
        $this->assertSame(['bagian' => 'Bagian kalimat tidak dikenal.'], $galat(fn () => SaranKalimatAi::minta($this->kegiatan(), 'anggaran', $pengguna)));
        $this->assertSame(['instruksi' => 'Instruksi maksimal 300 karakter.'], $galat(fn () => SaranKalimatAi::minta($this->kegiatan(), 'tujuan', $pengguna, str_repeat('a', 301))));
        $this->assertSame(['acuan' => 'Teks yang diperbaiki kosong — pilih versi atau tulis teksnya dulu.'], $galat(fn () => SaranKalimatAi::minta(['tujuan' => ''] + $this->kegiatan(), 'tujuan', $pengguna, 'lebih singkat')));

        try {
            SaranKalimatAi::minta(['nama_kegiatan' => ' '] + $this->kegiatan(), 'tujuan', $pengguna);
            $this->fail('Nama kosong harus ditolak.');
        } catch (GagalUsulanAi $e) {
            $this->assertSame('Isi nama kegiatan dulu di Data Kegiatan.', $e->getMessage());
        }

        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Pentas Seni', 'leading' => '1.2', 'user_id' => 4]);
        $this->expectException(AuthorizationException::class);
        SaranKalimatAi::minta($this->kegiatan($lain), 'tujuan', $pengguna);
    }

    public function test_endpoint_permintaan_saran(): void
    {
        $this->postJson(route('kegiatan.saran_ai.minta', ['kegiatan' => 2, 'bagian' => 'sasaran']))->assertCreated()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['bagian' => 'sasaran', 'kuota' => ['terpakai' => 1]])
            ->assertJsonStructure(['permintaan', 'versi' => [['judul', 'ciri', 'teks']], 'catatan_penyedia', 'penyedia', 'kuota' => ['batas', 'terpakai', 'sisa']]);
        $this->postJson(route('kegiatan.saran_ai.minta', ['kegiatan' => 2, 'bagian' => 'tujuan']), ['instruksi' => 'lebih singkat', 'acuan' => 'Menyusun program kerja tahunan madrasah.'])->assertCreated();
        $this->postJson(route('kegiatan.saran_ai.minta', ['kegiatan' => 2, 'bagian' => 'jadwal']))->assertStatus(422)->assertJsonStructure(['galat' => ['bagian']]);
        $this->postJson(route('kegiatan.saran_ai.minta', ['kegiatan' => 999, 'bagian' => 'tujuan']))->assertNotFound();

        $repo = app(KegiatanRepository::class);
        $repo->simpan(['status' => 'diperiksa'] + $repo->temukan(1), 1);
        $this->postJson(route('kegiatan.saran_ai.minta', ['kegiatan' => 1, 'bagian' => 'tujuan']))->assertForbidden();

        // Penyedia gagal → 503 beserta jenis kegagalannya.
        $this->app->instance(PenyusunSaranKalimat::class, new class implements PenyusunSaranKalimat
        {
            public function sarankan(PermintaanSaranKalimat $permintaan, string $penyedia = 'tiruan'): array
            {
                throw new GagalPenyediaAi('waktu_habis', 'ollama');
            }

            public function perbaiki(PermintaanSaranKalimat $permintaan): array
            {
                return [];
            }
        });
        $this->postJson(route('kegiatan.saran_ai.minta', ['kegiatan' => 2, 'bagian' => 'tujuan']))->assertStatus(503)->assertJsonPath('jenis', 'waktu_habis');
    }
}
