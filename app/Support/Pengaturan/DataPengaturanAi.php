<?php

namespace App\Support\Pengaturan;

use App\Contracts\PengaturanAiRepository;
use App\Contracts\RiwayatPengaturanRepository;
use App\Support\Kegiatan\BagianAi;
use App\Support\Kegiatan\KeamananAi;
use App\Support\Kegiatan\KegagalanAi;
use App\Support\Kegiatan\PayloadAi;
use App\Support\Kegiatan\StatusKuotaAi;
use App\Support\Stub\StubPengaturan;

/**
 * Pengambilan konfigurasi AI (PRD §21, F24) dalam satu permintaan:
 * - `untukAdmin()` (endpoint admin `pengaturan.ai.data`): versi, penyedia (KelolaPenyediaAi::data — tanpa kunci API,
 *   dengan kesehatan failover), parameter (KelolaModelAi::dataParameter), prompt (KelolaPromptAi::data), batas & pemakaian
 *   (KelolaBatasAi::data), simulasi kegagalan, data yang dikirim/tidak pernah dikirim, perubahan terakhir
 *   (`terakhirDisimpan()` = riwayat bagian `ai`, juga kepala halaman Pengaturan AI), tautan halaman & riwayat;
 * - `berlaku($pengguna)` (endpoint `ai.konfigurasi`, semua pengguna): yang berlaku bagi pengguna — nama penyedia aktif &
 *   cadangan ("AI tiruan" bila belum dipilih), jumlah versi, bagian AI beserta aktif/nonaktifnya, Batas Aman, data yang
 *   dikirim, kuota hari ini (StatusKuotaAi); simulasi hanya untuk admin. Tanpa alamat, model, maupun kunci penyedia.
 */
final class DataPengaturanAi
{
    /** @return array<string, mixed> */
    public static function untukAdmin(): array
    {
        $ai = app(PengaturanAiRepository::class)->ambil();

        return [
            'versi' => $ai['diperbarui_pada'],
            'penyedia' => KelolaPenyediaAi::data(),
            'parameter' => KelolaModelAi::dataParameter(),
            'prompt' => KelolaPromptAi::data(),
            'batas' => KelolaBatasAi::data(),
            'simulasi' => self::simulasi($ai),
            'data_dikirim' => self::dataDikirim(),
            'terakhir_disimpan' => self::terakhirDisimpan(),
            'tautan' => ['halaman' => route('pengaturan.ai.index'), 'riwayat' => route('pengaturan.riwayat.index', ['bagian' => 'ai'])],
        ];
    }

    /**
     * @param  array{id: int, role: string}  $pengguna
     * @return array<string, mixed>
     */
    public static function berlaku(array $pengguna): array
    {
        $ai = StubPengaturan::pengaturanAi();
        $aktif = $ai['provider_aktif'];

        return [
            'penyedia' => [
                'aktif' => $aktif,
                'nama' => PenyediaAi::nama($aktif) ?? 'AI tiruan',
                'jenis' => $aktif !== null ? PenyediaAi::DAFTAR[$aktif]['jenis'] : null,
                'cadangan' => $ai['provider_cadangan'],
                'nama_cadangan' => PenyediaAi::nama($ai['provider_cadangan']),
                'tiruan' => $aktif === null,
            ],
            'jumlah_versi' => ParameterAi::dari($ai)['jumlah_versi'],
            'bagian' => array_map(fn (string $b) => ['bagian' => $b, 'judul' => BagianAi::BAGIAN[$b]['judul'], 'tercetak' => BagianAi::BAGIAN[$b]['tercetak'], 'aktif' => PromptAi::aktif($b)], array_keys(BagianAi::BAGIAN)),
            'batas_aman' => PromptAi::BATAS_AMAN,
            'data_dikirim' => self::dataDikirim(),
            'kuota' => StatusKuotaAi::untuk($pengguna),
            'simulasi' => ($pengguna['role'] ?? null) === 'admin' ? self::simulasi($ai) : null,
        ];
    }

    /**
     * Perubahan Pengaturan AI terakhir (aksi bagian `ai` di Riwayat Perubahan).
     *
     * @return array{waktu: string, pengguna: string, label: string}|null
     */
    public static function terakhirDisimpan(): ?array
    {
        $entri = collect(app(RiwayatPengaturanRepository::class)->semua())->first(fn (array $r) => (RiwayatPengaturan::AKSI[$r['aksi']][1] ?? null) === 'ai');

        return $entri === null ? null : ['waktu' => $entri['waktu'], 'pengguna' => $entri['pengguna'], 'label' => RiwayatPengaturan::AKSI[$entri['aksi']][0]];
    }

    /** @return array{dikirim: list<string>, tidak_pernah: list<string>, disamarkan: list<string>} */
    public static function dataDikirim(): array
    {
        return ['dikirim' => PayloadAi::DIKIRIM, 'tidak_pernah' => PayloadAi::TIDAK_PERNAH, 'disamarkan' => array_values(KeamananAi::LABEL_PII)];
    }

    /**
     * @param  array<string, mixed>  $ai
     * @return array{aktif: bool, jenis: string|null, judul: string|null, cakupan: string|null}
     */
    private static function simulasi(array $ai): array
    {
        $s = $ai['simulasi_gagal'] ?? null;

        return ['aktif' => $s !== null, 'jenis' => $s['jenis'] ?? null, 'judul' => $s !== null ? KegagalanAi::judul($s['jenis']) : null, 'cakupan' => $s['cakupan'] ?? null];
    }
}
