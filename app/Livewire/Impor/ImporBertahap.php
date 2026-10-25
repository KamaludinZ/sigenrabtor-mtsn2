<?php

namespace App\Livewire\Impor;

use App\Support\Impor\GagalBacaImpor;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Alur impor Excel bertahap yang dipakai bersama (impor kegiatan, nanti impor rincian RAB):
 * unggah → periksa tiap baris (pratinjau siap/dilewati/gagal + alasannya) → proses per kelompok kecil dengan
 * bilah kemajuan → laporan berhasil/dilewati/gagal yang bisa diunduh.
 *
 * Setiap baris berbentuk: baris (nomor di Excel), tampil (kolom → teks pratinjau), status, pesan, peringatan, data
 * (muatan untuk disimpan). Turunan cukup membaca berkas dan menyimpan satu baris.
 */
abstract class ImporBertahap extends Component
{
    use WithFileUploads;

    /** Baris yang diproses per permintaan; kecil agar kemajuan terlihat dan tiap permintaan singkat. */
    public const UKURAN_KELOMPOK = 5;

    public const MAKS_KB = 2048;

    /** @var TemporaryUploadedFile|null */
    public $berkas = null;

    /** 'unggah' | 'periksa' | 'proses' | 'selesai' */
    #[Locked]
    public string $tahap = 'unggah';

    #[Locked]
    public string $namaBerkas = '';

    #[Locked]
    public ?string $galat = null;

    /** @var list<array{baris: int, tampil: array<string, string>, status: string, pesan: list<string>, peringatan: list<string>, data: array<string, mixed>}> */
    #[Locked]
    public array $baris = [];

    /** Indeks baris berikutnya yang diproses. */
    #[Locked]
    public int $posisi = 0;

    /** @var list<array{baris: int, label: string, status: string, pesan: string, tautan: string|null}> Log proses, urut baris. */
    #[Locked]
    public array $hasil = [];

    #[Locked]
    public bool $dihentikan = false;

    /**
     * Baca & periksa berkas menjadi baris impor.
     *
     * @return list<array{baris: int, tampil: array<string, string>, status: string, pesan: list<string>, peringatan: list<string>, data: array<string, mixed>}>
     *
     * @throws GagalBacaImpor
     */
    abstract protected function periksaBerkas(string $path, string $namaAsli): array;

    /**
     * Simpan satu baris berstatus siap. Kembalikan status akhir: 'berhasil', 'dilewati', atau 'gagal' beserta keterangannya.
     *
     * @param  array<string, mixed>  $data
     * @return array{status: string, pesan: string, tautan?: string|null}
     */
    abstract protected function simpanBaris(array $data): array;

    /** Kolom pratinjau: kunci di 'tampil' → judul kolom. */
    abstract public function kolom(): array;

    /** Label singkat satu baris untuk log & laporan (mis. nama kegiatan / uraian item). */
    abstract protected function labelBaris(array $baris): string;

    /** Dipanggil sekali setelah proses selesai/dihentikan (mis. memberi tahu daftar agar dimuat ulang). */
    protected function selesaiDiimpor(int $berhasil): void {}

    /** Nama berkas laporan tanpa ekstensi. */
    protected function namaLaporan(): string
    {
        return 'laporan-impor';
    }

    protected function pastikanBolehImpor(): void {}

    public function updatedBerkas(): void
    {
        $this->pastikanBolehImpor();
        $this->galat = null;

        try {
            $this->validate(
                ['berkas' => ['required', 'file', 'max:'.self::MAKS_KB, 'extensions:xlsx,xls,csv']],
                [
                    'berkas.max' => 'Ukuran berkas maksimal '.(self::MAKS_KB / 1024).' MB.',
                    'berkas.extensions' => 'Pilih berkas Excel (.xlsx, .xls) atau CSV.',
                ],
            );
        } catch (ValidationException $e) {
            $this->galat = $e->validator->errors()->first('berkas');
            $this->hapusBerkas();

            return;
        }

        $this->namaBerkas = $this->berkas->getClientOriginalName();

        try {
            $this->baris = $this->periksaBerkas($this->berkas->getRealPath(), $this->namaBerkas);
            $this->tahap = 'periksa';
        } catch (GagalBacaImpor $e) {
            $this->galat = $e->getMessage();
        } finally {
            $this->hapusBerkas();
        }
    }

    public function mulaiImpor(): void
    {
        $this->pastikanBolehImpor();

        if ($this->tahap !== 'periksa' || $this->jumlah('siap') === 0) {
            return;
        }

        $this->tahap = 'proses';
        $this->posisi = 0;
        $this->hasil = [];
        $this->dihentikan = false;
    }

    /**
     * Proses kelompok berikutnya. Mengembalikan true selama masih ada baris tersisa (dipanggil berulang dari klien).
     */
    public function lanjutkanImpor(): bool
    {
        if ($this->tahap !== 'proses') {
            return false;
        }

        $this->pastikanBolehImpor();
        $diproses = 0;

        while ($this->posisi < count($this->baris) && $diproses < self::UKURAN_KELOMPOK) {
            $baris = $this->baris[$this->posisi++];

            if ($baris['status'] === 'siap') {
                $akhir = $this->simpanBaris($baris['data']);
                $diproses++;
            } else {
                $akhir = ['status' => $baris['status'], 'pesan' => implode(' ', $baris['pesan'])];
            }

            $this->hasil[] = [
                'baris' => $baris['baris'],
                'label' => $this->labelBaris($baris),
                'status' => $akhir['status'],
                'pesan' => $akhir['pesan'] ?: implode(' ', $baris['peringatan']),
                'tautan' => $akhir['tautan'] ?? null,
            ];
        }

        if ($this->posisi >= count($this->baris)) {
            $this->akhiri();

            return false;
        }

        return true;
    }

    /** Hentikan di tengah jalan: baris yang belum diproses dicatat "dibatalkan". */
    public function hentikan(): void
    {
        if ($this->tahap !== 'proses') {
            return;
        }

        foreach (array_slice($this->baris, $this->posisi) as $baris) {
            $this->hasil[] = [
                'baris' => $baris['baris'],
                'label' => $this->labelBaris($baris),
                'status' => 'dibatalkan',
                'pesan' => 'Proses dihentikan sebelum baris ini diproses.',
                'tautan' => null,
            ];
        }

        $this->posisi = count($this->baris);
        $this->dihentikan = true;
        $this->akhiri();
    }

    public function ulangi(): void
    {
        $this->reset('berkas', 'tahap', 'namaBerkas', 'galat', 'baris', 'posisi', 'hasil', 'dihentikan');
        $this->resetValidation();
    }

    public function unduhLaporan(): ?StreamedResponse
    {
        if ($this->tahap !== 'selesai') {
            return null;
        }

        $buku = new Spreadsheet;
        $lembar = $buku->getActiveSheet()->setTitle('Laporan');
        $lembar->fromArray(['Baris Excel', 'Data', 'Status', 'Keterangan'], null, 'A1');
        $lembar->fromArray(array_map(fn ($h) => [$h['baris'], $h['label'], ucfirst($h['status']), $h['pesan']], $this->hasil), null, 'A2');
        $lembar->getStyle('A1:D1')->getFont()->setBold(true);
        foreach (['A' => 12, 'B' => 60, 'C' => 14, 'D' => 80] as $kolom => $lebar) {
            $lembar->getColumnDimension($kolom)->setWidth($lebar);
        }

        return response()->streamDownload(
            fn () => (new Xlsx($buku))->save('php://output'),
            $this->namaLaporan().'-'.now()->format('Ymd-His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /** Banyaknya baris pratinjau berstatus tertentu. */
    public function jumlah(string $status): int
    {
        return count(array_filter($this->baris, fn ($b) => $b['status'] === $status));
    }

    /** Banyaknya baris log berstatus tertentu. */
    public function jumlahHasil(string $status): int
    {
        return count(array_filter($this->hasil, fn ($h) => $h['status'] === $status));
    }

    /** Persentase kemajuan proses (0–100). */
    public function persen(): int
    {
        return $this->baris === [] ? 0 : (int) floor($this->posisi / count($this->baris) * 100);
    }

    private function akhiri(): void
    {
        $this->tahap = 'selesai';
        $this->selesaiDiimpor($this->jumlahHasil('berhasil'));
    }

    private function hapusBerkas(): void
    {
        $this->berkas?->delete();
        $this->berkas = null;
    }
}
