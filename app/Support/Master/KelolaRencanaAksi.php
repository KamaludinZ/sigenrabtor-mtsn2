<?php

namespace App\Support\Master;

use App\Contracts\MasterKodeRepository;
use App\Contracts\TahunAnggaranRepository;
use App\Support\Master\Impor\SimpanMasterKode;
use App\Support\MasterKode;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Pengaturan\RiwayatPengaturan;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Sunting tag kode satu rencana aksi di Master Kode (khusus admin, tahun tidak terkunci): SK, IKSK (indikator SK itu),
 * RKTM, leading, EDM (null = otomatis dari relasi EDM ↔ RKTM, selain itu daftar kode), dan 5 Budaya Mutu. Setiap kode
 * harus ada di master tahun itu (leading: aktif, atau leading lama yang tersimpan). Versi = SimpanMasterKode::sidik
 * rencana aksi tahun itu (`versi()`); usang → PengaturanBerubah. Disimpan lewat MasterKodeRepository::ganti (rencana aksi
 * lain utuh) + riwayat `ubah_tag_rencana_aksi` hanya bila ada perubahan. Dipakai tab Rencana Aksi halaman Master Kode
 * dan endpoint `master.rencana_aksi.tag`.
 */
final class KelolaRencanaAksi
{
    /** Isian tag → label. */
    public const TAG = [
        'kode_sk' => 'SK', 'kode_iksk' => 'IKSK', 'kode_rktm' => 'RKTM', 'kode_leading' => 'Leading',
        'kode_edm' => 'EDM', 'budaya_mutu' => '5 Budaya Mutu',
    ];

    public static function versi(int $tahun): string
    {
        return SimpanMasterKode::sidik($tahun, ['rencana_aksi']);
    }

    /**
     * Isian tag dari kiriman formulir/API: kosong = null; EDM `otomatis` true → null; daftar dirapikan tanpa dobel.
     *
     * @param  array<string, mixed>  $isian
     * @return array{kode_sk: string|null, kode_iksk: string|null, kode_rktm: string|null, kode_leading: string|null, kode_edm: list<string>|null, budaya_mutu: list<int>}
     */
    public static function rapikan(array $isian): array
    {
        $teks = fn (string $k) => filled($isian[$k] ?? null) ? trim((string) $isian[$k]) : null;
        $edm = ($isian['edm_otomatis'] ?? false) || ! array_key_exists('kode_edm', $isian) || $isian['kode_edm'] === null
            ? null
            : array_values(array_unique(array_filter(array_map(fn ($k) => mb_strtoupper(trim((string) $k)), (array) $isian['kode_edm']))));
        $budaya = array_values(array_unique(array_map('intval', array_filter((array) ($isian['budaya_mutu'] ?? []), fn ($n) => filled($n)))));
        sort($budaya);

        return [
            'kode_sk' => $teks('kode_sk') === null ? null : (FormatKode::sk($teks('kode_sk')) ?? $teks('kode_sk')),
            'kode_iksk' => $teks('kode_iksk') === null ? null : (FormatKode::iksk($teks('kode_iksk')) ?? $teks('kode_iksk')),
            'kode_rktm' => $teks('kode_rktm'),
            'kode_leading' => $teks('kode_leading'),
            'kode_edm' => $edm,
            'budaya_mutu' => $budaya,
        ];
    }

    /**
     * Pesan galat per isian tag (kosong = sah).
     *
     * @param  array<string, mixed>  $tag  Hasil rapikan().
     * @param  array<string, mixed>  $lama  Rencana aksi tersimpan.
     * @return array<string, string>
     */
    public static function galat(array $tag, array $lama, int $tahun): array
    {
        $galat = [];
        $ada = fn (string $jenis, string|int $kode) => MasterKode::cari(JenisKode::baris($jenis, $tahun), $kode, JenisKode::DAFTAR[$jenis]['kunci']) !== null;
        if ($tag['kode_sk'] !== null && ! $ada('sk', $tag['kode_sk'])) {
            $galat['kode_sk'] = "SK {$tag['kode_sk']} tidak ada di master TA {$tahun}.";
        }
        if ($tag['kode_iksk'] !== null) {
            if (! $ada('iksk', $tag['kode_iksk'])) {
                $galat['kode_iksk'] = "IKSK {$tag['kode_iksk']} tidak ada di master TA {$tahun}.";
            } elseif ($tag['kode_sk'] === null) {
                $galat['kode_iksk'] = 'Pilih SK terlebih dahulu.';
            } elseif (($induk = IkskPerSk::induk($tag['kode_iksk'], $tahun)) !== null && $induk !== $tag['kode_sk']) {
                $galat['kode_iksk'] = "{$tag['kode_iksk']} bukan indikator dari {$tag['kode_sk']}.";
            }
        }
        if ($tag['kode_rktm'] !== null && ! $ada('rktm', $tag['kode_rktm'])) {
            $galat['kode_rktm'] = "RKTM {$tag['kode_rktm']} tidak ada di master TA {$tahun}.";
        }
        if ($tag['kode_leading'] !== null && $tag['kode_leading'] !== ($lama['kode_leading'] ?? null)
            && MasterKode::cari(MasterKode::leadings(), $tag['kode_leading']) === null) {
            $galat['kode_leading'] = 'Pilih leading yang aktif.';
        }
        foreach ($tag['kode_edm'] ?? [] as $edm) {
            if (! $ada('edm', $edm)) {
                $galat['kode_edm'] = "EDM {$edm} tidak ada di master TA {$tahun}.";
                break;
            }
        }
        foreach ($tag['budaya_mutu'] as $nomor) {
            if (! $ada('budaya', $nomor)) {
                $galat['budaya_mutu'] = "5 Budaya Mutu nomor {$nomor} tidak ada di master TA {$tahun}.";
                break;
            }
        }

        return $galat;
    }

    /**
     * @param  array<string, mixed>  $isian
     * @param  array{id?: int, name?: string, role: string}  $pengguna
     * @return array{rencana_aksi: array<string, mixed>, berubah: bool, versi: string, pesan: string}
     *
     * @throws AuthorizationException Bukan admin, tahun belum dibuat/terkunci.
     * @throws ValidationException Berkunci isian tag.
     * @throws PengaturanBerubah Rencana aksi tahun itu berubah sejak dimuat.
     * @throws RuntimeException Rencana aksi tidak ada / penyimpanan gagal.
     */
    public static function ubahTag(int $tahun, string $kode, array $isian, array $pengguna, ?string $versi = null): array
    {
        self::pastikanBoleh($tahun, $pengguna);
        if ($versi !== null && $versi !== self::versi($tahun)) {
            throw new PengaturanBerubah("Rencana aksi TA {$tahun} baru saja diubah di tempat lain.");
        }

        $semua = MasterKode::rencanaAksi($tahun);
        $indeks = null;
        foreach ($semua as $i => $ra) {
            if ($ra['kode'] === $kode) {
                $indeks = $i;
                break;
            }
        }
        if ($indeks === null) {
            throw new RuntimeException("Rencana aksi {$kode} tidak ada di master TA {$tahun}.");
        }

        $lama = $semua[$indeks];
        $tag = self::rapikan($isian);
        if (($galat = self::galat($tag, $lama, $tahun)) !== []) {
            throw ValidationException::withMessages($galat);
        }

        $baru = array_merge($lama, $tag);
        $perubahan = self::perubahan($lama, $baru);
        if ($perubahan === []) {
            return ['rencana_aksi' => $lama, 'berubah' => false, 'versi' => self::versi($tahun), 'pesan' => 'Tag tidak berubah.'];
        }

        $semua[$indeks] = $baru;
        app(MasterKodeRepository::class)->ganti($tahun, ['rencana_aksi' => $semua]);
        RiwayatPengaturan::catat('ubah_tag_rencana_aksi', "{$kode} {$lama['uraian']} (TA {$tahun}) — ".implode('; ', $perubahan));

        return ['rencana_aksi' => $baru, 'berubah' => true, 'versi' => self::versi($tahun), 'pesan' => "Tag {$kode} tersimpan."];
    }

    /**
     * Sesuaikan tag RKTM rencana aksi yang kodenya tidak ada di master tahun itu (kode dinomori ulang): pengganti lewat
     * nama sub kegiatan kode lama di tahun lain, atau nama rencana aksi = nama sub kegiatan (PetaRktm::pengganti). Tag
     * EDM otomatis ikut kode baru. Riwayat `ubah_tag_rencana_aksi` bila ada yang berubah.
     *
     * @param  array{role: string}  $pengguna
     * @return array{diubah: list<array{kode: string, lama: string, baru: string}>, tersisa: int, versi: string}
     *
     * @throws AuthorizationException
     */
    public static function sesuaikanRktm(int $tahun, array $pengguna): array
    {
        self::pastikanBoleh($tahun, $pengguna);
        $semua = MasterKode::rencanaAksi($tahun);
        $ada = array_column(MasterKode::kodeRktm($tahun), 'kode', 'kode');
        $diubah = [];
        $tersisa = 0;
        foreach ($semua as $i => $ra) {
            $kode = $ra['kode_rktm'] ?? null;
            if ($kode === null || isset($ada[$kode])) {
                continue;
            }
            $baru = PetaRktm::pengganti($kode, $tahun) ?? PetaRktm::pengganti($kode, $tahun, nama: $ra['uraian']);
            if ($baru === null) {
                $tersisa++;

                continue;
            }
            $semua[$i]['kode_rktm'] = $baru;
            $diubah[] = ['kode' => $ra['kode'], 'lama' => $kode, 'baru' => $baru];
        }
        if ($diubah !== []) {
            app(MasterKodeRepository::class)->ganti($tahun, ['rencana_aksi' => $semua]);
            RiwayatPengaturan::catat('ubah_tag_rencana_aksi', "Tag RKTM rencana aksi TA {$tahun} disesuaikan: ".implode(', ', array_map(fn (array $d) => "{$d['kode']} {$d['lama']} → {$d['baru']}", $diubah)));
        }

        return ['diubah' => $diubah, 'tersisa' => $tersisa, 'versi' => self::versi($tahun)];
    }

    /**
     * Terapkan leading ke semua rencana aksi tahun itu yang unitnya sama (tanpa beda huruf/spasi/tanda baca) — unit di
     * berkas Perkin diatur admin; unggahan berikutnya mengingatnya lewat RencanaAksi::leadingDariUnit.
     *
     * @param  array{role: string}  $pengguna
     * @return int Jumlah rencana aksi yang berubah.
     *
     * @throws AuthorizationException
     * @throws ValidationException Berkunci `kode_leading`.
     */
    public static function terapkanLeadingUnit(int $tahun, string $unit, ?string $leading, array $pengguna): int
    {
        self::pastikanBoleh($tahun, $pengguna);
        if ($leading !== null && MasterKode::cari(MasterKode::leadings(), $leading) === null) {
            throw ValidationException::withMessages(['kode_leading' => 'Pilih leading yang aktif.']);
        }
        $kunci = RencanaAksi::kunciUnit($unit);
        $semua = MasterKode::rencanaAksi($tahun);
        $n = 0;
        foreach ($semua as $i => $ra) {
            if ($kunci !== '' && RencanaAksi::kunciUnit((string) ($ra['unit'] ?? '')) === $kunci && ($ra['kode_leading'] ?? null) !== $leading) {
                $semua[$i]['kode_leading'] = $leading;
                $n++;
            }
        }
        if ($n > 0) {
            app(MasterKodeRepository::class)->ganti($tahun, ['rencana_aksi' => $semua]);
            RiwayatPengaturan::catat('ubah_tag_rencana_aksi', "Leading {$n} rencana aksi unit \"{$unit}\" TA {$tahun} → ".($leading ?? '—'));
        }

        return $n;
    }

    /**
     * "Label: lama → baru" untuk tiap tag yang berubah.
     *
     * @param  array<string, mixed>  $lama
     * @param  array<string, mixed>  $baru
     * @return list<string>
     */
    public static function perubahan(array $lama, array $baru): array
    {
        $teks = fn (string $k, mixed $v) => match (true) {
            $k === 'kode_edm' && $v === null => 'otomatis',
            is_array($v) => $v === [] ? '—' : implode(', ', $v),
            default => filled($v) ? (string) $v : '—',
        };
        $hasil = [];
        foreach (self::TAG as $k => $label) {
            $a = $lama[$k] ?? null;
            $b = $baru[$k] ?? null;
            if ($a !== $b) {
                $hasil[] = "{$label}: {$teks($k, $a)} → {$teks($k, $b)}";
            }
        }

        return $hasil;
    }

    /** @param  array{role: string}  $pengguna */
    private static function pastikanBoleh(int $tahun, array $pengguna): void
    {
        if ($pengguna['role'] !== 'admin') {
            throw new AuthorizationException('Tag rencana aksi diatur admin.');
        }
        $data = collect(app(TahunAnggaranRepository::class)->semua())->firstWhere('tahun', $tahun);
        if ($data === null) {
            throw new AuthorizationException("Tahun Anggaran {$tahun} belum dibuat.");
        }
        if ($data['is_locked']) {
            throw new AuthorizationException("Tahun Anggaran {$tahun} terkunci — master kodenya hanya bisa dilihat.");
        }
    }
}
