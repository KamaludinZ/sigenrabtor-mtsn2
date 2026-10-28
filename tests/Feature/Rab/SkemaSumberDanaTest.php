<?php

namespace Tests\Feature\Rab;

use App\Enums\SumberDana;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SkemaSumberDanaTest extends TestCase
{
    use RefreshDatabase;

    private function ditolak(callable $aksi): void
    {
        try {
            DB::transaction($aksi);
        } catch (QueryException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('Seharusnya ditolak database.');
    }

    public function test_isi_tabel_sama_dengan_enum(): void
    {
        $this->assertSame(
            array_map(fn (SumberDana $s, int $i) => ['kode' => $s->value, 'keterangan' => $s->keterangan(), 'urutan' => $i + 1], SumberDana::cases(), array_keys(SumberDana::cases())),
            DB::table('sumber_dana')->orderBy('urutan')->get(['kode', 'keterangan', 'urutan'])->map(fn ($s) => (array) $s)->all(),
        );
    }

    public function test_anggaran_merujuk_sumber_dana(): void
    {
        $kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Workshop Kurikulum']);
        DB::table('anggaran_kegiatan')->insert(['activity_id' => $kegiatan, 'sumber_dana' => 'Komite', 'pagu' => 10_000_000]);

        $this->ditolak(fn () => DB::table('anggaran_kegiatan')->insert(['activity_id' => $kegiatan, 'sumber_dana' => 'BOS']));
        $this->ditolak(fn () => DB::table('sumber_dana')->where('kode', 'Komite')->delete());

        // Kode diganti: RAB yang memakainya ikut.
        DB::table('sumber_dana')->where('kode', 'Komite')->update(['kode' => 'KOMITE']);
        $this->assertSame('KOMITE', DB::table('anggaran_kegiatan')->where('activity_id', $kegiatan)->value('sumber_dana'));
    }

    public function test_rangkuman_rab_membawa_nama_dan_urutan_sumber_dana(): void
    {
        $kegiatan = DB::table('activities')->insertGetId(['tahun_anggaran' => 2026, 'nama_kegiatan' => 'Supervisi Akademik']);
        DB::table('anggaran_kegiatan')->insert([['activity_id' => $kegiatan, 'sumber_dana' => 'Komite', 'pagu' => 2_500_000], ['activity_id' => $kegiatan, 'sumber_dana' => 'DIPA', 'pagu' => 6_000_000]]);

        $this->assertSame(
            [['DIPA', 'DIPA', 1], ['Komite', 'Komite', 2]],
            DB::table('rab_per_sumber')->where('activity_id', $kegiatan)->orderBy('urutan_sumber')->get()->map(fn ($r) => [$r->sumber_dana, $r->nama_sumber, $r->urutan_sumber])->all(),
        );
    }
}
