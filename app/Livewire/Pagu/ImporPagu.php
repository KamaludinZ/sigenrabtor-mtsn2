<?php

namespace App\Livewire\Pagu;

use App\Contracts\DataPaguRepository;
use App\Contracts\KegiatanRepository;
use App\Livewire\Impor\ImporBertahap;
use App\Support\Format;
use App\Support\Kegiatan\Impor\PembacaImporKegiatan;
use App\Support\MasterKode;
use App\Support\Pagu\DataPagu;
use App\Support\Pagu\Impor\PemeriksaImporPagu;
use App\Support\Pagu\Impor\TemplateImporPagu;
use App\Support\Stub\StubData;

/**
 * Impor Data Pagu dari Excel/CSV (template Data Pagu atau template impor kegiatan): nama yang sudah ada dikenali —
 * pagu sama dilewati, pagu berbeda diperbarui — sehingga berkas cascade Perkin terbaru aman diimpor ulang. Khusus admin.
 */
class ImporPagu extends ImporBertahap
{
    public function kolom(): array
    {
        return ['nama' => 'Nama kegiatan', 'leading' => 'Leading', 'pagu' => 'Pagu', 'aksi' => 'Tindakan'];
    }

    protected function pastikanBolehImpor(): void
    {
        abort_unless(StubData::currentUser()['role'] === 'admin', 403, 'Impor Data Pagu khusus admin.');
    }

    protected function periksaBerkas(string $path, string $namaAsli): array
    {
        $tahun = StubData::tahunAktif()['tahun'];
        // Lembar "Data Pagu"; template impor kegiatan (lembar lain) dibaca dari lembar pertamanya.
        $mentah = PembacaImporKegiatan::baca($path, $namaAsli, TemplateImporPagu::LEMBAR);
        $diperiksa = PemeriksaImporPagu::periksa(
            $mentah,
            app(DataPaguRepository::class)->semua($tahun),
            array_column(app(KegiatanRepository::class)->semua(), 'nama_kegiatan'),
            MasterKode::leadings(),
            MasterKode::leadings(),
        );

        return array_map(fn (array $b) => [
            'baris' => $b['baris'],
            'tampil' => [
                'nama' => $b['nama'] ?: '—',
                'leading' => $b['leading_label'] ?? '—',
                'pagu' => implode(' · ', array_map(fn ($s) => $s['sumber'].' '.Format::rupiah($s['pagu']), DataPagu::sumberDana(['pagu' => $b['pagu']]))) ?: '—',
                'aksi' => match (true) {
                    $b['status'] === 'gagal' => '—',
                    $b['status'] === 'dilewati' => 'Sudah sama',
                    $b['aksi'] === 'perbarui' => 'Perbarui pagu',
                    default => 'Tambah',
                },
            ],
            'status' => $b['status'],
            'pesan' => $b['pesan'],
            'peringatan' => $b['peringatan'],
            'data' => ['id' => $b['id'], 'tahun' => $tahun, 'nama_kegiatan' => $b['nama'], 'leading' => $b['leading'], 'pagu' => $b['pagu']],
        ], $diperiksa);
    }

    protected function simpanBaris(array $data): array
    {
        $repo = app(DataPaguRepository::class);
        // Periksa ulang nama: data bernama sama bisa ditambahkan di tab lain selama pratinjau terbuka.
        $id = $data['id'] ?? collect($repo->semua($data['tahun']))->first(fn (array $p) => DataPagu::kunci($p['nama_kegiatan']) === DataPagu::kunci($data['nama_kegiatan']))['id'] ?? null;
        $lama = $id ? $repo->temukan($id) : null;
        $repo->simpan(['tahun' => $data['tahun'], 'nama_kegiatan' => $lama['nama_kegiatan'] ?? $data['nama_kegiatan'], 'leading' => $data['leading'] ?? $lama['leading'] ?? null, 'pagu' => $data['pagu'], 'asal' => 'impor'], $id);

        return ['status' => 'berhasil', 'pesan' => $lama ? 'Pagu diperbarui.' : 'Ditambahkan ke Data Pagu.'];
    }

    protected function labelBaris(array $baris): string
    {
        return $baris['tampil']['nama'];
    }

    protected function selesaiDiimpor(int $berhasil): void
    {
        $this->dispatch('pagu-diimpor', jumlah: $berhasil);
    }

    protected function namaLaporan(): string
    {
        return 'laporan-impor-data-pagu';
    }

    public function render()
    {
        return view('livewire.impor.bertahap', [
            'judul' => 'Impor Data Pagu dari Excel',
            'keterangan' => 'Satu baris = satu nama kegiatan dengan pagu DIPA/Komite. Nama yang sudah ada dikenali: pagu yang sama dilewati, pagu yang berbeda diperbarui.',
            'tautanTemplate' => route('pagu.template'),
            'satuan' => 'data pagu',
            'kataBerhasil' => 'disimpan (ditambah atau diperbarui)',
        ]);
    }
}
