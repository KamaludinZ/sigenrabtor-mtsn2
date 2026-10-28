<?php

namespace App\Support\Dokumen;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\KunciKegiatan;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Rab\AnggaranKegiatan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Simpan Draf dokumen (PRD F4 "dokumen setengah jadi bisa dilanjutkan kapan saja"): satu draf per kegiatan × jenis
 * (JenisDokumen) × sumber dana lewat DrafDokumenRepository. Isi dokumen selalu disusun dari data terbaru — draf mencatat
 * titik lanjutnya: status kegiatan, cap DRAF & alasannya (JenisDokumen::statusDraf, aturan StatusDraf), kelengkapan
 * bagian (SusunanOtomatis), total RAB, dan `sidik()` isi tiap bagian (cover, KAK/TOR per nomor, RAB) sehingga
 * `keadaan()` bisa menyebut bagian yang berubah sejak draf disimpan. Menyimpan & menghapus hanya selama kegiatan bisa
 * diubah (`alasanTerkunci()`: status & KunciKegiatan); melihat mengikuti AksesKegiatan. Dipakai tab Susun KAK/TOR dan
 * endpoint `kegiatan.draf` (GET `data()`), `kegiatan.draf.simpan` (PUT), `kegiatan.draf.hapus` (DELETE). Versi draf =
 * `disimpan_pada` (`versi()`): menghapus draf yang sudah disimpan ulang/dihapus di tempat lain ditolak
 * (PengaturanBerubah); menyimpan tidak memeriksa versi karena draf selalu direkam dari data terbaru. Gagal menulis
 * penyimpanan → RuntimeException dari repositori.
 */
final class DrafDokumen
{
    /** Keadaan draf jenis × sumber yang sedang disusun → [label, keterangan]. */
    public const KEADAAN = [
        'belum' => ['Belum ada draf', 'Simpan draf untuk menandai titik lanjut penyusunan dokumen ini.'],
        'sesuai' => ['Draf sesuai data terbaru', 'Isi dokumen sama dengan saat draf disimpan.'],
        'berubah' => ['Berubah sejak draf disimpan', 'Data kegiatan berubah sesudah draf disimpan — simpan draf lagi agar titik lanjutnya ikut diperbarui.'],
    ];

    /**
     * Sidik isi dokumen jenis itu: cover (nilai kotak teks), KAK/TOR per nomor bagian, dan RAB (judul, kepala, rincian,
     * mekanisme pembayaran, pengesahan). Cap DRAF/final tidak dihitung — itu status, bukan isi.
     *
     * @param  array<string, string>  $nilaiCover  TeksCover::nilai().
     * @return array{cover?: string, kak?: array<int, string>, rab?: string}
     */
    public static function sidik(string $jenis, SusunanKak $kak, SusunanRab $rab, array $nilaiCover): array
    {
        $isi = JenisDokumen::ISI[$jenis] ?? [];
        $md5 = fn (mixed $nilai) => md5((string) json_encode($nilai, JSON_UNESCAPED_UNICODE));
        $sidik = [];
        if (in_array('cover', $isi, true)) {
            $sidik['cover'] = $md5($nilaiCover);
        }
        if (in_array('kak', $isi, true)) {
            foreach ($kak->bagian as $b) {
                $sidik['kak'][$b['nomor']] = $md5($b);
            }
        }
        if (in_array('rab', $isi, true)) {
            $sidik['rab'] = $md5([$rab->judul, $rab->kepala, $rab->anggaran?->hasil->rincian ?? [], $rab->mekanisme, $rab->pengesahan]);
        }

        return $sidik;
    }

    /**
     * Catatan draf dokumen jenis × sumber dari data kegiatan saat ini (bentuk DrafDokumenRepository tanpa id & waktu).
     * Sumber null = sumber dana pertama (seperti SusunanKak).
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id?: int|null, name: string}  $pengguna
     * @return array<string, mixed>
     */
    public static function rekam(array $kegiatan, string $jenis, ?string $sumber, array $pengguna): array
    {
        $kak = SusunanKak::dari($kegiatan, $sumber);
        $rab = SusunanRab::dari($kegiatan, $sumber);
        $isi = JenisDokumen::ISI[$jenis];
        $status = JenisDokumen::statusDraf($jenis, StatusCetak::dari($kegiatan, $kak->sumber, $kak, $rab));
        $susunan = in_array('kak', $isi, true) ? SusunanOtomatis::dari($kak, $rab, $kegiatan, $pengguna) : null;

        return [
            'kegiatan_id' => (int) ($kegiatan['id'] ?? 0),
            'jenis' => $jenis,
            'sumber' => $kak->sumber,
            'status' => (StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf)->value,
            'bertanda_draf' => $status->draf,
            'alasan_draf' => array_column($status->alasan, 'teks'),
            'lengkap' => $susunan?->lengkap,
            'jumlah_bagian' => $susunan?->jumlah(),
            'total_rab' => in_array('rab', $isi, true) ? ($rab->anggaran?->total() ?? 0) : null,
            'sidik' => self::sidik($jenis, $kak, $rab, TeksCover::nilai($kegiatan, $kak->sumber)),
            'user_id' => $pengguna['id'] ?? null,
            'oleh' => $pengguna['name'],
        ];
    }

    /**
     * Simpan (atau perbarui) draf jenis × sumber yang sedang disusun.
     *
     * @param  array{id?: int|null, name: string, role?: string, leadings?: list<string>}  $pengguna
     * @return array{draf: array<string, mixed>, baru: bool, pesan: string}
     *
     * @throws RuntimeException Kegiatan tidak ada atau penyimpanan gagal ditulis.
     * @throws AuthorizationException Bukan cakupan akun atau kegiatan terkunci.
     * @throws ValidationException Jenis (`jenis`) atau sumber dana (`sumber`) tidak sah.
     */
    public static function simpan(int $id, string $jenis, ?string $sumber, array $pengguna): array
    {
        $kegiatan = self::pastikanDapatDiubah($id, $pengguna);
        self::periksa($kegiatan, $jenis, $sumber);
        $rekaman = self::rekam($kegiatan, $jenis, $sumber, $pengguna);
        if (JenisDokumen::perluSumber($jenis) && $rekaman['sumber'] === null) {
            throw ValidationException::withMessages(['sumber' => 'Pilih sumber dana kegiatan dulu — '.DokumenKegiatan::JENIS[$jenis].' disusun per sumber dana.']);
        }
        $repo = app(DrafDokumenRepository::class);
        $baru = $repo->temukan($id, $jenis, $rekaman['sumber']) === null;
        $draf = $repo->simpan($id, $rekaman);
        $nama = self::nama($draf['jenis'], $draf['sumber']);

        return ['draf' => $draf, 'baru' => $baru, 'pesan' => $baru ? "Draf {$nama} tersimpan — lanjutkan kapan saja dari tab Susun KAK/TOR." : "Draf {$nama} diperbarui."];
    }

    /**
     * Hapus draf jenis × sumber (isi dokumen & data kegiatan tidak berubah). `$versi` = versi draf yang terakhir dilihat
     * (null = tanpa pemeriksaan).
     *
     * @param  array{id?: int|null, name: string, role?: string, leadings?: list<string>}  $pengguna
     * @return bool false bila drafnya sudah tidak ada.
     *
     * @throws RuntimeException Kegiatan tidak ada atau penyimpanan gagal ditulis.
     * @throws AuthorizationException Bukan cakupan akun atau kegiatan terkunci.
     * @throws PengaturanBerubah Draf disimpan ulang atau dihapus di tempat lain sejak $versi.
     */
    public static function hapus(int $id, string $jenis, ?string $sumber, array $pengguna, ?string $versi = null): bool
    {
        self::pastikanDapatDiubah($id, $pengguna);
        $repo = app(DrafDokumenRepository::class);
        $sekarang = self::versi($repo->temukan($id, $jenis, $sumber));
        if ($versi !== null && $sekarang !== ($versi !== '' ? $versi : null)) {
            throw new PengaturanBerubah($sekarang === null
                ? 'Draf ini sudah dihapus di tempat lain.'
                : 'Draf ini baru saja disimpan ulang di tempat lain — periksa keadaannya lalu ulangi bila tetap ingin menghapus.');
        }

        return $repo->hapus($id, $jenis, $sumber);
    }

    /**
     * Versi draf (waktu simpan bermikrodetik); null = belum ada draf.
     *
     * @param  array<string, mixed>|null  $draf
     */
    public static function versi(?array $draf): ?string
    {
        return $draf['disimpan_pada'] ?? null;
    }

    /**
     * Alasan draf kegiatan ini hanya bisa dilihat (status bukan Draf/Dikembalikan atau tahun terkunci); null = boleh.
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function alasanTerkunci(array $kegiatan): ?string
    {
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
        if (! $status->dapatDiedit()) {
            return 'Kegiatan berstatus '.$status->label().' sehingga terkunci — draf dokumen hanya bisa dilihat.';
        }
        $tahun = KunciKegiatan::tahunTerkunci($kegiatan);

        return $tahun !== null ? KunciKegiatan::pesanTahun($tahun) : null;
    }

    /**
     * Keadaan satu draf dibanding data kegiatan sekarang (susunan disusun untuk jenis & sumber drafnya).
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array<string, mixed>  $draf
     * @return array{keadaan: string, label: string, keterangan: string, berubah: list<array{kunci: string, label: string, jangkar: string}>}
     */
    public static function keadaanUntuk(array $kegiatan, array $draf): array
    {
        $kak = SusunanKak::dari($kegiatan, $draf['sumber']);
        $rab = SusunanRab::dari($kegiatan, $draf['sumber']);

        return self::keadaan($draf, self::sidik($draf['jenis'], $kak, $rab, TeksCover::nilai($kegiatan, $kak->sumber)), $kak);
    }

    /**
     * Data endpoint `kegiatan.draf`: semua draf kegiatan (terbaru dulu) beserta keadaannya terhadap data sekarang.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, mixed>
     */
    public static function data(array $kegiatan): array
    {
        $alasan = self::alasanTerkunci($kegiatan);

        return [
            'kegiatan_id' => (int) $kegiatan['id'],
            'dapat_diubah' => $alasan === null,
            'alasan_terkunci' => $alasan,
            'keadaan' => array_map(fn (array $k) => ['label' => $k[0], 'keterangan' => $k[1]], self::KEADAAN),
            'draf' => array_map(fn (array $d) => self::untukJson($kegiatan, $d), app(DrafDokumenRepository::class)->untukKegiatan((int) $kegiatan['id'])),
        ];
    }

    /**
     * Satu draf dalam bentuk JSON: catatan draf (tanpa sidik) + label, versi, tautan lanjutkan, dan keadaannya.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array<string, mixed>  $draf
     * @return array<string, mixed>
     */
    public static function untukJson(array $kegiatan, array $draf): array
    {
        return array_diff_key($draf, ['sidik' => true]) + [
            'nama' => self::nama($draf['jenis'], $draf['sumber']),
            'label_jenis' => DokumenKegiatan::JENIS[$draf['jenis']] ?? $draf['jenis'],
            'label_status' => StatusKegiatan::tryFrom((string) $draf['status'])?->label(),
            'versi' => self::versi($draf),
            'tautan' => self::tautan($draf),
            'keadaan' => self::keadaanUntuk($kegiatan, $draf),
        ];
    }

    /**
     * Keadaan draf dokumen yang sedang tampil dibanding susunan sekarang: belum ada draf, sesuai, atau berubah beserta
     * bagian yang berubah (jangkar kartu tab Susun KAK/TOR).
     *
     * @param  array<string, mixed>|null  $draf
     * @param  array{cover?: string, kak?: array<int, string>, rab?: string}  $sidik  sidik() susunan sekarang.
     * @return array{keadaan: string, label: string, keterangan: string, berubah: list<array{kunci: string, label: string, jangkar: string}>}
     */
    public static function keadaan(?array $draf, array $sidik, SusunanKak $kak): array
    {
        $berubah = [];
        if ($draf !== null) {
            $lama = (array) ($draf['sidik'] ?? []);
            if (isset($sidik['cover']) && ($lama['cover'] ?? null) !== $sidik['cover']) {
                $berubah[] = ['kunci' => 'cover', 'label' => 'Cover', 'jangkar' => 'susun-cover'];
            }
            foreach ($kak->bagian as $b) {
                $n = $b['nomor'];
                if (isset($sidik['kak'][$n]) && ($lama['kak'][$n] ?? null) !== $sidik['kak'][$n]) {
                    $berubah[] = ['kunci' => "kak-{$n}", 'label' => "{$n}. {$b['judul']}", 'jangkar' => "susun-bagian-{$n}"];
                }
            }
            if (isset($sidik['rab']) && ($lama['rab'] ?? null) !== $sidik['rab']) {
                $berubah[] = ['kunci' => 'rab', 'label' => 'RAB'.($kak->sumber ? " {$kak->sumber}" : ''), 'jangkar' => 'susun-rab'];
            }
        }
        $keadaan = match (true) {
            $draf === null => 'belum',
            $berubah !== [] => 'berubah',
            default => 'sesuai',
        };

        return ['keadaan' => $keadaan, 'label' => self::KEADAAN[$keadaan][0], 'keterangan' => self::KEADAAN[$keadaan][1], 'berubah' => $berubah];
    }

    /** Nama draf di layar: "Cover + KAK/TOR + RAB · Komite". */
    public static function nama(string $jenis, ?string $sumber): string
    {
        return (DokumenKegiatan::JENIS[$jenis] ?? $jenis).($sumber ? " · {$sumber}" : '');
    }

    /**
     * Tautan melanjutkan draf: tab Susun KAK/TOR dengan jenis & sumber drafnya.
     *
     * @param  array{kegiatan_id: int, jenis: string, sumber: string|null}  $draf
     */
    public static function tautan(array $draf): string
    {
        return route('kegiatan.dokumen', array_filter([
            'kegiatan' => $draf['kegiatan_id'],
            'jenis' => $draf['jenis'] !== JenisDokumen::BAWAAN ? $draf['jenis'] : null,
            'sumber' => $draf['sumber'],
        ]));
    }

    /**
     * @param  array<string, mixed>  $pengguna
     * @return array<string, mixed> Data kegiatan tersimpan.
     */
    private static function pastikanDapatDiubah(int $id, array $pengguna): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($id) ?? throw new RuntimeException('Kegiatan tidak ditemukan.');
        if (! AksesKegiatan::bolehLihat($kegiatan, $pengguna)) {
            throw new AuthorizationException(AksesKegiatan::PESAN_TOLAK);
        }
        if (($alasan = self::alasanTerkunci($kegiatan)) !== null) {
            throw new AuthorizationException($alasan);
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
