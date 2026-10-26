<?php

namespace App\Support\Pengaturan;

use App\Contracts\TahunAnggaranRepository;
use App\Contracts\TemplateCoverRepository;
use App\Support\Format;
use App\Support\MasterKode;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;

/**
 * Daftar kesiapan tahun anggaran aktif untuk admin (PRD alur kerja inti langkah 1–2): yang wajib siap sebelum leading
 * mengisi kegiatan, dan yang opsional karena dokumen punya susunan baku (template) atau usulan tiruan (AI).
 */
final readonly class KesiapanPengaturan
{
    /**
     * @param  list<array{kunci: string, bagian: string, judul: string, siap: bool, wajib: bool, keterangan: string}>  $butir
     */
    public function __construct(
        public int $tahun,
        public array $butir,
    ) {}

    public static function tahunAktif(): self
    {
        $tahun = StubData::tahunAktif()['tahun'];
        $tahunAnggaran = app(TahunAnggaranRepository::class)->aktif();
        $madrasah = StubData::madrasah();
        $pejabat = StubData::pejabat();
        $template = ['cover' => count(app(TemplateCoverRepository::class)->semua($tahun))] + StubPengaturan::templateDokumen();
        $ai = StubPengaturan::pengaturanAi();

        $profilKurang = array_values(array_filter(['alamat' => 'alamat', 'telepon' => 'telepon', 'logo_path' => 'logo'], fn ($k) => blank($madrasah[$k] ?? null), ARRAY_FILTER_USE_KEY));
        $pejabatKurang = array_values(array_filter([
            $pejabat['kepala']['nama'] && $pejabat['kepala']['nip'] ? null : 'Kepala Madrasah',
            $pejabat['komite']['nama'] ? null : 'Ketua Komite',
        ]));
        $sk = filled($tahunAnggaran['no_sk_rktm'] ?? null) && filled($tahunAnggaran['tanggal_sk_rktm'] ?? null);
        $dasarHukum = count(StubData::dasarHukum($tahun));
        $jumlahKode = fn (string $label, array $daftar) => count($daftar).' '.$label;

        $butir = [
            ['kunci' => 'tahun', 'bagian' => 'tahun', 'judul' => 'Tahun anggaran aktif', 'siap' => $tahunAnggaran !== null, 'wajib' => true,
                'keterangan' => "Tahun Anggaran {$tahun} aktif."],
            ['kunci' => 'sk_rktm', 'bagian' => 'tahun', 'judul' => 'SK RKTM/RKAM', 'siap' => $sk, 'wajib' => true,
                'keterangan' => $sk ? "Nomor {$tahunAnggaran['no_sk_rktm']}, ".Format::tanggal($tahunAnggaran['tanggal_sk_rktm']).'.' : 'Nomor & tanggal SK belum diisi — tercetak di dasar hukum KAK.'],
            ['kunci' => 'profil', 'bagian' => 'madrasah', 'judul' => 'Profil madrasah (kop)', 'siap' => $profilKurang === [], 'wajib' => true,
                'keterangan' => $profilKurang === [] ? 'Nama, alamat, telepon, kota, dan logo kop lengkap.' : ucfirst(implode(', ', $profilKurang)).' kop belum diisi.'],
            ['kunci' => 'pejabat', 'bagian' => 'madrasah', 'judul' => "Pejabat penanda tangan {$tahun}", 'siap' => $pejabatKurang === [], 'wajib' => true,
                'keterangan' => $pejabatKurang === [] ? 'Kepala Madrasah dan Ketua Komite lengkap dengan NIP.' : 'Nama & NIP '.implode(' dan ', $pejabatKurang).' belum diisi.'],
            ['kunci' => 'dasar_hukum', 'bagian' => 'madrasah', 'judul' => 'Dasar hukum & latar belakang baku', 'siap' => $dasarHukum > 0, 'wajib' => true,
                'keterangan' => "{$dasarHukum} butir dasar hukum dan paragraf latar belakang baku."],
            ['kunci' => 'leading', 'bagian' => 'leading', 'judul' => 'Leading & akun', 'siap' => MasterKode::leadings() !== [], 'wajib' => true,
                'keterangan' => count(MasterKode::leadings()).' leading · '.count(StubPengaturan::pengguna()).' akun.'],
            ['kunci' => 'master', 'bagian' => 'master', 'judul' => "Master kode {$tahun}", 'siap' => MasterKode::tersedia($tahun), 'wajib' => true,
                'keterangan' => MasterKode::tersedia($tahun)
                    ? implode(' · ', [$jumlahKode('SK', MasterKode::kodeSk($tahun)), $jumlahKode('IKSK', MasterKode::kodeIksk(null, $tahun)), $jumlahKode('RKTM', MasterKode::kodeRktm($tahun)), $jumlahKode('EDM', MasterKode::kodeEdm($tahun)), $jumlahKode('budaya mutu', MasterKode::budayaMutu($tahun))]).'.'
                    : "Master kode {$tahun} belum diunggah."],
            ['kunci' => 'cover', 'bagian' => 'template', 'judul' => 'Template cover', 'siap' => $template['cover'] > 0, 'wajib' => false,
                'keterangan' => $template['cover'] > 0 ? "{$template['cover']} desain cover." : 'Belum ada desain — cover memakai tata letak baku.'],
            ['kunci' => 'kak', 'bagian' => 'template', 'judul' => 'Template KAK/TOR', 'siap' => $template['kak'] > 0, 'wajib' => false,
                'keterangan' => $template['kak'] > 0 ? "{$template['kak']} master .docx." : 'Belum ada master .docx — dokumen memakai susunan baku.'],
            ['kunci' => 'ai', 'bagian' => 'ai', 'judul' => 'Penyedia AI', 'siap' => $ai['provider_aktif'] !== null, 'wajib' => false,
                'keterangan' => $ai['provider_aktif'] !== null ? "Aktif: {$ai['provider_aktif']}." : 'Belum dipilih — "Buat dengan AI" memakai usulan tiruan.'],
        ];

        return new self($tahun, $butir);
    }

    /** Butir wajib yang sudah siap. */
    public function jumlahWajibSiap(): int
    {
        return count(array_filter($this->butir, fn (array $b) => $b['wajib'] && $b['siap']));
    }

    public function jumlahWajib(): int
    {
        return count(array_filter($this->butir, fn (array $b) => $b['wajib']));
    }

    /**
     * Butir milik satu bagian pengaturan (kunci BagianPengaturan).
     *
     * @return list<array{kunci: string, bagian: string, judul: string, siap: bool, wajib: bool, keterangan: string}>
     */
    public function untukBagian(string $bagian): array
    {
        return array_values(array_filter($this->butir, fn (array $b) => $b['bagian'] === $bagian));
    }
}
