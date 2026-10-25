<?php

namespace App\Support\Kegiatan;

use Carbon\CarbonImmutable;

/**
 * State bagian Pelaksanaan satu kegiatan (waktu, tempat & peserta, bentuk) dalam bentuk ternormalkan.
 *
 * Satu sumber bagi form Data Kegiatan, pratinjau "Tercetak di KAK", susunan KAK/TOR, dan nanti Matrik & Jadwal:
 * dibangun dari isian form maupun data tersimpan (kunci sama dengan kolom tabel activities). Nilai yang tidak sah
 * (lama 0, tanggal rusak, bulan di luar 1–12) dianggap belum terisi agar turunan seperti tanggal selesai tidak salah.
 */
final readonly class DataPelaksanaan
{
    public const MAKS_LAMA_HARI = 366;

    /** Isian wajib sebelum kegiatan diajukan (urutan sama dengan KegiatanForm::PELAKSANAAN_WAJIB). */
    public const WAJIB = ['tanggal_mulai', 'lama_hari', 'bulan_pelaksanaan', 'tempat', 'peserta_jumlah', 'peserta_jenis'];

    /**
     * @param  list<int>  $bulan  Nomor bulan pelaksanaan (1–12), urut naik tanpa duplikat.
     */
    public function __construct(
        public ?CarbonImmutable $mulai = null,
        public ?int $lamaHari = null,
        public bool $hariKerja = false,
        public array $bulan = [],
        public string $tempat = '',
        public ?int $pesertaJumlah = null,
        public string $pesertaJenis = '',
        public string $uraian = '',
    ) {}

    /**
     * @param  array<string, mixed>  $data  Isian form atau data kegiatan tersimpan.
     */
    public static function dari(array $data): self
    {
        $lama = filter_var($data['lama_hari'] ?? null, FILTER_VALIDATE_INT);
        $peserta = filter_var($data['peserta_jumlah'] ?? null, FILTER_VALIDATE_INT);

        $bulan = array_values(array_unique(array_filter(
            array_map('intval', (array) ($data['bulan_pelaksanaan'] ?? [])),
            fn (int $b) => $b >= 1 && $b <= 12,
        )));
        sort($bulan);

        return new self(
            mulai: self::tanggal($data['tanggal_mulai'] ?? null),
            lamaHari: $lama !== false && $lama >= 1 && $lama <= self::MAKS_LAMA_HARI ? $lama : null,
            hariKerja: (bool) ($data['hari_kerja'] ?? false),
            bulan: $bulan,
            tempat: trim((string) ($data['tempat'] ?? '')),
            pesertaJumlah: $peserta !== false && $peserta >= 1 ? $peserta : null,
            pesertaJenis: trim((string) ($data['peserta_jenis'] ?? '')),
            uraian: trim((string) ($data['uraian_pelaksanaan'] ?? '')),
        );
    }

    /**
     * Tanggal selesai hasil hitung, atau null bila tanggal mulai/lama belum sah.
     */
    public function selesai(): ?CarbonImmutable
    {
        return $this->mulai && $this->lamaHari
            ? JadwalPelaksanaan::tanggalSelesai($this->mulai, $this->lamaHari, $this->hariKerja)
            : null;
    }

    /**
     * Bulan yang dilalui jadwal mulai–selesai, atau null bila jadwal belum lengkap.
     *
     * @return list<int>|null
     */
    public function bulanDariTanggal(): ?array
    {
        $selesai = $this->selesai();

        return $selesai ? JadwalPelaksanaan::bulanTercakup($this->mulai, $selesai) : null;
    }

    /**
     * Batas laporan baku (tanggal selesai + 10 hari), atau null bila jadwal belum lengkap.
     */
    public function batasLaporanBaku(): ?CarbonImmutable
    {
        $selesai = $this->selesai();

        return $selesai ? JadwalPelaksanaan::batasLaporan($selesai) : null;
    }

    /**
     * Teks bulan tercetak, mis. "Juli – Agustus 2026"; null bila belum ada bulan dipilih.
     */
    public function teksBulan(int $tahun): ?string
    {
        return JadwalPelaksanaan::teksBulan($this->bulan, $tahun);
    }

    /**
     * Isian wajib (lihat WAJIB) yang sudah terisi sah, berkunci nama isian.
     *
     * @return array<string, bool>
     */
    public function terisi(): array
    {
        return [
            'tanggal_mulai' => $this->mulai !== null,
            'lama_hari' => $this->lamaHari !== null,
            'bulan_pelaksanaan' => $this->bulan !== [],
            'tempat' => $this->tempat !== '',
            'peserta_jumlah' => $this->pesertaJumlah !== null,
            'peserta_jenis' => $this->pesertaJenis !== '',
        ];
    }

    /**
     * Kelengkapan tiap kelompok: waktu, tempat & peserta (lengkap/sebagian/kosong) dan bentuk (lengkap/opsional).
     *
     * @return array{waktu: string, tempat: string, bentuk: string}
     */
    public function kelengkapan(): array
    {
        $terisi = $this->terisi();
        $status = function (array $isian) use ($terisi): string {
            $jumlah = count(array_filter(array_intersect_key($terisi, array_flip($isian))));

            return match (true) {
                $jumlah === 0 => 'kosong',
                $jumlah === count($isian) => 'lengkap',
                default => 'sebagian',
            };
        };

        return [
            'waktu' => $status(['tanggal_mulai', 'lama_hari', 'bulan_pelaksanaan']),
            'tempat' => $status(['tempat', 'peserta_jumlah', 'peserta_jenis']),
            'bentuk' => $this->uraian !== '' ? 'lengkap' : 'opsional',
        ];
    }

    /**
     * Kalimat KAK bagian 4b "Pelaksanaan"; null bila tanggal mulai & lama belum lengkap.
     */
    public function kalimatPelaksanaan(string $nama): ?string
    {
        $selesai = $this->selesai();

        return $selesai
            ? KalimatKak::pelaksanaan($nama, $this->lamaHari, $this->mulai, $selesai, $this->pesertaJumlah, $this->pesertaJenis ?: null, $this->uraian ?: null)
            : null;
    }

    /**
     * Kalimat KAK bagian 5 "Tempat Pelaksanaan"; null bila tempat belum diisi.
     */
    public function kalimatTempat(string $nama): ?string
    {
        return $this->tempat !== '' ? KalimatKak::tempat($nama, $this->tempat) : null;
    }

    private static function tanggal(mixed $nilai): ?CarbonImmutable
    {
        if (! is_string($nilai) || blank($nilai) || strtotime($nilai) === false) {
            return null;
        }

        return rescue(fn () => CarbonImmutable::parse($nilai)->startOfDay(), null, false);
    }
}
