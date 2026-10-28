<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Support\Kegiatan\BatasAi;
use App\Support\Stub\StubPengaturan;
use Tests\TestCase;

class ApiBatasAiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        session(['stub_role' => 'admin']);
    }

    /** @return list<string> */
    private function riwayat(): array
    {
        return array_column(array_filter(app(RiwayatPengaturanRepository::class)->semua(), fn (array $r) => $r['aksi'] === 'ubah_batas_ai'), 'keterangan');
    }

    /** @return array<string, mixed> */
    private function akun(int $id): array
    {
        return collect(StubPengaturan::pengguna())->firstWhere('id', $id);
    }

    public function test_data_dan_batas_umum(): void
    {
        $data = $this->getJson(route('pengaturan.ai.batas'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['versi' => null, 'batas_umum' => 20, 'rentang' => BatasAi::RENTANG, 'preset' => BatasAi::PRESET, 'kapasitas' => 100])
            ->assertJsonCount(5, 'akun')
            ->assertJsonStructure(['akun' => [['id', 'nama', 'peran', 'terpakai', 'batas', 'khusus', 'sisa']]])
            ->json();
        $this->assertNotContains(6, array_column($data['akun'], 'id'), 'Akun nonaktif tidak dihitung.');

        $simpan = fn (array $isian) => $this->putJson(route('pengaturan.ai.batas.simpan'), $isian);
        $simpan(['batas' => 0])->assertUnprocessable()->assertJsonStructure(['galat' => ['batas']]);
        $simpan(['batas' => 30, 'versi' => null])->assertOk()
            ->assertJson(['pesan' => 'Batas umum tersimpan: 30 permintaan AI per akun per hari.', 'berubah' => true, 'pengaturan' => ['batas_umum' => 30, 'kapasitas' => 150]]);
        $this->assertSame('Batas umum: 20 per akun per hari → 30 per akun per hari', $this->riwayat()[0]);
        $simpan(['batas' => 30])->assertOk()->assertJson(['berubah' => false]);
        $simpan(['batas' => 40, 'versi' => null])->assertStatus(409)->assertJsonPath('versi', app(PengaturanAiRepository::class)->ambil()['diperbarui_pada']);
    }

    public function test_batas_khusus_per_akun(): void
    {
        $nama = $this->akun(3)['name'];
        $khusus = fn (int $id, array $isian) => $this->putJson(route('pengaturan.ai.batas.akun', ['akun' => $id]), $isian);

        $khusus(3, ['batas' => 50, 'versi' => null])->assertOk()
            ->assertJson(['pesan' => "Batas khusus {$nama}: 50 permintaan per hari.", 'berubah' => true, 'pengaturan' => ['kapasitas' => 130]]);
        $this->assertSame([50, 50], [BatasAi::khusus($this->akun(3)), BatasAi::batas($this->akun(3))]);
        $this->assertSame("{$nama} — Batas: batas umum → 50 per hari", $this->riwayat()[0]);

        $khusus(3, ['batas' => 20])->assertOk()->assertJsonPath('pesan', "{$nama} kembali memakai batas umum.");
        $this->assertNull(BatasAi::khusus($this->akun(3)), 'Sama dengan batas umum = khusus dibuang.');

        $khusus(4, ['batas' => 5])->assertOk();
        $this->deleteJson(route('pengaturan.ai.batas.akun.hapus', ['akun' => 4]))->assertOk()->assertJsonPath('pesan', $this->akun(4)['name'].' kembali memakai batas umum.');
        $khusus(6, ['batas' => 10])->assertUnprocessable()->assertJsonPath('galat.akun.0', $this->akun(6)['name'].' nonaktif — aktifkan akunnya dulu untuk memberi batas khusus.');
        $khusus(3, ['batas' => 201])->assertUnprocessable()->assertJsonStructure(['galat' => ['batas']]);
        $khusus(99, ['batas' => 10])->assertNotFound();

        session(['stub_role' => 'leading']);
        $this->getJson(route('pengaturan.ai.batas'))->assertForbidden();
        $khusus(3, ['batas' => 10])->assertForbidden();
        $this->putJson(route('pengaturan.ai.batas.simpan'), ['batas' => 10])->assertForbidden();
    }
}
