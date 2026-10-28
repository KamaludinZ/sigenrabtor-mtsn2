<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatSaranAiRepository;
use App\Livewire\Kegiatan\KalimatAi;
use App\Support\Kegiatan\KetersediaanKonteks;
use App\Support\Kegiatan\PayloadAi;
use App\Support\Pengaturan\PromptAi;
use App\Support\Stub\StubKegiatanRepository;
use Livewire\Livewire;
use Tests\TestCase;

class KetersediaanKonteksTest extends TestCase
{
    public function test_status_per_bagian_dari_aspek_catatan(): void
    {
        // Contoh 1: catatan menyebut peserta, narasumber, dan output — belum menyebut latar khusus.
        $kegiatan = StubKegiatanRepository::contoh();

        $this->assertSame(['tidak_perlu', null], [KetersediaanKonteks::untuk('nama_cover', $kegiatan)['status'], KetersediaanKonteks::untuk('jadwal', $kegiatan)['arahan']]);
        $this->assertSame(['lengkap', [], null], array_values(array_intersect_key(KetersediaanKonteks::untuk('pelaksanaan', $kegiatan), array_flip(['status', 'kurang', 'arahan']))));
        $this->assertSame(
            ['status' => 'sebagian', 'aspek' => ['latar'], 'kurang' => ['latar'], 'arahan' => 'Catatan konteks belum menyebut latar khusus — jangan mengarang rinciannya; tulis secara umum.'],
            KetersediaanKonteks::untuk('latar_belakang', $kegiatan),
        );

        $kosong = KetersediaanKonteks::untuk('sasaran', ['konteks' => '  '] + $kegiatan);
        $this->assertSame(['kosong', ['peserta', 'output']], [$kosong['status'], $kosong['kurang']]);
        $this->assertSame('Catatan konteks masih kosong — susun kalimat umum dari bahan yang ada tanpa mengarang angka, nama, atau rincian.', $kosong['arahan']);
    }

    public function test_arahan_ikut_permintaan_prompt_dan_riwayat(): void
    {
        $kegiatan = ['konteks' => 'Peserta 30 guru.'] + StubKegiatanRepository::contoh();

        $permintaan = PayloadAi::saranKalimat(1, 'pelaksanaan', $kegiatan, '', '');
        $this->assertSame([['narasumber'], 'Catatan konteks belum menyebut narasumber — jangan mengarang rinciannya; tulis secara umum.'], [$permintaan->aspekKurang, $permintaan->arahanKonteks]);
        $this->assertStringContainsString('Konteks: Catatan konteks belum menyebut narasumber', PromptAi::susun('pelaksanaan', $kegiatan));
        $this->assertStringNotContainsString('Konteks:', PromptAi::susun('nama_cover', $kegiatan));

        $repo = app(KegiatanRepository::class);
        $repo->simpan($kegiatan + $repo->temukan(1), 1);
        Livewire::test(KalimatAi::class, ['kegiatan' => 1])->call('mintaSaran', 'pelaksanaan');
        $this->assertStringContainsString('Konteks: Catatan konteks belum menyebut narasumber', app(RiwayatSaranAiRepository::class)->semua()[0]['prompt']);
    }

    public function test_saran_tiruan_tidak_mengarang_narasumber(): void
    {
        $repo = app(KegiatanRepository::class);
        $teks = fn () => implode(' ', array_column(Livewire::test(KalimatAi::class, ['kegiatan' => 1])->call('mintaSaran', 'pelaksanaan')->get('saran')['pelaksanaan'], 'teks'));

        $this->assertStringContainsString('narasumber', $teks(), 'Contoh 1 menyebut narasumber di catatan.');

        $repo->simpan(['konteks' => 'Peserta 30 guru; output modul ajar.'] + $repo->temukan(1), 1);
        $this->assertStringNotContainsString('narasumber', $teks());
    }
}
