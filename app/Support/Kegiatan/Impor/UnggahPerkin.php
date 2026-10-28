<?php

namespace App\Support\Kegiatan\Impor;

use App\Contracts\ImporKegiatanRepository;
use App\Contracts\KegiatanRepository;
use App\Livewire\Impor\ImporBertahap;
use App\Support\Format;
use App\Support\Impor\GagalBacaImpor;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\JadwalPelaksanaan;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\MasterKode;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Unggah & parsing berkas cascade Perkin (PRD §19 F22) — dipakai tab Perkin (`Kegiatan\ImporPerkin::periksaBerkas`, lewat
 * `pratinjau()`) dan endpoint `kegiatan.impor_perkin.unggah` (`POST /kegiatan/impor-perkin`, multipart `berkas`) /
 * `kegiatan.impor_perkin.data` (`GET /kegiatan/impor-perkin/{impor}`):
 * - `pratinjau($path, $namaAsli, $pengguna)`: PembacaPerkin::baca (sheet, kolom) → PemeriksaPerkin::periksa terhadap nama
 *   kegiatan tahun aktif & leading akun (bawaan bila hanya satu) → baris {baris, perkin (kode & uraian master, asal,
 *   dikosongkan, leading, pagu, bulan), tampil, status, pesan, peringatan, data}; gagal baca → GagalBacaImpor;
 * - `unggah($berkas, $pengguna)`: tahun aktif terbuka (KunciKegiatan → AuthorizationException), berkas xlsx/xls/csv ≤
 *   ImporBertahap::MAKS_KB (galat `berkas`, juga untuk berkas yang tidak terbaca), lalu impor + baris draf dicatat di
 *   ImporKegiatanRepository (jenis perkin, status berjalan) → {impor, lembar, kolom_hilang, baris, cascade};
 * - `data($id, $pengguna)`: impor + baris drafnya (pengimpor atau admin; lain → AuthorizationException, tidak ada →
 *   RuntimeException).
 */
final class UnggahPerkin
{
    /**
     * @param  array{id: int, role: string, leadings?: list<string>}  $pengguna
     * @return array{lembar: string, kolom_hilang: list<string>, baris: list<array<string, mixed>>}
     *
     * @throws GagalBacaImpor
     */
    public static function pratinjau(string $path, string $namaAsli, array $pengguna): array
    {
        $leadings = MasterKode::leadingsUntuk($pengguna);
        $tahun = StubData::tahunAktif()['tahun'];
        $bacaan = PembacaPerkin::baca($path, $namaAsli);

        // Hanya kegiatan tahun aktif: nama yang sama di tahun lalu tetap boleh dibuat lagi untuk tahun ini.
        $namaAda = array_column(array_filter(app(KegiatanRepository::class)->semua(), fn (array $k) => TahunKegiatan::dari($k) === $tahun), 'nama_kegiatan');
        $diperiksa = PemeriksaPerkin::periksa($bacaan['baris'], $namaAda, $leadings, MasterKode::leadings(), count($leadings) === 1 ? $leadings[0]['kode'] : null);

        $uraianSk = array_column(MasterKode::kodeSk(), 'uraian', 'kode');
        $uraianIksk = array_column(MasterKode::kodeIksk(), 'uraian', 'kode');

        return [
            'lembar' => $bacaan['lembar'],
            'kolom_hilang' => array_values(array_diff_key(PembacaPerkin::LABEL, array_flip($bacaan['kolom']))),
            'baris' => array_map(fn (array $b) => [
                'baris' => $b['baris'],
                // Bentuk terurai untuk tabel pratinjau Perkin (partial tabel-perkin).
                'perkin' => [
                    'sk' => $b['kode_sk'], 'sk_uraian' => $uraianSk[$b['kode_sk'] ?? ''] ?? null, 'sk_asal' => $b['asal_sk'],
                    'iksk' => $b['kode_iksk'], 'iksk_uraian' => $uraianIksk[$b['kode_iksk'] ?? ''] ?? null, 'iksk_asal' => $b['asal_iksk'],
                    'dikosongkan' => $b['dikosongkan'],
                    'leading' => $b['leading'], 'leading_nama' => MasterKode::leading($b['leading'])['nama_unit'] ?? null,
                    'pagu' => array_column($b['sumber_dana'], 'pagu', 'sumber'),
                    'bulan' => JadwalPelaksanaan::teksBulan($b['bulan'], $tahun),
                ],
                'tampil' => [
                    'nama' => $b['nama'] ?: '—',
                    'kode' => implode(' · ', array_filter([$b['kode_sk'], $b['kode_iksk']])) ?: '—',
                    // Kode saja: tabel pratinjau Perkin sudah lebar (kode SK · IKSK).
                    'leading' => $b['leading'] ?? '—',
                    'pagu' => $b['sumber_dana'] === [] ? '—' : implode(' · ', array_map(fn ($s) => $s['sumber'].' '.Format::rupiah($s['pagu']), $b['sumber_dana'])),
                    'bulan' => JadwalPelaksanaan::teksBulan($b['bulan'], $tahun) ?? '—',
                ],
                'status' => $b['status'],
                'pesan' => $b['pesan'],
                'peringatan' => $b['peringatan'],
                'data' => PemeriksaPerkin::dataKegiatan($b),
            ], $diperiksa),
        ];
    }

    /**
     * @param  array{id: int, name: string, role: string, leadings?: list<string>}  $pengguna
     * @return array{impor: array<string, mixed>, lembar: string, kolom_hilang: list<string>, baris: list<array<string, mixed>>, cascade: list<array<string, mixed>>}
     *
     * @throws AuthorizationException Tahun anggaran aktif terkunci.
     * @throws ValidationException Berkunci `berkas`.
     */
    public static function unggah(mixed $berkas, array $pengguna): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        if (($terkunci = KunciKegiatan::tahunTerkunci(['tahun_anggaran' => $tahun])) !== null) {
            throw new AuthorizationException(KunciKegiatan::pesanTahun($terkunci));
        }
        Validator::make(['berkas' => $berkas], ['berkas' => ['required', 'file', 'max:'.ImporBertahap::MAKS_KB, 'extensions:xlsx,xls,csv']], [
            'berkas.required' => 'Pilih berkas cascade Perkin (.xlsx).',
            'berkas.file' => 'Pilih berkas cascade Perkin (.xlsx).',
            'berkas.max' => 'Ukuran berkas maksimal '.(ImporBertahap::MAKS_KB / 1024).' MB.',
            'berkas.extensions' => 'Pilih berkas Excel (.xlsx, .xls) atau CSV.',
        ])->validate();
        /** @var UploadedFile $berkas */
        $nama = $berkas->getClientOriginalName();

        try {
            $hasil = self::pratinjau((string) $berkas->getRealPath(), $nama, $pengguna);
        } catch (GagalBacaImpor $e) {
            throw ValidationException::withMessages(['berkas' => $e->getMessage()]);
        }

        $impor = app(ImporKegiatanRepository::class)->mulai(
            ['tahun' => $tahun, 'jenis' => 'perkin', 'nama_berkas' => $nama, 'lembar' => $hasil['lembar'], 'user_id' => $pengguna['id'] ?? null, 'pengguna' => $pengguna['name']],
            array_map(fn (array $b) => ['nomor_baris' => $b['baris'], 'status' => $b['status'], 'data' => $b['data'], 'pesan' => $b['pesan'], 'peringatan' => $b['peringatan']], $hasil['baris']),
        );

        return ['impor' => $impor] + $hasil + [
            'cascade' => PemeriksaPerkin::cascade(array_map(fn (array $b) => ['status' => $b['status']] + $b['data'], $hasil['baris'])),
        ];
    }

    /**
     * @param  array{id: int, role: string}  $pengguna
     * @return array{impor: array<string, mixed>, baris: list<array<string, mixed>>}
     *
     * @throws RuntimeException Impor tidak ditemukan.
     * @throws AuthorizationException Bukan pengimpor atau admin.
     */
    public static function data(string $id, array $pengguna): array
    {
        $repo = app(ImporKegiatanRepository::class);
        $impor = $repo->temukan($id) ?? throw new RuntimeException('Impor tidak ditemukan.');
        if ($pengguna['role'] !== 'admin' && $impor['user_id'] !== $pengguna['id']) {
            throw new AuthorizationException(AksesKegiatan::PESAN_TOLAK);
        }

        return ['impor' => $impor, 'baris' => $repo->baris($id)];
    }
}
