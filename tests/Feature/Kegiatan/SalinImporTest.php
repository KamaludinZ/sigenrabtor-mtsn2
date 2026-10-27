<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\SalinImpor;
use App\Support\Kegiatan\Duplikat\DuplikatKegiatan;
use Livewire\Livewire;
use Tests\TestCase;

class SalinImporTest extends TestCase
{
    private function kegiatanLalu(string $nama, string $leading): int
    {
        return app(KegiatanRepository::class)->simpan(['nama_kegiatan' => $nama, 'leading' => $leading, 'tahun_anggaran' => 2025, 'status' => 'final', 'sumber_dana' => [['sumber' => 'DIPA', 'pagu' => 2_000_000]]]);
    }

    public function test_halaman_dan_tautan_dari_data_kegiatan(): void
    {
        $this->get(route('kegiatan.index'))->assertSeeHtml('href="'.route('kegiatan.salin_impor').'"');

        $this->get(route('kegiatan.salin_impor'))
            ->assertOk()
            ->assertSeeLivewire(SalinImpor::class)
            ->assertSee('<title>Duplikat &amp; Impor · ', false)
            ->assertSee('Menjadi draf TA 2026');
    }

    public function test_tab_duplikat_memilih_kegiatan_tahun_lalu_dalam_cakupan_akun(): void
    {
        $this->assertSame([2025], DuplikatKegiatan::tahunSumber());

        Livewire::test(SalinImpor::class)
            ->assertSeeHtml('data-testid="panel-duplikat"')
            ->assertSee('Belum ada kegiatan TA 2025 yang bisa diduplikat.')
            ->assertSeeHtml('data-perubahan="kode"');

        $milik = $this->kegiatanLalu('Workshop Mutu 2025', '1.6');
        $lain = $this->kegiatanLalu('Lomba Sains', '1.1');

        Livewire::test(SalinImpor::class)
            ->assertSeeHtml('data-sumber-duplikat="'.$milik.'"')
            ->assertDontSeeHtml('data-sumber-duplikat="'.$lain.'"')
            ->assertDontSeeHtml('data-sumber-duplikat="1"')
            ->set('pilihan', [$milik, $lain, 999])
            ->assertSet('pilihan', [$milik], 'Hanya kegiatan sumber dalam cakupan akun.')
            ->call('kosongkanPilihan')
            ->call('pilihSemua')
            ->assertSet('pilihan', [$milik])
            ->assertSee('1 kegiatan dipilih')
            ->call('kosongkanPilihan')
            ->assertSet('pilihan', []);

        session(['stub_role' => 'admin']);
        Livewire::test(SalinImpor::class)
            ->assertSeeHtml('data-sumber-duplikat="'.$lain.'"')
            ->set('cari', 'lomba')
            ->assertDontSeeHtml('data-sumber-duplikat="'.$milik.'"')
            ->set('cari', '')
            ->set('leading', '1.6')
            ->assertSeeHtml('data-sumber-duplikat="'.$milik.'"')
            ->assertDontSeeHtml('data-sumber-duplikat="'.$lain.'"')
            ->call('pilihSemua')
            ->assertSet('pilihan', [$milik])
            ->assertSee('1 kegiatan dipilih');

        Livewire::withQueryParams(['tab' => 'acak', 'dari' => 2030])->test(SalinImpor::class)
            ->assertSet('tab', 'duplikat')
            ->assertSet('tahunSumber', 0);
    }

    public function test_daftar_kegiatan_tahun_lalu_dengan_rincian_dan_yang_sudah_ada(): void
    {
        $baru = app(KegiatanRepository::class)->simpan([
            'nama_kegiatan' => 'Rapat Kerja Tahunan', 'leading' => '1.6', 'tahun_anggaran' => 2025, 'status' => 'final', 'kode_rktm' => '6.17.12',
            'tujuan' => 'menyusun program kerja', 'tempat' => 'Aula', 'peserta_jumlah' => 40, 'peserta_jenis' => 'Guru', 'lama_hari' => 2,
            'bulan_pelaksanaan' => [1, 2], 'sumber_dana' => [['sumber' => 'Komite', 'pagu' => 3_000_000]],
        ]);
        // Nama sama dengan contoh kegiatan 2 di TA 2026 → sudah diduplikat.
        $ganda = app(KegiatanRepository::class)->simpan(['nama_kegiatan' => '  supervisi akademik dan refleksi  PEMBELAJARAN', 'leading' => '1.6', 'tahun_anggaran' => 2025]);

        $baris = collect(DuplikatKegiatan::sumber(app(KegiatanRepository::class)->semua(), 2025, ['role' => 'leading', 'leadings' => ['1.6']]))->keyBy('id');
        $this->assertSame(['Aula', '40 orang (Guru)', 2, 'Januari – Februari 2025', '6.17.12', null], [$baris[$baru]['tempat'], $baris[$baru]['peserta'], $baris[$baru]['lama_hari'], $baris[$baru]['bulan'], $baris[$baru]['kode_rktm'], $baris[$baru]['sudah_ada']]);
        $this->assertSame([['sumber' => 'Komite', 'total' => 0, 'item' => 0]], $baris[$baru]['sumber']);
        $this->assertSame(2, $baris[$ganda]['sudah_ada']);

        Livewire::test(SalinImpor::class)
            ->assertSeeHtml('data-testid="daftar-tahun-lalu"')
            ->assertSeeHtml('data-sumber-duplikat="'.$ganda.'" data-sudah-ada="ya"')
            ->assertSee('Sudah ada di TA 2026')
            ->assertSeeHtml('href="'.route('kegiatan.edit', ['kegiatan' => 2]).'"')
            ->assertSeeHtml('data-rincian-lalu="'.$baru.'"')
            ->assertSee('menyusun program kerja')
            ->call('pilihSemua')
            ->assertSet('pilihan', [$baru], 'Yang sudah ada dilewati.')
            ->set('pilihan', [$baru, $ganda])
            ->assertSet('pilihan', [$baru]);
    }

    public function test_tab_impor_perkin(): void
    {
        Livewire::withQueryParams(['tab' => 'perkin'])->test(SalinImpor::class)
            ->assertSeeHtml('data-testid="panel-perkin"')
            ->assertSeeHtml('data-testid="kolom-perkin"')
            ->assertSeeInOrder(['Kode SK', 'Kode IKSK', 'Nama Kegiatan', 'Leading', 'Pagu DIPA / Pagu Komite', 'Bulan Pelaksanaan'])
            ->assertSee('Aman diimpor ulang');
    }
}
