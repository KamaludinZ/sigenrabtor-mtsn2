<?php

namespace App\Support\Dokumen;

use App\Contracts\DrafDokumenRepository;
use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Support\Kegiatan\AksesKegiatan;
use App\Support\Kegiatan\KunciKegiatan;
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
 * diubah (KunciKegiatan); melihat mengikuti AksesKegiatan.
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
     * @return array<string, mixed> Draf tersimpan.
     *
     * @throws RuntimeException Kegiatan tidak ada.
     * @throws AuthorizationException Bukan cakupan akun atau kegiatan terkunci.
     * @throws ValidationException Jenis (`jenis`) atau sumber dana (`sumber`) tidak sah.
     */
    public static function simpan(int $id, string $jenis, ?string $sumber, array $pengguna): array
    {
        $kegiatan = self::pastikanDapatDiubah($id, $pengguna);
        self::periksa($kegiatan, $jenis, $sumber);
        $draf = self::rekam($kegiatan, $jenis, $sumber, $pengguna);
        if (JenisDokumen::perluSumber($jenis) && $draf['sumber'] === null) {
            throw ValidationException::withMessages(['sumber' => 'Pilih sumber dana kegiatan dulu — '.DokumenKegiatan::JENIS[$jenis].' disusun per sumber dana.']);
        }

        return app(DrafDokumenRepository::class)->simpan($id, $draf);
    }

    /**
     * Hapus draf jenis × sumber (isi dokumen & data kegiatan tidak berubah).
     *
     * @param  array{id?: int|null, name: string, role?: string, leadings?: list<string>}  $pengguna
     * @return bool false bila drafnya sudah tidak ada.
     *
     * @throws RuntimeException|AuthorizationException
     */
    public static function hapus(int $id, string $jenis, ?string $sumber, array $pengguna): bool
    {
        self::pastikanDapatDiubah($id, $pengguna);

        return app(DrafDokumenRepository::class)->hapus($id, $jenis, $sumber);
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
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
        if (! $status->dapatDiedit()) {
            throw new AuthorizationException('Kegiatan berstatus '.$status->label().' sehingga terkunci — draf dokumen hanya bisa dilihat.');
        }
        KunciKegiatan::pastikanTahunTerbuka($kegiatan);

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
