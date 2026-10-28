<?php

namespace App\Support\Dokumen;

use App\Contracts\TemplateDokumenRepository;
use App\Support\Pengaturan\ValidasiPejabat;

/**
 * Ringkasan "Susun KAK/TOR otomatis" satu kegiatan × sumber dana (PRD F4 "Susun Otomatis") untuk tab ruang kerja
 * Susun KAK/TOR: tiap bagian SusunanKak beserta status (`kurang` bila ada isian yang belum lengkap
 * (SusunanKak::$kurangPerBagian), `periksa` bila lengkap tetapi ada temuan — penanda tangan ValidasiPejabat::pengesahan
 * di blok pengesahan, RAB melebihi pagu/pagu belum diisi (PeringatanPagu) di Pembiayaan — selain itu `lengkap`), dan
 * asal isinya (`ASAL` nomor bagian → `TEMPAT`: tempat mengisi + tautan; tempat yang
 * diatur admin hanya bertautan untuk admin), kemajuan, master template KAK/TOR yang dipakai (`template()`: default tahun
 * aktif KAK+TOR → KAK → TOR, atau null = susunan baku aplikasi), dan kepala RAB (SusunanRab::$kepala) beserta kekurangannya.
 */
final readonly class SusunanOtomatis
{
    /** Tempat asal isi → [label, rute, parameter rute, jangkar, diatur admin]. Rute kegiatan menerima id kegiatan. */
    public const TEMPAT = [
        'identitas' => ['Data Kegiatan › Identitas', 'kegiatan.edit', [], '#identitas', false],
        'pelaksanaan' => ['Data Kegiatan › Pelaksanaan', 'kegiatan.edit', [], '#pelaksanaan', false],
        'panitia' => ['Data Kegiatan › Pelaksana & Panitia', 'kegiatan.edit', [], '#panitia', false],
        'kode' => ['Kode & Klasifikasi', 'kegiatan.kode', [], '', false],
        'kalimat' => ['Kalimat KAK', 'kegiatan.kalimat', [], '', false],
        'matrik' => ['Matrik & Jadwal', 'kegiatan.matrik', [], '', false],
        'jadwal' => ['Matrik & Jadwal › Jadwal', 'kegiatan.matrik', [], '#jadwal', false],
        'rab' => ['RAB', 'kegiatan.rab', [], '', false],
        'latar_baku' => ['Pengaturan › Latar belakang baku', 'pengaturan.madrasah.dasar_hukum', [], '', true],
        'dasar_hukum' => ['Pengaturan › Dasar hukum', 'pengaturan.madrasah.dasar_hukum', [], '', true],
        'profil' => ['Pengaturan › Profil madrasah', 'pengaturan.madrasah.index', [], '', true],
        'pejabat' => ['Pengaturan › Pejabat penanda tangan', 'pengaturan.madrasah.index', ['tab' => 'pejabat'], '', true],
    ];

    /** Nomor bagian KAK/TOR → tempat asal isinya, urut yang paling menentukan. */
    public const ASAL = [
        1 => ['latar_baku', 'identitas', 'kalimat'],
        2 => ['dasar_hukum'],
        3 => ['identitas', 'kalimat'],
        4 => ['pelaksanaan', 'matrik'],
        5 => ['pelaksanaan'],
        6 => ['kode', 'profil'],
        7 => ['jadwal'],
        8 => ['rab'],
        9 => ['pejabat', 'identitas'],
    ];

    /** Jenis master template KAK/TOR yang dipakai, urut pilihan. */
    public const JENIS_TEMPLATE = ['KAK+TOR', 'KAK', 'TOR'];

    /**
     * @param  list<array{nomor: int, judul: string, status: string, kurang: list<string>, periksa: list<string>, asal: list<array{kunci: string, label: string, tautan: string|null, admin: bool}>}>  $bagian
     * @param  array{id: string, nama: string, jenis: string, variabel: list<string>, diperbarui_pada: string}|null  $template
     * @param  list<array{label: string, nilai: string|null, uraian: string|null}>  $kepalaRab
     * @param  list<string>  $kurangRab
     */
    public function __construct(
        public array $bagian,
        public int $lengkap,
        public ?array $template,
        public array $kepalaRab,
        public array $kurangRab,
    ) {}

    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array{role: string}  $pengguna
     */
    public static function dari(SusunanKak $kak, SusunanRab $rab, array $kegiatan, array $pengguna, int $tahun): self
    {
        $id = filter_var($kegiatan['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $admin = ($pengguna['role'] ?? null) === 'admin';
        $bagian = [];

        foreach ($kak->bagian as $b) {
            $kurang = array_values(array_unique($kak->kurangPerBagian[$b['nomor']] ?? []));
            $periksa = self::periksa($b, $kak);
            $bagian[] = [
                'nomor' => $b['nomor'],
                'judul' => $b['judul'],
                'status' => $kurang !== [] ? 'kurang' : ($periksa !== [] ? 'periksa' : 'lengkap'),
                'kurang' => $kurang,
                'periksa' => $periksa,
                'asal' => array_map(function (string $kunci) use ($id, $admin) {
                    [$label, $rute, $param, $jangkar, $diaturAdmin] = self::TEMPAT[$kunci];
                    $boleh = $diaturAdmin ? $admin : $id !== null;

                    return [
                        'kunci' => $kunci,
                        'label' => $label,
                        'tautan' => $boleh ? route($rute, ($diaturAdmin ? [] : ['kegiatan' => $id]) + $param).$jangkar : null,
                        'admin' => $diaturAdmin,
                    ];
                }, self::ASAL[$b['nomor']] ?? []),
            ];
        }

        return new self(
            $bagian,
            count(array_filter($bagian, fn (array $b) => $b['status'] === 'lengkap')),
            self::template($tahun),
            array_map(fn (array $baris) => ['label' => $baris['label'], 'nilai' => filled($baris['nilai'] ?? null) ? (string) $baris['nilai'] : null, 'uraian' => filled($baris['uraian'] ?? null) ? (string) $baris['uraian'] : null], $rab->kepala),
            array_values(array_unique($rab->kurang)),
        );
    }

    /**
     * Temuan satu bagian yang tidak menghalangi susunan tetapi perlu diperiksa sebelum dicetak.
     *
     * @param  array{nomor: int, isi: list<array<string, mixed>>}  $bagian
     * @return list<string>
     */
    private static function periksa(array $bagian, SusunanKak $kak): array
    {
        $temuan = [];
        foreach ($bagian['isi'] as $blok) {
            if (($blok['jenis'] ?? null) === 'pengesahan') {
                array_push($temuan, ...ValidasiPejabat::pengesahan($blok['kolom'])['temuan']);
            }
            if (($blok['jenis'] ?? null) === 'pagu' && $kak->peringatanPagu !== null && $kak->peringatanPagu->nada() !== 'info') {
                $temuan[] = $kak->peringatanPagu->judul();
            }
        }

        return array_values(array_unique($temuan));
    }

    /**
     * Master KAK/TOR default tahun itu (KAK+TOR, lalu KAK, lalu TOR); null = belum ada, dokumen memakai susunan baku.
     *
     * @return array{id: string, nama: string, jenis: string, variabel: list<string>, diperbarui_pada: string}|null
     */
    public static function template(int $tahun): ?array
    {
        $semua = app(TemplateDokumenRepository::class)->semua($tahun);
        foreach (self::JENIS_TEMPLATE as $jenis) {
            $t = collect($semua)->first(fn (array $t) => $t['jenis'] === $jenis && $t['is_default']);
            if ($t !== null) {
                return array_intersect_key($t, array_flip(['id', 'nama', 'jenis', 'variabel', 'diperbarui_pada']));
            }
        }

        return null;
    }

    public function jumlah(): int
    {
        return count($this->bagian);
    }

    /** Persen bagian lengkap (dibulatkan ke bawah agar 100% hanya bila semuanya lengkap). */
    public function persen(): int
    {
        return $this->jumlah() === 0 ? 0 : (int) floor($this->lengkap / $this->jumlah() * 100);
    }
}
