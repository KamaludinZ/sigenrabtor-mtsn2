<?php

namespace App\Support\Stub;

use App\Contracts\PenyusunSaranKalimat;
use App\Support\Kegiatan\KalimatKak;
use App\Support\Kegiatan\PermintaanSaranKalimat;

/**
 * Saran kalimat tiruan untuk tombol "Buat dengan AI" di Kalimat KAK (PRD F23) selama penyedia AI belum tersambung: tiga
 * versi per bagian yang disusun dari nama kegiatan dan bahan bagian itu (sub kegiatan RKTM, output, peserta, IKSK);
 * dengan instruksi perbaikan, versi disusun dari teks yang diperbaiki (perbaiki()).
 * Tidak pernah menambah angka, kode, atau nama pejabat. Backend menggantinya dengan driver AI dalam bentuk array sama.
 */
class StubSaranKalimat implements PenyusunSaranKalimat
{
    public function sarankan(PermintaanSaranKalimat $permintaan): array
    {
        // Minta perbaikan: versi baru disusun dari teks yang sedang diperbaiki sesuai instruksi.
        if ($permintaan->instruksi !== '' && $permintaan->teks !== '') {
            return self::perbaiki($permintaan->bagian, $permintaan->teks, $permintaan->instruksi);
        }

        $nama = KalimatKak::tanpaAwalanKegiatan($permintaan->namaKegiatan);
        $bahan = $permintaan->bahanBagian;
        $sub = self::setelahKode($bahan['Kode RKTM & sub kegiatan'] ?? null);
        $output = self::kecil($bahan['Output RKTM'] ?? null);
        $iksk = self::kecil(self::setelahKode($bahan['IKSK'] ?? null));
        $peserta = $bahan['Peserta'] ?? null;

        return match ($permintaan->bagian) {
            'nama_cover' => self::namaCover($nama),
            'latar_belakang' => [
                ['judul' => 'Versi 1', 'ciri' => 'Formal', 'teks' => "Kegiatan {$nama} diselenggarakan sebagai bagian dari ".($sub ? "program {$sub} dalam " : '').'rencana kerja tahunan madrasah. Kegiatan ini diperlukan agar program tersebut terlaksana secara terencana, terukur, dan memberi manfaat nyata bagi warga madrasah.'],
                ['judul' => 'Versi 2', 'ciri' => 'Ringkas', 'teks' => "Kegiatan {$nama} menjadi kebutuhan madrasah untuk menjawab tantangan peningkatan mutu layanan pendidikan secara berkelanjutan."],
                ['judul' => 'Versi 3', 'ciri' => 'Menekankan capaian kinerja', 'teks' => "Peningkatan mutu madrasah menuntut setiap program dilaksanakan secara sistematis dan akuntabel. Oleh karena itu, kegiatan {$nama} dilaksanakan sebagai langkah nyata mendukung ".($iksk ?: 'capaian indikator kinerja madrasah').'.'],
            ],
            'tujuan' => [
                ['judul' => 'Versi 1', 'ciri' => 'Spesifik', 'teks' => 'menghasilkan '.($output ?: 'luaran kegiatan').' yang tersusun lengkap, tepat waktu, dan sesuai kebutuhan madrasah.'],
                ['judul' => 'Versi 2', 'ciri' => 'Peningkatan mutu', 'teks' => 'meningkatkan mutu layanan pendidikan madrasah melalui pelaksanaan kegiatan yang terencana, partisipatif, dan akuntabel.'],
                ['judul' => 'Versi 3', 'ciri' => 'Ringkas', 'teks' => 'memastikan '.($output ?: 'program kegiatan').' terlaksana dengan baik dan berdampak bagi warga madrasah.'],
            ],
            'sasaran' => [
                ['judul' => 'Versi 1', 'ciri' => 'Terukur', 'teks' => ($peserta ? "{$peserta} yang" : 'warga madrasah yang').' memahami dan menerapkan hasil kegiatan dalam tugas sehari-hari.'],
                ['judul' => 'Versi 2', 'ciri' => 'Berbasis hasil', 'teks' => 'luaran kegiatan yang lengkap, dapat dipertanggungjawabkan, dan siap dimanfaatkan madrasah.'],
                ['judul' => 'Versi 3', 'ciri' => 'Ringkas', 'teks' => 'pelaksanaan kegiatan yang tertib, efektif, dan tepat sasaran.'],
            ],
            // Narasumber hanya disebut bila catatan konteks menyebutnya (arahan KetersediaanKonteks: jangan mengarang).
            'pelaksanaan' => in_array('narasumber', $permintaan->aspekKurang, true) ? [
                ['judul' => 'Versi 1', 'ciri' => 'Tatap muka', 'teks' => 'kegiatan dilaksanakan secara tatap muka melalui pemaparan materi, diskusi, dan praktik.'],
                ['judul' => 'Versi 2', 'ciri' => 'Berurutan', 'teks' => 'kegiatan diawali pembukaan dan pemaparan materi, dilanjutkan kerja kelompok, lalu ditutup dengan presentasi hasil dan evaluasi.'],
                ['judul' => 'Versi 3', 'ciri' => 'Pendampingan', 'teks' => 'kegiatan berlangsung dengan pendampingan panitia hingga seluruh luaran kegiatan tersusun.'],
            ] : [
                ['judul' => 'Versi 1', 'ciri' => 'Tatap muka', 'teks' => 'kegiatan dilaksanakan secara tatap muka melalui pemaparan materi, diskusi, dan praktik bersama narasumber.'],
                ['judul' => 'Versi 2', 'ciri' => 'Berurutan', 'teks' => 'kegiatan diawali pembukaan dan pemaparan materi, dilanjutkan kerja kelompok, lalu ditutup dengan presentasi hasil dan evaluasi.'],
                ['judul' => 'Versi 3', 'ciri' => 'Pendampingan', 'teks' => 'kegiatan berlangsung dengan pendampingan panitia dan narasumber hingga seluruh luaran kegiatan tersusun.'],
            ],
            default => [],
        };
    }

    /**
     * Perbaikan tiruan: "lebih singkat/ringkas" → kalimat pertama & klausa pertama, "tambahkan X" → dua cara menyisipkan
     * X, "formal/baku" → kalimat dirapikan; instruksi lain → teks dirapikan apa adanya.
     *
     * @return list<array{judul: string, ciri: string, teks: string}>
     */
    private static function perbaiki(string $bagian, string $teks, string $instruksi): array
    {
        $cover = $bagian === 'nama_cover';
        $akhir = fn (string $t) => $cover ? mb_strtoupper(rtrim($t, '. ')) : rtrim($t, '. ').'.';
        $dasar = rtrim(trim(preg_replace('/\s+/u', ' ', $teks)), '. ');
        $i = mb_strtolower($instruksi);
        $versi = [];

        if (preg_match('/singkat|ringkas|pendek/u', $i)) {
            $pertama = rtrim(preg_split('/(?<=[.!?])\s+/u', $dasar)[0], '. ');
            $klausa = rtrim(preg_split('/,\s+/u', $pertama)[0], '. ');
            $versi[] = ['ciri' => 'Lebih singkat', 'teks' => $akhir($pertama)];
            if ($klausa !== $pertama) {
                $versi[] = ['ciri' => 'Paling ringkas', 'teks' => $akhir($klausa)];
            }
        }
        if (preg_match('/tambah(?:kan)?\s+(.+)/u', $instruksi, $m)) {
            $tambahan = rtrim(trim($m[1]), '. ');
            $versi[] = ['ciri' => 'Menambahkan '.$tambahan, 'teks' => $cover ? $akhir("{$dasar} berbasis {$tambahan}") : $akhir("{$dasar}, dengan menjunjung nilai {$tambahan}")];
            if (! $cover) {
                $versi[] = ['ciri' => 'Kalimat tambahan', 'teks' => $akhir("{$dasar}. Kegiatan ini juga menguatkan {$tambahan} di lingkungan madrasah")];
            }
        }
        if (preg_match('/formal|baku/u', $i)) {
            $versi[] = ['ciri' => 'Lebih formal', 'teks' => $akhir(preg_replace(['/\bkita\b/u', '/\bnggak\b/u', '/\bbikin\b/u'], ['madrasah', 'tidak', 'membuat'], $dasar))];
        }
        if ($versi === []) {
            $versi[] = ['ciri' => 'Mengikuti instruksi', 'teks' => $akhir($dasar)];
        }

        return array_values(array_map(fn (array $v, int $n) => ['judul' => 'Versi '.($n + 1)] + $v, array_slice($versi, 0, 3), array_keys(array_slice($versi, 0, 3))));
    }

    /** @return list<array{judul: string, ciri: string, teks: string}> */
    private static function namaCover(string $nama): array
    {
        $baku = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $nama)));
        $kata = explode(' ', $baku);
        $versi = [
            ['judul' => 'Versi 1', 'ciri' => 'Baku', 'teks' => $baku],
            ['judul' => 'Versi 2', 'ciri' => 'Dengan nama madrasah', 'teks' => $baku.' DI '.mb_strtoupper((string) StubData::madrasah()['nama_singkat'])],
        ];
        // Ringkas: potong di kata sambung pertama sesudah tiga kata (mis. "… MADRASAH DAN PERANGKAT …"), selain itu enam kata.
        if (count($kata) > 6) {
            $sambung = ['DAN', 'DI', 'UNTUK', 'SERTA', 'KE', 'DARI', 'MELALUI', 'DALAM'];
            $potong = collect($kata)->search(fn (string $k, int $i) => $i >= 3 && in_array($k, $sambung, true));
            $ringkas = array_slice($kata, 0, $potong === false ? 6 : $potong);
            while ($ringkas !== [] && in_array(end($ringkas), $sambung, true)) {
                array_pop($ringkas);
            }
            $versi[] = ['judul' => 'Versi 3', 'ciri' => 'Ringkas', 'teks' => implode(' ', $ringkas)];
        }

        return $versi;
    }

    /** "6.17.12 · Kegiatan Workshop …" → "Kegiatan Workshop …". */
    private static function setelahKode(?string $nilai): ?string
    {
        return $nilai === null ? null : (trim(str_contains($nilai, ' · ') ? substr($nilai, strpos($nilai, ' · ') + 4) : $nilai) ?: null);
    }

    private static function kecil(?string $teks): ?string
    {
        return $teks === null ? null : mb_strtolower(mb_substr($teks, 0, 1)).mb_substr($teks, 1);
    }
}
