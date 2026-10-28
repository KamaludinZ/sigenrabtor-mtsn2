<?php

namespace App\Support\Dokumen;

use App\Contracts\HasilDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Rab\AnggaranKegiatan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Layanan pembuatan dokumen otomatis (PRD F13 "KAK/TOR, RAB, cover, … dihasilkan dari data + template", F4 "Susun
 * Otomatis"): menyusun satu dokumen kegiatan × jenis (JenisDokumen/DokumenKegiatan::JENIS) × sumber dana dari data
 * terbaru — bagian KAK/TOR (HasilKakTor), cuplikan RAB (`rab()`: judul, kepala, tabel TabelRab, mekanisme pembayaran,
 * pengesahan), nilai kotak cover — beserta cap DRAF (JenisDokumen::statusDraf, aturan StatusDraf) dan sidik isi (`sidik()`
 * dari DrafDokumen::sidik), lalu menyimpannya lewat HasilDokumenRepository (disusun ulang = ditimpa). Dipakai endpoint
 * `kegiatan.dokumen.susun` (POST) & `kegiatan.dokumen.hasil` (GET); `untukJson()` menambah label, nama berkas
 * (CetakDokumen::namaBerkas), ringkasan bagian, dan keadaan hasil tersimpan terhadap data sekarang (sesuai/berubah).
 * Melihat & menyusun mengikuti AksesKegiatan (dokumen kegiatan terkunci tetap boleh disusun untuk dicetak).
 */
final class PembuatDokumen
{
    /** Keadaan hasil tersimpan → label. */
    public const KEADAAN = [
        'sesuai' => 'Sesuai data terbaru',
        'berubah' => 'Data berubah sejak disusun — susun ulang agar dokumen mengikuti data terbaru',
    ];

    /**
     * Susun (atau susun ulang) dokumen dan simpan hasilnya.
     *
     * @param  array{id?: int|null, name: string, role?: string, leadings?: list<string>}  $pengguna
     * @return array{dokumen: array<string, mixed>, baru: bool, berubah: bool, pesan: string}
     *
     * @throws RuntimeException Kegiatan tidak ada atau hasil gagal disimpan.
     * @throws AuthorizationException Di luar cakupan akun.
     * @throws ValidationException Jenis (`jenis`) atau sumber dana (`sumber`) tidak sah.
     */
    public static function susun(int $id, string $jenis, ?string $sumber, array $pengguna): array
    {
        $kegiatan = self::kegiatan($id, $pengguna);
        self::periksa($kegiatan, $jenis, $sumber);
        $rekaman = self::rekam($kegiatan, $jenis, $sumber, $pengguna);
        if (JenisDokumen::perluSumber($jenis) && $rekaman['sumber'] === null) {
            throw ValidationException::withMessages(['sumber' => 'Pilih sumber dana kegiatan dulu — '.DokumenKegiatan::JENIS[$jenis].' disusun per sumber dana.']);
        }

        $repo = app(HasilDokumenRepository::class);
        $lama = $repo->temukan($id, $jenis, $rekaman['sumber']);
        $hasil = $repo->simpan($id, $rekaman);
        $berubah = $lama === null || $lama['sidik_isi'] !== $hasil['sidik_isi'];
        $nama = DrafDokumen::nama($jenis, $hasil['sumber']);

        return [
            'dokumen' => self::untukJson($kegiatan, $hasil, $pengguna),
            'baru' => $lama === null,
            'berubah' => $berubah,
            'pesan' => match (true) {
                $lama === null => "Dokumen {$nama} disusun dari data terbaru.",
                $berubah => "Dokumen {$nama} disusun ulang — isinya mengikuti data terbaru.",
                default => "Dokumen {$nama} sudah sesuai data terbaru.",
            },
        ];
    }

    /**
     * Hasil tersimpan satu jenis × sumber (null bila belum pernah disusun) beserta keadaannya, dan daftar semua hasil
     * kegiatan itu.
     *
     * @param  array<string, mixed>  $pengguna
     * @return array{jenis: string, sumber: string|null, dokumen: array<string, mixed>|null, semua: list<array<string, mixed>>}
     */
    public static function data(int $id, string $jenis, ?string $sumber, array $pengguna): array
    {
        $kegiatan = self::kegiatan($id, $pengguna);
        self::periksa($kegiatan, $jenis, $sumber);
        $sumber = SusunanKak::dari($kegiatan, $sumber)->sumber;
        $repo = app(HasilDokumenRepository::class);
        $hasil = $repo->temukan($id, $jenis, $sumber);

        return [
            'jenis' => $jenis,
            'sumber' => $sumber,
            'dokumen' => $hasil ? self::untukJson($kegiatan, $hasil, $pengguna) : null,
            'semua' => array_map(fn (array $h) => [
                'id' => $h['id'], 'jenis' => $h['jenis'], 'sumber' => $h['sumber'], 'nama' => DrafDokumen::nama($h['jenis'], $h['sumber']),
                'bertanda_draf' => $h['bertanda_draf'], 'disusun_pada' => $h['disusun_pada'], 'oleh' => $h['oleh'],
            ], $repo->untukKegiatan($id)),
        ];
    }

    /**
     * Rekaman hasil dari data kegiatan saat ini (bentuk HasilDokumenRepository tanpa id & waktu). Sumber null = sumber
     * dana pertama (seperti SusunanKak).
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array<string, mixed>  $pengguna
     * @return array<string, mixed>
     */
    public static function rekam(array $kegiatan, string $jenis, ?string $sumber, array $pengguna): array
    {
        $kak = SusunanKak::dari($kegiatan, $sumber);
        $rab = SusunanRab::dari($kegiatan, $sumber);
        $isi = JenisDokumen::ISI[$jenis];
        $draf = JenisDokumen::statusDraf($jenis, StatusCetak::dari($kegiatan, $kak->sumber, $kak, $rab));
        $cover = TeksCover::nilai($kegiatan, $kak->sumber);

        return [
            'jenis' => $jenis,
            'sumber' => $kak->sumber,
            'status' => (StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf)->value,
            'bertanda_draf' => $draf->draf,
            'alasan_draf' => array_column($draf->alasan, 'teks'),
            'sidik_isi' => self::sidik($jenis, $kak, $rab, $cover),
            'bagian' => in_array('kak', $isi, true) ? HasilKakTor::bagian($kak, SusunanOtomatis::dari($kak, $rab, $kegiatan, $pengguna)) : [],
            'rab' => in_array('rab', $isi, true) ? self::rab($rab) : null,
            'cover' => in_array('cover', $isi, true) ? $cover : null,
            'user_id' => $pengguna['id'] ?? null,
            'oleh' => $pengguna['name'],
        ];
    }

    /**
     * Sidik isi dokumen jenis itu (md5 sidik cover/KAK per bagian/RAB DrafDokumen::sidik).
     *
     * @param  array<string, string>  $nilaiCover
     */
    public static function sidik(string $jenis, SusunanKak $kak, SusunanRab $rab, array $nilaiCover): string
    {
        return md5((string) json_encode(DrafDokumen::sidik($jenis, $kak, $rab, $nilaiCover), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Cuplikan RAB siap cetak: judul, kepala, tabel (TabelRab: judul kolom, baris teks, total & terbilang), mekanisme
     * pembayaran, pengesahan, isian kurang.
     *
     * @return array<string, mixed>
     */
    public static function rab(SusunanRab $rab): array
    {
        $tabel = $rab->tabel();

        return [
            'judul' => $rab->judul,
            'kepala' => array_map(fn (array $b) => ['label' => $b['label'], 'nilai' => $b['nilai'], 'uraian' => $b['uraian']], $rab->kepala),
            'tabel' => [
                'kolom' => $tabel->judul(),
                'baris' => array_map(fn (array $b) => array_values($b['teks']), $tabel->baris),
                'total' => $tabel->total,
                'total_teks' => $tabel->totalTeks,
                'terbilang' => $tabel->terbilang,
            ],
            'mekanisme' => $rab->mekanisme,
            'pengesahan' => $rab->pengesahan,
            'kurang' => array_values(array_unique($rab->kurang)),
        ];
    }

    /**
     * Hasil tersimpan untuk JSON: catatan + label jenis & status, nama berkas PDF, ringkasan bagian, dan keadaan.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array<string, mixed>  $hasil
     * @param  array<string, mixed>  $pengguna
     * @return array<string, mixed>
     */
    public static function untukJson(array $kegiatan, array $hasil, array $pengguna): array
    {
        $sekarang = self::rekam($kegiatan, $hasil['jenis'], $hasil['sumber'], $pengguna);
        $kak = SusunanKak::dari($kegiatan, $hasil['sumber']);
        $cetak = StatusCetak::dari($kegiatan, $kak->sumber, $kak, SusunanRab::dari($kegiatan, $hasil['sumber']));
        $keadaan = $sekarang['sidik_isi'] === $hasil['sidik_isi'] ? 'sesuai' : 'berubah';

        return $hasil + [
            'nama' => DrafDokumen::nama($hasil['jenis'], $hasil['sumber']),
            'label_jenis' => DokumenKegiatan::JENIS[$hasil['jenis']] ?? $hasil['jenis'],
            'label_status' => StatusKegiatan::tryFrom((string) $hasil['status'])?->label(),
            'nama_berkas' => CetakDokumen::namaBerkas($cetak, JenisDokumen::ISI[$hasil['jenis']] ?? ['kak']),
            'jumlah_bagian' => count($hasil['bagian']),
            'bagian_lengkap' => count(array_filter($hasil['bagian'], fn (array $b) => $b['status'] === 'lengkap')),
            'keadaan' => ['keadaan' => $keadaan, 'label' => self::KEADAAN[$keadaan]],
            'tautan_cetak' => CetakDokumen::tautan((int) $kegiatan['id'], $hasil['sumber'], JenisDokumen::ISI[$hasil['jenis']] ?? ['kak']),
        ];
    }

    /**
     * @param  array<string, mixed>  $pengguna
     * @return array<string, mixed>
     */
    private static function kegiatan(int $id, array $pengguna): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($id) ?? throw new RuntimeException('Kegiatan tidak ditemukan.');
        if (! AksesKegiatan::bolehLihat($kegiatan, $pengguna)) {
            throw new AuthorizationException(AksesKegiatan::PESAN_TOLAK);
        }

        return $kegiatan;
    }

    /** @param  array<string, mixed>  $kegiatan */
    private static function periksa(array $kegiatan, string $jenis, ?string $sumber): void
    {
        if (! isset(DokumenKegiatan::JENIS[$jenis])) {
            throw ValidationException::withMessages(['jenis' => 'Jenis dokumen tidak dikenal.']);
        }
        if ($sumber !== null && ! array_key_exists($sumber, AnggaranKegiatan::perSumber($kegiatan))) {
            throw ValidationException::withMessages(['sumber' => "Sumber dana {$sumber} tidak dipilih untuk kegiatan ini."]);
        }
    }
}
