<?php

namespace Tests\Feature\Akun;

use App\Contracts\PermohonanAkunRepository;
use App\Livewire\Akun\Daftar;
use App\Livewire\Akun\Masuk;
use App\Support\Akun\PendaftaranAkun;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PendaftaranAkunTest extends TestCase
{
    private const ISIAN_SAH = [
        'nama' => '  Hasan   Basri, S.Pd. ',
        'jabatan' => 'Koordinator  Adiwiyata',
        'nip' => '19850312 201001 1 004',
        'leadings' => ['1.4', '1.2', '1.2'],
        'surel' => ' Hasan.Basri@Example.com ',
        'sandi' => 'Madrasah#2026',
        'sandi_ulang' => 'Madrasah#2026',
        'catatan' => "Untuk kegiatan\n\n\n\nAdiwiyata.",
        'setuju' => true,
    ];

    public function test_halaman_daftar_dengan_layout_tamu(): void
    {
        $this->get(route('akun.daftar'))
            ->assertOk()
            ->assertSee('data-testid="panel-merek"', false)
            ->assertSee('data-testid="form-daftar"', false)
            ->assertSee('data-testid="syarat-sandi-daftar"', false);
        // Pilihan leading hanya yang aktif (1.9 nonaktif tidak tampil).
        $this->get(route('akun.daftar'))->assertSee('data-kode="1.8"', false)->assertDontSee('data-kode="1.9"', false);
        $this->get(route('akun.masuk'))->assertSee('data-testid="tautan-daftar"', false);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function kasusSandi(): iterable
    {
        foreach (json_decode((string) file_get_contents(__DIR__.'/../../fixtures/syarat-sandi.json'), true) as $i => $kasus) {
            yield "kasus {$i}: {$kasus['sandi']}" => [$kasus];
        }
    }

    /** @param  array<string, mixed>  $kasus */
    #[DataProvider('kasusSandi')]
    public function test_syarat_sandi_sama_dengan_klien(array $kasus): void
    {
        $this->assertSame($kasus['syarat'], PendaftaranAkun::syaratSandi($kasus['sandi'], $kasus['nama'], $kasus['surel']));
    }

    public function test_rapikan_dan_galat_per_kolom(): void
    {
        $rapi = PendaftaranAkun::rapikan(self::ISIAN_SAH);
        $this->assertSame('Hasan Basri, S.Pd.', $rapi['nama']);
        $this->assertSame('hasan.basri@example.com', $rapi['surel']);
        $this->assertSame('198503122010011004', $rapi['nip']);
        $this->assertSame(['1.2', '1.4'], $rapi['leadings']);
        $this->assertSame("Untuk kegiatan\n\nAdiwiyata.", $rapi['catatan']);
        $this->assertSame([], PendaftaranAkun::galat($rapi));

        $kosong = PendaftaranAkun::galat(PendaftaranAkun::rapikan([]));
        $this->assertSame(['nama', 'surel', 'leadings', 'sandi', 'sandi_ulang', 'setuju'], array_keys($kosong));

        $galat = fn (array $ubah) => PendaftaranAkun::galat(PendaftaranAkun::rapikan($ubah + self::ISIAN_SAH));
        $this->assertStringContainsString('sudah terdaftar', $galat(['surel' => 'PUSKOM@example.com'])['surel']);
        $this->assertStringContainsString('menunggu persetujuan', $galat(['surel' => 'nur.laili@example.com'])['surel']);
        $this->assertStringContainsString('tidak sah', $galat(['surel' => 'hasan@'])['surel']);
        $this->assertSame('Leading yang dipilih tidak dikenal atau nonaktif.', $galat(['leadings' => ['1.9']])['leadings']);
        $this->assertSame('Leading yang dipilih tidak dikenal atau nonaktif.', $galat(['leadings' => ['9.9']])['leadings']);
        $this->assertNotNull($galat(['nip' => '1234'])['nip'] ?? null);
        $this->assertSame('Nama harus berisi huruf.', $galat(['nama' => '123'])['nama']);
        $this->assertSame('Sandi minimal 8 karakter.', $galat(['sandi' => 'ab1', 'sandi_ulang' => 'ab1'])['sandi']);
        $this->assertSame('Sandi harus memuat huruf dan angka.', $galat(['sandi' => 'abcdefghij', 'sandi_ulang' => 'abcdefghij'])['sandi']);
        $this->assertSame('Sandi tidak boleh memuat nama atau surel Anda.', $galat(['sandi' => 'basri2026', 'sandi_ulang' => 'basri2026'])['sandi']);
        $this->assertSame('Sandi ulang tidak sama.', $galat(['sandi_ulang' => 'Madrasah#2025'])['sandi_ulang']);
        $this->assertArrayHasKey('catatan', $galat(['catatan' => str_repeat('a', PendaftaranAkun::MAKS_CATATAN + 1)]));
        $this->assertArrayHasKey('setuju', $galat(['setuju' => false]));
    }

    public function test_ajukan_menyimpan_hash_sandi_tanpa_sandi_asli(): void
    {
        $hasil = PendaftaranAkun::ajukan(self::ISIAN_SAH);

        $this->assertSame('menunggu', $hasil['status']);
        $this->assertSame('Menunggu persetujuan admin', $hasil['label_status']);
        $this->assertSame('19850312 201001 1 004', $hasil['nip_format']);
        $this->assertSame(['1.2', '1.4'], array_column($hasil['leading'], 'kode'));
        $this->assertArrayNotHasKey('sandi_hash', $hasil);

        $tersimpan = app(PermohonanAkunRepository::class)->temukan($hasil['id']);
        $this->assertTrue(Hash::check('Madrasah#2026', $tersimpan['sandi_hash']));
        $this->assertSame('hasan.basri@example.com', $tersimpan['surel']);
        $this->assertCount(2, app(PermohonanAkunRepository::class)->semua());
        $this->assertSame($hasil['id'], app(PermohonanAkunRepository::class)->semua()[0]['id']);
        $this->assertStringNotContainsString('Madrasah#2026', (string) Storage::disk('local')->get('stub/permohonan-akun.json'));

        // Surel yang sama tidak bisa diajukan lagi selama menunggu.
        try {
            PendaftaranAkun::ajukan(self::ISIAN_SAH);
            $this->fail('Permohonan ganda seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('surel', $e->errors());
        }
    }

    public function test_formulir_memeriksa_saat_blur_dan_mengirim(): void
    {
        Livewire::test(Daftar::class)
            ->set('isian.surel', 'bukan-surel')
            ->assertHasErrors(['isian.surel'])
            ->assertHasNoErrors(['isian.nama', 'isian.sandi'])
            ->set('isian.surel', 'hb.madrasah@example.com')
            ->assertHasNoErrors(['isian.surel'])
            ->set('isian.sandi', 'pendek1')
            ->assertHasErrors(['isian.sandi'])
            // Mengganti nama memeriksa ulang sandi yang sudah terisi.
            ->set('isian.sandi', 'hasan2026')
            ->assertHasNoErrors(['isian.sandi'])
            ->set('isian.nama', 'Hasan Basri, S.Pd.')
            ->assertHasErrors(['isian.sandi'])
            ->call('kirim')
            ->assertHasErrors(['isian.leadings', 'isian.sandi', 'isian.sandi_ulang', 'isian.setuju'])
            ->assertDispatched('galat-pendaftaran', kolom: 'leadings')
            ->assertSet('terkirim', null)
            ->set('isian', self::ISIAN_SAH)
            ->call('kirim')
            ->assertHasNoErrors()
            ->assertSet('isian.surel', '')
            ->assertSet('isian.sandi', '')
            ->assertSet('terkirim.status', 'menunggu')
            ->assertSee('data-testid="pendaftaran-terkirim"', false)
            ->assertSee('hasan.basri@example.com')
            ->assertDontSee('data-testid="form-daftar"', false);
    }

    public function test_masuk_dengan_surel_yang_masih_menunggu(): void
    {
        Livewire::test(Masuk::class)
            ->set('surel', 'nur.laili@example.com')->set('sandi', 'rahasia')->call('masuk')
            ->assertHasErrors('surel')
            ->assertSee('masih menunggu persetujuan admin');
        Livewire::test(Masuk::class)
            ->set('surel', 'baru@example.com')->set('sandi', 'rahasia')->call('masuk')
            ->assertSee('ajukan pendaftaran');
    }
}
