@use('App\Support\Rab\MekanismePembayaran')
@use('App\Support\Format')
@use('App\Livewire\Forms\ItemBelanjaForm')

@php
    $hasil = $this->hasil;
    $pagu = $hasil->pagu;
    $selisih = $hasil->selisih();
    $angkaDokumen = $this->angkaDokumen;
@endphp

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="min-w-0">
        <nav class="flex items-center gap-1 text-xs font-medium text-stone-500" aria-label="Breadcrumb">
            <a href="{{ route('kegiatan.index') }}" wire:navigate class="hover:text-stone-800">Data Kegiatan</a>
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

    <x-kegiatan.banner-kunci :kegiatan-id="$kegiatanId" :status="$this->status()->value" subjek="RAB" class="mt-6" />

    @if ($this->tampilan->mode === 'tanpa_sumber')
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
        @include('livewire.kegiatan.partials.ringkasan-sumber')

        {{-- Halaman di dalam RAB --}}
        {{-- Layar sempit: tab membungkus ke baris berikutnya agar semua halaman tetap terlihat. --}}
        <div class="mt-6 flex flex-wrap gap-0.5 rounded-xl bg-stone-100 p-1 sm:inline-flex sm:flex-nowrap" role="tablist" aria-label="Halaman RAB">
            @foreach ($this->tampilan->halamanTersedia() as $kunciHalaman => $labelHalaman)
                <button type="button" role="tab" aria-selected="{{ $halaman === $kunciHalaman ? 'true' : 'false' }}"
                        wire:click="$set('halaman', '{{ $kunciHalaman }}')"
                        @class([
                            'rounded-lg px-3 py-1.5 text-sm font-semibold whitespace-nowrap transition sm:px-3.5',
                            'bg-white text-stone-900 shadow-xs' => $halaman === $kunciHalaman,
                            'text-stone-500 hover:text-stone-800' => $halaman !== $kunciHalaman,
                        ])>{{ $labelHalaman }}</button>
            @endforeach
        </div>

        @if ($halaman === 'rekap')
            @include('livewire.kegiatan.partials.rekap-kelompok')
        @elseif ($halaman === 'semua')
            @include('livewire.kegiatan.partials.semua-rab')
        @elseif ($halaman === 'pagu')
            @include('livewire.kegiatan.partials.tabel-pagu')
        @elseif ($halaman === 'termin')
            @if ($tujuanTertunda)
                <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gold-50 px-4 py-3 text-sm text-stone-800 ring-1 ring-gold-300"
                     role="alertdialog" aria-label="Termin belum disimpan" data-testid="konfirmasi-tinggalkan-termin">
                    <span class="flex items-start gap-2">
                        <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0 text-gold-600" />
                        Perubahan termin RAB {{ $terminBelumDisimpan }} belum disimpan. Tinggalkan tanpa menyimpan?
                    </span>
                    <span class="flex gap-2">
                        <button type="button" wire:click="tetapDiTermin" class="btn-ghost px-3 py-1.5 text-sm">Kembali menyunting</button>
                        <button type="button" wire:click="tinggalkanTermin" class="btn-secondary px-3 py-1.5 text-sm text-red-700">Tinggalkan tanpa menyimpan</button>
                    </span>
                </div>
            @endif
            <livewire:kegiatan.termin-pembayaran :kegiatan-id="$kegiatanId" :sumber="$sumber" wire:key="termin-{{ $sumber }}" />
        @else
        <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_17rem]">
            <div class="min-w-0 space-y-6">
                <x-rab.peringatan-pagu :sumber="$sumber" :hasil="$hasil" :tautan-pagu="route('kegiatan.edit', ['kegiatan' => $kegiatanId]).'#sumber-dana'">
                    <button type="button" wire:click="$set('halaman', 'pagu')" class="text-brand-700 hover:text-brand-800">Lihat Pagu &amp; RAB</button>
                </x-rab.peringatan-pagu>

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
                            'Sumber dana' => $angkaDokumen->sebutanSumber(),
                        ] as $label => $nilai)
                            <div class="min-w-0">
                                <dt class="text-xs text-stone-500">{{ $label }}</dt>
                                <dd class="font-medium text-stone-900">{{ $nilai }}</dd>
                            </div>
                        @endforeach
                        <div class="min-w-0">
                            <dt class="text-xs text-stone-500">Jumlah yang diajukan</dt>
                            <dd class="font-medium text-stone-900" data-testid="header-nominal">{{ $angkaDokumen->nominal() }}</dd>
                            @if ($angkaDokumen->adaRincian())
                                <dd class="text-xs text-stone-500 italic" data-testid="header-terbilang">{{ $angkaDokumen->terbilang() }}</dd>
                            @else
                                <dd class="text-xs text-stone-400" data-testid="header-terbilang">belum ada rincian</dd>
                            @endif
                        </div>
                    </dl>

                    {{-- Kalimat Pembiayaan KAK: angka & terbilang dari total yang sama --}}
                    <div class="border-t border-stone-100 bg-stone-50/60 px-5 py-3.5 sm:px-6" data-testid="kalimat-pembiayaan">
                        <p class="flex items-center gap-1.5 text-[11px] font-semibold tracking-wider text-stone-400 uppercase">
                            <x-ui.icon name="file-text" class="size-3.5" /> Tercetak di KAK · Pembiayaan
                        </p>
                        @if ($angkaDokumen->adaRincian())
                            <p class="mt-1 text-sm leading-relaxed text-stone-700">{{ $angkaDokumen->kalimatPembiayaan() }}</p>
                        @else
                            <p class="mt-1 flex items-start gap-1.5 text-sm text-gold-700" data-testid="kalimat-pembiayaan-kosong">
                                <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                                Kalimat pembiayaan tercetak setelah rincian RAB {{ $sumber }} diisi; tanpa rincian, KAK tidak memuat nominal dan terbilang.
                            </p>
                        @endif
                    </div>
                </section>

                @php $anggaranAktif = collect($this->anggaran)->firstWhere('sumber', $sumber); @endphp
                @if ($anggaranAktif && $anggaranAktif->adaTermin() && ! $anggaranAktif->terminSesuai())
                    {{-- Rincian berubah setelah termin disimpan: jumlah termin tidak lagi sama dengan total RAB. --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-gold-50 px-4 py-3 text-sm text-stone-800 ring-1 ring-gold-300" role="status" data-testid="termin-tidak-pas">
                        <span class="flex items-start gap-2">
                            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0 text-gold-600" />
                            Termin RAB {{ $sumber }} tidak lagi sama dengan total RAB — {{ MekanismePembayaran::dari($anggaranAktif)->teksStatusKalimat() }}.
                        </span>
                        <button type="button" wire:click="$set('halaman', 'termin')" class="btn-secondary px-3 py-1.5 text-sm">Sesuaikan termin</button>
                    </div>
                @endif

                {{-- Rincian belanja --}}
                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" data-testid="rincian-rab">
                    <header class="flex items-center justify-between gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
                        <div class="min-w-0">
                            <h2 class="flex flex-wrap items-center gap-2 text-base font-semibold text-stone-900">
                                Rincian belanja <x-rab.lencana-sumber :sumber="$sumber" awalan="RAB" class="text-xs" />
                            </h2>
                            {{-- Pagu & statusnya tetap terlihat saat menyunting (di layar sempit ringkasan ada di bawah tabel). --}}
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm text-stone-500" data-testid="pagu-rincian">
                                <span>{{ count($this->rincian) }} baris · Pagu {{ $pagu !== null ? Format::rupiah($pagu) : 'belum diisi' }}</span>
                                <x-rab.status-pagu :status="$hasil->keteranganPagu()" class="text-xs" />
                            </p>
                        </div>
                        @if ($this->dapatDiedit())
                            <div class="flex shrink-0 gap-2">
                                <button type="button" wire:click="bukaImpor" class="btn-secondary" aria-expanded="{{ $imporTerbuka ? 'true' : 'false' }}"
                                        aria-controls="panel-impor-rab" title="Impor rincian RAB {{ $sumber }} dari Excel">
                                    <x-ui.icon name="sheet" class="size-4" />
                                    <span class="hidden sm:inline">Impor Excel</span>
                                </button>
                                @unless ($formItemTerbuka)
                                    <button type="button" wire:click="bukaFormItem" class="btn-secondary">
                                        <x-ui.icon name="plus" class="size-4" />
                                        <span class="hidden sm:inline">Tambah item</span>
                                    </button>
                                @endunless
                            </div>
                        @endif
                    </header>

                    @if ($imporTerbuka && $this->dapatDiedit())
                        <div class="border-b border-stone-100 bg-stone-50/70 p-3 sm:p-5" id="panel-impor-rab">
                            <livewire:kegiatan.impor-rab :kegiatan-id="$kegiatanId" :sumber="$sumber" wire:key="impor-rab-{{ $sumber }}" />
                        </div>
                    @endif

                    @if ($itemDipindah && $itemDipindah['dari'] === $sumber)
                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 border-b border-stone-100 bg-stone-800 px-5 py-2.5 text-sm text-white sm:px-6" role="status" data-testid="banner-pindah">
                            <span class="min-w-0">“{{ $itemDipindah['item']['uraian'] }}” dipindah ke RAB {{ $itemDipindah['ke'] }}.</span>
                            <span class="flex shrink-0 gap-3">
                                <button type="button" wire:click="pilihSumber('{{ $itemDipindah['ke'] }}')" class="font-semibold text-stone-200 hover:text-white">Buka RAB {{ $itemDipindah['ke'] }}</button>
                                <button type="button" wire:click="urungkanPindahItem" class="font-semibold text-gold-300 hover:text-gold-200">Urungkan</button>
                            </span>
                        </div>
                    @endif

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
                    <x-rab.total-anggaran :nilai="$this->total" :sumber="$sumber" :pagu="$pagu" />

                    @if ($pagu !== null)
                        <div class="mt-3 space-y-1.5 text-sm">
                            <div class="flex justify-between"><span class="text-stone-500">Pagu</span><x-ui.uang :nilai="$pagu" class="font-medium" /></div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-stone-100" aria-hidden="true">
                                <div @class(['h-full rounded-full', 'bg-brand-600' => $selisih >= 0, 'bg-red-500' => $selisih < 0]) style="width: {{ $hasil->persenPagu() }}%"></div>
                            </div>
                        </div>
                    @endif

                    <x-dokumen.tempat-cetak :angka="$angkaDokumen" :sumber="$sumber" :tautan="route('kegiatan.pratinjau', ['kegiatan' => $kegiatanId, 'sumber' => $sumber])" />

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
    @endif
</div>
