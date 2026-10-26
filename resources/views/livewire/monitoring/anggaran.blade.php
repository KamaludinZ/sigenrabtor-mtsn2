@use('App\Enums\StatusKegiatan')
@use('App\Enums\SumberDana')
@use('App\Support\MasterKode')

@php
    $ringkasan = $this->ringkasan;
    $aktif = $this->saringanAktif();
@endphp

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Monitoring Anggaran</h1>
                <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-800 ring-1 ring-brand-200 ring-inset" data-testid="tahun-monitoring">
                    <x-ui.icon name="calendar" class="size-3.5" /> TA {{ $this->tahun() }}
                </span>
                @if ($tahun !== '')
                    <span class="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-semibold text-stone-600" data-testid="tahun-bukan-aktif">
                        <x-ui.icon name="lock" class="size-3" /> Bukan tahun aktif — hanya dipantau
                    </span>
                @endif
            </div>
            <p class="mt-1 max-w-3xl text-sm text-stone-500">Penggunaan anggaran seluruh kegiatan dan leading per tahun anggaran: pagu dari Cascade Perkin dibandingkan dengan total RAB tiap sumber dana.</p>
        </div>
        {{-- Ekspor mengikuti saringan & urutan di layar (parameter URL yang sama) --}}
        <div class="flex shrink-0 flex-wrap gap-2" data-testid="ekspor-monitoring">
            <a href="{{ route('monitoring.ekspor.excel', $this->queryEkspor()) }}" class="btn-secondary" data-testid="ekspor-excel" download
               title="Unduh ringkasan, per leading, rincian kegiatan, kelompok belanja, dan operator sesuai saringan">
                <x-ui.icon name="sheet" class="size-4" /> Ekspor Excel
            </a>
            <button type="button" class="btn-secondary" disabled title="Ekspor PDF sedang disiapkan">
                <x-ui.icon name="download" class="size-4" /> PDF
                <span class="rounded bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-stone-500 uppercase">Segera</span>
            </button>
        </div>
    </div>

    {{-- Saringan --}}
    <section class="mt-6 rounded-2xl bg-white px-5 py-4 shadow-xs ring-1 ring-stone-200 sm:px-6" aria-label="Saringan monitoring" data-testid="saringan-monitoring">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label>
                <span class="form-label">Tahun anggaran</span>
                <select wire:model.live="tahun" class="form-input">
                    @foreach ($this->daftarTahun() as $ta)
                        <option value="{{ $ta['is_active'] ? '' : $ta['tahun'] }}">{{ $ta['tahun'] }}{{ $ta['is_active'] ? ' · aktif' : ($ta['is_locked'] ? ' · terkunci' : '') }}</option>
                    @endforeach
                </select>
            </label>
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

        {{-- Rentang tanggal pelaksanaan (batas boleh salah satu) --}}
        <fieldset class="mt-3" data-testid="rentang-tanggal">
            <legend class="form-label">Tanggal pelaksanaan</legend>
            <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 sm:flex sm:flex-wrap">
                <input type="date" wire:model.live="dari" min="{{ $this->tahun() }}-01-01" max="{{ $this->tahun() }}-12-31" aria-label="Dari tanggal"
                       @class(['form-input w-full sm:w-auto', 'border-red-400' => $this->rentangTerbalik()]) aria-describedby="keterangan-rentang">
                <span class="text-sm text-stone-400" aria-hidden="true">s.d.</span>
                <input type="date" wire:model.live="sampai" min="{{ $this->tahun() }}-01-01" max="{{ $this->tahun() }}-12-31" aria-label="Sampai tanggal"
                       @class(['form-input w-full sm:w-auto', 'border-red-400' => $this->rentangTerbalik()]) aria-describedby="keterangan-rentang">
                @if ($dari !== '' || $sampai !== '')
                    <button type="button" wire:click="hapusSaringan('tanggal')" class="btn-ghost col-span-3 justify-self-start px-2 py-1 text-xs">Kosongkan tanggal</button>
                @endif
            </div>
            @if ($this->rentangTerbalik())
                <p id="keterangan-rentang" class="mt-1.5 flex items-center gap-1 text-xs font-medium text-red-700" role="alert" data-testid="rentang-terbalik">
                    <x-ui.icon name="alert" class="size-3.5 shrink-0" /> Tanggal akhir sebelum tanggal awal — rentang belum diterapkan.
                </p>
            @elseif ($ringkasan->tanpaJadwal > 0)
                <p id="keterangan-rentang" class="mt-1.5 text-xs text-gold-700" data-testid="tanpa-jadwal">{{ $ringkasan->tanpaJadwal }} kegiatan belum punya tanggal maupun bulan pelaksanaan sehingga tidak ikut tersaring.</p>
            @else
                <p id="keterangan-rentang" class="mt-1.5 text-xs text-stone-500">Kegiatan yang jadwal pelaksanaannya (atau bulan pelaksanaannya) beririsan dengan rentang ini.</p>
            @endif
        </fieldset>
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

    {{-- Ringkasan tahun terpilih --}}
    <x-monitoring.ringkasan :ringkasan="$ringkasan" :sumber="$sumber === '' ? null : $sumber" class="mt-6" />

    {{-- Perbandingan pagu DIPA & Komite --}}
    <x-monitoring.perbandingan-sumber :ringkasan="$ringkasan" :sumber="$sumber === '' ? null : $sumber" class="mt-6" />

    {{-- Total per leading --}}
    <x-monitoring.tabel-leading :ringkasan="$ringkasan" :sumber="$sumber === '' ? null : $sumber" :leading="$leading === '' ? null : $leading"
                              :urut="$urutLeading" :arah="$arahLeading" class="mt-6" />

    {{-- Rekap per kelompok belanja & per operator --}}
    <div class="mt-6 grid items-start gap-6 lg:grid-cols-2">
        <x-monitoring.rekap-kelompok :ringkasan="$ringkasan" />
        <x-monitoring.rekap-operator :operator="$this->operator" />
    </div>

    {{-- Rincian per kegiatan --}}
    @include('livewire.monitoring.partials.rincian-kegiatan')
</div>
