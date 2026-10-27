<?php

namespace App\Support\Stub;

use App\Contracts\DasarHukumRepository;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Dasar hukum & latar belakang baku tiruan di berkas JSON (disk "local"), berkunci tahun. Saat berkas belum ada, contoh
 * TA 2026 (4 butir, tahun aktif) dan TA 2025 (3 butir, tahun terkunci) disiapkan agar tampilan per tahun bisa dicoba;
 * tahun lain belum diatur.
 */
class StubDasarHukumRepository implements DasarHukumRepository
{
    public function untukTahun(int $tahun): array
    {
        $tersimpan = $this->baca()[(string) $tahun] ?? null;
        $bawaan = self::bawaan();

        if (! is_array($tersimpan)) {
            return ['dasar_hukum' => [], ...$bawaan, 'diatur' => false, 'diperbarui_pada' => null];
        }

        $butir = [];
        foreach (is_array($tersimpan['dasar_hukum'] ?? null) ? $tersimpan['dasar_hukum'] : [] as $i => $b) {
            if (is_array($b) && filled($b['teks'] ?? null)) {
                $butir[] = ['id' => filled($b['id'] ?? null) ? (string) $b['id'] : 'butir-'.($i + 1), 'teks' => (string) $b['teks']];
            }
        }
        $teks = fn (string $kunci) => filled($tersimpan[$kunci] ?? null) ? (string) $tersimpan[$kunci] : $bawaan[$kunci];

        return [
            'dasar_hukum' => $butir,
            'latar_belakang' => $teks('latar_belakang'),
            'kalimat_penutup' => $teks('kalimat_penutup'),
            'diatur' => true,
            'diperbarui_pada' => $tersimpan['diperbarui_pada'] ?? null,
        ];
    }

    public function simpan(int $tahun, array $data): void
    {
        $semua = $this->baca();
        $semua[(string) $tahun] = [
            'dasar_hukum' => array_map(fn (array $b) => [
                'id' => filled($b['id'] ?? null) ? (string) $b['id'] : (string) Str::uuid(),
                'teks' => (string) $b['teks'],
            ], array_values($data['dasar_hukum'])),
            'latar_belakang' => (string) $data['latar_belakang'],
            'kalimat_penutup' => (string) $data['kalimat_penutup'],
            // Mikrodetik: dua simpan dalam satu detik tetap berbeda versinya.
            'diperbarui_pada' => now()->format('Y-m-d\\TH:i:s.uP'),
        ];
        if (! $this->disk()->put($this->berkas(), json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            throw new RuntimeException('Data dasar hukum gagal ditulis.');
        }
    }

    public function teksBawaan(): array
    {
        return self::bawaan();
    }

    /**
     * Paragraf latar belakang baku (visi madrasah) & kalimat penutup bawaan template KAK madrasah.
     *
     * @return array{latar_belakang: string, kalimat_penutup: string}
     */
    public static function bawaan(): array
    {
        return [
            'latar_belakang' => 'Madrasah Tsanawiyah Negeri 2 Kota Malang, sebagai lembaga pendidikan Islam, berkomitmen memberikan kontribusi yang besar bagi masyarakat '
                .'sebagai penerus perjuangan dalam rangka syiar agama Islam. Dengan memperhatikan kebutuhan tersebut, MTsN 2 Kota Malang terus berusaha '
                .'memberikan layanan terbaik kepada peserta didik, masyarakat, dan pengguna layanan lainnya sesuai dengan visi madrasah. Peningkatan '
                .'pelayanan dan kualitas pendidikan perlu terus diupayakan melalui pembinaan dan evaluasi yang berkesinambungan, sehingga penyelenggaraan '
                .'pendidikan pada Tahun Anggaran {tahun} memerlukan kegiatan-kegiatan yang selaras dengan situasi dan kondisi madrasah.',
            'kalimat_penutup' => 'Salah satu kegiatan yang relevan dan mendukung program Perjanjian Kinerja (Perkin) Kepala Madrasah adalah kegiatan {nama_kegiatan}.',
        ];
    }

    /**
     * Contoh TA 2026 & 2025 (butir dasar hukum berbeda, tanpa tanda baca akhir; tahun di butir SK RKTM lewat penanda, TA 2025
     * juga nomor & tanggal SK-nya).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function contoh(): array
    {
        $uu = 'Undang-Undang Republik Indonesia Nomor 20 Tahun 2003 tentang Sistem Pendidikan Nasional';
        $pma90 = 'Peraturan Menteri Agama Nomor 90 Tahun 2013 tentang Penyelenggaraan Pendidikan Madrasah';
        $rktm = 'Surat Keputusan Kepala MTsN 2 Kota Malang tentang Rencana Kerja Tahunan Madrasah (RKTM) MTsN 2 Kota Malang Tahun {tahun}';

        return [
            '2026' => self::bawaan() + [
                'dasar_hukum' => [
                    ['id' => 'dh-2026-1', 'teks' => $uu],
                    ['id' => 'dh-2026-2', 'teks' => 'Peraturan Menteri Agama Nomor 10 Tahun 2010 tentang Organisasi dan Tata Kerja Kementerian Agama'],
                    ['id' => 'dh-2026-3', 'teks' => $pma90],
                    ['id' => 'dh-2026-4', 'teks' => $rktm],
                ],
                'diperbarui_pada' => '2026-01-05T09:00:00+07:00',
            ],
            '2025' => self::bawaan() + [
                'dasar_hukum' => [
                    ['id' => 'dh-2025-1', 'teks' => $uu],
                    ['id' => 'dh-2025-2', 'teks' => $pma90],
                    ['id' => 'dh-2025-3', 'teks' => 'Surat Keputusan Kepala MTsN 2 Kota Malang Nomor {no_sk_rktm} tanggal {tanggal_sk_rktm} tentang Rencana Kerja Tahunan Madrasah (RKTM) MTsN 2 Kota Malang Tahun {tahun}'],
                ],
                'diperbarui_pada' => '2025-01-06T09:00:00+07:00',
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function baca(): array
    {
        if (! $this->disk()->exists($this->berkas())) {
            return self::contoh();
        }

        $isi = json_decode((string) $this->disk()->get($this->berkas()), true);

        return is_array($isi) ? $isi : [];
    }

    private function berkas(): string
    {
        return config('sigenrabtor.stub.dasar_hukum_file');
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
