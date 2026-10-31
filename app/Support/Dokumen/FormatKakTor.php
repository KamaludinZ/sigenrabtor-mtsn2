<?php

namespace App\Support\Dokumen;

use App\Contracts\PilihanTemplateRepository;
use App\Support\Kegiatan\TahunKegiatan;
use App\Support\Pengaturan\RiwayatPengaturan;
use App\Support\Pengaturan\TahunDipilih;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Format susunan baku KAK/TOR & lembar pengesahan (PRD §9 "Susunan Baku"; contoh madrasah TA 2026 dan "Format Term Of
 * Reference (TOR)" TA 2027). Dua format tetap bisa dipakai berdampingan:
 * - `sembilan_bagian` — sembilan bagian bernomor 1–9 (Latar Belakang … Lembar Pengesahan); tanda tangan Mengetahui,
 *   Pembuat, Menyetujui sejajar.
 * - `lima_bab` — lima bab I–V (Pendahuluan, Kegiatan yang Dilaksanakan, Tahapan Kegiatan, Hasil yang Akan Dicapai,
 *   Penutup); tanda tangan Menyetujui (Kepala) & Penanggungjawab/pembuat berdampingan, Mengetahui (Ketua Komite) di tengah
 *   bawah — juga lembar pengesahan RAB (Form RAB 2027).
 * Format yang berlaku untuk satu kegiatan (`untuk()`): pilihan kegiatan itu (`format_dokumen`) → pilihan admin per tahun
 * anggaran (`tahun()`, Pengaturan › Format Seragam; tersimpan di PilihanTemplateRepository `format_kak`) → bawaan tahun
 * (`bawaan()`: mulai `MULAI_LIMA_BAB` lima bab). `BAGIAN` = per format nomor → judul, label nomor tercetak, kunci isi yang
 * dimuat (kunci SuntingDokumen/TeksKhusus/BagianAi & isi lain), tempat asal isi (SusunanOtomatis::TEMPAT), dan isian yang
 * bisa dilengkapi di tempat (IsianSusun::KOLOM) — semua pemetaan nomor bagian lewat sini.
 */
final class FormatKakTor
{
    public const SEMBILAN_BAGIAN = 'sembilan_bagian';

    public const LIMA_BAB = 'lima_bab';

    /** Format → [label, keterangan]. */
    public const FORMAT = [
        self::SEMBILAN_BAGIAN => ['Format 9 bagian', 'Susunan TA 2026: sembilan bagian bernomor 1–9 (Latar Belakang sampai Lembar Pengesahan); tanda tangan Mengetahui, Pembuat, dan Menyetujui sejajar.'],
        self::LIMA_BAB => ['Format 5 bab', 'Format TOR 2027: bab I–V (Pendahuluan, Kegiatan yang Dilaksanakan, Tahapan Kegiatan, Hasil yang Akan Dicapai, Penutup); Menyetujui & Penanggungjawab berdampingan, Mengetahui di tengah bawah.'],
    ];

    /** Tahun anggaran pertama yang bawaannya format 5 bab. */
    public const MULAI_LIMA_BAB = 2027;

    /**
     * Format → nomor bagian → [judul, label nomor tercetak, kunci isi, tempat asal isi, isian di tempat].
     *
     * @var array<string, array<int, array{0: string, 1: string, 2: list<string>, 3: list<string>, 4: list<string>}>>
     */
    public const BAGIAN = [
        self::SEMBILAN_BAGIAN => [
            1 => ['Latar Belakang', '1', ['latar_belakang'], ['latar_baku', 'identitas', 'kalimat'], ['latar_belakang']],
            2 => ['Dasar Hukum', '2', ['dasar_hukum'], ['dasar_hukum'], []],
            3 => ['Tujuan dan Sasaran', '3', ['tujuan', 'sasaran'], ['identitas', 'kalimat'], ['tujuan', 'sasaran']],
            4 => ['Tahapan Kegiatan', '4', ['persiapan', 'pelaksanaan', 'uraian_pelaksanaan', 'pelaporan', 'analisis_hasil', 'matrik'], ['pelaksanaan', 'matrik'], ['tanggal_mulai', 'lama_hari', 'peserta_jumlah', 'peserta_jenis', 'uraian_pelaksanaan']],
            5 => ['Tempat Pelaksanaan', '5', ['tempat'], ['pelaksanaan'], ['tempat']],
            6 => ['Penyelenggara Kegiatan', '6', ['pelaksana', 'pengarah'], ['kode', 'profil'], []],
            7 => ['Jadwal Kegiatan', '7', ['jadwal'], ['jadwal'], []],
            8 => ['Pembiayaan', '8', ['pembiayaan'], ['rab'], []],
            9 => ['Lembar Pengesahan', '9', ['pengesahan'], ['pejabat', 'identitas'], ['tanggal_dokumen']],
        ],
        self::LIMA_BAB => [
            1 => ['Pendahuluan', 'I', ['latar_belakang', 'dasar_hukum', 'tujuan'], ['latar_baku', 'dasar_hukum', 'identitas', 'kalimat'], ['latar_belakang', 'tujuan']],
            2 => ['Kegiatan yang Dilaksanakan', 'II', ['nama_kegiatan', 'peserta', 'waktu', 'tempat', 'pembiayaan', 'penanggung_jawab', 'pelaksana', 'narasumber'], ['identitas', 'pelaksanaan', 'rab', 'kode'], ['peserta_jumlah', 'peserta_jenis', 'tanggal_mulai', 'lama_hari', 'tempat']],
            3 => ['Tahapan Kegiatan', 'III', ['persiapan', 'pelaksanaan', 'uraian_pelaksanaan', 'pelaporan', 'analisis_hasil', 'matrik'], ['pelaksanaan', 'matrik'], ['uraian_pelaksanaan']],
            4 => ['Hasil yang Akan Dicapai', 'IV', ['output', 'outcome'], ['identitas'], []],
            5 => ['Penutup', 'V', ['penutup', 'pengesahan'], ['pejabat', 'identitas'], ['tanggal_dokumen']],
        ],
    ];

    public static function sah(mixed $format): bool
    {
        return is_string($format) && isset(self::FORMAT[$format]);
    }

    public static function label(string $format): string
    {
        return self::FORMAT[$format][0];
    }

    /** Sebutan satu bagian di kalimat layar: "Bagian 4" / "Bab II". */
    public static function sebutan(string $format, int $nomor): string
    {
        return $format === self::LIMA_BAB ? 'Bab '.(self::BAGIAN[$format][$nomor][1] ?? $nomor) : "Bagian {$nomor}";
    }

    /** Bawaan tahun anggaran tanpa pilihan admin. */
    public static function bawaan(int $tahun): string
    {
        return $tahun >= self::MULAI_LIMA_BAB ? self::LIMA_BAB : self::SEMBILAN_BAGIAN;
    }

    /** Pilihan admin untuk tahun itu; null = mengikuti bawaan. */
    public static function pilihanTahun(int $tahun): ?string
    {
        $format = app(PilihanTemplateRepository::class)->untuk($tahun)['format_kak'] ?? null;

        return self::sah($format) ? $format : null;
    }

    /** Format yang berlaku untuk tahun anggaran itu (pilihan admin atau bawaan). */
    public static function tahun(int $tahun): string
    {
        return self::pilihanTahun($tahun) ?? self::bawaan($tahun);
    }

    /**
     * Format dokumen satu kegiatan: pilihan kegiatan → pilihan tahun → bawaan tahun.
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function untuk(array $kegiatan, ?int $tahun = null): string
    {
        return self::sah($kegiatan['format_dokumen'] ?? null) ? $kegiatan['format_dokumen'] : self::tahun($tahun ?? TahunKegiatan::dari($kegiatan));
    }

    /**
     * Pilihan format untuk kegiatan (dropdown): '' = ikut tahun, lalu tiap format.
     *
     * @return list<array{nilai: string, label: string, keterangan: string}>
     */
    public static function pilihan(int $tahun): array
    {
        return [
            ['nilai' => '', 'label' => 'Ikuti tahun anggaran ('.self::label(self::tahun($tahun)).')', 'keterangan' => 'Format yang dipilih admin untuk TA '.$tahun.'.'],
            ...array_map(fn (string $f) => ['nilai' => $f, 'label' => self::FORMAT[$f][0], 'keterangan' => self::FORMAT[$f][1]], array_keys(self::FORMAT)),
        ];
    }

    /**
     * Nomor bagian format itu yang memuat kunci isi $isi (null bila tidak dimuat format itu).
     */
    public static function nomor(string $format, string $isi): ?int
    {
        foreach (self::BAGIAN[$format] as $nomor => [, , $kunci]) {
            if (in_array($isi, $kunci, true)) {
                return $nomor;
            }
        }

        return null;
    }

    /** @return list<string> Kunci isi satu bagian. */
    public static function isi(string $format, int $nomor): array
    {
        return self::BAGIAN[$format][$nomor][2] ?? [];
    }

    /** @return list<string> Tempat asal isi satu bagian (SusunanOtomatis::TEMPAT). */
    public static function asal(string $format, int $nomor): array
    {
        return self::BAGIAN[$format][$nomor][3] ?? [];
    }

    /** @return list<string> Isian yang bisa dilengkapi di tempat (IsianSusun::KOLOM). */
    public static function isian(string $format, int $nomor): array
    {
        return self::BAGIAN[$format][$nomor][4] ?? [];
    }

    /**
     * Simpan pilihan format tahun anggaran (null = kembali ke bawaan). Khusus admin; tahun terkunci tidak bisa diubah.
     *
     * @param  array{role: string}  $pengguna
     * @return array{format: string, pilihan: string|null, berubah: bool}
     *
     * @throws AuthorizationException
     * @throws ValidationException Berkunci `format`.
     */
    public static function simpanTahun(int $tahun, ?string $format, array $pengguna): array
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Format dokumen diatur admin.');
        }
        if (TahunDipilih::terkunci($tahun)) {
            throw new AuthorizationException("Tahun Anggaran {$tahun} terkunci — format dokumennya hanya bisa dilihat.");
        }
        $format = $format === '' ? null : $format;
        if ($format !== null && ! self::sah($format)) {
            throw ValidationException::withMessages(['format' => 'Pilih format dokumen yang tersedia.']);
        }

        $lama = self::pilihanTahun($tahun);
        if ($lama === $format) {
            return ['format' => self::tahun($tahun), 'pilihan' => $format, 'berubah' => false];
        }
        app(PilihanTemplateRepository::class)->simpanFormat($tahun, $format);
        RiwayatPengaturan::catat('ubah_format_kak', "Format KAK/TOR TA {$tahun}: ".($lama ? self::label($lama) : 'bawaan ('.self::label(self::bawaan($tahun)).')').' → '.($format ? self::label($format) : 'bawaan ('.self::label(self::bawaan($tahun)).')').'.');

        return ['format' => self::tahun($tahun), 'pilihan' => $format, 'berubah' => true];
    }
}
