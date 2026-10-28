<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\PengaturanAiRepository;
use App\Contracts\PenyusunUsulanJadwal;
use App\Contracts\PromptAiRepository;
use App\Contracts\RiwayatSaranAiRepository;
use App\Support\Kegiatan\BatasAi;
use App\Support\Kegiatan\GagalUsulanAi;
use App\Support\Kegiatan\JadwalKegiatan;
use App\Support\Kegiatan\PermintaanUsulanJadwal;
use App\Support\Kegiatan\SaranJadwalAi;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaranJadwalAiTest extends TestCase
{
    private const LEADING = ['id' => 1, 'role' => 'leading', 'leadings' => ['1.6']];

    private function kegiatan(int $id = 3): array
    {
        return app(KegiatanRepository::class)->temukan($id);
    }

    /** @param  array<string, mixed>  $ubah */
    private function aturAi(array $ubah): void
    {
        $data = array_diff_key(app(PengaturanAiRepository::class)->ambil(), ['diperbarui_pada' => 1]);
        app(PengaturanAiRepository::class)->simpan(array_replace_recursive($data, $ubah));
    }

    private function gagal(callable $aksi): string
    {
        try {
            $aksi();
        } catch (GagalUsulanAi $e) {
            return $e->getMessage();
        }
        $this->fail('Seharusnya gagal.');
    }

    /** @param  list<mixed>  $jawaban */
    private function penyusun(array $jawaban): void
    {
        $this->app->instance(PenyusunUsulanJadwal::class, new class($jawaban) implements PenyusunUsulanJadwal
        {
            public ?PermintaanUsulanJadwal $permintaan = null;

            public function __construct(private array $jawaban) {}

            public function usulkan(PermintaanUsulanJadwal $permintaan): array
            {
                $this->permintaan = $permintaan;

                return $this->jawaban;
            }
        });
    }

    public function test_usulan_tiruan_dicatat_dan_dihitung(): void
    {
        $sisa = BatasAi::sisa(self::LEADING);

        $hasil = SaranJadwalAi::minta($this->kegiatan(), self::LEADING, 'supervisi', '  lebih   ringkas ');

        $this->assertSame([6, 5, 4], array_map(fn (array $v) => count($v['baris']), $hasil['versi']));
        $this->assertSame(['Versi 1', 'Versi 2', 'Versi 3'], array_column($hasil['versi'], 'judul'));
        $this->assertSame(['tiruan', null], [$hasil['penyedia'], $hasil['catatan']]);
        $this->assertSame($sisa - 1, BatasAi::sisa(self::LEADING));

        $riwayat = collect(app(RiwayatSaranAiRepository::class)->semua())->firstWhere('id', $hasil['permintaan']);
        $this->assertSame([3, 'jadwal', 'lebih ringkas', 3], [$riwayat['kegiatan_id'], $riwayat['bagian'], $riwayat['instruksi'], count($riwayat['versi'])]);
        $this->assertStringContainsString('Jenis kegiatan: Supervisi / evaluasi / rapat kerja', $riwayat['prompt']);
        $this->assertStringContainsString(' — ', $riwayat['versi'][0]['teks'], 'Versi tercatat sebagai baris "uraian — bulan".');

        SaranJadwalAi::putuskan($hasil['permintaan'], 1);
        $riwayat = collect(app(RiwayatSaranAiRepository::class)->semua())->firstWhere('id', $hasil['permintaan']);
        $this->assertSame(['ditolak', 'diterima', 'ditolak'], array_column($riwayat['versi'], 'status'));
        SaranJadwalAi::putuskan(null, null);
    }

    public function test_perapian_hasil_penyusun_dan_acuan_bulan(): void
    {
        $this->penyusun([
            ['ciri' => "  Ringkas\nsekali ", 'baris' => [
                ['uraian' => "Rapat\n  persiapan", 'mulai' => '10', 'sampai' => '9'],
                ['uraian' => '   ', 'mulai' => 11],
                ['uraian' => str_repeat('a', 250), 'mulai' => 13, 'sampai' => 12],
            ]],
            ['baris' => []],
            'bukan versi',
            ...array_fill(0, 3, ['baris' => [['uraian' => 'Laporan', 'mulai' => 11, 'sampai' => 12]]]),
        ]);

        $hasil = SaranJadwalAi::minta($this->kegiatan(), self::LEADING, 'supervisi');

        $this->assertCount(3, $hasil['versi'], 'Paling banyak 3 versi berisi.');
        $this->assertSame(['judul' => 'Versi 1', 'ciri' => 'Ringkas sekali', 'keterangan' => ''], array_diff_key($hasil['versi'][0], ['baris' => 1]));
        $this->assertSame([
            ['uraian' => 'Rapat persiapan', 'mulai' => 10, 'sampai' => null],
            ['uraian' => str_repeat('a', JadwalKegiatan::MAKS_URAIAN), 'mulai' => null, 'sampai' => null],
        ], $hasil['versi'][0]['baris']);
        $this->assertSame(['uraian' => 'Laporan', 'mulai' => 11, 'sampai' => 12], $hasil['versi'][1]['baris'][0]);

        $permintaan = app(PenyusunUsulanJadwal::class)->permintaan;
        $this->assertSame(['persiapan' => 10, 'pelaksanaan' => [11], 'laporan' => 11], $permintaan->acuan);
        $this->assertStringNotContainsString('7.500.000', $permintaan->bahan, 'Pagu tidak ikut terkirim.');

        // Jumlah versi mengikuti parameter Pengaturan AI.
        $this->aturAi(['parameter' => ['jumlah_versi' => 2]]);
        $this->assertCount(2, SaranJadwalAi::minta($this->kegiatan(), self::LEADING, 'supervisi')['versi']);
    }

    public function test_permintaan_ditolak_dan_kegagalan(): void
    {
        try {
            SaranJadwalAi::minta($this->kegiatan(), self::LEADING, 'piknik', str_repeat('a', 301));
            $this->fail('Seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(['jenis' => ['Pilih jenis kegiatan dari daftar.'], 'instruksi' => ['Instruksi maksimal 300 karakter.']], $e->errors());
        }

        // Jawaban tanpa baris berurai: tetap terhitung (penyedia sudah menjawab), tidak dicatat di riwayat.
        $this->penyusun([['baris' => [['uraian' => '', 'mulai' => 3]]]]);
        $sisa = BatasAi::sisa(self::LEADING);
        $jumlahRiwayat = count(app(RiwayatSaranAiRepository::class)->semua());
        $this->assertSame('AI tidak memberi usulan yang bisa dipakai. Coba lagi atau ubah instruksi.', $this->gagal(fn () => SaranJadwalAi::minta($this->kegiatan(), self::LEADING, 'supervisi')));
        $this->assertSame([$sisa - 1, $jumlahRiwayat], [BatasAi::sisa(self::LEADING), count(app(RiwayatSaranAiRepository::class)->semua())]);

        $this->aturAi(['simulasi_gagal' => ['jenis' => 'waktu_habis', 'cakupan' => 'semua']]);
        $admin = ['id' => 2, 'role' => 'admin', 'leadings' => []];
        $this->assertStringEndsWith('(simulasi kegagalan dari Pengaturan AI)', $this->gagal(fn () => SaranJadwalAi::minta($this->kegiatan(), $admin, 'supervisi')));
        $this->assertStringEndsNotWith('(simulasi kegagalan dari Pengaturan AI)', $this->gagal(fn () => SaranJadwalAi::minta($this->kegiatan(), self::LEADING, 'supervisi')));
        $this->aturAi(['simulasi_gagal' => null]);

        $this->aturAi(['batas_per_user_per_hari' => 1]);
        $this->assertSame(BatasAi::pesanHabis(self::LEADING), $this->gagal(fn () => SaranJadwalAi::minta($this->kegiatan(), self::LEADING, 'supervisi')));

        $prompt = app(PromptAiRepository::class)->ambil();
        app(PromptAiRepository::class)->simpan(['gaya_bahasa' => $prompt['gaya_bahasa'], 'bagian' => ['jadwal' => ['aktif' => false]] + $prompt['bagian']]);
        $this->assertSame('Usulan jadwal AI dinonaktifkan admin di Pengaturan AI.', $this->gagal(fn () => SaranJadwalAi::minta($this->kegiatan(), self::LEADING, 'supervisi')));
    }
}
