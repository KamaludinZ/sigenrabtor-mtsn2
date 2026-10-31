<?php

namespace App\Support\Akun;

use App\Contracts\ArsipFinalRepository;
use App\Contracts\DataPaguRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Dokumen\ChecklistIsian;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\AlurStatus;
use App\Support\Kegiatan\ArsipFinal;
use App\Support\Kegiatan\PerbaikanKegiatan;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Pengaturan\BagianPengaturan;
use App\Support\Pengaturan\KesiapanPengaturan;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Support\Facades\Route;

/**
 * Panduan langkah di Dashboard (PRD alur kerja inti):
 * - `admin()` — urutan persiapan tahun anggaran aktif yang harus dilengkapi admin sebelum leading mengisi kegiatan
 *   (`LANGKAH_ADMIN`; status siap dari KesiapanPengaturan + Data Pagu, wajib/opsional, tautan ke tempat mengaturnya,
 *   `berikutnya` = langkah wajib pertama yang belum siap, lalu opsional);
 * - `leading($pengguna, $fokus)` — `TAHAP` alur satu kegiatan dari dibuat sampai final, dengan apa yang dilakukan
 *   (`petunjuk`), kapan selesai, kekurangan (ChecklistIsian: identitas, pelaksanaan, kode, anggaran, matrik), dan tautan;
 *   status tiap tahap `STATUS` selesai|sekarang|perbaiki|belum untuk kegiatan fokus (pilihan pengguna, atau yang paling
 *   perlu perhatian: Dikembalikan → Draf/Disetujui terbaru → lainnya), `sebaran` = jumlah kegiatan per tahap saat ini.
 *   Tanpa kegiatan: tahap 1 menjadi langkah sekarang dengan tautan Buat kegiatan / Data Pagu.
 */
final class PanduanLangkah
{
    public const STATUS = [
        'selesai' => 'Selesai',
        'sekarang' => 'Langkah sekarang',
        'perbaiki' => 'Perlu perbaikan',
        'belum' => 'Belum',
    ];

    /** Tahapan satu kegiatan → [judul, ringkas, petunjuk, label tautan]. Urutan = urutan kerja. */
    public const TAHAP = [
        'identitas' => ['Isi identitas kegiatan', 'Nama, tujuan, sasaran, serta tanggal dokumen & pelaporan.', [
            'Buat kegiatan baru — atau ambil dari Data Pagu agar nama, leading, sumber dana, dan pagu langsung terisi.',
            'Isi tujuan dan sasaran; kalimatnya bisa dibantu AI di tab Kalimat KAK.',
            'Tekan Simpan Draf. Selama berstatus Draf semua isian masih bisa diubah.',
        ], 'Isi identitas'],
        'pelaksanaan' => ['Atur pelaksanaan', 'Tanggal mulai, lama, bulan, tempat, dan peserta kegiatan.', [
            'Di Data Kegiatan bagian Pelaksanaan, pilih tanggal mulai dan lama hari — tanggal selesai & bulan dihitung otomatis.',
            'Isi tempat, jumlah dan jenis peserta; saran dari kegiatan sebelumnya bisa dipilih.',
        ], 'Atur pelaksanaan'],
        'kode' => ['Pilih kode & klasifikasi', 'Kode RKTM, SK, IKSK, EDM, dan 5 Budaya Mutu dari master tahun ini.', [
            'Buka tab Kode & Klasifikasi, pilih RKTM lalu SK — IKSK yang tersedia mengikuti SK terpilih.',
            'Lengkapi EDM dan 5 Budaya Mutu, lalu Simpan.',
        ], 'Pilih kode'],
        'anggaran' => ['Susun sumber dana & RAB', 'Pagu per sumber dana, rincian belanja, dan termin bila dibayar bertahap.', [
            'Centang sumber dana (DIPA/Komite) dan isi pagunya di Data Kegiatan.',
            'Di tab RAB tambahkan rincian (volume × harga) atau impor dari Excel; jaga total tidak melebihi pagu.',
            'Bila dibayar bertahap, atur Termin pembayaran sampai jumlahnya sama dengan total RAB.',
        ], 'Susun RAB'],
        'matrik' => ['Isi matrik tahapan & jadwal', 'Bulan tiap tahapan (persiapan, pelaksanaan, pelaporan) dan jadwal kegiatan.', [
            'Di tab Matrik & Jadwal klik/seret bulan pada tiap tahapan, atau pakai Isi otomatis dari bulan pelaksanaan.',
            'Lengkapi baris jadwal (bisa diusulkan AI), lalu Simpan.',
        ], 'Isi matrik'],
        'ajukan' => ['Periksa dokumen lalu ajukan', 'Rapikan kalimat KAK, cek pratinjau & kelengkapan, lalu ajukan ke admin.', [
            'Rapikan latar belakang, tujuan, dan pelaksanaan di tab Kalimat KAK (bisa Buat dengan AI).',
            'Buka Pratinjau: pastikan checklist isian lengkap dan tidak ada isian wajib yang kosong.',
            'Di Data Kegiatan panel Status, tekan Ajukan — kegiatan terkunci selama diperiksa.',
        ], 'Periksa & ajukan'],
        'pemeriksaan' => ['Pemeriksaan admin', 'Admin memeriksa lalu menyetujui atau mengembalikan dengan catatan.', [
            'Tunggu keputusan admin — notifikasi muncul di lonceng bilah atas.',
            'Bila dikembalikan, baca catatannya, perbaiki bagian yang diminta, lalu Ajukan ulang.',
            'Perlu mengubah sesuatu sebelum diputuskan? Tarik pengajuan dari panel Status.',
        ], 'Lihat status'],
        'final' => ['Unggah arsip final', 'Cetak, tandatangani, unggah PDF arsip final — kegiatan menjadi Final.', [
            'Cetak dokumen yang sudah disetujui (Cetak & Unduh), mintakan tanda tangan pejabat.',
            'Pindai lalu unggah PDF bertanda tangan per sumber dana di Data Kegiatan bagian Arsip final.',
            'Setelah semua arsip lengkap kegiatan otomatis Final dan masuk Buku Dokumen.',
        ], 'Unggah arsip'],
    ];

    /** Langkah persiapan admin → [judul, alasan, rute, parameter, label tautan] (kunci = kunci KesiapanPengaturan + data_pagu). */
    public const LANGKAH_ADMIN = [
        'tahun' => ['Aktifkan tahun anggaran', 'Tahun aktif menentukan tahun di semua dokumen, master kode, dan pejabat.', 'pengaturan.tahun.index', [], 'Atur tahun'],
        'profil' => ['Lengkapi profil & kop madrasah', 'Alamat, telepon, kota, dan logo tercetak di kop setiap dokumen.', 'pengaturan.madrasah.index', ['tab' => 'profil'], 'Isi profil'],
        'pejabat' => ['Isi pejabat penanda tangan', 'Kepala Madrasah (dengan NIP) dan Ketua Komite tercetak di lembar pengesahan.', 'pengaturan.madrasah.index', ['tab' => 'pejabat'], 'Isi pejabat'],
        'leading' => ['Siapkan leading & akun', 'Setiap leading butuh akun aktif yang tertaut agar bisa membuat kegiatan.', 'pengaturan.leading.index', [], 'Kelola leading'],
        'master' => ['Unggah master kode', 'Kode SK, IKSK, RKTM, EDM, dan 5 Budaya Mutu tahun ini menjadi pilihan kegiatan.', 'master.index', [], 'Unggah master'],
        'dasar_hukum' => ['Atur dasar hukum & latar belakang baku', 'Tercetak di bagian Dasar Hukum dan Latar Belakang setiap KAK/TOR.', 'pengaturan.madrasah.dasar_hukum', [], 'Atur dasar hukum'],
        'sk_rktm' => ['Isi nomor & tanggal SK RKTM/RKAM', 'Dirujuk butir dasar hukum; selama kosong dokumen bercap DRAF.', 'pengaturan.madrasah.dasar_hukum', [], 'Isi SK'],
        'data_pagu' => ['Isi Data Pagu kegiatan', 'Leading cukup memilih nama kegiatan — pagu DIPA/Komite langsung terisi.', 'pagu.index', [], 'Isi Data Pagu'],
        'cover' => ['Unggah template cover', 'Tanpa template, cover memakai tata letak baku.', 'pengaturan.template.index', ['tab' => 'cover'], 'Atur cover'],
        'kak' => ['Unggah template Word KAK/TOR', 'Tanpa master .docx, unduhan Word memakai susunan baku.', 'pengaturan.template.master.index', [], 'Template master'],
        'ai' => ['Pilih penyedia AI', 'Tanpa penyedia, tombol Buat dengan AI memakai usulan tiruan.', 'pengaturan.ai.index', [], 'Atur AI'],
    ];

    /**
     * @return array{tahun: int, langkah: list<array<string, mixed>>, wajib: int, wajib_siap: int, siap: int, jumlah: int, lengkap: bool, berikutnya: string|null, persen: int}
     */
    public static function admin(): array
    {
        $kesiapan = KesiapanPengaturan::tahunAktif();
        $butir = collect($kesiapan->butir)->keyBy('kunci');
        $jumlahPagu = count(app(DataPaguRepository::class)->semua($kesiapan->tahun));
        $butir->put('data_pagu', ['siap' => $jumlahPagu > 0, 'wajib' => false,
            'keterangan' => $jumlahPagu > 0 ? "{$jumlahPagu} data pagu kegiatan TA {$kesiapan->tahun}." : 'Belum ada — leading mengetik nama & pagu sendiri.']);

        $langkah = [];
        foreach (self::LANGKAH_ADMIN as $kunci => [$judul, $alasan, $rute, $param, $label]) {
            if (($b = $butir->get($kunci)) === null) {
                continue;
            }
            $langkah[] = [
                'kunci' => $kunci,
                'judul' => $judul,
                'alasan' => $alasan,
                'keterangan' => $b['keterangan'],
                'wajib' => (bool) $b['wajib'],
                'siap' => (bool) $b['siap'],
                'tautan' => Route::has($rute) ? route($rute, $param) : (BagianPengaturan::cari($b['bagian'] ?? '')['url'] ?? null),
                'label_tautan' => $label,
            ];
        }
        $berikutnya = collect($langkah)->first(fn (array $l) => $l['wajib'] && ! $l['siap'])['kunci']
            ?? collect($langkah)->first(fn (array $l) => ! $l['siap'])['kunci'] ?? null;
        foreach ($langkah as $i => $l) {
            $langkah[$i]['nomor'] = $i + 1;
            $langkah[$i]['status'] = $l['siap'] ? 'selesai' : ($l['kunci'] === $berikutnya ? 'sekarang' : 'belum');
        }
        $wajib = array_filter($langkah, fn (array $l) => $l['wajib']);
        $wajibSiap = count(array_filter($wajib, fn (array $l) => $l['siap']));
        $siap = count(array_filter($langkah, fn (array $l) => $l['siap']));

        return [
            'tahun' => $kesiapan->tahun,
            'langkah' => $langkah,
            'wajib' => count($wajib),
            'wajib_siap' => $wajibSiap,
            'siap' => $siap,
            'jumlah' => count($langkah),
            'lengkap' => $wajibSiap === count($wajib),
            'berikutnya' => $berikutnya,
            'persen' => $langkah === [] ? 100 : (int) floor($siap * 100 / count($langkah)),
        ];
    }

    /**
     * @param  array{id: int, name: string, role: string, leadings: list<string>}  $pengguna
     * @return array<string, mixed>
     */
    public static function leading(array $pengguna, ?int $fokus = null): array
    {
        $tahun = (int) StubData::tahunAktif()['tahun'];
        $kegiatan = array_values(array_filter(
            AksesKegiatan::saring(app(KegiatanRepository::class)->semua(), $pengguna),
            fn (array $k) => TahunKegiatan::dari($k) === $tahun,
        ));
        AnggaranKegiatan::muat($kegiatan);
        // Paling perlu perhatian dulu (Dikembalikan → Draf/Disetujui → Diperiksa → Final), lalu terbaru diubah.
        $alur = collect($kegiatan)->map(fn (array $k) => ['kegiatan' => $k] + self::alur($k))
            ->sortBy(fn (array $a) => [self::prioritas($a), -strtotime((string) ($a['kegiatan']['diperbarui_pada'] ?? $a['kegiatan']['dibuat_pada'] ?? 'now'))])
            ->values()->all();
        $dipilih = collect($alur)->first(fn (array $a) => (int) $a['kegiatan']['id'] === $fokus) ?? ($alur[0] ?? null);
        $sebaran = array_fill_keys(array_keys(self::TAHAP), 0) + ['selesai' => 0];
        foreach ($alur as $a) {
            $sebaran[$a['sekarang'] ?? 'selesai']++;
        }
        $bisaBuat = MasterKode::pilihanLeading($pengguna, null) !== [] && Route::has('kegiatan.create');

        return [
            'tahun' => $tahun,
            'jumlah_kegiatan' => count($kegiatan),
            'pilihan' => array_map(fn (array $a) => [
                'id' => (int) $a['kegiatan']['id'],
                'nama' => trim((string) ($a['kegiatan']['nama_kegiatan'] ?? '')) ?: 'Kegiatan tanpa nama',
                'tahap' => $a['sekarang'] === null ? 'Final' : 'Tahap '.$a['nomor'].' · '.self::TAHAP[$a['sekarang']][0],
            ], $alur),
            'fokus' => $dipilih === null ? null : [
                'id' => (int) $dipilih['kegiatan']['id'],
                'nama' => trim((string) ($dipilih['kegiatan']['nama_kegiatan'] ?? '')) ?: 'Kegiatan tanpa nama',
                'status' => (string) ($dipilih['kegiatan']['status'] ?? 'draf'),
                'sekarang' => $dipilih['sekarang'],
                'persen' => $dipilih['persen'],
                'catatan' => $dipilih['catatan'],
            ],
            'tahap' => $dipilih['tahap'] ?? self::tanpaKegiatan($bisaBuat),
            'sebaran' => $sebaran,
            'bisa_buat' => $bisaBuat,
            'tautan_buat' => $bisaBuat ? route('kegiatan.create') : null,
            'tautan_pagu' => Route::has('pagu.index') ? route('pagu.index') : null,
        ];
    }

    /**
     * Tahapan satu kegiatan.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array{tahap: list<array<string, mixed>>, sekarang: string|null, nomor: int|null, persen: int, catatan: string|null}
     */
    public static function alur(array $kegiatan): array
    {
        $id = (int) $kegiatan['id'];
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
        $diajukan = in_array($status, [StatusKegiatan::Diperiksa, StatusKegiatan::Disetujui, StatusKegiatan::Final], true);
        $grup = collect(ChecklistIsian::untuk($kegiatan))->keyBy('kunci');
        $kurang = fn (string $kunci) => array_values(array_map(fn (array $b) => $b['label'], array_filter($grup[$kunci]['butir'] ?? [], fn (array $b) => ! $b['terisi'])));
        $catatan = $status === StatusKegiatan::Dikembalikan ? (PerbaikanKegiatan::dari($kegiatan)?->catatan ?: 'Dikembalikan admin — perbaiki lalu ajukan ulang.') : null;
        $arsipKurang = $status === StatusKegiatan::Disetujui ? ArsipFinal::kurang($kegiatan, app(ArsipFinalRepository::class)->untukKegiatan($id)) : [];
        $syarat = in_array($status, [StatusKegiatan::Draf, StatusKegiatan::Dikembalikan], true) ? array_values(AlurStatus::syaratAjukan($kegiatan)) : [];
        $tautan = [
            'identitas' => route('kegiatan.edit', $id).'#identitas',
            'pelaksanaan' => route('kegiatan.edit', $id).'#pelaksanaan',
            'kode' => route('kegiatan.kode', $id),
            'anggaran' => route('kegiatan.rab', $id),
            'matrik' => route('kegiatan.matrik', $id),
            'ajukan' => $syarat === [] ? route('kegiatan.edit', $id) : route('kegiatan.pratinjau', $id).'#checklist-isian',
            'pemeriksaan' => route('kegiatan.edit', $id),
            'final' => $status === StatusKegiatan::Final && Route::has('cetak.index') ? route('cetak.index') : route('kegiatan.edit', $id).'#arsip-final',
        ];

        $selesai = [];
        foreach (['identitas', 'pelaksanaan', 'kode', 'anggaran', 'matrik'] as $kunci) {
            $selesai[$kunci] = ['selesai' => $diajukan || $kurang($kunci) === [], 'kurang' => $diajukan ? [] : $kurang($kunci)];
        }
        $selesai['ajukan'] = ['selesai' => $diajukan, 'kurang' => $syarat, 'perbaiki' => $status === StatusKegiatan::Dikembalikan];
        $selesai['pemeriksaan'] = ['selesai' => in_array($status, [StatusKegiatan::Disetujui, StatusKegiatan::Final], true), 'kurang' => []];
        $selesai['final'] = ['selesai' => $status === StatusKegiatan::Final, 'kurang' => $arsipKurang === [] ? [] : [ArsipFinal::pesanKurang($arsipKurang)]];

        $sekarang = collect($selesai)->search(fn (array $s) => ! $s['selesai']) ?: null;
        $tahap = [];
        foreach (self::TAHAP as $kunci => [$judul, $ringkas, $petunjuk, $label]) {
            $s = $selesai[$kunci];
            $tahap[] = [
                'kunci' => $kunci,
                'nomor' => count($tahap) + 1,
                'judul' => $judul,
                'ringkas' => $ringkas,
                'petunjuk' => $petunjuk,
                'status' => match (true) {
                    $s['selesai'] => 'selesai',
                    $s['perbaiki'] ?? false => 'perbaiki',
                    $kunci === $sekarang => 'sekarang',
                    default => 'belum',
                },
                'kurang' => array_slice($s['kurang'], 0, 4),
                'kurang_lainnya' => max(0, count($s['kurang']) - 4),
                'tautan' => $tautan[$kunci],
                'label_tautan' => $kunci === 'final' && $status === StatusKegiatan::Final ? 'Cetak & unduh' : $label,
            ];
        }
        $jumlahSelesai = count(array_filter($tahap, fn (array $t) => $t['status'] === 'selesai'));

        return [
            'tahap' => $tahap,
            'sekarang' => $sekarang,
            'nomor' => $sekarang === null ? null : array_search($sekarang, array_keys(self::TAHAP), true) + 1,
            'persen' => (int) floor($jumlahSelesai * 100 / count($tahap)),
            'catatan' => $catatan,
        ];
    }

    /** Urutan kegiatan yang paling perlu perhatian lebih dulu. @param  array<string, mixed>  $alur */
    private static function prioritas(array $alur): int
    {
        return match ((string) ($alur['kegiatan']['status'] ?? 'draf')) {
            StatusKegiatan::Dikembalikan->value => 0,
            StatusKegiatan::Draf->value, StatusKegiatan::Disetujui->value => 1,
            StatusKegiatan::Diperiksa->value => 2,
            default => 3,
        };
    }

    /** @return list<array<string, mixed>> */
    private static function tanpaKegiatan(bool $bisaBuat): array
    {
        $tahap = [];
        foreach (self::TAHAP as $kunci => [$judul, $ringkas, $petunjuk, $label]) {
            $pertama = $tahap === [];
            $tahap[] = [
                'kunci' => $kunci, 'nomor' => count($tahap) + 1, 'judul' => $judul, 'ringkas' => $ringkas, 'petunjuk' => $petunjuk,
                'status' => $pertama ? 'sekarang' : 'belum', 'kurang' => [], 'kurang_lainnya' => 0,
                'tautan' => $pertama && $bisaBuat ? route('kegiatan.create') : null,
                'label_tautan' => $pertama ? 'Buat kegiatan' : $label,
            ];
        }

        return $tahap;
    }
}
