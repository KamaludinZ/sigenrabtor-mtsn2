<?php

namespace App\Support\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Enums\StatusKegiatan;
use App\Support\Teks;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Simpan matrik tahapan & jadwal satu kegiatan di server (PRD F10, bagian 4 & 7 KAK) — aturan isian sama dengan halaman
 * Matrik & Jadwal (`aturanMatrik()`/`aturanJadwal()` beserta pesannya). Matrik: tahapan hanya MatrikTahapan::TAHAPAN,
 * uraian butir wajib ≤ MatrikTahapan::MAKS_URAIAN, bulan 1–12, dirapikan MatrikTahapan::dari; pembaruan sebagian
 * (`simpanMatrik`: hanya tahapan yang dikirim yang diganti). Jadwal dalam bentuk formulir {uraian, mulai, sampai}
 * (`isianJadwal()` ⇄ `jadwalUntukDisimpan()`: uraian dirapikan, mulai..sampai → bulan bersambung): ganti seluruhnya
 * (`simpanJadwal`) atau per baris bernomor 1.. (`tambahBarisJadwal`, `ubahBarisJadwal`, `hapusBarisJadwal`,
 * `geserBarisJadwal`; urutan = urutan cetak, maks. JadwalKegiatan::MAKS_BARIS). `simpan()` menyimpan keduanya sekaligus
 * (tombol Simpan halaman). Bagian yang tidak diubah tetap seperti tersimpan; kegiatan terkunci/tidak ada ditolak. Setiap
 * aksi mengembalikan matrik, jadwal, dan KonsistensiBulan terbaru.
 */
final class SimpanMatrikJadwal
{
    /** @return array<string, list<mixed>> */
    public static function aturanMatrik(): array
    {
        return [
            'matrik' => ['array:'.implode(',', array_keys(MatrikTahapan::TAHAPAN))],
            'matrik.*' => ['array'],
            'matrik.*.*' => ['array'],
            'matrik.*.*.uraian' => ['required', 'string', 'max:'.MatrikTahapan::MAKS_URAIAN],
            'matrik.*.*.bulan' => ['array'],
            'matrik.*.*.bulan.*' => ['integer', 'between:1,12'],
        ];
    }

    /** @return array<string, string> */
    public static function pesanMatrik(): array
    {
        return [
            'matrik.array' => 'Tahapan matrik hanya '.implode(', ', MatrikTahapan::TAHAPAN).'.',
            'matrik.*.*.uraian.required' => 'Uraian butir tahapan wajib diisi.',
            'matrik.*.*.uraian.max' => 'Uraian butir tahapan maksimal :max karakter.',
            'matrik.*.*.bulan.*.between' => 'Bulan matrik harus 1–12.',
        ];
    }

    /**
     * Aturan isian jadwal bentuk formulir; $jadwal = isian yang diperiksa (untuk aturan "s.d." tidak sebelum mulai).
     *
     * @param  array<int, mixed>  $jadwal
     * @return array<string, list<mixed>>
     */
    public static function aturanJadwal(array $jadwal): array
    {
        $waktuUrut = function (string $attribute, mixed $nilai, Closure $fail) use ($jadwal) {
            $mulai = $jadwal[(int) explode('.', $attribute)[1]]['mulai'] ?? null;
            if ($nilai !== null && ($mulai === null || $nilai < $mulai)) {
                $fail('Bulan "s.d." tidak boleh sebelum bulan mulai.');
            }
        };

        return [
            'jadwal' => ['array', 'list', 'max:'.JadwalKegiatan::MAKS_BARIS],
            'jadwal.*' => ['array'],
            // Baris boleh kosong; uraian baru wajib bila bulannya sudah dipilih (required_with bersifat implisit, tetap
            // diperiksa walau uraian kosong).
            'jadwal.*.uraian' => ['nullable', 'string', 'max:'.JadwalKegiatan::MAKS_URAIAN, 'required_with:jadwal.*.mulai'],
            'jadwal.*.mulai' => ['nullable', 'integer', 'between:1,12'],
            'jadwal.*.sampai' => ['nullable', 'integer', 'between:1,12', $waktuUrut],
        ];
    }

    /** @return array<string, string> */
    public static function pesanJadwal(): array
    {
        return [
            'jadwal.max' => 'Jadwal maksimal :max baris.',
            'jadwal.*.uraian.max' => 'Uraian jadwal maksimal :max karakter.',
            'jadwal.*.uraian.required_with' => 'Isi uraian kegiatan untuk bulan yang sudah dipilih.',
        ];
    }

    /** Nomor bulan 1–12 dari isian (teks dari <select>, angka); selain itu null. */
    public static function bulanAtauNull(mixed $nilai): ?int
    {
        $bulan = filter_var($nilai, FILTER_VALIDATE_INT);

        return $bulan !== false && $bulan >= 1 && $bulan <= 12 ? $bulan : null;
    }

    /**
     * Baris jadwal formulir → bentuk penyimpanan {uraian (rapi), bulan (rentang mulai..s.d.)}.
     *
     * @param  list<array{uraian?: mixed, mulai?: mixed, sampai?: mixed}>  $jadwal
     * @return list<array{uraian: string, bulan: list<int>}>
     */
    public static function jadwalUntukDisimpan(array $jadwal): array
    {
        return array_values(array_map(function (array $b) {
            $mulai = self::bulanAtauNull($b['mulai'] ?? null);
            $sampai = self::bulanAtauNull($b['sampai'] ?? null);

            return [
                'uraian' => Teks::rapikan((string) ($b['uraian'] ?? '')),
                'bulan' => $mulai === null ? [] : range($mulai, max($mulai, $sampai ?? $mulai)),
            ];
        }, $jadwal));
    }

    /**
     * Jadwal tersimpan → baris formulir {uraian, mulai, sampai (null bila sebulan)}.
     *
     * @return list<array{uraian: string, mulai: int|null, sampai: int|null}>
     */
    public static function isianJadwal(JadwalKegiatan $jadwal): array
    {
        return array_map(fn (array $b) => [
            'uraian' => $b['uraian'],
            'mulai' => $b['bulan'][0] ?? null,
            'sampai' => count($b['bulan']) > 1 ? end($b['bulan']) : null,
        ], $jadwal->baris);
    }

    /**
     * Simpan matrik & jadwal sekaligus (tombol Simpan halaman Matrik & Jadwal).
     *
     * @param  array<string, mixed>  $matrik  Seluruh matrik (kunci tahapan → butir).
     * @param  list<array<string, mixed>>  $jadwal  Seluruh jadwal bentuk formulir.
     * @return array{matrik: MatrikTahapan, jadwal: JadwalKegiatan, konsistensi: KonsistensiBulan}
     */
    public static function simpan(int $kegiatanId, array $matrik, array $jadwal): array
    {
        $kegiatan = self::pastikanDapatDiubah($kegiatanId);
        self::periksa($matrik, $jadwal);
        app(MatrikJadwalRepository::class)->simpan(
            $kegiatanId,
            MatrikTahapan::dari(['matrik' => $matrik]),
            JadwalKegiatan::dari(['jadwal' => self::jadwalUntukDisimpan($jadwal)]),
        );

        return self::hasil($kegiatan, $kegiatanId);
    }

    /**
     * Ganti seluruh jadwal (daftar kosong = jadwal kosong); matrik tetap.
     *
     * @param  list<array<string, mixed>>  $jadwal
     * @return array{matrik: MatrikTahapan, jadwal: JadwalKegiatan, konsistensi: KonsistensiBulan}
     */
    public static function simpanJadwal(int $kegiatanId, array $jadwal): array
    {
        $kegiatan = self::pastikanDapatDiubah($kegiatanId);
        self::periksa(null, $jadwal);
        $repo = app(MatrikJadwalRepository::class);
        $repo->simpan($kegiatanId, $repo->matrik($kegiatanId), JadwalKegiatan::dari(['jadwal' => self::jadwalUntukDisimpan($jadwal)]));

        return self::hasil($kegiatan, $kegiatanId);
    }

    /**
     * Tambah satu baris di nomor $nomor (1..jumlah+1; null = di akhir); baris sesudahnya bergeser turun.
     *
     * @param  array{uraian?: mixed, mulai?: mixed, sampai?: mixed}  $baris
     * @return array{matrik: MatrikTahapan, jadwal: JadwalKegiatan, konsistensi: KonsistensiBulan}
     */
    public static function tambahBarisJadwal(int $kegiatanId, array $baris, ?int $nomor = null): array
    {
        return self::ubahJadwal($kegiatanId, function (array $jadwal) use ($baris, $nomor) {
            $nomor ??= count($jadwal) + 1;
            self::pastikanNomor($nomor, count($jadwal) + 1);
            if (count($jadwal) >= JadwalKegiatan::MAKS_BARIS) {
                throw ValidationException::withMessages(['jadwal' => 'Jadwal sudah mencapai batas '.JadwalKegiatan::MAKS_BARIS.' baris.']);
            }
            array_splice($jadwal, $nomor - 1, 0, [self::baris($baris)]);

            return $jadwal;
        });
    }

    /**
     * @param  array{uraian?: mixed, mulai?: mixed, sampai?: mixed}  $baris
     * @return array{matrik: MatrikTahapan, jadwal: JadwalKegiatan, konsistensi: KonsistensiBulan}
     */
    public static function ubahBarisJadwal(int $kegiatanId, int $nomor, array $baris): array
    {
        return self::ubahJadwal($kegiatanId, function (array $jadwal) use ($baris, $nomor) {
            self::pastikanNomor($nomor, count($jadwal));
            $jadwal[$nomor - 1] = self::baris($baris);

            return $jadwal;
        });
    }

    /** @return array{matrik: MatrikTahapan, jadwal: JadwalKegiatan, konsistensi: KonsistensiBulan} */
    public static function hapusBarisJadwal(int $kegiatanId, int $nomor): array
    {
        return self::ubahJadwal($kegiatanId, function (array $jadwal) use ($nomor) {
            self::pastikanNomor($nomor, count($jadwal));
            array_splice($jadwal, $nomor - 1, 1);

            return $jadwal;
        });
    }

    /**
     * Naikkan ($arah < 0) atau turunkan ($arah > 0) satu baris; di ujung jadwal tidak berubah.
     *
     * @return array{matrik: MatrikTahapan, jadwal: JadwalKegiatan, konsistensi: KonsistensiBulan}
     */
    public static function geserBarisJadwal(int $kegiatanId, int $nomor, int $arah): array
    {
        return self::ubahJadwal($kegiatanId, function (array $jadwal) use ($nomor, $arah) {
            self::pastikanNomor($nomor, count($jadwal));
            $asal = $nomor - 1;
            $tujuan = $asal + ($arah > 0 ? 1 : -1);
            if (isset($jadwal[$tujuan])) {
                [$jadwal[$asal], $jadwal[$tujuan]] = [$jadwal[$tujuan], $jadwal[$asal]];
            }

            return $jadwal;
        });
    }

    /**
     * @param  array<string, mixed>  $matrik  Kunci tahapan → daftar butir {uraian, bulan}; tahapan yang tidak dikirim tetap.
     * @return array{matrik: MatrikTahapan, jadwal: JadwalKegiatan, konsistensi: KonsistensiBulan}
     *
     * @throws ValidationException Berkunci `matrik.*`.
     * @throws AuthorizationException Kegiatan terkunci.
     * @throws RuntimeException Kegiatan tidak ditemukan atau penyimpanan gagal.
     */
    public static function simpanMatrik(int $kegiatanId, array $matrik): array
    {
        $kegiatan = self::pastikanDapatDiubah($kegiatanId);
        Validator::make(['matrik' => $matrik], self::aturanMatrik(), self::pesanMatrik())->validate();

        $repo = app(MatrikJadwalRepository::class);
        $susunan = MatrikTahapan::dari(['matrik' => array_replace($repo->matrik($kegiatanId)->butir, $matrik)]);
        $jadwal = $repo->jadwal($kegiatanId);
        $repo->simpan($kegiatanId, $susunan, $jadwal);

        return self::hasil($kegiatan, $kegiatanId);
    }

    /**
     * Kegiatan tersimpan yang isiannya masih boleh diubah (Draf/Dikembalikan).
     *
     * @return array<string, mixed>
     */
    public static function pastikanDapatDiubah(int $kegiatanId): array
    {
        $kegiatan = app(KegiatanRepository::class)->temukan($kegiatanId) ?? throw new RuntimeException("Kegiatan {$kegiatanId} tidak ditemukan.");
        $status = StatusKegiatan::tryFrom((string) ($kegiatan['status'] ?? '')) ?? StatusKegiatan::Draf;
        if (! $status->dapatDiedit()) {
            throw new AuthorizationException("Matrik & jadwal terkunci karena kegiatan berstatus {$status->label()}.");
        }
        KunciKegiatan::pastikanTahunTerbuka($kegiatan);

        return $kegiatan;
    }

    /**
     * Ubah jadwal tersimpan lewat $ubah (menerima & mengembalikan baris formulir), periksa, lalu simpan; matrik tetap.
     *
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $ubah
     * @return array{matrik: MatrikTahapan, jadwal: JadwalKegiatan, konsistensi: KonsistensiBulan}
     */
    private static function ubahJadwal(int $kegiatanId, callable $ubah): array
    {
        self::pastikanDapatDiubah($kegiatanId);

        return self::simpanJadwal($kegiatanId, array_values($ubah(self::isianJadwal(app(MatrikJadwalRepository::class)->jadwal($kegiatanId)))));
    }

    /**
     * @param  array<string, mixed>|null  $matrik  null = matrik tidak diperiksa.
     * @param  array<int, mixed>  $jadwal
     */
    private static function periksa(?array $matrik, array $jadwal): void
    {
        // Urutan galat sama dengan halaman: matrik dulu, lalu jadwal.
        $data = ($matrik === null ? [] : ['matrik' => $matrik]) + ['jadwal' => $jadwal];
        $aturan = ($matrik === null ? [] : self::aturanMatrik()) + self::aturanJadwal($jadwal);

        Validator::make($data, $aturan, self::pesanMatrik() + self::pesanJadwal())->validate();
    }

    /**
     * @param  array<string, mixed>  $baris
     * @return array{uraian: mixed, mulai: mixed, sampai: mixed}
     */
    private static function baris(array $baris): array
    {
        return ['uraian' => $baris['uraian'] ?? '', 'mulai' => $baris['mulai'] ?? null, 'sampai' => $baris['sampai'] ?? null];
    }

    private static function pastikanNomor(int $nomor, int $terbesar): void
    {
        if ($nomor < 1 || $nomor > $terbesar) {
            throw ValidationException::withMessages(['nomor' => "Baris jadwal nomor {$nomor} tidak ada."]);
        }
    }

    /**
     * @param  array<string, mixed>  $kegiatan
     * @return array{matrik: MatrikTahapan, jadwal: JadwalKegiatan, konsistensi: KonsistensiBulan}
     */
    private static function hasil(array $kegiatan, int $kegiatanId): array
    {
        $repo = app(MatrikJadwalRepository::class);
        $matrik = $repo->matrik($kegiatanId);
        $jadwal = $repo->jadwal($kegiatanId);

        return ['matrik' => $matrik, 'jadwal' => $jadwal, 'konsistensi' => PemeriksaKonsistensi::bulan($kegiatan, $matrik, $jadwal)];
    }
}
