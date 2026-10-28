<?php

namespace App\Support\Dokumen;

use App\Contracts\KegiatanRepository;
use App\Support\Kegiatan\TeksKhusus;
use App\Support\Kegiatan\UbahDataKegiatan;
use App\Support\Pengaturan\PengaturanBerubah;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Sunting teks langsung di pratinjau (PRD F5 "Edit Teks Langsung — perbaiki kalimat pada bagian KAK; perubahan
 * tersimpan di data kegiatan"): teks KAK/TOR yang berasal dari satu isian kegiatan (IsianSusun::KOLOM) atau teks baku
 * yang bisa disesuaikan (TeksKhusus::BIDANG). `BIDANG` kunci → [nomor bagian, asal isian|teks_khusus, petunjuk]; blok
 * SusunanKak yang menampilkannya membawa `sunting` (daftar: `sunting_butir`). Bidang opsional yang belum tampil di
 * dokumen (latar belakang khusus) ditawarkan sebagai "Tambah …" (`tambahan()`). Disimpan lewat UbahDataKegiatan (aturan,
 * kunci status/tahun, & bentrok versi yang sama dengan Data Kegiatan) dan dicatat RiwayatSunting bila teksnya berubah;
 * kalimat lain (tanggal, peserta, jabatan, angka) tetap disusun dari isian & master.
 */
final class SuntingDokumen
{
    /** Kunci → [nomor bagian KAK/TOR, asal isian|teks_khusus, petunjuk di penyunting]. */
    public const BIDANG = [
        'latar_belakang' => [1, 'isian', 'Paragraf khusus kegiatan ini — tercetak di antara paragraf baku dan kalimat penutup. Kosongkan untuk menghapusnya.'],
        'tujuan' => [3, 'isian', 'Tercetak "Kegiatan … bertujuan …" — tulis lanjutan kalimatnya.'],
        'sasaran' => [3, 'isian', 'Tercetak sebagai kalimat sasaran kegiatan.'],
        'persiapan' => [4, 'teks_khusus', 'Satu butir per baris. Tulis {nama_kegiatan} agar diisi nama kegiatan. Kosongkan untuk kembali ke teks baku.'],
        'uraian_pelaksanaan' => [4, 'isian', 'Bentuk pelaksanaan di akhir kalimat 4b. Tanggal, lama, dan peserta diatur di Data Kegiatan › Pelaksanaan.'],
        'analisis_hasil' => [4, 'teks_khusus', 'Butir pertama pelaporan (4c). Tulis {nama_kegiatan} agar diisi nama kegiatan. Kosongkan untuk kembali ke teks baku.'],
        'tempat' => [5, 'isian', 'Tercetak "Kegiatan … bertempat di …".'],
        'pengarah' => [6, 'teks_khusus', 'Kalimat 6b. Tulis {nama_kegiatan} agar diisi nama kegiatan. Kosongkan untuk kembali ke teks baku.'],
    ];

    public static function label(string $kunci): string
    {
        return self::BIDANG[$kunci][1] === 'teks_khusus' ? TeksKhusus::BIDANG[$kunci][1] : IsianSusun::KOLOM[$kunci][0];
    }

    /** Jenis isian penyunting: teks (satu baris), uraian (paragraf), daftar (satu butir per baris). */
    public static function jenis(string $kunci): string
    {
        return self::BIDANG[$kunci][1] === 'teks_khusus'
            ? (TeksKhusus::BIDANG[$kunci][2] === 'daftar' ? 'daftar' : 'uraian')
            : IsianSusun::KOLOM[$kunci][1];
    }

    /** Panjang maksimal (per butir untuk daftar). */
    public static function maks(string $kunci): int
    {
        return self::BIDANG[$kunci][1] === 'teks_khusus' ? TeksKhusus::BIDANG[$kunci][4] : IsianSusun::KOLOM[$kunci][2];
    }

    /** Panjang maksimal seluruh teks bidang (daftar: semua butir + pemisah baris) — batas jejak RiwayatSunting di database. */
    public static function maksTeks(string $kunci): int
    {
        return self::jenis($kunci) === 'daftar' ? self::maks($kunci) * TeksKhusus::MAKS_BUTIR + TeksKhusus::MAKS_BUTIR - 1 : self::maks($kunci);
    }

    public static function petunjuk(string $kunci): string
    {
        return self::BIDANG[$kunci][2];
    }

    /** @return list<string> Kunci bidang satu bagian KAK/TOR. */
    public static function untukBagian(int $nomor): array
    {
        return array_keys(array_filter(self::BIDANG, fn (array $b) => $b[0] === $nomor));
    }

    /**
     * Bidang bagian ini yang belum tampil di dokumen (tidak ada blok bersunting itu) — ditawarkan "Tambah …".
     *
     * @param  array{isi: list<array<string, mixed>>}  $bagian  Satu bagian SusunanKak.
     * @return list<string>
     */
    public static function tambahan(array $bagian): array
    {
        $tampil = collect($bagian['isi'])->flatMap(fn (array $b) => [...(isset($b['sunting']) ? [$b['sunting']] : []), ...($b['sunting_butir'] ?? [])])->all();

        return array_values(array_diff(self::untukBagian($bagian['nomor']), $tampil));
    }

    /**
     * Teks awal penyunting: isian tersimpan, atau teks khusus tersimpan/baku berpenanda (daftar: satu butir per baris).
     *
     * @param  array<string, mixed>  $kegiatan
     */
    public static function teks(array $kegiatan, string $kunci): string
    {
        if (self::BIDANG[$kunci][1] === 'isian') {
            return is_scalar($kegiatan[$kunci] ?? null) ? (string) $kegiatan[$kunci] : '';
        }
        $teks = TeksKhusus::dari($kegiatan)[$kunci] ?? TeksKhusus::bakuMentah($kunci);

        return is_array($teks) ? implode("\n", $teks) : $teks;
    }

    /**
     * Kiriman UbahDataKegiatan untuk teks bidang ini: isian (kosong = null) atau seluruh teks khusus kegiatan dengan
     * bidang ini diganti (kosong = kembali ke teks baku; teks lain yang sudah disesuaikan tetap).
     *
     * @param  array<string, mixed>  $kegiatan
     * @return array<string, mixed>
     */
    public static function kiriman(array $kegiatan, string $kunci, string $teks): array
    {
        $teks = trim($teks);
        if (self::BIDANG[$kunci][1] === 'isian') {
            return [$kunci => $teks === '' ? null : $teks];
        }
        $semua = TeksKhusus::dari($kegiatan);
        if ($teks === '') {
            unset($semua[$kunci]);
        } else {
            $semua[$kunci] = $teks;
        }

        return ['teks_khusus' => $semua];
    }

    /**
     * Simpan teks satu bidang ke data kegiatan.
     *
     * @param  array<string, mixed>  $kegiatan  Data tersimpan saat penyunting dibuka.
     * @param  array<string, mixed>  $pengguna
     * @return array{kegiatan: array<string, mixed>, diubah: list<string>, pesan: string}
     *
     * @throws ValidationException Pesan aturan isian (kunci kolom data kegiatan).
     * @throws AuthorizationException Bukan cakupan akun atau kegiatan terkunci.
     * @throws PengaturanBerubah Kegiatan berubah di tempat lain sejak $versi.
     * @throws RuntimeException Kegiatan tidak ada.
     */
    public static function simpan(int $id, array $kegiatan, string $kunci, string $teks, array $pengguna, ?string $versi): array
    {
        if (! isset(self::BIDANG[$kunci])) {
            throw ValidationException::withMessages(['kunci' => 'Teks ini tidak bisa disunting di pratinjau.']);
        }
        $sebelum = app(KegiatanRepository::class)->temukan($id) ?? $kegiatan;
        $hasil = UbahDataKegiatan::ubah($id, self::kiriman($kegiatan, $kunci, $teks), $pengguna, $versi);
        if ($hasil['diubah'] !== []) {
            RiwayatSunting::catat($id, $sebelum, $hasil['kegiatan'], $kunci, $pengguna);
        }
        $label = self::label($kunci);
        $baku = self::BIDANG[$kunci][1] === 'teks_khusus' && ! TeksKhusus::disesuaikan($kunci, $hasil['kegiatan']);

        return array_merge($hasil, ['pesan' => match (true) {
            $hasil['diubah'] === [] => "{$label} tidak berubah.",
            $baku => "{$label} kembali memakai teks baku.",
            default => "{$label} diperbarui — tersimpan di data kegiatan.",
        }]);
    }
}
