<?php

namespace App\Support\Master\Impor;

use App\Contracts\TahunAnggaranRepository;
use App\Support\Impor\GagalBacaImpor;
use App\Support\Master\JenisKode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * Pemrosesan & validasi berkas Excel master kode di server (PRD §14, F7) — satu jalur untuk panel *Unggah Excel*
 * (`Master\UnggahMaster`) dan penyimpanan master:
 * 1. `bacaBerkas($berkas)`: aturan berkas (`aturan()`/`pesan()`: wajib, ≤ BerkasMaster::MAKS_KB, .xlsx/.xls/.csv) dan
 *    nama sheet terbaca tanpa memuat isi (BerkasMaster::lembar) → {nama, ukuran, lembar}; `galatCakupan()` = CSV hanya
 *    untuk satu jenis & minimal satu sheet dikenali (BerkasMaster::kenali) — berkas tetap terpilih selama cakupan bisa
 *    diganti; `periksaBerkas($berkas, $cakupan)` = keduanya + sheet tiap jenis. Galat → GagalBacaImpor berpesan untuk
 *    pengguna.
 * 2. `proses($path, $namaAsli, $cakupan, $tahun, $pengguna)`: izin (admin, tahun terdaftar & tidak terkunci), isi tiap
 *    sheet dibaca pembaca jenisnya & dibandingkan dengan master tahun itu (PratinjauMaster::susun) → {sheet, pratinjau,
 *    ringkasan semua jenis, kendala, peringatan, siap}.
 * 3. `kendala($pratinjau)` = alasan master belum bisa disimpan (jenis gagal dibaca, baris gagal, jenis tanpa baris sah);
 *    `peringatan($pratinjau)` = catatan yang tidak memblokir (kode hilang yang dipakai kegiatan, catatan tingkat jenis).
 */
final class ProsesBerkasMaster
{
    /** @return array<string, list<string>> */
    public static function aturan(): array
    {
        return ['berkas' => ['required', 'file', 'max:'.BerkasMaster::MAKS_KB, 'extensions:xlsx,xls,csv']];
    }

    /** @return array<string, string> */
    public static function pesan(): array
    {
        return [
            'berkas.required' => 'Pilih berkas master kode.',
            'berkas.max' => 'Ukuran berkas maksimal '.(BerkasMaster::MAKS_KB / 1024).' MB.',
            'berkas.extensions' => 'Pilih berkas Excel (.xlsx, .xls) atau CSV.',
        ];
    }

    /**
     * Berkas memenuhi aturan dan nama sheet-nya terbaca (belum dicocokkan dengan cakupan).
     *
     * @return array{nama: string, ukuran: int, lembar: list<string>}
     *
     * @throws GagalBacaImpor
     */
    public static function bacaBerkas(?UploadedFile $berkas): array
    {
        $validator = Validator::make(['berkas' => $berkas], self::aturan(), self::pesan());
        if ($validator->fails()) {
            throw new GagalBacaImpor($validator->errors()->first('berkas'));
        }

        $nama = $berkas->getClientOriginalName();

        return ['nama' => $nama, 'ukuran' => (int) $berkas->getSize(), 'lembar' => BerkasMaster::lembar($berkas->getRealPath(), $nama)];
    }

    /**
     * bacaBerkas() lalu galatCakupan(): berkas siap diteruskan ke pratinjau beserta sheet tiap jenis.
     *
     * @return array{nama: string, ukuran: int, lembar: list<string>, sheet: array<string, string|null>}
     *
     * @throws GagalBacaImpor
     */
    public static function periksaBerkas(?UploadedFile $berkas, string $cakupan): array
    {
        $hasil = self::bacaBerkas($berkas);
        if (($galat = self::galatCakupan($hasil['lembar'], $cakupan)) !== null) {
            throw new GagalBacaImpor($galat);
        }

        return $hasil + ['sheet' => BerkasMaster::kenali($hasil['lembar'], $cakupan)];
    }

    /**
     * Galat berkas terbaca untuk cakupan $cakupan, atau null bila bisa diteruskan ke pratinjau.
     *
     * @param  list<string>  $lembar
     */
    public static function galatCakupan(array $lembar, string $cakupan): ?string
    {
        if ($cakupan === BerkasMaster::SEMUA && $lembar === ['CSV']) {
            return 'Berkas CSV hanya untuk satu jenis kode. Pilih jenisnya, atau unggah berkas .xlsx berisi semua sheet.';
        }
        if (array_filter(BerkasMaster::kenali($lembar, $cakupan)) === []) {
            return 'Tidak ada sheet yang dikenali. Beri nama sheet Perkin (SK & IKSK), SNP (RKTM), EDM, dan 5 Budaya Mutu seperti template.';
        }

        return null;
    }

    /**
     * @param  array{role: string}  $pengguna
     * @return array{sheet: array<string, string>, pratinjau: array<string, array<string, mixed>>, ringkasan: array<string, int>, kendala: list<string>, peringatan: list<string>, siap: bool}
     *
     * @throws AuthorizationException Bukan admin, tahun tidak terdaftar, atau terkunci.
     * @throws GagalBacaImpor Berkas tidak bisa dibuka atau tidak cocok dengan cakupan.
     */
    public static function proses(string $path, string $namaAsli, string $cakupan, int $tahun, array $pengguna): array
    {
        self::pastikanBoleh($tahun, $pengguna);
        if (! array_key_exists($cakupan, BerkasMaster::cakupan())) {
            throw new GagalBacaImpor('Cakupan unggahan tidak dikenal.');
        }
        $lembar = BerkasMaster::lembar($path, $namaAsli);
        if (($galat = self::galatCakupan($lembar, $cakupan)) !== null) {
            throw new GagalBacaImpor($galat);
        }

        $sheet = array_filter(BerkasMaster::kenali($lembar, $cakupan));
        $pratinjau = PratinjauMaster::susun($path, $namaAsli, $sheet, $tahun);
        $ringkasan = array_fill_keys(array_keys(PratinjauMaster::STATUS), 0);
        foreach ($pratinjau as $item) {
            foreach ($item['ringkasan'] as $status => $n) {
                $ringkasan[$status] += $n;
            }
        }
        $kendala = self::kendala($pratinjau);

        return ['sheet' => $sheet, 'pratinjau' => $pratinjau, 'ringkasan' => $ringkasan, 'kendala' => $kendala, 'peringatan' => self::peringatan($pratinjau), 'siap' => $kendala === []];
    }

    /**
     * Alasan pratinjau belum bisa disimpan menjadi master.
     *
     * @param  array<string, array<string, mixed>>  $pratinjau  PratinjauMaster::susun().
     * @return list<string>
     */
    public static function kendala(array $pratinjau): array
    {
        $hasil = [];
        foreach ($pratinjau as $jenis => $item) {
            $label = JenisKode::DAFTAR[$jenis]['label'];
            if ($item['galat'] !== null) {
                $hasil[] = "{$label}: {$item['galat']}";

                continue;
            }
            $gagal = $item['ringkasan']['gagal'] ?? 0;
            if ($gagal > 0) {
                $hasil[] = "{$label}: {$gagal} baris gagal — perbaiki berkasnya lalu unggah lagi.";
            }
            if (($item['ringkasan']['baru'] ?? 0) + ($item['ringkasan']['berubah'] ?? 0) + ($item['ringkasan']['tetap'] ?? 0) === 0) {
                $hasil[] = "{$label}: tidak ada baris yang bisa disimpan.";
            }
        }

        return $hasil;
    }

    /**
     * Catatan yang tidak memblokir: kode master yang hilang dari berkas padahal dipakai kegiatan, dan catatan tingkat
     * jenis dari pembacanya.
     *
     * @param  array<string, array<string, mixed>>  $pratinjau
     * @return list<string>
     */
    public static function peringatan(array $pratinjau): array
    {
        $hasil = [];
        foreach ($pratinjau as $jenis => $item) {
            $label = JenisKode::DAFTAR[$jenis]['label'];
            $hilang = array_filter($item['baris'], fn (array $b) => $b['status'] === 'hilang' && $b['dipakai'] > 0);
            if ($hilang !== []) {
                $hasil[] = "{$label}: ".count($hilang).' kode yang dipakai kegiatan tidak ada di berkas ('.implode(', ', array_column($hilang, 'kode')).').';
            }
            foreach ($item['peringatan'] as $p) {
                $hasil[] = "{$label}: {$p}";
            }
        }

        return $hasil;
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanBoleh(int $tahun, array $pengguna): void
    {
        $data = collect(app(TahunAnggaranRepository::class)->semua())->firstWhere('tahun', $tahun);
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Master kode diunggah admin.');
        }
        if ($data === null) {
            throw new AuthorizationException("Tahun Anggaran {$tahun} belum dibuat.");
        }
        if ($data['is_locked']) {
            throw new AuthorizationException("Tahun Anggaran {$tahun} terkunci — master kodenya hanya bisa dilihat.");
        }
    }
}
