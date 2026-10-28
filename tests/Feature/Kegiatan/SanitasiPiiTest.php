<?php

namespace Tests\Feature\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\RiwayatSaranAiRepository;
use App\Livewire\Kegiatan\KalimatAi;
use App\Support\Kegiatan\PayloadAi;
use App\Support\Kegiatan\SanitasiPii;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SanitasiPiiTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function kasus(): array
    {
        return [
            'surel' => ['Hubungi ahmad.fauzi@example.sch.id sebelum rapat.', 'Hubungi [surel disembunyikan] sebelum rapat.'],
            'telepon seluler' => ['WA panitia 0812-3456-7890 atau 081234567890.', 'WA panitia [nomor telepon disembunyikan] atau [nomor telepon disembunyikan].'],
            'telepon kode negara' => ['Narasumber: +62 812 3456 7890.', 'Narasumber: [nomor telepon disembunyikan].'],
            'telepon kantor' => ['Kantor (0341) 551752.', 'Kantor [nomor telepon disembunyikan].'],
            'NIK' => ['NIK peserta 3573011201900001.', 'NIK peserta [NIK disembunyikan].'],
            'rekening' => ['Transfer ke rekening BRI 0123-01-000123-50-1; no. rek: 1234567890.', 'Transfer ke [nomor rekening disembunyikan]; [nomor rekening disembunyikan].'],
        ];
    }

    #[DataProvider('kasus')]
    public function test_data_pribadi_disembunyikan(string $teks, string $harapan): void
    {
        $this->assertSame($harapan, SanitasiPii::bersihkan($teks));
        $this->assertSame($harapan, SanitasiPii::bersihkan($harapan), 'Idempoten.');
    }

    public function test_teks_biasa_tidak_berubah(): void
    {
        $teks = 'Pagu Rp6.000.000, RKTM 6.17.12, IKSK 10.6, 54 guru, 14 Juli 2026 (2026-07-14), rekap kegiatan 2026, rekomendasi 1234567, tahun 2026 ke 2027, kode akun 521211.';

        $this->assertSame($teks, SanitasiPii::bersihkan($teks));
        $this->assertSame([], SanitasiPii::jenis($teks));
        $this->assertSame(['surel', 'telepon'], SanitasiPii::jenis('ahmad@example.com, 08123456789'));
    }

    public function test_nama_panitia_dan_akun_disembunyikan_dari_muatan_ai(): void
    {
        $repo = app(KegiatanRepository::class);
        $kegiatan = $repo->temukan(1);
        $panitia = $kegiatan['panitia'][0]['nama'];
        $inti = trim(explode(',', $panitia)[0]);

        $teks = "Koordinasi dengan {$inti} dan Muslimin, M.Pd. (WA 081234567890, {$panitia}).";
        $this->assertSame('Koordinasi dengan [nama disembunyikan] dan [nama disembunyikan] (WA [nomor telepon disembunyikan], [nama disembunyikan]).', PayloadAi::bersihkan($teks, $kegiatan));

        $repo->simpan(['konteks' => "Narasumber {$inti}, kontak ahmad@example.com."] + $kegiatan, 1);
        Livewire::test(KalimatAi::class, ['kegiatan' => 1])->call('mintaSaran', 'pelaksanaan');
        $prompt = app(RiwayatSaranAiRepository::class)->semua()[0]['prompt'];
        $this->assertStringNotContainsString($inti, $prompt);
        $this->assertStringNotContainsString('ahmad@example.com', $prompt);
        $this->assertStringContainsString('Narasumber [nama disembunyikan], kontak [surel disembunyikan].', $prompt);
    }
}
