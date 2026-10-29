<?php

namespace Tests\Feature\Pengaturan;

use App\Contracts\PenomoranDokumenRepository;
use App\Contracts\RiwayatDokumenRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Livewire\Dokumen\Riwayat;
use App\Livewire\Pengaturan\FormatSeragam;
use App\Support\Dokumen\PenomoranDokumen;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PenomoranDokumenTest extends TestCase
{
    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin'];

    public function test_aturan_bawaan_galat_dan_simpan(): void
    {
        $aturan = PenomoranDokumen::aturan(2026);
        $this->assertSame(['pola' => 'RAB/{urut}/{sumber}/{leading}/{tahun}', 'digit' => 3, 'aktif' => true, 'terakhir' => 12, 'berikutnya' => 13], $aturan['RAB']);
        $this->assertFalse($aturan['TOR']['aktif']);
        $this->assertSame(0, PenomoranDokumen::aturan(2025)['RAB']['terakhir'], 'Tahun lain mulai dari nol.');

        $rapi = PenomoranDokumen::rapikan(['RAB' => ['pola' => ' RAB / {urut} / {tahun} ', 'digit' => '4', 'aktif' => '0']]);
        $this->assertSame(['pola' => 'RAB/{urut}/{tahun}', 'digit' => 4, 'aktif' => false], $rapi['RAB']);
        $this->assertSame(PenomoranDokumen::BAWAAN['KAK'], $rapi['KAK'], 'Jenis tanpa isian = bawaan.');
        $this->assertSame(['RAB.pola', 'RAB.digit'], array_keys(PenomoranDokumen::galat(PenomoranDokumen::rapikan(['RAB' => ['pola' => 'RAB/{tahun}', 'digit' => 9]]))));

        $hasil = PenomoranDokumen::simpanAturan(2026, ['RAB' => ['pola' => 'RAB/{urut}/{bulan_romawi}/{tahun}', 'digit' => 4, 'aktif' => true], 'TOR' => ['aktif' => true]], self::ADMIN);
        $this->assertSame('TOR: dinomori otomatis; RAB: pola RAB/{urut}/{sumber}/{leading}/{tahun} → RAB/{urut}/{bulan_romawi}/{tahun}, 3 → 4 digit', $hasil['ringkasan'], 'Urut jenis BAWAAN.');
        $this->assertSame('RAB/{urut}/{bulan_romawi}/{tahun}', PenomoranDokumen::aturan(2026)['RAB']['pola']);
        $this->assertSame(12, PenomoranDokumen::aturan(2026)['RAB']['terakhir'], 'Nomor urut terakhir tetap.');
        $riwayat = collect(app(RiwayatPengaturanRepository::class)->semua())->firstWhere('aksi', 'ubah_penomoran');
        $this->assertStringStartsWith('TA 2026 — TOR: dinomori otomatis; RAB: pola', $riwayat['keterangan']);
        $this->assertNull(PenomoranDokumen::simpanAturan(2026, $hasil['aturan'], self::ADMIN)['ringkasan']);

        $this->assertSame('RAB/0013/'.\App\Support\Dokumen\FormatSeragam::bulanRomawi((int) now()->format('n')).'/2026', PenomoranDokumen::nomor(['jenis' => 'RAB', 'tahun' => 2026]));
        $this->assertNull(PenomoranDokumen::nomor(['jenis' => 'KAK', 'tahun' => 2026], ['pola' => 'KAK/{urut}', 'digit' => 3, 'aktif' => false]), 'Tidak dinomori.');

        try {
            PenomoranDokumen::simpanAturan(2026, [], ['role' => 'leading']);
            $this->fail('Hanya admin.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
        try {
            PenomoranDokumen::simpanAturan(2026, ['KAK' => ['pola' => '']], self::ADMIN);
            $this->fail('Pola kosong ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(['KAK.pola'], array_keys($e->errors()));
        }
        app(TahunAnggaranRepository::class)->kunci(2026, true);
        $this->expectException(AuthorizationException::class);
        PenomoranDokumen::simpanAturan(2026, [], self::ADMIN);
    }

    public function test_formulir_penomoran_format_seragam(): void
    {
        session(['stub_role' => 'admin']);
        Livewire::test(FormatSeragam::class)
            ->set('tab', 'penomoran')
            ->assertSeeHtml('data-berubah="0"')
            ->assertSet('aturanNomor.RAB.pola', 'RAB/{urut}/{sumber}/{leading}/{tahun}')
            ->set('aturanNomor.RAB.pola', 'RAB/{tahun}')
            ->assertHasErrors('aturanNomor.RAB.pola')
            ->assertSee('data-testid="galat-pola-RAB"', false)
            ->set('aturanNomor.RAB.pola', 'RAB/{urut}/{tahun}')
            ->assertHasNoErrors()
            ->assertSeeText('RAB/013/2026')
            ->set('aturanNomor.RAB.digit', '5')
            ->assertSeeText('RAB/00013/2026')
            ->assertSeeHtml('data-berubah="1"')
            ->assertSee('data-testid="nomor-belum-disimpan"', false)
            ->call('batalNomor')
            ->assertSet('aturanNomor.RAB.pola', 'RAB/{urut}/{sumber}/{leading}/{tahun}')
            ->set('aturanNomor.TOR.aktif', true)
            ->set('aturanNomor.TOR.pola', 'TOR-{urut}')
            ->call('simpanNomor')
            ->assertDispatched('notify', type: 'success')
            ->assertSeeHtml('data-berubah="0"')
            ->set('aturanNomor.KAK.pola', '')
            ->call('simpanNomor')
            ->assertHasErrors('aturanNomor.KAK.pola')
            ->assertDispatched('notify', type: 'error');
        $this->assertSame(['pola' => 'TOR-{urut}', 'digit' => 3, 'aktif' => true], app(PenomoranDokumenRepository::class)->aturan(2026)['TOR']);
    }

    public function test_usulan_nomor_di_form_beri_nomor_riwayat_dan_api(): void
    {
        $komponen = Livewire::test(Riwayat::class)
            ->call('bukaLihat', 'rd-2026-1')
            ->assertSet('nomorDokumen', 'RAB/013/DIPA/1.6/2026')
            ->assertSeeHtml('data-usulan="RAB/013/DIPA/1.6/2026"')
            ->call('beriNomor')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success')
            ->assertDontSee('data-testid="beri-nomor"', false);
        $this->assertSame('RAB/013/DIPA/1.6/2026', app(RiwayatDokumenRepository::class)->temukan('rd-2026-1')['nomor_dokumen']);
        $this->assertSame(13, PenomoranDokumen::aturan(2026)['RAB']['terakhir'], 'Usulan dipakai → nomor urut maju.');

        $komponen->call('bukaLihat', 'rd-2026-6')
            ->assertSet('nomorDokumen', 'RAB/014/KOMITE/1.6/2026')
            ->set('nomorDokumen', 'RAB/013/DIPA/1.6/2026')
            ->call('beriNomor')
            ->assertHasErrors('nomorDokumen')
            ->set('nomorDokumen', 'RAB/KHUSUS/2026')
            ->call('beriNomor')
            ->assertHasNoErrors();
        $this->assertSame(13, PenomoranDokumen::aturan(2026)['RAB']['terakhir'], 'Nomor ketikan sendiri tidak memajukan nomor urut.');

        $this->patchJson(route('dokumen.perbarui', ['dokumen' => 'rd-2026-2']), ['nomor_dokumen' => PenomoranDokumen::usulan(app(RiwayatDokumenRepository::class)->temukan('rd-2026-2'))])->assertOk();
        $this->assertSame(8, PenomoranDokumen::aturan(2026)['lengkap']['terakhir'], 'Lewat API juga memajukan nomor urut.');
        $this->assertNull(PenomoranDokumen::usulan(['jenis' => 'TOR'] + app(RiwayatDokumenRepository::class)->temukan('rd-2026-2')), 'TOR tidak dinomori otomatis.');
    }
}
