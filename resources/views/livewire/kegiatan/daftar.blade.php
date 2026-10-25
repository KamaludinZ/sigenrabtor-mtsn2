@use('App\Support\Format')

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Data Kegiatan</h1>
            <p class="mt-1 text-sm text-stone-500">Tahun Anggaran {{ $tahun }} · {{ $this->jumlahSemua }} kegiatan</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('kegiatan.template') }}" class="btn-secondary" data-testid="unduh-template">
                <x-ui.icon name="download" class="size-4" /> Unduh template
            </a>
            <button type="button" wire:click="bukaImpor" class="btn-secondary" aria-expanded="{{ $imporTerbuka ? 'true' : 'false' }}" aria-controls="panel-impor">
                <x-ui.icon name="sheet" class="size-4" /> Impor dari Excel
            </button>
            <a href="{{ route('kegiatan.create') }}" wire:navigate class="btn-primary">
                <x-ui.icon name="plus" class="size-4" /> Kegiatan baru
            </a>
        </div>
    </div>

    @if ($imporTerbuka)
        <div class="mt-5" id="panel-impor">
            <livewire:kegiatan.impor-kegiatan wire:key="impor-kegiatan" />
        </div>
    @endif

    {{-- Daftar --}}
    <section class="mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-label="Daftar kegiatan">
        <div class="flex items-center gap-3 border-b border-stone-100 px-5 py-3 sm:px-6">
            <label for="cari-kegiatan" class="sr-only">Cari nama kegiatan</label>
            <div class="relative w-full max-w-sm">
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-400" />
                <input type="search" id="cari-kegiatan" wire:model.live.debounce.300ms="cari" placeholder="Cari nama kegiatan…" class="form-input pl-9">
            </div>
            <p class="ml-auto hidden shrink-0 text-xs text-stone-500 sm:block">{{ count($this->kegiatan) }} ditampilkan</p>
        </div>

        @if ($this->kegiatan === [])
            <div class="px-6 py-12 text-center" data-testid="daftar-kosong">
                <x-ui.icon name="clipboard" class="mx-auto size-9 text-stone-300" />
                <p class="mt-2 text-sm font-semibold text-stone-800">{{ $cari !== '' ? 'Tidak ada kegiatan yang cocok.' : 'Belum ada kegiatan.' }}</p>
                <p class="mt-0.5 text-xs text-stone-500">Buat kegiatan baru atau impor banyak kegiatan sekaligus dari Excel.</p>
            </div>
        @else
            <ul class="divide-y divide-stone-100" data-testid="daftar-kegiatan">
                @foreach ($this->kegiatan as $k)
                    <li class="flex flex-col gap-2 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-6" wire:key="kegiatan-{{ $k['id'] }}" data-kegiatan="{{ $k['id'] }}">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('kegiatan.edit', ['kegiatan' => $k['id']]) }}" wire:navigate class="font-semibold text-stone-900 hover:text-brand-800">{{ $k['nama'] }}</a>
                                <x-ui.status-badge :status="$k['status']->value" />
                                @if ($k['impor'])
                                    <span class="rounded bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold text-stone-500" title="Dibuat lewat impor Excel">IMPOR</span>
                                @endif
                            </div>
                            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-stone-500">
                                <span>{{ $k['leading'] ?? 'Leading belum dipilih' }}</span>
                                @if ($k['bulan'])
                                    <span>{{ $k['bulan'] }}</span>
                                @endif
                                @forelse ($k['sumber'] as $s)
                                    <span class="inline-flex items-center gap-1">
                                        <x-rab.lencana-sumber :sumber="$s['sumber']" class="text-[10px]" />
                                        {{ $s['pagu'] !== null ? Format::rupiah($s['pagu']) : 'pagu belum diisi' }}
                                    </span>
                                @empty
                                    <span>Sumber dana belum dipilih</span>
                                @endforelse
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-1">
                            <a href="{{ route('kegiatan.edit', ['kegiatan' => $k['id']]) }}" wire:navigate class="btn-ghost px-2.5 py-1.5 text-xs">Data</a>
                            <a href="{{ route('kegiatan.rab', ['kegiatan' => $k['id']]) }}" wire:navigate class="btn-ghost px-2.5 py-1.5 text-xs">RAB</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
