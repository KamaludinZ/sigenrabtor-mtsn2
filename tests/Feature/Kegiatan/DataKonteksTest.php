<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Kegiatan\KonteksAi;
use App\Support\Stub\StubKegiatanRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class DataKonteksTest extends TestCase
{
    use RefreshDatabase;

    public function test_konteks_aspek_dan_bahan_ai(): void
    {
        $repo = app(KegiatanRepository::class);
        $json = $this->getJson(route('kegiatan.konteks', ['kegiatan' => 1]))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
        $kegiatan = $repo->temukan(1);

        $this->assertSame([$kegiatan['konteks'], mb_strlen($kegiatan['konteks']), 3000, null], [$json['konteks'], $json['panjang'], $json['batas'], $json['peringatan_privasi']]);
        $this->assertSame(array_keys(KonteksAi::ASPEK), array_column($json['aspek'], 'kunci'));
        $this->assertSame(array_values(KonteksAi::aspekTerisi($kegiatan['konteks'])), array_column($json['aspek'], 'terisi'));
        $this->assertSame(KonteksAi::teks($kegiatan), $json['bahan_ai']);
        $this->assertSame('Nama kegiatan', $json['bahan_isian'][0]['label']);
    }

    public function test_nip_disembunyikan_dari_bahan_ai(): void
    {
        $repo = app(KegiatanRepository::class);
        $repo->simpan(['konteks' => 'Narasumber pengawas, NIP 197001011995031001.'] + $repo->temukan(1), 1);

        $json = $this->getJson(route('kegiatan.konteks', ['kegiatan' => 1]))->assertOk()->json();
        $this->assertNotNull($json['peringatan_privasi']);
        $this->assertStringContainsString('197001011995031001', $json['konteks'], 'Catatan asli tetap utuh untuk penyuntingnya.');
        $this->assertStringNotContainsString('197001011995031001', $json['bahan_ai']);
        $this->assertStringContainsString('[NIP disembunyikan]', $json['bahan_ai']);
    }

    public function test_hak_akses_dan_waktu_konteks_dari_database(): void
    {
        $this->getJson(route('kegiatan.konteks', ['kegiatan' => 999]))->assertNotFound();
        session(['stub_role' => 'admin']);
        $lain = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => 'Lomba Kebersihan Kelas', 'leading' => '1.2']);
        session(['stub_role' => 'leading']);
        $this->getJson(route('kegiatan.konteks', ['kegiatan' => $lain]))->assertForbidden();

        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);
        $id = app(KegiatanRepository::class)->simpan(Arr::except(StubKegiatanRepository::contoh(), ['id', 'status']));
        $json = $this->getJson(route('kegiatan.konteks', ['kegiatan' => $id]))->assertOk()->json();
        $this->assertSame(app(KegiatanRepository::class)->temukan($id)['konteks_diperbarui_pada'], $json['diperbarui_pada']);
        $this->assertNotNull($json['diperbarui_pada']);
    }
}
