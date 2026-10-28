<?php

namespace App\Http\Controllers;

use App\Support\Impor\GagalBacaImpor;
use App\Support\Master\Impor\GagalSimpanMaster;
use App\Support\Master\Impor\PemeriksaBudayaMutu;
use App\Support\Master\Impor\ProsesBerkasMaster;
use App\Support\Master\Impor\SimpanMasterKode;
use App\Support\Master\JenisKode;
use App\Support\Pengaturan\PengaturanBerubah;
use App\Support\Stub\StubData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint unggah master kode satu jenis (PRD §14; `POST /master/{tahun}/unggah/{jenis}`, rute admin `master.unggah`,
 * jenis SimpanMasterKode::DIDUKUNG): berkas `berkas` (xlsx/xls/csv, ProsesBerkasMaster) dibaca & dibandingkan dengan
 * master tahun itu; tanpa `simpan` hanya pratinjau, dengan `simpan=1` disimpan bila tidak ada kendala. Jawaban JSON:
 * berkas, baris pratinjau, ringkasan, `induk` (IKSK: ringkasan per SK induk, SimpanMasterKode::indukIksk), `struktur`
 * (RKTM: standar → kegiatan → jumlah sub kegiatan, SimpanMasterKode::strukturRktm), `komponen` (EDM: nama komponen per
 * huruf yang akan tersimpan, SimpanMasterKode::komponenEdm), `butir` (5 Budaya Mutu: jumlah tersimpan & jumlah baku),
 * kendala, peringatan, siap, disimpan (+ id unggahan), dan `sidik` master saat ini — kirim kembali sebagai `sidik` bersama
 * `simpan=1` agar penyimpanan ditolak (409) bila master berubah sejak pratinjau; berkas/kendala → 422, izin
 * (bukan admin, tahun terkunci/belum dibuat) → 403.
 */
class UnggahMasterKode extends Controller
{
    public function __invoke(Request $request, int $tahun, string $jenis): JsonResponse
    {
        abort_unless(in_array($jenis, SimpanMasterKode::DIDUKUNG, true), 404);
        $pengguna = StubData::currentUser();

        try {
            $berkas = ProsesBerkasMaster::periksaBerkas($request->file('berkas'), $jenis);
            $path = $request->file('berkas')->getRealPath();
            $hasil = $request->boolean('simpan')
                ? SimpanMasterKode::simpan($path, $berkas['nama'], $jenis, $tahun, $pengguna, $request->filled('sidik') ? (string) $request->input('sidik') : null)
                : ProsesBerkasMaster::proses($path, $berkas['nama'], $jenis, $tahun, $pengguna) + ['disimpan' => false, 'unggahan' => null];
        } catch (GagalBacaImpor $e) {
            return response()->json(['pesan' => $e->getMessage()], 422);
        } catch (GagalSimpanMaster $e) {
            return response()->json(['pesan' => 'Master '.JenisKode::DAFTAR[$jenis]['label'].' belum bisa disimpan.', 'kendala' => $e->kendala], 422);
        } catch (AuthorizationException $e) {
            return response()->json(['pesan' => $e->getMessage()], 403);
        } catch (PengaturanBerubah $e) {
            return response()->json(['pesan' => $e->getMessage().' Minta pratinjau lagi lalu simpan dengan sidik yang baru.'], 409);
        }

        $kendala = SimpanMasterKode::kendala($hasil['pratinjau'], $tahun);
        $item = $hasil['pratinjau'][$jenis];

        return response()->json([
            'tahun' => $tahun,
            'jenis' => $jenis,
            'berkas' => ['nama' => $berkas['nama'], 'ukuran' => $berkas['ukuran'], 'sheet' => $item['sheet']],
            'ringkasan' => $item['ringkasan'],
            'baris' => array_map(fn (array $b) => ['kode' => $b['kode'], 'status' => $b['status'], 'isi' => $b['tampil'], 'lama' => $b['lama'], 'pesan' => $b['pesan'], 'dipakai' => $b['dipakai'], 'baris_berkas' => $b['baris']], $item['baris']),
            'induk' => $jenis === 'iksk' ? SimpanMasterKode::indukIksk($item['baris'], $tahun) : null,
            'struktur' => $jenis === 'rktm' ? SimpanMasterKode::strukturRktm($item['baris']) : null,
            'komponen' => $jenis === 'edm' ? SimpanMasterKode::komponenEdm($item['baris'], $tahun) : null,
            'butir' => $jenis === 'budaya' ? ['jumlah' => count(array_filter($item['baris'], fn (array $b) => in_array($b['status'], ['baru', 'berubah', 'tetap'], true))), 'jumlah_baku' => PemeriksaBudayaMutu::JUMLAH_BAKU] : null,
            'kendala' => $hasil['disimpan'] ? [] : $kendala,
            'peringatan' => $hasil['peringatan'],
            'siap' => $hasil['disimpan'] || $kendala === [],
            'disimpan' => $hasil['disimpan'],
            'unggahan' => $hasil['unggahan'],
            'sidik' => SimpanMasterKode::sidik($tahun, [$jenis]),
        ], $hasil['disimpan'] ? 201 : 200, ['Cache-Control' => 'no-store, private']);
    }
}
