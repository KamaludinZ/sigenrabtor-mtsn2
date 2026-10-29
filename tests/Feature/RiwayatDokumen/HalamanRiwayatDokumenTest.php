<?php

namespace Tests\Feature\RiwayatDokumen;

use App\Contracts\RiwayatDokumenRepository;
use App\Livewire\Dokumen\Riwayat;
use App\Support\Dokumen\RiwayatDokumen;
use App\Support\Navigation;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class HalamanRiwayatDokumenTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private const ADMIN = ['id' => 2, 'name' => 'Koordinator PUSKOM', 'role' => 'admin', 'leadings' => []];

    public function test_cakupan_akun_kelompok_tahun_dan_ringkasan(): void
    {
        $admin = RiwayatDokumen::untuk(self::ADMIN);
        $leading = RiwayatDokumen::untuk(self::LEADING);
        $this->assertCount(12, $admin);
        $this->assertSame(['rd-2025-4', 'rd-2025-5'], array_values(array_intersect(['rd-2025-4', 'rd-2025-5'], array_column($leading, 'id'))), 'Dokumen leading 1.6 tahun lalu ikut.');
        $this->assertSame([], array_values(array_intersect(['rd-2025-1', 'rd-2025-2', 'rd-2025-3'], array_column($leading, 'id'))), 'Leading 1.2 & 1.3 buatan akun lain di luar cakupan akun leading 1.6.');
        $this->assertCount(9, $leading);
        $this->assertContains('rd-2026-6', array_column($leading, 'id'), 'Dibuat admin, tetapi leading-nya milik akun.');

        $this->assertSame([2026, 2025], array_keys(RiwayatDokumen::perTahun($admin)));
        $this->assertSame(['jumlah' => 12, 'kegiatan' => 6, 'tahun' => 2, 'arsip' => 2, 'draf' => 9, 'format' => ['pdf' => 8, 'docx' => 2, 'xlsx' => 2]], RiwayatDokumen::ringkasan($admin));

        $satu = collect($admin)->firstWhere('id', 'rd-2026-1');
        $this->assertSame(route('kegiatan.pratinjau', ['kegiatan' => 2, 'sumber' => 'DIPA']), $satu['tautan_kegiatan']);
        $this->assertNull(collect($admin)->firstWhere('id', 'rd-2025-3')['tautan_kegiatan'], 'Kegiatan tahun lalu tidak tersimpan.');
    }

    public function test_halaman_riwayat_dokumen(): void
    {
        $this->get(route('dokumen.index'))->assertOk()->assertSee('Riwayat Dokumen')->assertSee('data-testid="ringkasan-riwayat"', false);

        Livewire::test(Riwayat::class)
            ->assertSeeInOrder(['Tahun Anggaran 2026', 'Tahun Anggaran 2025'])
            ->assertSee('KAK-TOR_RAB_Lomba_Kreativitas_Siswa_Akhir_Tahun_KOMITE_2025.pdf')
            ->assertSee('KAK/031/2025')
            ->assertDontSee('Pelatihan Penulisan Karya Ilmiah Guru')
            ->assertSee('data-testid="dokumen-riwayat" data-id="rd-2026-7"', false)
            ->assertSee('data-testid="kartu-riwayat" data-id="rd-2026-7"', false);

        session(['stub_role' => 'admin']);
        Livewire::test(Riwayat::class)->assertSee('Pelatihan Penulisan Karya Ilmiah Guru');

        $menu = collect(Navigation::groups('leading'))->flatMap(fn (array $g) => $g['items'])->firstWhere('route', 'dokumen.index');
        $this->assertTrue($menu['available']);
    }

    public function test_riwayat_kosong(): void
    {
        Storage::disk('local')->put(config('sigenrabtor.stub.riwayat_dokumen_file'), '[]');
        $this->assertSame([], app(RiwayatDokumenRepository::class)->semua());
        Livewire::test(Riwayat::class)->assertSee('data-testid="riwayat-kosong"', false)->assertSee('Belum ada dokumen yang tercatat.');
    }
}
