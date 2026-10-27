<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Livewire\Kegiatan\Form;
use App\Repositories\EloquentKegiatanRepository;
use App\Support\Kegiatan\SusunanPanitia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SusunanPanitiaTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array{key: string, nama: string, jabatan: string, peran: string}> */
    private static function tim(string ...$peran): array
    {
        return array_map(fn (string $p, int $i) => ['key' => "k{$i}", 'nama' => "Anggota {$i}", 'jabatan' => '', 'peran' => $p], $peran, array_keys($peran));
    }

    public function test_operasi_daftar(): void
    {
        $tim = self::tim('Ketua', 'Sekretaris', 'Anggota');

        $this->assertSame(['k1', 'k2'], array_column(SusunanPanitia::hapus($tim, 0), 'key'));
        $this->assertSame(['k0', 'k2', 'k1'], array_column(SusunanPanitia::geser($tim, 2, -1), 'key'));
        $this->assertSame(['k0', 'k1', 'k2'], array_column(SusunanPanitia::geser($tim, 2, 1), 'key'), 'Di ujung diabaikan.');
        $this->assertSame(['k1', 'k2', 'k0'], array_column(SusunanPanitia::pindahkan($tim, 'k0', 9), 'key'));
        $this->assertSame($tim, SusunanPanitia::pindahkan($tim, 'tidak-ada', 0));
        $this->assertSame(['k0', 'baru', 'k1', 'k2'], array_column(SusunanPanitia::sisipkan($tim, 1, ['key' => 'baru']), 'key'));
        $this->assertSame(['baru', 'k0', 'k1', 'k2'], array_column(SusunanPanitia::sisipkan($tim, -3, ['key' => 'baru']), 'key'));

        $penuh = self::tim(...array_fill(0, SusunanPanitia::MAKS, 'Anggota'));
        $this->assertNull(SusunanPanitia::tambah($penuh, ['key' => 'x']));
        $this->assertCount(4, SusunanPanitia::tambah($tim, ['key' => 'x']));
    }

    public function test_urutkan_sesuai_peran_dan_nama_ganda(): void
    {
        $tim = self::tim('Anggota', 'Seksi Konsumsi', ' ketua ', 'Pengarah', 'Anggota');

        // Peran di luar daftar sebelum Anggota; urutan antar peran sama tetap.
        $this->assertSame(['k3', 'k2', 'k1', 'k0', 'k4'], array_column(SusunanPanitia::urutkanSesuaiPeran($tim), 'key'));

        $tim[4]['nama'] = '  anggota   0 ';
        $this->assertSame([0, 4], SusunanPanitia::ganda($tim));
    }

    public function test_rapikan_bentuk_simpan(): void
    {
        $this->assertSame(
            [['nama' => 'Ahmad Fauzi, S.Pd.', 'jabatan' => 'Guru IPA', 'peran' => 'Wakil Ketua'], ['nama' => 'Siti', 'jabatan' => '', 'peran' => 'Seksi Dokumentasi']],
            SusunanPanitia::rapikan([
                ['key' => 'a', 'nama' => '  Ahmad   Fauzi, S.Pd. ', 'jabatan' => 'Guru  IPA', 'peran' => 'wakil KETUA'],
                ['key' => 'b', 'nama' => 'Siti', 'jabatan' => null, 'peran' => ' Seksi  Dokumentasi'],
            ]),
        );
        $this->assertSame('Penanggung Jawab', SusunanPanitia::peranBaku('penanggung jawab'));
    }

    public function test_simpan_draf_merapikan_dan_menyimpan_ke_database(): void
    {
        $this->app->singleton(KegiatanRepository::class, EloquentKegiatanRepository::class);

        $komponen = Livewire::test(Form::class)
            ->set('form.nama_kegiatan', 'Workshop Kurikulum Madrasah')
            ->call('isiSusunanBaku')
            ->set('form.panitia.0.nama', ' Ahmad  Fauzi ')
            ->set('form.panitia.0.peran', 'ketua')
            ->set('form.panitia.1.nama', 'Siti Aminah')
            ->set('form.panitia.2.nama', 'Rina Wulandari')
            ->set('form.panitia.3.nama', 'Dewi Lestari')
            ->call('geserPanitia', 3, -1)
            ->call('saveDraft')
            ->assertHasNoErrors();

        $harapan = [['Ahmad Fauzi', 'Ketua'], ['Siti Aminah', 'Sekretaris'], ['Dewi Lestari', 'Anggota'], ['Rina Wulandari', 'Bendahara']];
        $tersimpan = app(KegiatanRepository::class)->semua()[0]['panitia'];
        $this->assertSame($harapan, array_map(fn ($a) => [$a['nama'], $a['peran']], $tersimpan));
        $this->assertSame($harapan, array_map(fn ($a) => [$a['nama'], $a['peran']], $komponen->get('form.panitia')));
        $this->assertSame([1, 2, 3, 4], DB::table('committees')->orderBy('urutan')->pluck('urutan')->all());
    }
}
