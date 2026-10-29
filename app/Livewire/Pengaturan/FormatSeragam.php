<?php

namespace App\Livewire\Pengaturan;

use App\Enums\SumberDana;
use App\Support\Dokumen\DokumenKegiatan;
use App\Support\Dokumen\FormatSeragam as AturanFormat;
use App\Support\Kegiatan\KonteksAi;
use App\Support\MasterKode;
use App\Support\Pengaturan\KelolaProfilMadrasah;
use App\Support\Stub\StubData;
use App\Support\Stub\StubFormatSeragam;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Halaman admin Format Seragam (PRD §9; `/pengaturan/format`, rute `pengaturan.format.index`, HanyaAdmin): semua dokumen
 * mengikuti format resmi madrasah yang sama. Tab `?tab=` (TAB): ringkasan (kesiapan empat unsur + pratinjau kop), susunan
 * baku (sembilan bagian KAK/TOR, kepala & kolom RAB — AturanFormat::susunan), penomoran (aturan per jenis dokumen + contoh
 * nomor berikutnya AturanFormat::nomor), pilihan template (template KAK/TOR & cover per jenis kegiatan dan khusus leading).
 * Data aturan penomoran & template masih tiruan (StubFormatSeragam); kop dari profil madrasah (KelolaProfilMadrasah::data).
 */
#[Layout('layouts.app')]
class FormatSeragam extends Component
{
    public const TAB = ['ringkasan' => 'Ringkasan', 'susunan' => 'Susunan baku', 'penomoran' => 'Penomoran', 'template' => 'Pilihan template'];

    /** Label reset nomor urut. */
    public const RESET = ['tahunan' => 'Mulai dari 1 tiap tahun anggaran'];

    #[Url(except: 'ringkasan')]
    public string $tab = 'ringkasan';

    public function mount(): void
    {
        $this->rapikan();
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

    /** @return array{jenis: list<array<string, mixed>>, leading: list<array<string, mixed>>} */
    #[Computed]
    public function template(): array
    {
        $data = StubFormatSeragam::template();
        $jenis = KonteksAi::jenisKegiatan();

        return [
            'jenis' => array_map(fn (string $kunci, array $t) => $t + ['kunci' => $kunci, 'label' => $jenis[$kunci] ?? $kunci], array_keys($data['jenis']), $data['jenis']),
            'leading' => array_map(fn (array $t) => $t + ['nama_leading' => MasterKode::leading($t['leading'])['nama_unit'] ?? '', 'label_jenis' => $jenis[$t['jenis']] ?? $t['jenis']], $data['leading']),
        ];
    }

    /** @return array<string, array{status: string, teks: string}> Kesiapan empat unsur format (siap|perlu). */
    #[Computed]
    public function ringkasan(): array
    {
        $kurang = $this->profil['kurang'];
        $nomor = array_filter($this->penomoran, fn (array $b) => $b['aktif']);
        $khusus = count(array_filter($this->template['jenis'], fn (array $t) => $t['kak'] !== null || $t['cover'] !== null));

        return [
            'kop' => ['status' => $kurang === [] ? 'siap' : 'perlu', 'teks' => $kurang === [] ? 'Kop lengkap: nama, alamat, telepon, kota, dan logo.' : 'Belum diisi: '.implode(', ', $kurang).'.'],
            'susunan' => ['status' => 'siap', 'teks' => count($this->susunan['kak']).' bagian KAK/TOR · '.count($this->susunan['rab_kolom']).' kolom RAB, urutan terkunci.'],
            'penomoran' => ['status' => $nomor !== [] ? 'siap' : 'perlu', 'teks' => count($nomor).' dari '.count($this->penomoran).' jenis dokumen bernomor otomatis.'],
            'template' => ['status' => 'siap', 'teks' => $khusus.' jenis kegiatan bertemplate khusus · '.count($this->template['leading']).' khusus leading; lainnya susunan baku.'],
        ];
    }

    public function render()
    {
        return view('livewire.pengaturan.format-seragam')->title('Format Seragam · Pengaturan');
    }
}
