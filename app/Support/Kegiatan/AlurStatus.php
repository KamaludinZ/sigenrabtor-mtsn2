<?php

namespace App\Support\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Enums\StatusKegiatan;
use App\Livewire\Forms\KegiatanForm;
use App\Support\Dokumen\KunciTemplate;
use App\Support\Dokumen\StatusDokumen;
use App\Support\Pemeriksaan\KeputusanPemeriksaan;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Rab\AnggaranKegiatan;
use App\Support\Rab\BatasPagu;
use App\Support\Rab\MekanismePembayaran;
use App\Support\Rab\SimpanTermin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Alur status dokumen kegiatan (PRD F16–F18, §16; Draf → Diperiksa → Disetujui → Final, cabang Dikembalikan) — satu pintu
 * semua perpindahan status, dipakai panel status Data Kegiatan (`Form::ubahStatus`) dan endpoint `kegiatan.status` (GET
 * `data()`) & `kegiatan.status.ubah` (POST `ubah()`):
 * - `ubah($id, $aksi, $catatan, $pengguna, $versi)`: AksesKegiatan, `$versi` usang → PengaturanBerubah, aksi dikenal
 *   StatusKegiatan::semuaAksi; Setujui/Kembalikan lewat KeputusanPemeriksaan (admin, BatasPagu, catatan wajib), Tandai
 *   final & Buka kunci lewat KunciFinal (arsip final, alasan), Ajukan & Tarik di sini — aksi harus tersedia dari status
 *   **tersimpan** untuk peran itu (galat `aksi`; mis. sudah diajukan di tab lain), tahun anggaran terbuka
 *   (AuthorizationException), dan Ajukan memenuhi `syaratAjukan()` (data tersimpan: ValidasiKegiatan, isian wajib
 *   pengajuan, termin pas, RAB dalam pagu — galat berkunci isian);
 * - `terapkan()` = satu-satunya penulis perpindahan status (dipakai juga KeputusanPemeriksaan, KunciFinal, FinalOtomatis):
 *   status + entri RiwayatStatus::entri (beserta `sidik` PerbaikanKegiatan untuk kembalikan & buka kunci) disimpan
 *   bersama, lalu dokumen tersimpan disamakan (StatusDokumen::sinkronkan — gagal ditulis dilaporkan di `dokumen.gagal`,
 *   status tetap berpindah);
 * - `data()` = status + deskripsi, langkah alur, aksi untuk peran pengguna beserta `halangan()` (kenapa belum bisa),
 *   riwayat (terbaru dulu), status dokumen tersimpan (StatusDokumen::untuk), versi.
 */
final class AlurStatus
{
    /** Aksi yang dijalankan langsung di sini (keputusan admin & kunci final punya layanannya sendiri). */
    public const AKSI = ['ajukan', 'tarik'];

    public const PESAN = [
        'ajukan' => 'Kegiatan diajukan — data dikunci selama diperiksa admin.',
        'tarik' => 'Pengajuan ditarik — data bisa diubah lagi.',
    ];

    public const PESAN_BENTROK = 'Status kegiatan ini baru saja berubah di tempat lain. Muat ulang lalu coba lagi.';

    /** Langkah alur utama (StatusKegiatan::langkah) → [kunci, label]; Dikembalikan ditampilkan di langkah pertama. */
    public const LANGKAH = [['draf', 'Draf'], ['diperiksa', 'Diperiksa'], ['disetujui', 'Disetujui'], ['final', 'Final']];

    /** @param  array<string, mixed>  $kegiatan */
    public static function versi(array $kegiatan): string
    {
        return KeputusanPemeriksaan::versi($kegiatan);
    }

    /**
     * Syarat pengajuan dari data tersimpan (sama dengan pemeriksaan panel status sebelum mengajukan): isian → pesan.
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, string>
     */
    public static function syaratAjukan(array $kegiatan): array
    {
        $galat = ValidasiKegiatan::galatIsian($kegiatan, leadingTersimpan: is_string($kegiatan['leading'] ?? null) ? $kegiatan['leading'] : null);
        foreach (KegiatanForm::PELAKSANAAN_WAJIB + KegiatanForm::KODE_WAJIB as $isian => $pesan) {
            if (blank(is_string($kegiatan[$isian] ?? null) ? trim($kegiatan[$isian]) : ($kegiatan[$isian] ?? null))) {
                $galat[$isian] ??= $pesan;
            }
        }
        $anggaran = AnggaranKegiatan::untuk($kegiatan);
        foreach ($anggaran as $a) {
            if ($pesan = SimpanTermin::pesanTidakPas(MekanismePembayaran::dari($a), $a->sumber)) {
                $galat['termin_'.mb_strtolower($a->sumber)] = $pesan.' Sesuaikan di halaman RAB › Termin pembayaran.';
            }
        }
        foreach (BatasPagu::terlampaui($anggaran) as $sumber => $pesan) {
            $galat['pagu_'.mb_strtolower($sumber)] = $pesan;
        }

        return $galat;
    }

    /**
     * Kenapa aksi $aksi (yang tersedia untuk peran pengguna) belum bisa dijalankan; kosong = bisa.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id?: int|null, role: string, leadings?: list<string>}  $pengguna
     * @return list<string>
     */
    public static function halangan(array $kegiatan, string $aksi, array $pengguna): array
    {
        if ($aksi === 'finalkan' || $aksi === 'buka_kunci') {
            $alasan = $aksi === 'finalkan' ? KunciFinal::alasanKunci($kegiatan, $pengguna) : KunciFinal::alasanBuka($kegiatan, $pengguna);

            return $alasan === null ? [] : [$alasan];
        }
        $tahun = KunciKegiatan::tahunTerkunci($kegiatan);

        return array_values(array_filter([
            $tahun !== null ? KunciKegiatan::pesanTahun($tahun) : null,
            ...($aksi === 'ajukan' ? array_values(self::syaratAjukan($kegiatan)) : []),
            ...($aksi === 'setujui' ? array_values(BatasPagu::terlampaui(AnggaranKegiatan::untuk($kegiatan))) : []),
        ]));
    }

    /**
     * Data endpoint `kegiatan.status`.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id?: int|null, role: string, leadings?: list<string>}  $pengguna
     * @return array<string, mixed>
     */
    public static function data(array $kegiatan, array $pengguna): array
    {
        $status = self::status($kegiatan);
        $aksi = [];
        foreach ($status->aksiUntuk($pengguna['role']) as $kunci => $definisi) {
            $halangan = self::halangan($kegiatan, $kunci, $pengguna);
            $aksi[] = [
                'aksi' => $kunci,
                'label' => $definisi['label'],
                'ke' => $definisi['ke']->value,
                'label_ke' => $definisi['ke']->label(),
                'konfirmasi' => $definisi['konfirmasi'],
                'catatan_wajib' => isset(RiwayatStatus::CATATAN_WAJIB[$kunci]),
                'maks_catatan' => RiwayatStatus::MAKS_CATATAN,
                'bisa' => $halangan === [],
                'halangan' => $halangan,
            ];
        }
        $langkah = $status->langkah();

        return [
            'kegiatan_id' => (int) $kegiatan['id'],
            'status' => $status->value,
            'label_status' => $status->label(),
            'deskripsi' => $status->deskripsi(),
            'dapat_diubah' => KunciKegiatan::dapatDiubah($kegiatan),
            'tahun_terkunci' => KunciKegiatan::tahunTerkunci($kegiatan),
            'langkah' => array_map(fn (array $l, int $i) => [
                'kunci' => $l[0],
                'label' => $i === 0 && $status === StatusKegiatan::Dikembalikan ? StatusKegiatan::Dikembalikan->label() : $l[1],
                'selesai' => $i < $langkah,
                'aktif' => $i === $langkah,
            ], self::LANGKAH, array_keys(self::LANGKAH)),
            'aksi' => $aksi,
            'riwayat' => array_reverse(array_map(
                fn (array $e) => $e + ['label' => StatusKegiatan::labelRiwayat((string) ($e['aksi'] ?? ''))],
                array_values((array) ($kegiatan['riwayat_status'] ?? [])),
            )),
            'dokumen' => StatusDokumen::untuk($kegiatan),
            'versi' => self::versi($kegiatan),
        ];
    }

    /**
     * Jalankan aksi status $aksi atas kegiatan $id.
     *
     * @param  array{id?: int|null, name: string, role: string, leadings?: list<string>}  $pengguna
     * @return array{kegiatan: array<string, mixed>, entri: array<string, mixed>, pesan: string, versi: string, dokumen: array{hasil: int, draf: int, gagal: string|null}}
     *
     * @throws RuntimeException Kegiatan tidak ditemukan.
     * @throws AuthorizationException Di luar cakupan, bukan peran yang berhak, atau tahun anggaran terkunci.
     * @throws PengaturanBerubah Status/riwayat berubah di tempat lain sejak $versi.
     * @throws ValidationException Berkunci `aksi`, `catatan`, `keputusan`, `arsip`, atau isian syarat pengajuan.
     */
    public static function ubah(int $id, string $aksi, ?string $catatan, array $pengguna, ?string $versi = null): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($id) ?? throw new RuntimeException('Kegiatan tidak ditemukan.');
        if (! AksesKegiatan::bolehLihat($kegiatan, $pengguna)) {
            throw new AuthorizationException(AksesKegiatan::PESAN_TOLAK);
        }
        if ($versi !== null && $versi !== self::versi($kegiatan)) {
            throw new PengaturanBerubah(self::PESAN_BENTROK);
        }
        if (! isset(StatusKegiatan::semuaAksi()[$aksi])) {
            throw ValidationException::withMessages(['aksi' => 'Aksi status tidak dikenal.']);
        }

        $hasil = match ($aksi) {
            'finalkan' => KunciFinal::kunci($id, $pengguna),
            'buka_kunci' => KunciFinal::buka($id, $catatan, $pengguna),
            'setujui', 'kembalikan' => KeputusanPemeriksaan::putuskan($id, $aksi, $catatan, $pengguna),
            default => self::jalankan($kegiatan, $aksi, $catatan, $pengguna),
        };

        return $hasil + ['versi' => self::versi($hasil['kegiatan'])];
    }

    /**
     * Tulis perpindahan status: status + entri riwayat disimpan bersama, lalu dokumen tersimpan disamakan.
     *
     * @param  array<string, mixed>  $kegiatan  Data tersimpan (ber-id).
     * @param  array{id?: int|null, name: string, role: string}  $pengguna
     * @return array{kegiatan: array<string, mixed>, entri: array<string, mixed>, dokumen: array{hasil: int, draf: int, gagal: string|null}}
     */
    public static function terapkan(array $kegiatan, string $aksi, StatusKegiatan $ke, array $pengguna, ?string $catatan = null): array
    {
        $id = (int) $kegiatan['id'];
        if ($ke === StatusKegiatan::Final) {
            // Template & format yang berlaku dibekukan bersama dokumen final (diubah lagi hanya sesudah admin membuka kunci).
            $kegiatan = KunciTemplate::bekukan($kegiatan, $pengguna);
        }
        $kegiatan['status'] = $ke->value;
        $entri = RiwayatStatus::entri($aksi, $ke, $pengguna, $catatan);
        if (in_array($aksi, PerbaikanKegiatan::AKSI, true)) {
            // Titik awal perbaikan: bagian yang berubah sesudahnya ditandai untuk leading & pemeriksa berikutnya.
            $entri['sidik'] = PerbaikanKegiatan::sidik($kegiatan);
        }
        $kegiatan['riwayat_status'] = [...array_values((array) ($kegiatan['riwayat_status'] ?? [])), $entri];
        $repo = app(KegiatanRepository::class);
        $repo->simpan($kegiatan, $id);

        try {
            $dokumen = StatusDokumen::sinkronkan($id, $pengguna) + ['gagal' => null];
        } catch (RuntimeException $e) {
            report($e);
            $dokumen = ['hasil' => 0, 'draf' => 0, 'gagal' => $e->getMessage()];
        }

        return ['kegiatan' => $repo->temukan($id), 'entri' => $entri, 'dokumen' => $dokumen];
    }

    /**
     * Ajukan & Tarik.
     *
     * @param  array<string, mixed>  $kegiatan
     * @param  array{id?: int|null, name: string, role: string, leadings?: list<string>}  $pengguna
     * @return array{kegiatan: array<string, mixed>, entri: array<string, mixed>, pesan: string, dokumen: array{hasil: int, draf: int, gagal: string|null}}
     */
    private static function jalankan(array $kegiatan, string $aksi, ?string $catatan, array $pengguna): array
    {
        $status = self::status($kegiatan);
        $definisi = $status->aksiUntuk($pengguna['role'])[$aksi] ?? null;
        if ($definisi === null) {
            $label = StatusKegiatan::semuaAksi()[$aksi]['label'];
            throw ValidationException::withMessages(['aksi' => "Aksi “{$label}” tidak tersedia untuk kegiatan berstatus {$status->label()}."]);
        }
        KunciKegiatan::pastikanTahunTerbuka($kegiatan);
        $catatan = RiwayatStatus::rapikanCatatan((string) $catatan);
        Validator::make(['catatan' => $catatan], ...RiwayatStatus::validasiCatatan($aksi, 'catatan'))->validate();
        if ($aksi === 'ajukan' && ($syarat = self::syaratAjukan($kegiatan)) !== []) {
            throw ValidationException::withMessages($syarat);
        }

        return self::terapkan($kegiatan, $aksi, $definisi['ke'], $pengguna, $catatan) + ['pesan' => self::PESAN[$aksi]];
    }

    /** @param  array<string, mixed>  $kegiatan */
    private static function status(array $kegiatan): StatusKegiatan
    {
        return StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
    }
}
