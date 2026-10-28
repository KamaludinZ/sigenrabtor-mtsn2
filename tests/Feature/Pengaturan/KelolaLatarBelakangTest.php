<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\DasarHukumRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Support\Pengaturan\KelolaLatarBelakang;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubDasarHukumRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KelolaLatarBelakangTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    /** @return array<string, string> */
    private function galat(array $isian): array
    {
        try {
            KelolaLatarBelakang::simpan(2026, $isian + StubDasarHukumRepository::bawaan(), self::ADMIN);
        } catch (ValidationException $e) {
            return array_map(fn (array $p) => $p[0], $e->errors());
        }
        $this->fail('Seharusnya ditolak.');
    }

    public function test_simpan_dirapikan_diperiksa_dan_tercatat(): void
    {
        $repo = app(DasarHukumRepository::class);
        $versi = $repo->untukTahun(2026)['diperbarui_pada'];
        $hasil = KelolaLatarBelakang::simpan(2026, [
            'latar_belakang' => "MTsN 2 Kota Malang  berkomitmen\nmeningkatkan mutu pada TA {tahun}.",
            'kalimat_penutup' => ' Kegiatan {nama_kegiatan} oleh {leading}. ',
        ], self::ADMIN, $versi);

        $this->assertSame(['MTsN 2 Kota Malang berkomitmen meningkatkan mutu pada TA {tahun}.', 'Kegiatan {nama_kegiatan} oleh {leading}.', ['paragraf baku', 'kalimat penutup']], [$hasil['latar_belakang'], $hasil['kalimat_penutup'], $hasil['berubah']]);
        $this->assertSame('Kegiatan {nama_kegiatan} oleh {leading}.', $repo->untukTahun(2026)['kalimat_penutup']);
        $this->assertCount(4, $repo->untukTahun(2026)['dasar_hukum'], 'Butir dasar hukum tetap.');
        $this->assertSame('Latar belakang TA 2026 — ubah paragraf baku & kalimat penutup: Kegiatan {nama_kegiatan} oleh {leading}.', app(RiwayatPengaturanRepository::class)->semua()[0]['keterangan']);

        $jumlahRiwayat = count(app(RiwayatPengaturanRepository::class)->semua());
        $this->assertSame([], KelolaLatarBelakang::simpan(2026, $hasil, self::ADMIN)['berubah']);
        $this->assertCount($jumlahRiwayat, app(RiwayatPengaturanRepository::class)->semua(), 'Tanpa perubahan: tidak tercatat.');

        try {
            KelolaLatarBelakang::simpan(2026, ['kalimat_penutup' => 'Lain {nama_kegiatan}.'] + $hasil, self::ADMIN, $versi);
            $this->fail('Versi lama harus ditolak.');
        } catch (PengaturanBerubah) {
            $this->assertSame('Kegiatan {nama_kegiatan} oleh {leading}.', $repo->untukTahun(2026)['kalimat_penutup']);
        }

        $this->assertSame(['latar_belakang' => 'Paragraf latar belakang baku wajib diisi.', 'kalimat_penutup' => 'Sisipkan {nama_kegiatan} di tempat nama kegiatan tercetak.'], $this->galat(['latar_belakang' => '  ', 'kalimat_penutup' => 'Kegiatan ini.']));
        $this->assertArrayHasKey('latar_belakang', $this->galat(['latar_belakang' => 'Kegiatan {nama_kegiatan} TA {tahun}.']));
        $this->assertSame(['kalimat_penutup' => '{nama_kegiatan} hanya boleh dipakai sekali.'], $this->galat(['kalimat_penutup' => '{nama_kegiatan} dan {nama_kegiatan}']));

        foreach ([[2025, self::ADMIN], [2026, ['role' => 'leading']]] as [$tahun, $pengguna]) {
            try {
                KelolaLatarBelakang::simpan($tahun, StubDasarHukumRepository::bawaan(), $pengguna);
                $this->fail('Seharusnya ditolak.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_endpoint_latar_belakang(): void
    {
        $this->withSession(['stub_role' => 'leading'])->getJson(route('pengaturan.madrasah.latar_belakang.data'))->assertForbidden();

        $respons = $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.madrasah.latar_belakang.data', ['kegiatan' => 1]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('tahun', 2026)
            ->assertJsonPath('diatur', true)
            ->assertJsonPath('kalimat_penutup', StubDasarHukumRepository::bawaan()['kalimat_penutup'])
            ->assertJsonPath('penanda.latar_belakang', ['{tahun}'])
            ->assertJsonPath('maks.kalimat_penutup', 500)
            ->assertJsonPath('kegiatan.id', 1);
        $this->assertStringContainsString('Tahun Anggaran 2026', $respons->json('cetak.latar_belakang'));
        $this->assertStringNotContainsString('{nama_kegiatan}', $respons->json('kegiatan.kalimat_penutup'));
        $this->assertContains('{nama_kegiatan}', array_column($respons->json('cetak.kalimat_penutup'), 'penanda'), 'Kalimat penutup terpotong di penanda.');

        $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.madrasah.latar_belakang.data', ['ta' => 2025]))->assertJsonPath('terkunci', true);
        $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.madrasah.latar_belakang.data', ['ta' => 2031]))->assertNotFound();
        $this->withSession(['stub_role' => 'admin'])->getJson(route('pengaturan.madrasah.latar_belakang.data', ['kegiatan' => 999]))->assertNotFound();
    }
}
