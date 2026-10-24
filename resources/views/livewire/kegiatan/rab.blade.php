@use('App\Support\Format')
@use('App\Livewire\Forms\ItemBelanjaForm')

@php
    $hasil = $this->hasil;
    $pagu = $hasil->pagu;
    $selisih = $hasil->selisih();
    $statusPagu = $hasil->statusPagu();
@endphp

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="min-w-0">
        <nav class="flex items-center gap-1 text-xs font-medium text-stone-500" aria-label="Breadcrumb">
            <span>Data Kegiatan</span>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="truncate hover:text-stone-800">{{ $dataKegiatan['nama_kegiatan'] }}</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span class="text-stone-700">RAB</span>
        </nav>
        <div class="mt-1.5 flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Rencana Anggaran Biaya</h1>
            <x-ui.status-badge :status="$this->status()->value" />
        </div>
        <p class="mt-1 text-sm text-stone-500">{{ $dataKegiatan['nama_kegiatan'] }}</p>
    </div>

    <div class="mt-5">
        <x-kegiatan.workspace-tabs active="rab" :kegiatan-id="$kegiatanId" />
    </div>

    @if ($this->daftarSumber() === [])
        <div class="mt-6 rounded-2xl border border-dashed border-stone-300 bg-white px-6 py-12 text-center" data-testid="rab-tanpa-sumber">
            <x-ui.icon name="wallet" class="mx-auto size-10 text-stone-300" />
            <p class="mt-3 text-base font-semibold text-stone-800">Sumber dana belum dipilih</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-stone-500">RAB disusun terpisah untuk tiap sumber dana. Pilih DIPA, Komite, atau keduanya beserta pagunya di Data Kegiatan.</p>
            <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}#sumber-dana" class="btn-primary mt-5">
                <x-ui.icon name="wallet" class="size-4" />
                Atur sumber dana
            </a>
        </div>
    @else
        {{-- Pilih RAB per sumber dana --}}
        <div class="mt-6 flex flex-wrap gap-2" role="tablist" aria-label="RAB per sumber dana">
            @foreach ($dataKegiatan['sumber_dana'] as $baris)
                @php $aktif = $baris['sumber'] === $sumber; @endphp
                <button type="button" role="tab" aria-selected="{{ $aktif ? 'true' : 'false' }}"
                        wire:click="pilihSumber('{{ $baris['sumber'] }}')"
                        @class([
                            'min-w-40 rounded-xl px-4 py-2.5 text-left ring-1 transition',
                            'bg-brand-800 text-white ring-brand-800 shadow-sm' => $aktif,
                            'bg-white text-stone-700 ring-stone-200 hover:ring-stone-300' => ! $aktif,
                        ])>
                    <span class="block text-sm font-semibold">RAB {{ $baris['sumber'] }}</span>
                    <span @class(['block text-xs', 'text-brand-100' => $aktif, 'text-stone-500' => ! $aktif])>
                        Pagu {{ $baris['pagu'] ? Format::rupiah($baris['pagu']) : 'belum diisi' }}
                    </span>
                </button>
            @endforeach
        </div>

        <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_17rem]">
            <div class="min-w-0 space-y-6">
                {{-- Header RAB (format resmi) --}}
                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" data-testid="header-rab">
                    <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
                        <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Rencana Anggaran Belanja</p>
                        <h2 class="mt-0.5 text-base font-bold text-stone-900 uppercase">Kegiatan {{ $dataKegiatan['nama_kegiatan'] }}</h2>
                    </header>
                    <dl class="grid gap-x-6 gap-y-3 px-5 py-4 text-sm sm:grid-cols-2 sm:px-6">
                        @foreach ([
                            'Leading sektor' => $this->header['leading'],
                            'Nama program kegiatan' => $this->header['program'],
                            'Kode anggaran' => $this->header['kode_anggaran'],
                            'Tahun anggaran' => $this->header['tahun'],
                            'Sumber dana' => $sumber,
                            'Jumlah yang diajukan' => Format::rupiahDokumen($this->total),
                        ] as $label => $nilai)
                            <div class="min-w-0">
                                <dt class="text-xs text-stone-500">{{ $label }}</dt>
                                <dd class="font-medium text-stone-900">{{ $nilai }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>

                {{-- Rincian belanja --}}
                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" data-testid="rincian-rab">
                    <header class="flex items-center justify-between gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
                        <div>
                            <h2 class="text-base font-semibold text-stone-900">Rincian belanja</h2>
                            <p class="text-sm text-stone-500">{{ count($this->rincian) }} baris · jumlah = volume × harga satuan</p>
                        </div>
                        @if ($this->dapatDiedit() && ! $formItemTerbuka)
                            <button type="button" wire:click="bukaFormItem" class="btn-secondary shrink-0">
                                <x-ui.icon name="plus" class="size-4" />
                                <span class="hidden sm:inline">Tambah item</span>
                            </button>
                        @endif
                    </header>

                    @if ($itemTerhapus && $itemTerhapus['sumber'] === $sumber)
                        <div class="flex items-center justify-between gap-3 border-b border-stone-100 bg-stone-800 px-5 py-2.5 text-sm text-white sm:px-6" role="status">
                            <span class="min-w-0 truncate">“{{ $itemTerhapus['item']['uraian'] }}” dihapus dari RAB {{ $sumber }}.</span>
                            <button type="button" wire:click="urungkanHapusItem" class="shrink-0 font-semibold text-gold-300 hover:text-gold-200">Urungkan</button>
                        </div>
                    @endif

                    @if ($this->rincian === [])
                        <div class="px-6 py-10 text-center">
                            <x-ui.icon name="coins" class="mx-auto size-8 text-stone-300" />
                            <p class="mt-2 text-sm font-medium text-stone-700">Belum ada rincian belanja untuk RAB {{ $sumber }}</p>
                            <p class="mt-0.5 text-xs text-stone-500">Rincian ditambahkan baris demi baris; jumlah, total, dan terbilang dihitung otomatis.</p>
                        </div>
                    @else
                        @include('livewire.kegiatan.partials.tabel-rincian')
                    @endif

                    @if ($this->dapatDiedit())
                        @if ($formItemTerbuka)
                            @include('livewire.kegiatan.partials.form-item-belanja')
                        @else
                            <div class="border-t border-stone-100 px-5 py-4 sm:px-6">
                                <button type="button" wire:click="bukaFormItem"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-stone-300 py-2.5 text-sm font-medium text-stone-600 hover:border-brand-400 hover:bg-brand-50 hover:text-brand-800">
                                    <x-ui.icon name="plus" class="size-4" />
                                    Tambah item belanja
                                </button>
                            </div>
                        @endif
                    @else
                        <p class="flex items-center gap-2 border-t border-stone-100 px-5 py-3 text-xs text-stone-500 sm:px-6">
                            <x-ui.icon name="lock" class="size-3.5" />
                            RAB terkunci karena kegiatan berstatus {{ $this->status()->label() }}.
                        </p>
                    @endif
                </section>
            </div>

            {{-- Ringkasan --}}
            <aside class="space-y-4">
                <div class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" data-testid="ringkasan-rab">
                    <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Ringkasan RAB {{ $sumber }}</p>
                    <x-ui.uang :nilai="$this->total" class="mt-2 block text-2xl font-bold text-stone-900" />
                    <p class="text-xs text-stone-500">Total RAB = total KAK = jumlah yang diajukan</p>

                    <div class="mt-4 space-y-1.5 text-sm">
                        <div class="flex justify-between"><span class="text-stone-500">Pagu</span>@if ($pagu !== null)<x-ui.uang :nilai="$pagu" class="font-medium" />@else<span>—</span>@endif</div>
                        @if ($selisih !== null)
                            <div class="flex justify-between">
                                <span class="text-stone-500" data-testid="status-pagu">{{ ['sesuai' => 'Sesuai pagu', 'sisa' => 'Sisa pagu', 'melebihi' => 'Melebihi pagu'][$statusPagu] }}</span>
                                <span @class(['font-semibold tabular-nums', 'text-brand-700' => $selisih >= 0, 'text-red-600' => $selisih < 0])>{{ Format::rupiah(abs($selisih)) }}</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-stone-100" aria-hidden="true">
                                <div @class(['h-full rounded-full', 'bg-brand-600' => $selisih >= 0, 'bg-red-500' => $selisih < 0]) style="width: {{ $hasil->persenPagu() }}%"></div>
                            </div>
                        @endif
                    </div>

                    @if ($hasil->jumlahItem() > 0)
                        <div class="mt-4 border-t border-stone-100 pt-3">
                            <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Per kelompok belanja</p>
                            <dl class="mt-2 space-y-1 text-sm">
                                @foreach (ItemBelanjaForm::LABEL_KATEGORI as $kunci => $label)
                                    <div>
                                        <button type="button" class="-mx-1.5 flex w-[calc(100%+0.75rem)] justify-between rounded-md px-1.5 py-0.5 text-left hover:bg-stone-100"
                                                title="Tampilkan kelompok {{ $label }}"
                                                x-on:click="$wire.$set('kelompok', 'kategori').then(() => $nextTick(() => [...document.querySelectorAll('[data-kelompok-anchor={{ $kunci }}]')].find(el => el.offsetParent !== null)?.scrollIntoView({ behavior: 'smooth', block: 'center' })))">
                                            <dt class="text-stone-500 underline decoration-stone-300 underline-offset-2">{{ $label }}</dt>
                                            <dd><x-ui.uang :nilai="$hasil->perKategori[$kunci] ?? 0" /></dd>
                                        </button>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endif
                </div>

                <a href="{{ route('kegiatan.edit', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="btn-secondary w-full">
                    <x-ui.icon name="clipboard" class="size-4" />
                    Kembali ke Data Kegiatan
                </a>
            </aside>
        </div>
    @endif
</div>
