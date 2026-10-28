<?php

namespace App\Support\Kegiatan;

use App\Contracts\PenyusunSaranKalimat;
use App\Support\Pengaturan\ParameterAi;
use App\Support\Pengaturan\PromptAi;
use App\Support\Stub\StubPengaturan;
use App\Support\Teks;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Permintaan saran kalimat KAK ke AI di server (PRD §20 F23–F25) — dipakai Kalimat KAK (`KalimatAi::susunSaran`, "Buat
 * dengan AI" & "Minta perbaikan") dan endpoint `kegiatan.saran_ai.minta` (`POST /kegiatan/{id}/saran-ai/{bagian}`):
 * - bagian kalimat BagianAi::BAGIAN berkolom (jadwal lewat SaranJadwalAi) — lain → galat `bagian`; kegiatan dalam
 *   cakupan akun & masih bisa diubah (AksesKegiatan, KunciKegiatan → AuthorizationException); instruksi perbaikan
 *   ≤ MAKS_INSTRUKSI dan, bila ada, teks acuan wajib (galat `instruksi`/`acuan`);
 * - bagian dinonaktifkan admin (PromptAi::aktif), nama kegiatan kosong, atau batas harian habis (BatasAi) → GagalUsulanAi;
 * - muatan PayloadAi::saranKalimat (tanpa NIP, nama pejabat, pagu) → RantaiPenyediaAi (penyedia aktif → cadangan;
 *   gagal → GagalUsulanAi ber-previous GagalPenyediaAi, admin + simulasi diberi akhiran) → `rapikan()` (maks. jumlah versi ParameterAi, teks rapi,
 *   batas panjang kolom, nama cover kapital, tanpa kosong/kembar) → BatasAi::catat setelah penyedia menjawab → kosong =
 *   GagalUsulanAi → RiwayatSaranAi::catat → {versi, peringatan (KeamananAi::peringatan), bertentangan (BatasDataMaster::galatTeks
 *   per versi — versi itu tidak bisa dipakai apa adanya), catatan, penyedia, permintaan}.
 *   Muatan diperiksa KeamananAi::pastikanMuatanAman sebelum terkirim; keluaran dibersihkan KeamananAi::bersihkanKeluaran.
 */
final class SaranKalimatAi
{
    public const MAKS_INSTRUKSI = 300;

    /** Batas panjang teks per bagian (= aturan kolomnya di ValidasiKegiatan). */
    public const MAKS_TEKS = ['nama_cover' => 255, 'latar_belakang' => 5000, 'tujuan' => 2000, 'sasaran' => 2000, 'pelaksanaan' => 3000];

    /**
     * @param  array<string, mixed>  $kegiatan  Data kegiatan (boleh berisi teks yang sedang disunting).
     * @param  array{id: int, name: string, role: string, leadings?: list<string>}  $pengguna
     * @param  string|null  $acuan  Teks yang diperbaiki; null = teks kolom bagian di $kegiatan.
     * @return array{versi: list<array{judul: string, ciri: string, teks: string}>, peringatan: list<list<string>>, bertentangan: list<list<string>>, catatan: string|null, penyedia: string|null, permintaan: string}
     *
     * @throws ValidationException Berkunci `bagian`, `instruksi`, atau `acuan`.
     * @throws AuthorizationException
     * @throws GagalUsulanAi
     */
    public static function minta(array $kegiatan, string $bagian, array $pengguna, string $instruksi = '', ?string $acuan = null, ?PenyusunSaranKalimat $penyusun = null): array
    {
        if (! isset(BagianAi::BAGIAN[$bagian]) || BagianAi::BAGIAN[$bagian]['kolom'] === null) {
            throw ValidationException::withMessages(['bagian' => isset(BagianAi::BAGIAN[$bagian]) ? 'Usulan jadwal dibuat di halaman Matrik & Jadwal.' : 'Bagian kalimat tidak dikenal.']);
        }
        if (! AksesKegiatan::bolehLihat($kegiatan, $pengguna)) {
            throw new AuthorizationException(AksesKegiatan::PESAN_TOLAK);
        }
        if (! KunciKegiatan::dapatDiubah($kegiatan)) {
            throw new AuthorizationException('Kegiatan ini terkunci — kalimat KAK tidak bisa diubah.');
        }
        $instruksi = Teks::rapikan(preg_replace('/\s+/u', ' ', $instruksi));
        $acuan = Teks::rapikan($acuan ?? (string) ($kegiatan[BagianAi::BAGIAN[$bagian]['kolom']] ?? ''));
        if (mb_strlen($instruksi) > self::MAKS_INSTRUKSI) {
            throw ValidationException::withMessages(['instruksi' => 'Instruksi maksimal '.self::MAKS_INSTRUKSI.' karakter.']);
        }
        if ($instruksi !== '' && $acuan === '') {
            throw ValidationException::withMessages(['acuan' => 'Teks yang diperbaiki kosong — pilih versi atau tulis teksnya dulu.']);
        }

        if (! PromptAi::aktif($bagian)) {
            throw new GagalUsulanAi('Bagian ini dinonaktifkan admin di Pengaturan AI.');
        }
        if (blank(trim((string) ($kegiatan['nama_kegiatan'] ?? '')))) {
            throw new GagalUsulanAi('Isi nama kegiatan dulu di Data Kegiatan.');
        }
        if (BatasAi::sisa($pengguna) <= 0) {
            throw new GagalUsulanAi(BatasAi::pesanHabis($pengguna));
        }

        // Muatan AI lewat PayloadAi: NIP, nama pejabat, dan pagu tidak pernah ikut terkirim.
        $permintaan = PayloadAi::saranKalimat((int) $kegiatan['id'], $bagian, $kegiatan, $acuan, $instruksi);
        // Penjaga terakhir sebelum terkirim: tidak ada data pribadi yang lolos PayloadAi.
        KeamananAi::pastikanMuatanAman(RiwayatSaranAi::prompt($permintaan));
        $penyusun ??= app(PenyusunSaranKalimat::class);
        try {
            $jalan = RantaiPenyediaAi::jalankan(fn (string $penyedia) => $penyusun->sarankan($permintaan, $penyedia));
        } catch (GagalPenyediaAi $e) {
            // Pesan siap tampil; kegagalan penyedia aslinya tetap terbawa (previous) untuk pemanggil yang membedakannya.
            throw new GagalUsulanAi($e->getMessage().($e->simulasi && $pengguna['role'] === 'admin' ? ' (simulasi kegagalan dari Pengaturan AI)' : ''), previous: $e);
        }
        $versi = self::rapikan($bagian, $jalan['hasil']);
        BatasAi::catat($pengguna, $bagian);
        if ($versi === []) {
            throw new GagalUsulanAi('AI tidak memberi saran yang bisa dipakai. Coba lagi.');
        }

        return [
            'versi' => $versi,
            'peringatan' => KeamananAi::peringatan($versi, $kegiatan),
            'bertentangan' => array_map(fn (array $v) => BatasDataMaster::galatTeks($v['teks'], $kegiatan), $versi),
            'catatan' => RantaiPenyediaAi::catatan($jalan),
            'penyedia' => $jalan['penyedia'],
            'permintaan' => RiwayatSaranAi::catat((int) $kegiatan['id'], $bagian, RiwayatSaranAi::prompt($permintaan), $permintaan->instruksi, $versi, $pengguna, $jalan['penyedia']),
        ];
    }

    /**
     * Hasil penyusun dirapikan: maks. jumlah versi Pengaturan AI (ParameterAi, 2–3), teks satu spasi (nama cover kapital
     * satu baris), sesuai batas panjang kolomnya, tanpa versi kosong/kembar.
     *
     * @param  array<int, mixed>  $saran
     * @return list<array{judul: string, ciri: string, teks: string}>
     */
    public static function rapikan(string $bagian, array $saran): array
    {
        $maks = self::MAKS_TEKS[$bagian] ?? 2000;
        $hasil = [];
        foreach ($saran as $s) {
            if (! is_array($s)) {
                continue;
            }
            $mentah = KeamananAi::bersihkanKeluaran((string) ($s['teks'] ?? ''));
            $teks = $bagian === 'nama_cover' ? mb_strtoupper(Teks::rapikan(preg_replace('/\s+/u', ' ', $mentah))) : Teks::rapikan($mentah);
            $teks = mb_substr($teks, 0, $maks);
            if ($teks === '' || in_array($teks, array_column($hasil, 'teks'), true)) {
                continue;
            }
            $hasil[] = ['judul' => Teks::rapikan(KeamananAi::bersihkanKeluaran((string) ($s['judul'] ?? ''))) ?: 'Versi '.(count($hasil) + 1), 'ciri' => mb_substr(Teks::rapikan(KeamananAi::bersihkanKeluaran((string) ($s['ciri'] ?? ''))), 0, 60), 'teks' => $teks];
        }

        return array_slice($hasil, 0, ParameterAi::dari(StubPengaturan::pengaturanAi())['jumlah_versi']);
    }
}
