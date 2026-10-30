<?php

namespace App\Support\Dokumen;

use App\Contracts\HasilDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Pengaturan\KelolaProfilMadrasah;
use App\Support\Rab\AnggaranKegiatan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Simpan dokumen lengkap (cover + KAK/TOR + RAB satu sumber dana) dalam format seragam madrasah (PRD §9, F13) — satu-satunya
 * jalur endpoint `kegiatan.dokumen.lengkap`:
 * - dokumen disusun dari data terbaru lewat `PembuatDokumen::susun(…, 'lengkap', …, $tambahan)` (hasil kegiatan × sumber
 *   ditimpa) beserta `format` = format seragam yang dipakai (`format()`: template berlaku `DaftarTemplate::untukKegiatan` —
 *   asal leading/jenis/bawaan, master & cover —, kelengkapan kop `KelolaProfilMadrasah::data()['kurang']`, aturan
 *   penomoran set lengkap `PenomoranDokumen::aturan`);
 * - nomor dokumen (`NOMOR` keadaan): `tetap` (nomor tersimpan tidak bisa diganti — beda → galat), `manual` (ketikan sendiri,
 *   aturan `PerbaruiRiwayatDokumen::galatNomor` terhadap kunci versi set lengkap × sumber × PDF), `arsip` (nomor arsip final
 *   yang sudah tercatat), `terbit` (`PenomoranDokumen::terbitkan` selama status `STATUS_BERNOMOR`), `tidak_dinomori`
 *   (aturan set lengkap tidak aktif), `menunggu` (draf belum disetujui — nomor terbit saat disetujui);
 * - `nomorTersimpan($kegiatanId, $sumber)` = nomor set lengkap yang sudah ada (hasil tersimpan, lalu arsip) — dipakai juga
 *   CatatRiwayatDokumen::dariArsip agar arsip & dokumen lengkap bernomor sama.
 */
final class SimpanDokumenLengkap
{
    public const STATUS_BERNOMOR = [StatusKegiatan::Disetujui, StatusKegiatan::Final];

    public const NOMOR = [
        'tetap' => 'Nomor dokumen tetap',
        'manual' => 'Nomor diberikan manual',
        'arsip' => 'Nomor mengikuti arsip final',
        'terbit' => 'Nomor diterbitkan otomatis',
        'tidak_dinomori' => 'Set lengkap tidak dinomori otomatis (aturan penomoran)',
        'menunggu' => 'Nomor terbit otomatis setelah kegiatan disetujui',
    ];

    /**
     * @param  array{id?: int|null, name: string, role: string, leadings?: list<string>}  $pengguna
     * @return array{dokumen: array<string, mixed>, baru: bool, berubah: bool, nomor: array{nomor: string|null, keadaan: string, label: string}, pesan: string}
     *
     * @throws RuntimeException Kegiatan tidak ada / gagal disimpan.
     * @throws AuthorizationException Di luar cakupan akun.
     * @throws ValidationException Sumber dana (`sumber`) atau nomor (`nomor_dokumen`) tidak sah.
     */
    public static function simpan(int $id, ?string $sumber, array $pengguna, mixed $nomor = null): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($id) ?? throw new RuntimeException('Kegiatan tidak ditemukan.');
        if (! AksesKegiatan::bolehLihat($kegiatan, $pengguna)) {
            throw new AuthorizationException(AksesKegiatan::PESAN_TOLAK);
        }
        $perSumber = AnggaranKegiatan::perSumber($kegiatan);
        if ($sumber !== null && ! array_key_exists($sumber, $perSumber)) {
            throw ValidationException::withMessages(['sumber' => "Sumber dana {$sumber} tidak dipilih untuk kegiatan ini."]);
        }
        $sumber ??= array_key_first($perSumber);
        if ($sumber === null) {
            throw ValidationException::withMessages(['sumber' => 'Pilih sumber dana kegiatan dulu — dokumen lengkap disusun per sumber dana.']);
        }
        $tahun = TahunKegiatan::dari($kegiatan);
        [$nomorAkhir, $keadaan] = self::nomor($kegiatan, $sumber, $tahun, PerbaruiRiwayatDokumen::rapikanNomor($nomor));

        $hasil = PembuatDokumen::susun($id, 'lengkap', $sumber, $pengguna, ['nomor' => $nomorAkhir, 'format' => self::format($kegiatan, $sumber, $tahun)]);

        return array_merge($hasil, [
            'nomor' => ['nomor' => $nomorAkhir, 'keadaan' => $keadaan, 'label' => self::NOMOR[$keadaan]],
            'pesan' => $hasil['pesan'].($nomorAkhir !== null ? " Nomor dokumen {$nomorAkhir}." : ' '.self::NOMOR[$keadaan].'.'),
        ]);
    }

    /** Nomor set lengkap kegiatan × sumber yang sudah ada: hasil tersimpan, lalu arsip final tercatat. */
    public static function nomorTersimpan(int $kegiatanId, string $sumber): ?string
    {
        $hasil = app(HasilDokumenRepository::class)->temukan($kegiatanId, 'lengkap', $sumber);
        if (filled($hasil['nomor'] ?? null)) {
            return (string) $hasil['nomor'];
        }
        $kunci = implode('|', [$kegiatanId, 'lengkap', $sumber, 'pdf']);
        $arsip = collect(RiwayatDokumen::semua())->first(fn (array $c) => RiwayatDokumen::kunciVersi($c) === $kunci && filled($c['nomor_dokumen'] ?? null));

        return $arsip['nomor_dokumen'] ?? null;
    }

    /**
     * Format seragam yang dipakai dokumen lengkap kegiatan itu.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, mixed>
     */
    public static function format(array $kegiatan, string $sumber, int $tahun): array
    {
        $template = DaftarTemplate::untukKegiatan($kegiatan);
        unset($template['kegiatan_id'], $template['tahun']);
        $kop = KelolaProfilMadrasah::data()['kurang'];
        $aturan = PenomoranDokumen::aturan($tahun)['lengkap'];

        return [
            'tahun' => $tahun,
            'sumber' => $sumber,
            'template' => $template,
            'kop_lengkap' => $kop === [],
            'kop_kurang' => array_values($kop),
            'aturan_nomor' => ['pola' => $aturan['pola'], 'digit' => $aturan['digit'], 'aktif' => $aturan['aktif']],
        ];
    }

    /**
     * Urutan kunci baku `format` (jsonb PostgreSQL mengurutkan ulang kunci objek; EloquentHasilDokumenRepository
     * mengembalikannya lewat fungsi ini agar bentuknya sama dengan tiruan).
     *
     * @param  array<string, mixed>  $format
     * @return array<string, mixed>
     */
    public static function urutkanFormat(array $format): array
    {
        $urut = fn (array $a, array $kunci) => array_replace(array_intersect_key(array_flip($kunci), $a), $a);
        if (is_array($format['template'] ?? null)) {
            $t = $format['template'];
            foreach (['kak' => ['id', 'nama', 'jenis', 'ada_berkas'], 'cover' => ['id', 'nama', 'tautan_gambar']] as $k => $kunci) {
                if (is_array($t[$k] ?? null)) {
                    $t[$k] = $urut($t[$k], $kunci);
                }
            }
            $format['template'] = $urut($t, ['jenis', 'label_jenis', 'asal', 'label_asal', 'kak', 'kak_hilang', 'cover', 'cover_hilang', 'susunan_baku']);
        }
        if (is_array($format['aturan_nomor'] ?? null)) {
            $format['aturan_nomor'] = $urut($format['aturan_nomor'], ['pola', 'digit', 'aktif']);
        }

        return $urut($format, ['tahun', 'sumber', 'template', 'kop_lengkap', 'kop_kurang', 'aturan_nomor']);
    }

    /**
     * @param  array<string, mixed>  $kegiatan
     * @return array{0: string|null, 1: string}
     *
     * @throws ValidationException
     */
    private static function nomor(array $kegiatan, string $sumber, int $tahun, ?string $manual): array
    {
        $id = (int) $kegiatan['id'];
        $tersimpan = app(HasilDokumenRepository::class)->temukan($id, 'lengkap', $sumber)['nomor'] ?? null;
        if (filled($tersimpan)) {
            if ($manual !== null && mb_strtolower($manual) !== mb_strtolower((string) $tersimpan)) {
                throw ValidationException::withMessages(['nomor_dokumen' => "Dokumen ini sudah bernomor {$tersimpan} — nomor tidak bisa diganti."]);
            }

            return [(string) $tersimpan, 'tetap'];
        }
        if ($manual !== null) {
            $catatan = ['kegiatan_id' => $id, 'jenis' => 'lengkap', 'sumber' => $sumber, 'format' => 'pdf', 'tahun_anggaran' => $tahun, 'nama_kegiatan' => (string) ($kegiatan['nama_kegiatan'] ?? '')];
            if (($galat = PerbaruiRiwayatDokumen::galatNomor($manual, $catatan)) !== null) {
                throw ValidationException::withMessages(['nomor_dokumen' => $galat]);
            }

            return [$manual, 'manual'];
        }
        if (($arsip = self::nomorTersimpan($id, $sumber)) !== null) {
            return [$arsip, 'arsip'];
        }
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
        if (! in_array($status, self::STATUS_BERNOMOR, true)) {
            return [null, 'menunggu'];
        }
        $terbit = PenomoranDokumen::terbitkan(['jenis' => 'lengkap', 'leading' => ($kegiatan['leading'] ?? '') ?: null, 'sumber' => $sumber, 'tahun' => $tahun]);

        return $terbit === null ? [null, 'tidak_dinomori'] : [$terbit['nomor'], 'terbit'];
    }
}
