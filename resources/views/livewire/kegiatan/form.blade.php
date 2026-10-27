<div class="mx-auto max-w-7xl"
     {{-- dirty: ada perubahan belum disimpan → peringatan saat menutup/meninggalkan halaman --}}
     x-on:input="dirty = true"
     x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = ''; }"
     x-data="{
        dirty: $wire.entangle('adaPerubahan'),
        active: @js($this->sections[0]['id']),
        init() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => { if (entry.isIntersecting) this.active = entry.target.dataset.section; });
            }, { rootMargin: '-35% 0px -60% 0px' });
            this.$root.querySelectorAll('[data-section]').forEach((el) => observer.observe(el));
        },
     }">
    {{-- Kepala halaman --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <nav class="flex items-center gap-1 text-xs font-medium text-stone-500" aria-label="Breadcrumb">
                <a href="{{ route('kegiatan.index') }}" wire:navigate class="hover:text-stone-800">Data Kegiatan</a>
                <x-ui.icon name="chevron-right" class="size-3.5" />
                <span class="truncate text-stone-700">{{ $kegiatanId ? 'Edit kegiatan' : 'Kegiatan Baru' }}</span>
            </nav>
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <h1 class="min-w-0 text-xl font-bold tracking-tight text-balance text-stone-900 sm:text-2xl">
                    {{ $kegiatanId && $form->nama_kegiatan ? $form->nama_kegiatan : 'Kegiatan Baru' }}
                </h1>
                <x-ui.status-badge :status="$status" />
            </div>
            <p class="mt-1 flex flex-wrap items-center gap-x-2 text-sm text-stone-500">
                @if ($diperbaruiPada)
                    <span data-testid="info-simpan">Terakhir disimpan {{ \Illuminate\Support\Carbon::parse($diperbaruiPada)->locale('id')->translatedFormat('j F Y, H.i') }}</span>
                @else
                    <span>Isi data sekali — cover, KAK/TOR, RAB, dan lembar pengesahan Tahun Anggaran {{ $this->tahunAktif['tahun'] }} disusun dari sini.</span>
                @endif
                @if ($adaPerubahan)
                    <span class="inline-flex items-center gap-1 font-medium text-gold-700" data-testid="belum-disimpan">
                        <span class="size-1.5 rounded-full bg-gold-500"></span> Ada perubahan belum disimpan
                    </span>
                @endif
            </p>
        </div>
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{-- Buka draf lain --}}
            <div x-data="{ open: false }" class="relative" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                <button type="button" class="btn-secondary" x-on:click="open = ! open" :aria-expanded="open" aria-haspopup="true">
                    <x-ui.icon name="history" class="size-4" />
                    Buka draf
                </button>
                <div x-cloak x-show="open" x-transition.origin.top
                     class="absolute left-0 z-30 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-xl bg-white p-1.5 shadow-lg ring-1 ring-stone-200 sm:right-0 sm:left-auto">
                    @if ($kegiatanId)
                        <a href="{{ route('kegiatan.create') }}"
                           class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm font-semibold text-brand-800 hover:bg-brand-50">
                            <x-ui.icon name="plus" class="size-4" /> Kegiatan baru
                        </a>
                        <div class="my-1 border-t border-stone-100"></div>
                    @endif
                    <p class="px-2.5 pt-1.5 pb-1 text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Draf terbaru</p>
                    @forelse ($this->drafTerbaru as $draf)
                        <a href="{{ route('kegiatan.edit', ['kegiatan' => $draf['id']]) }}"
                           @class(['flex items-start justify-between gap-3 rounded-lg px-2.5 py-2 hover:bg-stone-50', 'bg-brand-50' => $draf['id'] === $kegiatanId])>
                            <span class="min-w-0">
                                <span class="line-clamp-2 text-sm font-medium text-stone-800">{{ $draf['nama_kegiatan'] ?: 'Tanpa nama' }}</span>
                                <span class="block text-xs text-stone-500">{{ \Illuminate\Support\Carbon::parse($draf['diperbarui_pada'])->locale('id')->diffForHumans() }}</span>
                            </span>
                            <x-ui.status-badge :status="$draf['status']" class="shrink-0" />
                        </a>
                    @empty
                        <p class="px-2.5 py-3 text-sm text-stone-500">Belum ada draf tersimpan.</p>
                    @endforelse
                </div>
            </div>

            <button type="button" class="btn-secondary" wire:click="$set('pratinjauTerbuka', true)" title="Pratinjau KAK/TOR dari isian saat ini">
                <x-ui.icon name="eye" class="size-4" />
                Pratinjau
            </button>
            @if ($this->dapatDiedit())
                <button type="button" class="btn-primary" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                    <x-ui.icon name="save" class="size-4" />
                    <span wire:loading.remove wire:target="saveDraft">Simpan Draf</span>
                    <span wire:loading wire:target="saveDraft">Menyimpan…</span>
                </button>
            @endif
        </div>
    </div>

    <div class="mt-5">
        <x-kegiatan.workspace-tabs active="data" :kegiatan-id="$kegiatanId" />
    </div>

    {{-- Navigasi bagian versi mobile --}}
    <div class="sticky top-16 z-20 -mx-4 mt-4 overflow-x-auto border-b border-stone-200 bg-stone-50/95 px-4 py-2 backdrop-blur sm:-mx-6 sm:px-6 lg:hidden">
        <div class="flex gap-2">
            @foreach ($this->sections as $section)
                <a href="#{{ $section['id'] }}"
                   x-effect="if (active === @js($section['id'])) $el.closest('.overflow-x-auto').scrollTo({ left: $el.offsetLeft - 16, behavior: 'smooth' })"
                   class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset transition"
                   :class="active === @js($section['id']) ? 'bg-brand-700 text-white ring-brand-700' : 'bg-white text-stone-600 ring-stone-300'">
                    {{ $loop->iteration }}. {{ $section['title'] }}@if (($this->kelengkapan[$section['id']] ?? null) === 'lengkap') ✓@endif
                </a>
            @endforeach
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_15rem]">
        <form wire:submit="saveDraft" class="min-w-0 space-y-6">
            {{-- Panel status versi mobile --}}
            <div class="lg:hidden">
                @include('livewire.kegiatan.partials.status-panel')
            </div>

            @unless ($this->dapatDiedit())
                <div @class([
                        'flex items-start gap-3 rounded-xl px-4 py-3 text-sm ring-1',
                        'bg-brand-900 text-brand-50 ring-brand-900' => $status === 'final',
                        'bg-sky-50 text-sky-900 ring-sky-200' => $status !== 'final',
                     ]) role="status" data-testid="banner-terkunci">
                    <x-ui.icon name="lock" class="mt-0.5 size-4 shrink-0" />
                    <div>
                        <p class="font-semibold">Isian terkunci — status {{ $this->statusKegiatan()->label() }}</p>
                        <p class="mt-0.5 opacity-90">{{ $this->statusKegiatan()->deskripsi() }}</p>
                    </div>
                </div>
            @endunless

            @include('livewire.kegiatan.partials.banner-perbaikan')

            {{-- fieldset[disabled] mengunci semua isian & tombol di dalamnya saat status bukan Draf/Dikembalikan --}}
            <fieldset @disabled(! $this->dapatDiedit()) class="min-w-0 space-y-6">
            @foreach ($this->sections as $section)
                <x-ui.form-section :id="$section['id']" :number="$loop->iteration" :title="$section['title']"
                                   :description="$section['description']" :icon="$section['icon']"
                                   :kelengkapan="$this->kelengkapan[$section['id']] ?? null">
                    @include('livewire.kegiatan.sections.'.$section['id'])
                </x-ui.form-section>
            @endforeach
            </fieldset>

            <div class="lg:hidden">
                @include('livewire.kegiatan.partials.ringkasan-pagu', ['varian' => 'bawah'])
            </div>

            @include('livewire.kegiatan.partials.lanjut-rab')
        </form>

        {{-- Navigasi bagian versi desktop --}}
        <aside class="hidden space-y-4 lg:block">
            @include('livewire.kegiatan.partials.status-panel')

            <div class="sticky top-24 space-y-4">

                @php
                    $wajib = array_intersect_key($this->kelengkapan, array_flip(\App\Support\Kegiatan\FormSections::wajib()));
                    $wajibLengkap = count(array_filter($wajib, fn ($k) => $k === 'lengkap'));
                @endphp
                <nav class="rounded-2xl bg-white p-3 shadow-xs ring-1 ring-stone-200" aria-label="Bagian form">
                    <div class="flex items-baseline justify-between px-2 pt-1 pb-2">
                        <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Bagian form</p>
                        <p class="text-[11px] font-semibold text-stone-500" data-testid="ringkasan-kelengkapan">{{ $wajibLengkap }}/{{ count($wajib) }} lengkap</p>
                    </div>
                    <div class="mx-2 mb-2 h-1 overflow-hidden rounded-full bg-stone-100">
                        <div class="h-full rounded-full bg-brand-600 transition-all" style="width: {{ count($wajib) ? round($wajibLengkap / count($wajib) * 100) : 0 }}%"></div>
                    </div>
                    <ol class="space-y-0.5">
                        @foreach ($this->sections as $section)
                            <li>
                                <a href="#{{ $section['id'] }}"
                                   class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm transition"
                                   :class="active === @js($section['id']) ? 'bg-brand-50 font-semibold text-brand-800' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900'">
                                    @if (($this->kelengkapan[$section['id']] ?? null) === 'lengkap')
                                        <span class="grid size-5 shrink-0 place-items-center rounded-full bg-brand-100 text-brand-700" title="Lengkap">
                                            <x-ui.icon name="check" class="size-3" />
                                        </span>
                                    @else
                                        <span class="grid size-5 shrink-0 place-items-center rounded-full text-[11px] font-bold"
                                              :class="active === @js($section['id']) ? 'bg-brand-700 text-white' : 'bg-stone-100 text-stone-500'">{{ $loop->iteration }}</span>
                                    @endif
                                    {{ $section['title'] }}
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </nav>

                @include('livewire.kegiatan.partials.ringkasan-pagu', ['varian' => 'samping'])

                <div class="rounded-2xl bg-gold-50 p-4 text-xs text-gold-700 ring-1 ring-gold-200">
                    <p class="flex items-center gap-1.5 font-semibold">
                        <x-ui.icon name="info" class="size-4" />
                        Satu data, semua dokumen
                    </p>
                    <p class="mt-1.5 leading-relaxed">Tahun, pejabat penanda tangan, kode, total, dan terbilang diambil otomatis — tidak perlu diketik ulang di Word.</p>
                </div>
            </div>
        </aside>
    </div>

    {{-- Panel pratinjau KAK/TOR --}}
    @if ($pratinjauTerbuka)
        @php
            $kak = $this->susunanKak;
        @endphp
        <div class="fixed inset-0 z-50 flex" role="dialog" aria-modal="true" aria-label="Pratinjau KAK/TOR" data-testid="panel-pratinjau"
             x-data="{ init() { document.documentElement.classList.add('overflow-hidden') }, destroy() { document.documentElement.classList.remove('overflow-hidden') } }"
             x-on:keydown.escape.window="$wire.set('pratinjauTerbuka', false)">
            <div class="absolute inset-0 bg-stone-900/40" wire:click="$set('pratinjauTerbuka', false)" aria-hidden="true"></div>
            <div class="relative ml-auto flex h-full w-full max-w-4xl flex-col bg-stone-100 shadow-xl">
                <header class="flex flex-wrap items-center gap-3 border-b border-stone-200 bg-white px-4 py-3 sm:px-6">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-stone-900">Pratinjau KAK/TOR</p>
                        <p class="hidden text-xs text-stone-500 sm:block">Dari isian saat ini, termasuk yang belum disimpan.</p>
                    </div>
                    @if (count($kak->daftarSumber) > 1)
                        <div class="order-last inline-flex rounded-lg bg-stone-100 p-0.5 sm:order-none" role="tablist" aria-label="Dokumen per sumber dana">
                            @foreach ($kak->daftarSumber as $sumberDok)
                                <button type="button" role="tab" aria-selected="{{ $sumberDok === $kak->sumber ? 'true' : 'false' }}"
                                        wire:click="$set('pratinjauSumber', '{{ $sumberDok }}')"
                                        @class(['inline-flex items-center gap-1.5 rounded-md px-3 py-1 text-xs font-semibold', 'bg-white text-stone-900 shadow-xs' => $sumberDok === $kak->sumber, 'text-stone-500 hover:text-stone-800' => $sumberDok !== $kak->sumber])>
                                    KAK {{ $sumberDok }}
                                    @if (in_array($kak->statusPagu[$sumberDok] ?? null, ['melebihi', 'tanpa_pagu'], true))
                                        @php $melebihi = $kak->statusPagu[$sumberDok] === 'melebihi'; @endphp
                                        <span @class(['size-1.5 rounded-full', 'bg-red-500' => $melebihi, 'bg-gold-500' => ! $melebihi])
                                              data-testid="tanda-pagu-{{ mb_strtolower($sumberDok) }}" title="{{ $melebihi ? 'RAB melebihi pagu' : 'Pagu belum diisi' }}"></span>
                                        <span class="sr-only">({{ $melebihi ? 'RAB melebihi pagu' : 'pagu belum diisi' }})</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endif
                    <button type="button" class="btn-ghost" wire:click="$set('pratinjauTerbuka', false)">
                        <x-ui.icon name="x" class="size-4" />
                        Tutup
                    </button>
                </header>

                @if ($kak->kurang)
                    <div class="flex flex-wrap items-center gap-1.5 border-b border-gold-200 bg-gold-50 px-4 py-2 text-xs text-stone-700 sm:px-6" data-testid="pratinjau-kurang">
                        <x-ui.icon name="alert" class="size-4 shrink-0 text-gold-600" />
                        <span class="font-semibold">{{ count($kak->kurang) }} isian belum lengkap:</span>
                        @foreach ($kak->kurang as $apa)
                            <span class="rounded-full bg-white px-2 py-0.5 ring-1 ring-gold-200">{{ $apa }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="flex-1 overflow-y-auto p-3 sm:p-6">
                    {{-- Ikut tergulir bersama dokumen agar layar sempit tetap lega. --}}
                    @if ($kak->peringatanPagu)
                        <x-rab.peringatan-pagu :sumber="$kak->sumber" :hasil="$kak->peringatanPagu->hasil" ringkas class="mx-auto mb-3 max-w-[210mm]">
                            @if ($kegiatanId)
                                <a href="{{ route('kegiatan.rab', ['kegiatan' => $kegiatanId, 'sumber' => $kak->sumber]) }}" wire:navigate class="text-brand-700 hover:text-brand-800">Buka RAB {{ $kak->sumber }}</a>
                            @endif
                        </x-rab.peringatan-pagu>
                    @endif
                    <x-dokumen.kak-tor :susunan="$kak" />
                </div>
            </div>
        </div>
    @endif
</div>
