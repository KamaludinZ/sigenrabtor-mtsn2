<?php

namespace App\Support\Dokumen;

/**
 * Ringkasan kelengkapan satu set dokumen (PRD F5 "Periksa Kelengkapan"; cover, KAK/TOR, RAB satu sumber dana) untuk
 * <x-dokumen.ringkasan-kelengkapan>: tiap bagian — cover, sembilan bagian KAK/TOR (status dari SusunanOtomatis:
 * lengkap/kurang/periksa beserta isian yang kurang & temuan periksa), dan RAB (isian kurang SusunanRab; periksa bila
 * peringatan pagu bukan info) — dengan kunci yang sama dengan daftar isi penampil (PenampilDokumen::isi: cover, kak-{n},
 * rab) dan jangkarnya di Pratinjau Dokumen; jumlah per status, persen lengkap (dibulatkan ke bawah), isian kosong wajib
 * (PenandaKosong), dan cap DRAF set dokumen.
 */
final readonly class KelengkapanDokumen
{
    /** Status → [label, ikon]. */
    public const STATUS = [
        'lengkap' => ['Lengkap', 'check-circle'],
        'kurang' => ['Perlu dilengkapi', 'alert'],
        'periksa' => ['Perlu diperiksa', 'info'],
    ];

    /**
     * @param  list<array{kunci: string, dokumen: string, label: string, status: string, kurang: list<string>, periksa: list<string>, jangkar: string}>  $bagian
     */
    public function __construct(
        public array $bagian,
        public int $wajib,
        public StatusDraf $draf,
    ) {}

    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array{role?: string}  $pengguna
     */
    public static function dari(SusunanKak $kak, SusunanRab $rab, array $kegiatan, array $pengguna, ?StatusDraf $draf = null): self
    {
        $namaKosong = blank(trim((string) ($kegiatan['nama_kegiatan'] ?? '')));
        $bagian = [[
            'kunci' => 'cover', 'dokumen' => 'cover', 'label' => 'Cover',
            'status' => $namaKosong ? 'kurang' : 'lengkap', 'kurang' => $namaKosong ? ['nama kegiatan'] : [], 'periksa' => [], 'jangkar' => 'dok-cover',
        ]];
        foreach (SusunanOtomatis::dari($kak, $rab, $kegiatan, $pengguna)->bagian as $b) {
            $bagian[] = [
                'kunci' => "kak-{$b['nomor']}", 'dokumen' => 'kak', 'label' => SusunanKak::labelBagian($b).". {$b['judul']}",
                'status' => $b['status'], 'kurang' => $b['kurang'], 'periksa' => $b['periksa'], 'jangkar' => "cetak-kak-bagian-{$b['nomor']}",
            ];
        }
        $kurangRab = array_values(array_unique($rab->kurang));
        $periksaRab = $rab->peringatanPagu !== null && $rab->peringatanPagu->nada() !== 'info' ? [$rab->peringatanPagu->judul()] : [];
        $bagian[] = [
            'kunci' => 'rab', 'dokumen' => 'rab', 'label' => 'RAB'.($rab->sumber ? " {$rab->sumber}" : ''),
            'status' => $kurangRab !== [] ? 'kurang' : ($periksaRab !== [] ? 'periksa' : 'lengkap'), 'kurang' => $kurangRab, 'periksa' => $periksaRab, 'jangkar' => 'dok-rab',
        ];

        $wajib = PenandaKosong::ringkasan(PenandaKosong::daftar($kak, $rab))['wajib'];

        return new self($bagian, $wajib, $draf ?? StatusDraf::gabung(...array_filter([$kak->statusDraf, $rab->statusDraf])));
    }

    public function jumlah(?string $status = null): int
    {
        return $status === null ? count($this->bagian) : count(array_filter($this->bagian, fn (array $b) => $b['status'] === $status));
    }

    /** Persen bagian lengkap (dibulatkan ke bawah: 100% hanya bila semuanya lengkap). */
    public function persen(): int
    {
        return $this->jumlah() === 0 ? 0 : (int) floor($this->jumlah('lengkap') / $this->jumlah() * 100);
    }

    /** Bagian per dokumen (cover|kak|rab), urut cetak. */
    public function dokumen(string $dokumen): array
    {
        return array_values(array_filter($this->bagian, fn (array $b) => $b['dokumen'] === $dokumen));
    }
}
