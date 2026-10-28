<?php

namespace App\Support\Kegiatan;

use App\Contracts\KegiatanRepository;
use App\Contracts\MatrikJadwalRepository;
use App\Enums\StatusKegiatan;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Simpan matrik tahapan satu kegiatan di server (PRD F10, bagian 4 KAK): aturan isian sama dengan halaman Matrik &
 * Jadwal (`aturanMatrik()`/`pesanMatrik()` — tahapan hanya MatrikTahapan::TAHAPAN, uraian butir wajib ≤
 * MatrikTahapan::MAKS_URAIAN, bulan 1–12), lalu dirapikan MatrikTahapan::dari (uraian tanpa spasi tepi, bulan tanpa
 * ganda & urut). Pembaruan sebagian: hanya tahapan yang dikirim yang diganti, tahapan lain & jadwal tetap seperti
 * tersimpan (MatrikJadwalRepository menyimpan keduanya dalam satu transaksi). Kegiatan terkunci/tidak ada ditolak.
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

        return $kegiatan;
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
        $laporan = DataMatrikJadwal::bulanLaporan($kegiatan, StubData::tahunAktif()['tahun']);

        return [
            'matrik' => $matrik,
            'jadwal' => $jadwal,
            'konsistensi' => new KonsistensiBulan(DataPelaksanaan::dari($kegiatan)->bulan, $laporan, $matrik->butir, $jadwal->baris),
        ];
    }
}
