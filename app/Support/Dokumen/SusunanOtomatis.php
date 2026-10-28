<?php

namespace App\Support\Dokumen;

use App\Support\Pengaturan\ValidasiPejabat;

/**
 * Ringkasan "Susun KAK/TOR otomatis" satu kegiatan × sumber dana (PRD F4 "Susun Otomatis") untuk tab ruang kerja
 * Susun KAK/TOR: tiap bagian SusunanKak beserta status (`kurang` bila ada isian yang belum lengkap
 * (SusunanKak::$kurangPerBagian), `periksa` bila lengkap tetapi ada temuan — penanda tangan ValidasiPejabat::pengesahan
 * di blok pengesahan, RAB melebihi pagu/pagu belum diisi (PeringatanPagu) di Pembiayaan — selain itu `lengkap`), dan
 * asal isinya (`ASAL` nomor bagian → `TEMPAT`: tempat mengisi + tautan; tempat yang
 * diatur admin hanya bertautan untuk admin), kemajuan, dan kepala RAB (SusunanRab::$kepala) beserta kekurangannya.
 * Master template per jenis dokumen lewat JenisDokumen::template().
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
        'sumber_dana' => ['Data Kegiatan › Sumber Dana', 'kegiatan.edit', [], '#sumber-dana', false],
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

    /**
     * @param  list<array{nomor: int, judul: string, status: string, kurang: list<string>, periksa: list<string>, asal: list<array{kunci: string, label: string, tautan: string|null, admin: bool}>}>  $bagian
     * @param  list<array{label: string, nilai: string|null, uraian: string|null}>  $kepalaRab
     * @param  list<string>  $kurangRab
     */
    public function __construct(
        public array $bagian,
        public int $lengkap,
        public array $kepalaRab,
        public array $kurangRab,
    ) {}

    /**
     * @param  array<string, mixed>  $kegiatan
     * @param  array{role: string}  $pengguna
     */
    public static function dari(SusunanKak $kak, SusunanRab $rab, array $kegiatan, array $pengguna): self
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
                'asal' => array_map(fn (string $kunci) => self::tempat($kunci, $id, $admin), self::ASAL[$b['nomor']] ?? []),
            ];
        }

        return new self(
            $bagian,
            count(array_filter($bagian, fn (array $b) => $b['status'] === 'lengkap')),
            array_map(fn (array $baris) => ['label' => $baris['label'], 'nilai' => filled($baris['nilai'] ?? null) ? (string) $baris['nilai'] : null, 'uraian' => filled($baris['uraian'] ?? null) ? (string) $baris['uraian'] : null], $rab->kepala),
            array_values(array_unique($rab->kurang)),
        );
    }

    /**
     * Tempat mengisi (TEMPAT) beserta tautannya untuk kegiatan ini; tempat yang diatur admin hanya bertautan bagi admin,
     * tempat di ruang kerja kegiatan hanya bila kegiatannya sudah tersimpan.
     *
     * @return array{kunci: string, label: string, tautan: string|null, admin: bool}
     */
    public static function tempat(string $kunci, ?int $kegiatanId, bool $admin): array
    {
        [$label, $rute, $param, $jangkar, $diaturAdmin] = self::TEMPAT[$kunci];
        $boleh = $diaturAdmin ? $admin : $kegiatanId !== null;

        return [
            'kunci' => $kunci,
            'label' => $label,
            'tautan' => $boleh ? route($rute, ($diaturAdmin ? [] : ['kegiatan' => $kegiatanId]) + $param).$jangkar : null,
            'admin' => $diaturAdmin,
        ];
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
