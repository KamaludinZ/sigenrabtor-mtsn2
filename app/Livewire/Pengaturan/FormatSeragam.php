<?php

namespace App\Livewire\Pengaturan;

use App\Contracts\PilihanTemplateRepository;
use App\Enums\SumberDana;
use App\Support\Dokumen\DokumenKegiatan;
use App\Support\Dokumen\FormatSeragam as AturanFormat;
use App\Support\Dokumen\PilihanTemplate;
use App\Support\Kegiatan\KonteksAi;
use App\Support\MasterKode;
use App\Support\Pengaturan\KelolaProfilMadrasah;
use App\Support\Stub\StubData;
use App\Support\Stub\StubFormatSeragam;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Halaman admin Format Seragam (PRD §9; `/pengaturan/format`, rute `pengaturan.format.index`, HanyaAdmin): semua dokumen
 * mengikuti format resmi madrasah yang sama. Tab `?tab=` (TAB): ringkasan (kesiapan empat unsur + pratinjau kop), susunan
 * baku (sembilan bagian KAK/TOR, kepala & kolom RAB — AturanFormat::susunan), penomoran (aturan per jenis dokumen + contoh
 * nomor berikutnya AturanFormat::nomor), pilihan template (template KAK/TOR & cover per jenis kegiatan dan khusus leading —
 * PilihanTemplate; disunting di `$pilihan` lalu `simpanTemplate()`/`batalTemplate()`, baris khusus leading `tambahLeading()`/
 * `hapusLeading($i)`, galat ke `pilihan.*`). Aturan penomoran masih tiruan (StubFormatSeragam); kop dari profil madrasah
 * (KelolaProfilMadrasah::data).
 */
#[Layout('layouts.app')]
class FormatSeragam extends Component
{
    public const TAB = ['ringkasan' => 'Ringkasan', 'susunan' => 'Susunan baku', 'penomoran' => 'Penomoran', 'template' => 'Pilihan template'];

    /** Label reset nomor urut. */
    public const RESET = ['tahunan' => 'Mulai dari 1 tiap tahun anggaran'];

    #[Url(except: 'ringkasan')]
    public string $tab = 'ringkasan';

    /** @var array{jenis: array<string, array{kak: string|null, cover: string|null}>, leading: list<array<string, mixed>>} Pilihan template yang disunting. */
    public array $pilihan = ['jenis' => [], 'leading' => []];

    public function mount(): void
    {
        $this->rapikan();
        $this->muatPilihan();
    }

    private function muatPilihan(): void
    {
        $data = PilihanTemplate::data($this->tahun());
        $this->pilihan = [
            'jenis' => array_combine(array_column($data['jenis'], 'kunci'), array_map(fn (array $j) => ['kak' => $j['kak'] ?? '', 'cover' => $j['cover'] ?? ''], $data['jenis'])),
            'leading' => array_map(fn (array $l) => ['leading' => $l['leading'], 'jenis' => $l['jenis'], 'kak' => $l['kak'] ?? '', 'cover' => $l['cover'] ?? ''], $data['leading']),
        ];
        $this->resetErrorBag();
    }

    private function tahun(): int
    {
        return (int) StubData::tahunAktif()['tahun'];
    }

    public function tambahLeading(): void
    {
        if (count($this->pilihan['leading']) < PilihanTemplate::MAKS_LEADING) {
            $this->pilihan['leading'][] = ['leading' => '', 'jenis' => '', 'kak' => '', 'cover' => ''];
        }
    }

    public function hapusLeading(int $i): void
    {
        unset($this->pilihan['leading'][$i]);
        $this->pilihan['leading'] = array_values($this->pilihan['leading']);
        $this->resetErrorBag();
    }

    public function batalTemplate(): void
    {
        $this->muatPilihan();
    }

    public function simpanTemplate(): void
    {
        $this->resetErrorBag();
        try {
            $hasil = PilihanTemplate::simpan($this->tahun(), $this->pilihan, StubData::currentUser());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $kunci => $pesan) {
                $this->addError('pilihan.'.$kunci, $pesan[0]);
            }
            $this->dispatch('notify', message: 'Pilihan template belum disimpan — periksa isian yang ditandai.', type: 'error');

            return;
        } catch (AuthorizationException|RuntimeException $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }
        unset($this->template, $this->ringkasan);
        $this->muatPilihan();
        $this->dispatch('notify', message: $hasil['ringkasan'] === null ? 'Tidak ada perubahan pilihan template.' : 'Pilihan template disimpan.', type: $hasil['ringkasan'] === null ? 'info' : 'success');
    }

    /** Ada pilihan yang belum disimpan (dibanding data tersimpan). */
    #[Computed]
    public function templateBerubah(): bool
    {
        $tersimpan = PilihanTemplate::rapikan(app(PilihanTemplateRepository::class)->untuk($this->tahun()));

        return PilihanTemplate::rapikan($this->pilihan) !== $tersimpan;
    }

    public function updatedTab(): void
    {
        $this->rapikan();
    }

    private function rapikan(): void
    {
        $this->tab = array_key_exists($this->tab, self::TAB) ? $this->tab : 'ringkasan';
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function profil(): array
    {
        return KelolaProfilMadrasah::data();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function susunan(): array
    {
        return AturanFormat::susunan();
    }

    /** @return list<array<string, mixed>> Aturan penomoran per jenis dokumen + contoh nomor berikutnya. */
    #[Computed]
    public function penomoran(): array
    {
        $tahun = (int) StubData::tahunAktif()['tahun'];
        $contoh = ['leading' => MasterKode::leadings()[0]['kode'] ?? '1.1', 'sumber' => SumberDana::cases()[0]->value, 'bulan' => (int) now()->format('n'), 'tahun' => $tahun];
        $baris = [];
        foreach (StubFormatSeragam::penomoran() as $jenis => $aturan) {
            $berikut = $aturan['terakhir'] + 1;
            $baris[] = $aturan + [
                'jenis' => $jenis,
                'label' => DokumenKegiatan::JENIS[$jenis],
                'berikut' => $berikut,
                'contoh' => AturanFormat::nomor($aturan['pola'], $berikut, ['jenis' => $jenis] + $contoh, $aturan['digit']),
                'label_reset' => self::RESET[$aturan['reset']] ?? $aturan['reset'],
            ];
        }

        return $baris;
    }

    /** @return array{jenis: list<array<string, mixed>>, leading: list<array<string, mixed>>, diperbarui_pada: string|null, kak: list<array<string, mixed>>, cover: list<array<string, mixed>>, leadings: list<array<string, mixed>>, jenis_kegiatan: array<string, string>} */
    #[Computed]
    public function template(): array
    {
        return PilihanTemplate::data($this->tahun()) + [
            'kak' => PilihanTemplate::pilihanKak($this->tahun()),
            'cover' => PilihanTemplate::pilihanCover($this->tahun()),
            'leadings' => MasterKode::leadings(),
            'jenis_kegiatan' => KonteksAi::jenisKegiatan(),
        ];
    }

    /** @return array<string, array{status: string, teks: string}> Kesiapan empat unsur format (siap|perlu). */
    #[Computed]
    public function ringkasan(): array
    {
        $kurang = $this->profil['kurang'];
        $nomor = array_filter($this->penomoran, fn (array $b) => $b['aktif']);
        $khusus = count(array_filter($this->template['jenis'], fn (array $t) => $t['kak'] !== null || $t['cover'] !== null));
        $hilang = count(array_filter([...$this->template['jenis'], ...$this->template['leading']], fn (array $t) => $t['kak_hilang'] || $t['cover_hilang']));

        return [
            'kop' => ['status' => $kurang === [] ? 'siap' : 'perlu', 'teks' => $kurang === [] ? 'Kop lengkap: nama, alamat, telepon, kota, dan logo.' : 'Belum diisi: '.implode(', ', $kurang).'.'],
            'susunan' => ['status' => 'siap', 'teks' => count($this->susunan['kak']).' bagian KAK/TOR · '.count($this->susunan['rab_kolom']).' kolom RAB, urutan terkunci.'],
            'penomoran' => ['status' => $nomor !== [] ? 'siap' : 'perlu', 'teks' => count($nomor).' dari '.count($this->penomoran).' jenis dokumen bernomor otomatis.'],
            'template' => ['status' => $hilang === 0 ? 'siap' : 'perlu', 'teks' => $hilang > 0
                ? $hilang.' pilihan memakai template yang sudah dihapus — pilih pengganti.'
                : $khusus.' jenis kegiatan bertemplate khusus · '.count($this->template['leading']).' khusus leading; lainnya susunan baku.'],
        ];
    }

    public function render()
    {
        return view('livewire.pengaturan.format-seragam')->title('Format Seragam · Pengaturan');
    }
}
