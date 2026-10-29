<?php

namespace Tests\Feature\RiwayatDokumen;

use App\Contracts\KegiatanRepository;
use App\Contracts\RabRepository;
use App\Contracts\RiwayatDokumenRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Livewire\Dokumen\Riwayat;
use App\Support\Dokumen\AksiRiwayatDokumen;
use App\Support\Dokumen\RiwayatDokumen;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class AksiRiwayatDokumenTest extends TestCase
{
    private const LEADING = ['id' => 1, 'name' => 'Muslimin, M.Pd.', 'role' => 'leading', 'leadings' => ['1.6']];

    private function catat(array $kegiatan, string $jenis, ?string $sumber, string $asal = 'cetak'): string
    {
        return app(RiwayatDokumenRepository::class)->catat(RiwayatDokumen::entri($kegiatan, $jenis, $sumber, 'pdf', $asal, 'BERKAS.pdf', 2048, false, self::LEADING))['id'];
    }

    public function test_lihat_rincian_versi_dan_buka_ulang(): void
    {
        $rab = AksiRiwayatDokumen::lihat('rd-2026-1', self::LEADING);
        $this->assertTrue($rab['terbaru']);
        $this->assertSame(['rab'], $rab['buka']['lembar']);
        $this->assertStringContainsString('/kegiatan/2/cetak?sumber=DIPA&dokumen=rab', $rab['buka']['cetak']);
        $this->assertStringContainsString('/kegiatan/2/cetak/pdf', $rab['buka']['pdf']);
        $this->assertSame([false, true, null], [$rab['buka']['word'], $rab['buka']['excel'], $rab['buka']['arsip']]);
        $this->assertSame('draf', $rab['sekarang']['status']);
        $this->assertNull($rab['alasan_buka']);
        $this->assertSame([], $rab['perbedaan'], 'Total, status, dan cap DRAF masih sama.');

        $unduhLama = AksiRiwayatDokumen::lihat('rd-2026-6', self::LEADING);
        $this->assertSame([['kunci' => 'total', 'teks' => 'Total RAB Komite kini Rp10.000.000 (saat dibuat Rp9.250.000).']], $unduhLama['perbedaan']);

        $lama = AksiRiwayatDokumen::lihat('rd-2025-4', self::LEADING);
        $this->assertFalse($lama['terbaru']);
        $this->assertSame(['rd-2025-5', 'rd-2025-4'], array_column($lama['versi'], 'id'), 'Semua versi, terbaru dulu.');
        $this->assertNull($lama['sekarang']);
        $this->assertStringContainsString('tidak lagi tersimpan', $lama['alasan_buka']);

        $kegiatan = app(KegiatanRepository::class)->temukan(1);
        $arsip = $this->catat($kegiatan, 'lengkap', 'Komite', 'arsip');
        $this->assertSame(route('kegiatan.arsip', ['kegiatan' => 1, 'sumber' => 'Komite']), AksiRiwayatDokumen::lihat($arsip, self::LEADING)['buka']['arsip']);
        $lama = $this->catat(['status' => 'disetujui'] + $kegiatan, 'lengkap', 'Komite');
        $this->catat($kegiatan, 'lengkap', 'Komite');
        $beda = AksiRiwayatDokumen::lihat($lama, self::LEADING)['perbedaan'];
        $this->assertSame(['versi', 'status', 'draf'], array_column($beda, 'kunci'));
        $this->assertStringContainsString('Status kegiatan kini Draf (saat dibuat Disetujui).', $beda[1]['teks']);
        $siap = app(RiwayatDokumenRepository::class)->catat(RiwayatDokumen::entri($kegiatan, 'RAB', 'Komite', 'xlsx', 'unduh', 'RAB.xlsx', 100, false, self::LEADING, ['total_rab' => 10_000_000]))['id'];
        $this->assertSame([['kunci' => 'draf', 'teks' => 'Dokumen kini bercap DRAF (saat dibuat tanpa cap).']], AksiRiwayatDokumen::lihat($siap, self::LEADING)['perbedaan']);
        $lepas = $this->catat($kegiatan, 'RAB', 'DIPA');
        $this->assertStringContainsString('Sumber dana DIPA tidak lagi dipilih', AksiRiwayatDokumen::lihat($lepas, self::LEADING)['alasan_buka']);

        $this->expectException(AuthorizationException::class);
        AksiRiwayatDokumen::lihat('rd-2025-1', self::LEADING);
    }

    public function test_catatan_tidak_ada(): void
    {
        $this->expectException(RuntimeException::class);
        AksiRiwayatDokumen::temukan('rd-tidak-ada', self::LEADING);
    }

    public function test_duplikat_kegiatan_tahun_ini_dengan_nama_baru(): void
    {
        $rencana = AksiRiwayatDokumen::rencanaDuplikat('rd-2026-1', self::LEADING);
        $this->assertSame([true, 'tahun_ini', false], [$rencana['bisa'], $rencana['mode'], $rencana['nama_terkunci']]);
        $this->assertSame('Supervisi Akademik dan Refleksi Pembelajaran (salinan)', $rencana['nama']);
        $this->assertSame(AksiRiwayatDokumen::PESAN_PAGU_SALINAN, $rencana['catatan'][0]['teks']);
        $this->assertSame(['rab' => ['DIPA' => count(app(RabRepository::class)->rincian(2, 'DIPA')), 'Komite' => count(app(RabRepository::class)->rincian(2, 'Komite'))]], array_intersect_key($rencana['ringkasan'], ['rab' => 0]));
        $this->assertSame([], $rencana['salinan_sebelumnya']);

        try {
            AksiRiwayatDokumen::duplikat('rd-2026-1', 'supervisi akademik dan  refleksi pembelajaran', self::LEADING);
            $this->fail('Nama yang sudah ada ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Sudah ada kegiatan bernama', $e->errors()['nama'][0]);
        }
        $this->assertSame('Nama kegiatan baru wajib diisi.', AksiRiwayatDokumen::galatNama('', 2026));

        $hasil = AksiRiwayatDokumen::duplikat('rd-2026-1', '  Supervisi Akademik Semester Genap ', self::LEADING);
        $this->assertSame($this->idKegiatanBaru(), $hasil['id']);
        $baru = app(KegiatanRepository::class)->temukan($hasil['id']);
        $this->assertSame(['Supervisi Akademik Semester Genap', 'SUPERVISI AKADEMIK SEMESTER GENAP', 'draf', 2026], [$baru['nama_kegiatan'], $baru['nama_kegiatan_cover'], $baru['status'], (int) $baru['tahun_anggaran']]);
        $this->assertStringContainsString('Disalin dari kegiatan “Supervisi Akademik dan Refleksi Pembelajaran” (dokumen RAB v1).', end($baru['riwayat_status'])['catatan']);
        $this->assertCount(count(app(RabRepository::class)->rincian(2, 'DIPA')), app(RabRepository::class)->rincian($hasil['id'], 'DIPA'), 'Rincian RAB ikut disalin.');
        $this->assertSame(route('kegiatan.dokumen', ['kegiatan' => $hasil['id'], 'jenis' => 'RAB', 'sumber' => 'DIPA']), $hasil['tautan_dokumen']);
        $this->assertSame('Supervisi Akademik dan Refleksi Pembelajaran (salinan)', AksiRiwayatDokumen::namaSalinan('Supervisi Akademik dan Refleksi Pembelajaran (salinan)', 2026));
        $this->assertSame([['id' => $hasil['id'], 'nama' => 'Supervisi Akademik Semester Genap', 'tautan' => $hasil['tautan']]], AksiRiwayatDokumen::rencanaDuplikat('rd-2026-1', self::LEADING)['salinan_sebelumnya'], 'Salinan sebelumnya diberitahukan.');
    }

    public function test_duplikat_kegiatan_tahun_lalu_dan_yang_tidak_bisa(): void
    {
        $tidak = AksiRiwayatDokumen::rencanaDuplikat('rd-2025-5', self::LEADING);
        $this->assertFalse($tidak['bisa']);
        $this->assertSame(route('kegiatan.create'), $tidak['tautan']['url']);
        try {
            AksiRiwayatDokumen::duplikat('rd-2025-5', null, self::LEADING);
            $this->fail('Kegiatan yang tidak tersimpan tidak bisa disalin.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('duplikat', $e->errors());
        }

        $repo = app(KegiatanRepository::class);
        $id = $repo->simpan(['nama_kegiatan' => 'Pentas Seni Akhir Tahun', 'leading' => '1.6', 'tahun_anggaran' => 2025, 'user_id' => 1]);
        $catatan = $this->catat($repo->temukan($id), 'KAK+TOR', null);
        $rencana = AksiRiwayatDokumen::rencanaDuplikat($catatan, self::LEADING);
        $this->assertSame([true, 'tahun_lalu', true, 'Pentas Seni Akhir Tahun'], [$rencana['bisa'], $rencana['mode'], $rencana['nama_terkunci'], $rencana['nama']]);
        $this->assertCount(6, $rencana['perubahan']);

        $hasil = AksiRiwayatDokumen::duplikat($catatan, 'nama diabaikan', self::LEADING);
        $baru = $repo->temukan($hasil['id']);
        $this->assertSame(['Pentas Seni Akhir Tahun', 2026, ['id' => $id, 'tahun' => 2025]], [$baru['nama_kegiatan'], (int) $baru['tahun_anggaran'], $baru['duplikat_dari']]);
        $this->assertSame(route('kegiatan.dokumen', ['kegiatan' => $hasil['id'], 'jenis' => 'KAK+TOR']), $hasil['tautan_dokumen']);

        $lagi = AksiRiwayatDokumen::rencanaDuplikat($catatan, self::LEADING);
        $this->assertFalse($lagi['bisa'], 'Sudah ada di tahun aktif.');
        $this->assertSame(route('kegiatan.edit', ['kegiatan' => $hasil['id']]), $lagi['tautan']['url']);

        app(TahunAnggaranRepository::class)->kunci(2026, true);
        $terkunci = AksiRiwayatDokumen::rencanaDuplikat('rd-2026-1', self::LEADING);
        $this->assertFalse($terkunci['bisa']);
        $this->assertStringContainsString('2026', $terkunci['alasan']);
    }

    public function test_modal_lihat_dan_duplikat(): void
    {
        Livewire::test(Riwayat::class, ['tampilan' => 'kronologis'])
            ->set('tampilan', 'kronologis')
            ->assertSeeHtml('data-testid="lihat-dokumen" data-id="rd-2026-1"')
            ->assertSeeHtml('data-testid="duplikat-dokumen" data-id="rd-2026-1"')
            ->call('bukaLihat', 'rd-2026-1')
            ->assertSet('dilihat', 'rd-2026-1')
            ->assertSeeHtml('data-testid="modal-lihat-dokumen" data-id="rd-2026-1"')
            ->assertSee('data-testid="cetak-ulang"', false)
            ->assertSee('data-testid="unduh-ulang-excel"', false)
            ->assertDontSee('data-testid="unduh-ulang-word"', false)
            ->assertSeeHtml('data-perbedaan="0"')
            ->assertDontSee('data-testid="konfirmasi-buka-ulang"', false)
            ->call('bukaLihat', 'rd-2026-6')
            ->assertSee('data-testid="konfirmasi-buka-ulang"', false)
            ->assertSeeHtml('data-perbedaan="1"')
            ->assertSeeText('Total RAB Komite kini Rp10.000.000 (saat dibuat Rp9.250.000).')
            ->call('bukaLihat', 'rd-2025-1')
            ->assertSet('dilihat', 'rd-2026-6')
            ->assertDispatched('notify', type: 'error')
            ->call('bukaDuplikat', 'rd-2026-1')
            ->assertSet('dilihat', null)
            ->assertSet('namaDuplikat', 'Supervisi Akademik dan Refleksi Pembelajaran (salinan)')
            ->assertSeeHtml('data-testid="modal-duplikat-dokumen" data-id="rd-2026-1" data-mode="tahun_ini"')
            ->assertSee('data-testid="ringkasan-salin"', false)
            ->assertSeeText('Akan dibuat: draf “Supervisi Akademik dan Refleksi Pembelajaran (salinan)” TA 2026')
            ->set('namaDuplikat', 'Review RKJM dan Penyusunan RKTM')
            ->assertHasErrors('namaDuplikat')
            ->call('duplikat')
            ->assertSee('data-testid="galat-nama-duplikat"', false)
            ->assertSet('hasilDuplikat', null)
            ->set('namaDuplikat', 'Supervisi Akademik Tahap 2')
            ->assertHasNoErrors()
            ->call('duplikat')
            ->assertSee('data-testid="hasil-duplikat-dokumen"', false)
            ->assertSeeHtml('href="'.route('kegiatan.edit', ['kegiatan' => $this->idKegiatanBaru()]).'"')
            ->assertDispatched('notify', type: 'success', tautan: ['label' => 'Buka kegiatan baru', 'url' => route('kegiatan.edit', ['kegiatan' => $this->idKegiatanBaru()])])
            ->call('tutupDuplikat')
            ->assertSet('diduplikat', null)
            ->assertDontSee('data-testid="modal-duplikat-dokumen"', false)
            ->call('bukaDuplikat', 'rd-2025-5')
            ->assertSee('data-testid="duplikat-tidak-bisa"', false)
            ->assertSee('data-testid="tautan-duplikat"', false);
    }
}
