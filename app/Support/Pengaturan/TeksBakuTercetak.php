<?php

namespace App\Support\Pengaturan;

use App\Contracts\DasarHukumRepository;

/**
 * Teks baku bagian 1–2 KAK/TOR satu kegiatan pada tahun $tahun persis seperti tercetak (PRD §13): paragraf latar belakang
 * baku, kalimat penutup berisi variabel kegiatan, dan butir dasar hukum — semuanya lewat TeksBaku::cetak (satu
 * lintasan; nilai kosong & penanda tak dikenal tercetak "…"). Pengaturan tahun itu dibaca sekali. Bersama hasilnya:
 * variabel kegiatan yang kosong (`kosong`, label), SK RKTM/RKAM yang masih kurang (`skKurang`), dan penanda tak dikenal
 * yang tersimpan per tempat (`asing` — mis. teks lama sebelum aturan penanda) agar dokumen mencatatnya sebagai kekurangan.
 * Dipakai SusunanKak, NilaiVariabel, dan susunan latar belakang Data Kegiatan.
 */
final class TeksBakuTercetak
{
    /**
     * @param  list<string>  $dasarHukum
     * @param  list<string>  $kosong
     * @param  array<string, list<string>>  $asing  Tempat → penanda tak dikenal.
     */
    private function __construct(
        public readonly int $tahun,
        public readonly string $latarBelakang,
        public readonly ?string $kalimatPenutup,
        public readonly array $dasarHukum,
        public readonly array $kosong,
        public readonly bool $skKurang,
        public readonly array $asing,
    ) {}

    /**
     * @param  array<string, mixed>  $kegiatan  Kalimat penutup null bila nama kegiatan belum diisi.
     */
    public static function dari(array $kegiatan, int $tahun): self
    {
        $pengaturan = app(DasarHukumRepository::class)->untukTahun($tahun);
        $nilai = TeksBaku::nilaiKegiatan($kegiatan);
        $adaNama = trim((string) ($kegiatan['nama_kegiatan'] ?? '')) !== '';
        $butir = array_column($pengaturan['dasar_hukum'], 'teks');

        $asing = array_filter([
            'paragraf latar belakang baku' => TeksBaku::penandaAsing($pengaturan['latar_belakang'], [TeksBaku::TAHUN]),
            'kalimat penutup latar belakang' => $adaNama ? TeksBaku::penandaAsing($pengaturan['kalimat_penutup'], TeksBaku::bolehPenutup()) : [],
        ]);
        foreach ($butir as $i => $teks) {
            if (($temuan = TeksBaku::penandaAsing($teks, DaftarDasarHukum::boleh())) !== []) {
                $asing['dasar hukum butir '.($i + 1)] = $temuan;
            }
        }

        return new self(
            tahun: $tahun,
            latarBelakang: TeksBaku::cetak($pengaturan['latar_belakang'], $tahun),
            kalimatPenutup: $adaNama ? TeksBaku::cetak($pengaturan['kalimat_penutup'], $tahun, $nilai) : null,
            dasarHukum: DaftarDasarHukum::cetak($butir, $tahun),
            kosong: $adaNama ? TeksBaku::kosong($pengaturan['kalimat_penutup'], $nilai) : [],
            skKurang: DaftarDasarHukum::skKurang($butir, $tahun),
            asing: $asing,
        );
    }

    /**
     * Kekurangan untuk daftar "isian kurang" dokumen, per bagian KAK (1 = latar belakang, 2 = dasar hukum).
     *
     * @return array<int, list<string>>
     */
    public function kurang(): array
    {
        $hasil = [];
        foreach ($this->kosong as $label) {
            $hasil[1][] = mb_strtolower($label).' (kalimat penutup latar belakang)';
        }
        if ($this->skKurang) {
            $hasil[2][] = 'nomor & tanggal SK RKTM/RKAM (Pengaturan › Dasar hukum)';
        }
        foreach ($this->asing as $tempat => $penanda) {
            $hasil[str_starts_with($tempat, 'dasar hukum') ? 2 : 1][] = 'penanda '.implode(', ', $penanda)." tidak dikenal di {$tempat} (Pengaturan › Dasar hukum)";
        }

        return $hasil;
    }

    /** Paragraf latar belakang lengkap untuk variabel `${latar_belakang}`: baku + kalimat penutup (bila ada nama). */
    public function latarLengkap(): string
    {
        return $this->latarBelakang.($this->kalimatPenutup !== null ? ' '.$this->kalimatPenutup : '');
    }
}
