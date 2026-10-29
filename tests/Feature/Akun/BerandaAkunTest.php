<?php

namespace Tests\Feature\Akun;

use App\Contracts\KegiatanRepository;
use App\Livewire\Dashboard;
use App\Support\Akun\BerandaAkun;
use App\Support\Akun\SesiAkun;
use App\Support\Stub\StubData;
use Livewire\Livewire;
use Tests\TestCase;

class BerandaAkunTest extends TestCase
{
    public function test_leading_melihat_kegiatan_yang_siap_diajukan_dan_perlu_dilengkapi(): void
    {
        $beranda = BerandaAkun::untuk(StubData::currentUser());

        $this->assertSame(3, $beranda['jumlah_kegiatan']);
        $this->assertSame([['ajukan', 2], ['ajukan', 1], ['lengkapi', 3]], array_map(fn (array $t) => [$t['jenis'], $t['kegiatan_id']], $beranda['tindakan']));
        $this->assertStringStartsWith('Belum bisa diajukan: RAB Komite', $beranda['tindakan'][2]['keterangan']);
        $this->assertStringEndsWith('/kegiatan/3/pratinjau#checklist-isian', $beranda['tindakan'][2]['tautan']);
        $this->assertSame([3, 2, 1], array_column($beranda['terakhir'], 'id'));
        $this->assertSame(['buat', 'kegiatan', 'cetak', 'dokumen'], array_column($beranda['pintasan'], 'kunci'));
        $this->assertCount(BerandaAkun::BATAS_AKTIVITAS, $beranda['aktivitas']);
        $this->assertSame(['dokumen'], array_values(array_unique(array_column($beranda['aktivitas'], 'jenis'))));
        $this->assertSame('Muslimin, M.Pd.', $beranda['akun']['nama']);
    }

    public function test_dikembalikan_didahulukan_beserta_catatan_dan_riwayat_status_jadi_aktivitas(): void
    {
        $repo = app(KegiatanRepository::class);
        $k = $repo->temukan(2);
        $k['status'] = 'dikembalikan';
        $k['riwayat_status'] = [
            ['aksi' => 'ajukan', 'status' => 'diperiksa', 'oleh' => 'Muslimin, M.Pd.', 'peran' => 'leading', 'pada' => '2026-01-10T08:00:00+07:00'],
            ['aksi' => 'kembalikan', 'status' => 'dikembalikan', 'oleh' => 'Koordinator PUSKOM', 'peran' => 'admin', 'pada' => now()->toIso8601String(), 'catatan' => 'Lengkapi narasumber pada bagian pelaksanaan.'],
        ];
        $repo->simpan($k, 2);

        $beranda = BerandaAkun::untuk(StubData::currentUser());
        $this->assertSame('perbaiki', $beranda['tindakan'][0]['jenis']);
        $this->assertSame('Lengkapi narasumber pada bagian pelaksanaan.', $beranda['tindakan'][0]['keterangan']);
        $this->assertSame('Dari Koordinator PUSKOM', $beranda['tindakan'][0]['subjudul']);
        $this->assertSame(['status', 'Dikembalikan untuk perbaikan'], [$beranda['aktivitas'][0]['jenis'], $beranda['aktivitas'][0]['teks']]);
    }

    public function test_admin_melihat_antrean_permohonan_dan_pengaturan(): void
    {
        $repo = app(KegiatanRepository::class);
        $k = $repo->temukan(1);
        $k['status'] = 'diperiksa';
        $k['riwayat_status'] = [['aksi' => 'ajukan', 'status' => 'diperiksa', 'oleh' => 'Muslimin, M.Pd.', 'peran' => 'leading', 'pada' => '2026-01-10T08:00:00+07:00']];
        $repo->simpan($k, 1);
        session(['stub_role' => 'admin']);

        $beranda = BerandaAkun::untuk(StubData::currentUser());
        $jenis = array_column($beranda['tindakan'], 'jenis');
        $this->assertSame(['periksa', 'permohonan'], array_slice($jenis, 0, 2));
        $this->assertContains('pengaturan', $jenis);
        $this->assertStringContainsString('Diajukan Muslimin, M.Pd.', $beranda['tindakan'][0]['keterangan']);
        $this->assertStringEndsWith('/pemeriksaan/1', $beranda['tindakan'][0]['tautan']);
        $this->assertSame('1 permohonan akun menunggu persetujuan', $beranda['tindakan'][1]['judul']);
        $this->assertSame(['pemeriksaan', 'monitoring', 'akun', 'pengaturan'], array_column($beranda['pintasan'], 'kunci'));
    }

    public function test_dashboard_menampilkan_bagian_pribadi(): void
    {
        Livewire::test(Dashboard::class)
            ->assertSee('data-testid="beranda-akun"', false)
            ->assertSee('data-testid="perlu-tindakan" data-jumlah="3"', false)
            ->assertSee('data-testid="pintasan-buat"', false)
            ->assertSee('data-testid="kegiatan-terakhir" data-kegiatan="3"', false)
            ->assertSee('Ini pertama kalinya Anda masuk.');

        SesiAkun::masuk(['id' => 6, 'role' => 'leading']);
        // Akun leading tanpa kegiatan di cakupannya: keadaan kosong.
        $this->assertSame(0, BerandaAkun::untuk(StubData::currentUser())['jumlah_kegiatan']);
        Livewire::test(Dashboard::class)->assertSee('data-testid="tindakan-kosong"', false)->assertSee('Buat kegiatan pertama');
    }
}
