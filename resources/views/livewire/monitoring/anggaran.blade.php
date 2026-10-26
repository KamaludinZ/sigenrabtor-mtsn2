@use('App\Enums\StatusKegiatan')
@use('App\Enums\SumberDana')
@use('App\Support\MasterKode')

@php
    $ringkasan = $this->ringkasan;
    $aktif = $this->saringanAktif();
    $bagianBerikut = [
        ['kunci' => 'kelompok', 'judul' => 'Rekap per kelompok belanja', 'isi' => 'Total belanja bahan, jasa, dan lain-lain seluruh kegiatan.', 'lebar' => false],
        ['kunci' => 'operator', 'judul' => 'Rekap per operator', 'isi' => 'Jumlah kegiatan dan anggaran per pembuat kegiatan.', 'lebar' => false],
    ];
@endphp

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Monitoring Anggaran</h1>
                <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-800 ring-1 ring-brand-200 ring-inset">
                    <x-ui.icon name="calendar" class="size-3.5" /> TA {{ $this->tahun() }}
                </span>
            </div>
            <p class="mt-1 max-w-3xl text-sm text-stone-500">Penggunaan anggaran seluruh kegiatan dan leading tahun ini: pagu dari Cascade Perkin dibandingkan dengan total RAB tiap sumber dana.</p>
        </div>
        <button type="button" class="btn-secondary shrink-0" disabled title="Ekspor Excel & PDF sedang disiapkan">
            <x-ui.icon name="download" class="size-4" /> Ekspor
            <span class="rounded bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-stone-500 uppercase">Segera</span>
        </button>
    </div>

    {{-- Saringan --}}
    <section class="mt-6 rounded-2xl bg-white px-5 py-4 shadow-xs ring-1 ring-stone-200 sm:px-6" aria-label="Saringan monitoring" data-testid="saringan-monitoring">
        <div class="grid gap-3 sm:grid-cols-3">
            <label>
                <span class="form-label">Leading</span>
                <select wire:model.live="leading" class="form-input">
                    <option value="">Semua leading</option>
                    @foreach (MasterKode::leadings() as $l)
                        <option value="{{ $l['kode'] }}">{{ $l['kode'] }} · {{ $l['nama_unit'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="form-label">Sumber dana</span>
                <select wire:model.live="sumber" class="form-input">
                    <option value="">DIPA &amp; Komite</option>
                    @foreach (SumberDana::urutan() as $s)
                        <option value="{{ $s }}">{{ $s }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="form-label">Status dokumen</span>
                <select wire:model.live="status" class="form-input">
                    <option value="">Semua status</option>
                    @foreach (StatusKegiatan::cases() as $st)
                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        @if ($aktif !== [])
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs" data-testid="saringan-aktif">
                @foreach ($aktif as $kunci => $label)
                    <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 py-0.5 pr-1 pl-2.5 font-medium text-brand-800 ring-1 ring-brand-200">
                        {{ $label }}
                        <button type="button" wire:click="hapusSaringan('{{ $kunci }}')" class="rounded-full p-0.5 hover:bg-brand-100" aria-label="Lepas saringan {{ $label }}"><x-ui.icon name="x" class="size-3" /></button>
                    </span>
                @endforeach
                <button type="button" wire:click="hapusSaringan" class="font-semibold text-brand-700 hover:text-brand-800">Hapus semua</button>
            </div>
        @endif
    </section>

    {{-- Ringkasan tahun aktif --}}
    <x-monitoring.ringkasan :ringkasan="$ringkasan" :sumber="$sumber === '' ? null : $sumber" class="mt-6" />

    {{-- Perbandingan pagu DIPA & Komite --}}
    <x-monitoring.perbandingan-sumber :ringkasan="$ringkasan" :sumber="$sumber === '' ? null : $sumber" class="mt-6" />

    {{-- Total per leading --}}
    <x-monitoring.tabel-leading :ringkasan="$ringkasan" :sumber="$sumber === '' ? null : $sumber" :leading="$leading === '' ? null : $leading"
                              :urut="$urutLeading" :arah="$arahLeading" class="mt-6" />

    {{-- Bagian rekap (diisi bertahap) --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @foreach ($bagianBerikut as $bagian)
            <section id="monitoring-{{ $bagian['kunci'] }}" class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200 {{ $bagian['lebar'] ? 'lg:col-span-2' : '' }}"
                     aria-labelledby="judul-monitoring-{{ $bagian['kunci'] }}" data-testid="bagian-monitoring-{{ $bagian['kunci'] }}">
                <header class="flex items-center justify-between gap-2 border-b border-stone-100 px-5 py-4 sm:px-6">
                    <h2 id="judul-monitoring-{{ $bagian['kunci'] }}" class="text-base font-semibold text-stone-900">{{ $bagian['judul'] }}</h2>
                    <span class="rounded bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-stone-500 uppercase">Segera</span>
                </header>
                <p class="px-5 py-6 text-sm text-stone-500 sm:px-6">{{ $bagian['isi'] }}</p>
            </section>
        @endforeach
    </div>

    {{-- Rincian per kegiatan --}}
    @include('livewire.monitoring.partials.rincian-kegiatan')
</div>
