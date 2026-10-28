<?php

namespace App\Support\Kegiatan;

use App\Contracts\PenyusunUsulanJadwal;
use App\Support\Pengaturan\ParameterAi;
use App\Support\Pengaturan\PromptAi;
use App\Support\Stub\StubData;
use App\Support\Stub\StubPengaturan;
use App\Support\Teks;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Saran jadwal AI di server (PRD F10 "Usul Jadwal AI", F23–F25) — alur yang dipakai panel usulan halaman Matrik & Jadwal:
 * permintaan (jenis kegiatan dari KonteksAi::jenisKegiatan, instruksi ≤ 300) diperiksa (`aturan()`), bagian `jadwal`
 * nonaktif atau batas harian habis ditolak, bahan disusun PayloadAi (tanpa NIP, nama pejabat, pagu), dijalankan lewat
 * RantaiPenyediaAi (aktif → cadangan; tanpa penyedia = tiruan StubUsulanJadwal), lalu `rapikan()` (maks. jumlah versi
 * ParameterAi & 3, ≤ JadwalKegiatan::MAKS_BARIS baris berurai satu baris ≤ MAKS_URAIAN, bulan 1–12, "s.d." sesudah
 * mulai). Permintaan yang dijawab penyedia dihitung BatasAi dan dicatat di Riwayat Saran AI; `putuskan()` mencatat versi
 * yang dipakai atau penolakan. Kegagalan apa pun → GagalUsulanAi berpesan siap tampil.
 */
final class SaranJadwalAi
{
    public const MAKS_INSTRUKSI = 300;

    /** @return array<string, list<mixed>> */
    public static function aturan(): array
    {
        return [
            'jenis' => ['required', Rule::in(array_keys(KonteksAi::jenisKegiatan()))],
            'instruksi' => ['nullable', 'string', 'max:'.self::MAKS_INSTRUKSI],
        ];
    }

    /** @return array<string, string> */
    public static function pesan(): array
    {
        return [
            'jenis.required' => 'Pilih jenis kegiatan.',
            'jenis.in' => 'Pilih jenis kegiatan dari daftar.',
            'instruksi.max' => 'Instruksi maksimal :max karakter.',
        ];
    }

    /**
     * @param  array<string, mixed>  $kegiatan  Data KegiatanRepository.
     * @param  array{id: int, role: string}  $pengguna
     * @return array{versi: list<array{judul: string, ciri: string, keterangan: string, baris: list<array{uraian: string, mulai: int|null, sampai: int|null}>}>, catatan: string|null, penyedia: string, permintaan: string}
     *
     * @throws ValidationException Berkunci `jenis`/`instruksi`.
     * @throws GagalUsulanAi Bagian nonaktif, batas harian habis, penyedia gagal, atau tidak ada usulan yang bisa dipakai.
     */
    public static function minta(array $kegiatan, array $pengguna, string $jenis, string $instruksi = ''): array
    {
        Validator::make(['jenis' => $jenis, 'instruksi' => $instruksi], self::aturan(), self::pesan())->validate();
        if (! PromptAi::aktif('jadwal')) {
            throw new GagalUsulanAi('Usulan jadwal AI dinonaktifkan admin di Pengaturan AI.');
        }
        if (BatasAi::sisa($pengguna) <= 0) {
            throw new GagalUsulanAi(BatasAi::pesanHabis($pengguna));
        }

        $tahun = StubData::tahunAktif()['tahun'];
        $acuan = DataMatrikJadwal::acuan(DataPelaksanaan::dari($kegiatan)->bulan, DataMatrikJadwal::bulanLaporan($kegiatan, $tahun));
        // Muatan AI lewat PayloadAi: NIP, nama pejabat, dan pagu tidak pernah ikut terkirim.
        $permintaan = PayloadAi::usulanJadwal((int) $kegiatan['id'], $kegiatan, $jenis, Teks::rapikan($instruksi), $acuan);

        try {
            $jalan = RantaiPenyediaAi::jalankan(fn () => app(PenyusunUsulanJadwal::class)->usulkan($permintaan));
        } catch (GagalPenyediaAi $e) {
            throw new GagalUsulanAi($e->getMessage().($e->simulasi && $pengguna['role'] === 'admin' ? ' (simulasi kegagalan dari Pengaturan AI)' : ''), previous: $e);
        }
        BatasAi::catat($pengguna, 'usul_jadwal');

        $versi = self::rapikan($jalan['hasil']);
        if ($versi === []) {
            throw new GagalUsulanAi('AI tidak memberi usulan yang bisa dipakai. Coba lagi atau ubah instruksi.');
        }

        $prompt = implode("\n", [
            'Bagian: Jadwal kegiatan (Bagian 7 · Jadwal Kegiatan)',
            'Jenis kegiatan: '.(KonteksAi::jenisKegiatan()[$permintaan->jenis] ?? $permintaan->jenis),
            'Instruksi: '.($permintaan->instruksi ?: '-'),
            'Bahan kegiatan:',
            $permintaan->bahan,
        ]);
        $riwayat = array_map(fn (array $v) => ['judul' => $v['judul'], 'ciri' => $v['ciri'], 'teks' => self::teks($v, $tahun)], $versi);

        return [
            'versi' => $versi,
            'catatan' => RantaiPenyediaAi::catatan($jalan),
            'penyedia' => $jalan['penyedia'],
            'permintaan' => RiwayatSaranAi::catat((int) $kegiatan['id'], 'jadwal', $prompt, $permintaan->instruksi, $riwayat, $pengguna, $jalan['penyedia']),
        ];
    }

    /** Catat keputusan atas satu permintaan: nomor versi (0..) yang dipakai, atau null = semua ditolak. */
    public static function putuskan(?string $permintaan, ?int $versi): void
    {
        if ($permintaan !== null) {
            RiwayatSaranAi::putuskan($permintaan, $versi);
        }
    }

    /**
     * Hasil penyusun (bisa langsung dari model AI) dirapikan: versi bernomor ulang, baris berurai satu baris.
     *
     * @param  array<mixed>  $versi
     * @return list<array{judul: string, ciri: string, keterangan: string, baris: list<array{uraian: string, mulai: int|null, sampai: int|null}>}>
     */
    public static function rapikan(array $versi): array
    {
        $satuBaris = fn (mixed $teks) => trim((string) preg_replace('/\s+/u', ' ', is_scalar($teks) ? (string) $teks : ''));
        $maksVersi = min(3, ParameterAi::dari(StubPengaturan::pengaturanAi())['jumlah_versi']);
        $hasil = [];

        foreach (array_filter($versi, 'is_array') as $v) {
            $baris = [];
            foreach (array_filter(is_array($v['baris'] ?? null) ? $v['baris'] : [], 'is_array') as $b) {
                $uraian = Str::limit($satuBaris($b['uraian'] ?? ''), JadwalKegiatan::MAKS_URAIAN, '');
                $mulai = SimpanMatrikJadwal::bulanAtauNull($b['mulai'] ?? null);
                $sampai = SimpanMatrikJadwal::bulanAtauNull($b['sampai'] ?? null);
                if ($uraian !== '' && count($baris) < JadwalKegiatan::MAKS_BARIS) {
                    $baris[] = ['uraian' => $uraian, 'mulai' => $mulai, 'sampai' => $mulai !== null && $sampai !== null && $sampai > $mulai ? $sampai : null];
                }
            }

            if ($baris !== [] && count($hasil) < $maksVersi) {
                $hasil[] = [
                    'judul' => 'Versi '.(count($hasil) + 1),
                    'ciri' => Str::limit($satuBaris($v['ciri'] ?? ''), 40),
                    'keterangan' => Str::limit($satuBaris($v['keterangan'] ?? ''), 160),
                    'baris' => $baris,
                ];
            }
        }

        return $hasil;
    }

    /** Teks satu versi untuk Riwayat Saran AI: baris "uraian — bulan". */
    private static function teks(array $versi, int $tahun): string
    {
        return implode("\n", array_map(
            fn (array $b) => $b['uraian'].' — '.(JadwalPelaksanaan::teksBulan($b['mulai'] ? range($b['mulai'], $b['sampai'] ?? $b['mulai']) : [], $tahun) ?? 'bulan belum diisi'),
            $versi['baris'],
        ));
    }
}
