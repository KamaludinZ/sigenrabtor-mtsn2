<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Daftar;
use App\Livewire\Kegiatan\Form;
use App\Livewire\Kegiatan\Klasifikasi;
use App\Livewire\Kegiatan\SalinImpor;
use App\Support\Kegiatan\Duplikat\DuplikatKegiatan;
use App\Support\Kegiatan\KodeHilang;
use App\Support\Stub\StubData;
use Livewire\Livewire;
use Tests\TestCase;

class KodeHilangTest extends TestCase
{
    public function test_penanda_dari_master_tahun_kegiatan_dan_catatan_salinan(): void
    {
        $hilang = KodeHilang::dari([
            'tahun_anggaran' => 2026, 'kode_rktm' => '1.1.2', 'kode_sk' => 'SK.99', 'kode_iksk' => null, 'kode_edm' => null, 'budaya_mutu' => 9,
            'kode_hilang' => [
                'kode_iksk' => KodeHilang::catatan('IKSK 7.7', 'duplikat', 2025),
                'kode_edm' => KodeHilang::catatan('Z9', 'impor'),
                // Kolom sudah diisi lagi → catatan gugur.
                'kode_rktm' => KodeHilang::catatan('9.9.9', 'duplikat', 2025),
                'asing' => KodeHilang::catatan('X', 'duplikat', 2025),
            ],
        ]);

        $this->assertSame(['kode_sk', 'kode_iksk', 'kode_edm', 'budaya_mutu'], array_column($hilang, 'kolom'));
        $this->assertSame(['master', 'duplikat', 'impor', 'master'], array_column($hilang, 'asal'));
        $this->assertSame([
            'Kode SK SK.99 tidak ada di master TA 2026 — pilih kode pengganti.',
            'IKSK IKSK 7.7 dari TA 2025 tidak ada di master TA 2026 — pilih kode pengganti.',
            'Kode EDM Z9 dari cascade Perkin tidak ada di master TA 2026 — pilih kode pengganti.',
            '5 Budaya Mutu 9 tidak ada di master TA 2026 — pilih kode pengganti.',
        ], array_column($hilang, 'teks'));
        $this->assertSame(['kode-perkin', 'kode-perkin', 'kode-edm', 'kode-budaya'], array_column($hilang, 'jangkar'));

        $this->assertSame(
            ['kode_sk' => ['kode' => 'SK.1', 'asal' => 'impor', 'tahun' => null]],
            KodeHilang::sisa(['kode_sk' => ['kode' => 'SK.1', 'asal' => 'impor'], 'kode_iksk' => ['kode' => '', 'asal' => 'impor'], 'kode_edm' => ['kode' => 'A1', 'asal' => 'lain']], []),
        );
        $this->assertSame([], KodeHilang::dari(['tahun_anggaran' => 2026, 'kode_rktm' => '1.1.2', 'kode_sk' => 'SK.9', 'kode_iksk' => 'IKSK 9.1', 'kode_edm' => 'A1', 'budaya_mutu' => 1]));
    }

    public function test_penanda_kode_hilang_dari_duplikat_hingga_dipilih_ulang(): void
    {
        $repo = app(KegiatanRepository::class);
        $lalu = $repo->simpan(['nama_kegiatan' => 'Rapat Kerja Tahunan', 'leading' => '1.6', 'tahun_anggaran' => 2025, 'status' => 'final', 'kode_rktm' => '1.1.2', 'kode_sk' => 'SK.99', 'kode_iksk' => 'IKSK 99.1']);

        // Sebelum diduplikat: lencana di daftar tahun lalu.
        $sumber = collect(DuplikatKegiatan::sumber($repo->semua(), 2025, StubData::currentUser()))->firstWhere('id', $lalu);
        $this->assertSame(['kode_sk', 'kode_iksk'], array_column($sumber['kode_hilang'], 'kolom'));
        Livewire::test(SalinImpor::class)
            ->assertSeeHtml('data-testid="lencana-kode-hilang"')
            ->assertSee('Tidak ada di master TA 2026: Kode SK SK.99, IKSK IKSK 99.1')
            ->set('pilihan', [$lalu])
            ->call('duplikat')
            ->assertSeeHtml('data-testid="pilih-ulang-kode"');

        $baru = collect($repo->semua())->first(fn (array $k) => $k['nama_kegiatan'] === 'Rapat Kerja Tahunan' && $k['id'] !== $lalu);
        $this->assertSame([null, null], [$baru['kode_sk'], $baru['kode_iksk']]);
        $this->assertSame(['kode_sk' => ['kode' => 'SK.99', 'asal' => 'duplikat', 'tahun' => 2025], 'kode_iksk' => ['kode' => 'IKSK 99.1', 'asal' => 'duplikat', 'tahun' => 2025]], $baru['kode_hilang']);

        // Data Kegiatan: banner bertaut ke Kode & Klasifikasi; menyimpan isian tidak membuang penanda.
        Livewire::test(Form::class, ['kegiatan' => $baru['id']])
            ->assertSeeHtml('data-testid="penanda-kode-hilang"')
            ->assertSee('2 kode master perlu dipilih ulang')
            ->assertSeeHtml('href="'.route('kegiatan.kode', ['kegiatan' => $baru['id']]).'#kode-perkin"')
            ->set('form.tujuan', 'menyusun program kerja')
            ->call('saveDraft');
        $this->assertCount(2, $repo->temukan($baru['id'])['kode_hilang']);

        // Daftar kegiatan: lencana bertaut ke halaman kode.
        Livewire::test(Daftar::class)
            ->assertSeeHtml('data-testid="lencana-kode-hilang"')
            ->assertSeeHtml('href="'.route('kegiatan.kode', ['kegiatan' => $baru['id']]).'"');

        // Kode & Klasifikasi: banner + penanda per isian; memilih kode pengganti menghapus penandanya.
        Livewire::test(Klasifikasi::class, ['kegiatan' => $baru['id']])
            ->assertSeeHtml('data-testid="penanda-kode-hilang"')
            ->assertSeeHtml('data-kode-hilang-isian="kode_sk"')
            ->assertSeeHtml('data-kode-hilang-isian="kode_iksk"')
            ->assertSee('dari TA 2025 tidak ada di master TA 2026')
            ->set('form.kode_sk', 'SK.9')
            ->assertDontSeeHtml('data-kode-hilang-isian="kode_sk"')
            ->assertSeeHtml('data-kode-hilang-isian="kode_iksk"')
            ->set('form.kode_iksk', 'IKSK 9.1')
            ->assertDontSeeHtml('data-testid="penanda-kode-hilang"')
            ->call('simpan');
        $this->assertSame([], $repo->temukan($baru['id'])['kode_hilang']);
        $this->assertDontSeeKodeHilang($baru['id']);
    }

    private function assertDontSeeKodeHilang(int $id): void
    {
        Livewire::test(Form::class, ['kegiatan' => $id])->assertDontSeeHtml('data-testid="penanda-kode-hilang"');
    }
}
