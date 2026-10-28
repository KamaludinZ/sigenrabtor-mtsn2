<?php

namespace App\Support\Dokumen;

use App\Support\Format;
use App\Support\Stub\StubData;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Keputusan cetak satu set dokumen kegiatan × sumber dana (PRD aturan 8 & F12): dokumen selalu boleh dicetak, tetapi
 * bercap DRAF bila StatusDraf KAK/TOR atau RAB-nya DRAF (belum disetujui, isian kurang, RAB melebihi pagu). Satu-satunya
 * tempat menggabungkan cap KAK/TOR + RAB (cover ikut set) — dipakai Pratinjau Dokumen, Buku Dokumen, susun buku, dan
 * kelak pembuat Word/PDF — beserta keluaran cetaknya: tanda air `DRAF` di setiap halaman, nama berkas bersufiks `_DRAF`,
 * kaki halaman berisi alasan pertama & waktu cetak, serta saran cara menghilangkan tiap alasan (`saran()`).
 */
final readonly class StatusCetak
{
    /** Saran per kunci alasan StatusDraf (status | kurang | pagu). */
    public const SARAN = [
        'status' => 'Ajukan pemeriksaan dari Data Kegiatan — cap hilang setelah disetujui admin.',
        'kurang' => 'Lengkapi lewat checklist Isian wajib.',
        'pagu' => 'Sesuaikan rincian RAB atau pagunya.',
    ];

    public const TANDA_AIR = 'DRAF';

    public function __construct(
        public int $kegiatanId,
        public ?string $sumber,
        public string $namaKegiatan,
        public StatusDraf $kak,
        public StatusDraf $rab,
        public StatusDraf $set,
    ) {}

    /**
     * @param  array<string, mixed>  $kegiatan  Data KegiatanRepository.
     * @param  string|null  $sumber  null = sumber dana pertama kegiatan (seperti SusunanKak).
     * @param  SusunanKak|null  $kak  Susunan yang sudah dibuat pemanggil (agar tidak disusun dua kali).
     */
    public static function dari(array $kegiatan, ?string $sumber, ?SusunanKak $kak = null, ?SusunanRab $rab = null): self
    {
        $kak ??= SusunanKak::dari($kegiatan, $sumber);
        $rab ??= SusunanRab::dari($kegiatan, $sumber);

        return new self(
            (int) ($kegiatan['id'] ?? 0),
            $kak->sumber ?? $sumber,
            trim((string) ($kegiatan['nama_kegiatan'] ?? '')),
            $kak->statusDraf,
            $rab->statusDraf,
            StatusDraf::gabung($kak->statusDraf, $rab->statusDraf),
        );
    }

    /**
     * Keputusan cetak setiap sumber dana yang dipilih kegiatan (satu set tanpa sumber bila belum memilih).
     *
     * @param  array<string, mixed>  $kegiatan
     * @return list<self>
     */
    public static function semua(array $kegiatan): array
    {
        $sumber = array_values(array_unique(array_column($kegiatan['sumber_dana'] ?? [], 'sumber')));

        return array_map(fn (?string $s) => self::dari($kegiatan, $s), $sumber ?: [null]);
    }

    public function draf(): bool
    {
        return $this->set->draf;
    }

    /** Tanda air setiap halaman tercetak; null bila siap dicetak. */
    public function tandaAir(): ?string
    {
        return $this->draf() ? self::TANDA_AIR : null;
    }

    /** "Tercetak sebagai DRAF" / "Siap dicetak". */
    public function keadaan(): string
    {
        return $this->draf() ? 'Tercetak sebagai DRAF' : 'Siap dicetak';
    }

    /**
     * Nama berkas set dokumen, mis. "KAK-TOR_RAB_Review_RKJM_dan_Penyusunan_RKTM_KOMITE_2026_DRAF.pdf".
     *
     * @param  string  $jenis  'KAK-TOR_RAB', 'KAK-TOR', atau 'RAB'.
     */
    public function namaBerkas(string $jenis = 'KAK-TOR_RAB', string $ekstensi = 'pdf'): string
    {
        $nama = trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $this->namaKegiatan), '_') ?: 'Kegiatan_'.$this->kegiatanId;
        $nama = rtrim(mb_substr($nama, 0, 60), '_');
        $bagian = [$jenis, $nama, ...($this->sumber ? [mb_strtoupper($this->sumber)] : []), (string) StubData::tahunAktif()['tahun']];

        return implode('_', $bagian).($this->draf() ? '_'.self::TANDA_AIR : '').'.'.$ekstensi;
    }

    /** Kaki halaman dokumen DRAF: "DRAF — dicetak Rabu, 28 Oktober 2026 pukul 09.15 · Belum disetujui admin (status Draf)."; null bila siap. */
    public function kakiHalaman(?DateTimeInterface $waktu = null): ?string
    {
        if (! $this->draf()) {
            return null;
        }
        $waktu = CarbonImmutable::instance($waktu ?? now())->setTimezone(config('app.timezone'));

        return self::TANDA_AIR.' — dicetak '.Format::tanggalPanjang($waktu->toDateString()).' pukul '.$waktu->format('H.i')
            .' · '.$this->set->alasan[0]['teks'];
    }

    /**
     * Alasan DRAF beserta saran & tautan cara menghilangkannya (tautan `#checklist-isian` = jangkar di halaman pratinjau).
     *
     * @return list<array{kunci: string, teks: string, saran: string, tautan: string}>
     */
    public function saran(): array
    {
        return array_map(fn (array $a) => $a + [
            'saran' => self::SARAN[$a['kunci']],
            'tautan' => match ($a['kunci']) {
                'status' => route('kegiatan.edit', ['kegiatan' => $this->kegiatanId]),
                'kurang' => '#checklist-isian',
                'pagu' => route('kegiatan.rab', array_filter(['kegiatan' => $this->kegiatanId, 'sumber' => $this->sumber])),
            },
        ], $this->set->alasan);
    }

    /** Bentuk JSON endpoint `kegiatan.status_cetak`. */
    public function untukJson(?DateTimeInterface $waktu = null): array
    {
        return [
            'sumber' => $this->sumber,
            'draf' => $this->draf(),
            'keadaan' => $this->keadaan(),
            'tanda_air' => $this->tandaAir(),
            'nama_berkas' => $this->namaBerkas(),
            'kaki_halaman' => $this->kakiHalaman($waktu),
            'alasan' => $this->saran(),
            'dokumen' => ['kak' => ['draf' => $this->kak->draf, 'alasan' => array_column($this->kak->alasan, 'kunci')], 'rab' => ['draf' => $this->rab->draf, 'alasan' => array_column($this->rab->alasan, 'kunci')]],
        ];
    }
}
