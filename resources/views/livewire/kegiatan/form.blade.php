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
                <span>Data Kegiatan</span>
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

            <button type="button" class="btn-secondary" disabled title="Pratinjau tersedia pada fase dokumen">
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
                    {{ $loop->iteration }}. {{ $section['title'] }}
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

            {{-- fieldset[disabled] mengunci semua isian & tombol di dalamnya saat status bukan Draf/Dikembalikan --}}
            <fieldset @disabled(! $this->dapatDiedit()) class="min-w-0 space-y-6">
            @foreach ($this->sections as $section)
                <x-ui.form-section :id="$section['id']" :number="$loop->iteration" :title="$section['title']"
                                   :description="$section['description']" :icon="$section['icon']">
                    @if (view()->exists('livewire.kegiatan.sections.'.$section['id']))
                        @include('livewire.kegiatan.sections.'.$section['id'])
                    @else
                    <div class="rounded-xl border border-dashed border-stone-300 bg-stone-50 px-4 py-5">
                        <p class="text-sm font-medium text-stone-600">Isian bagian ini:</p>
                        <ul class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($section['fields'] as $field)
                                <li class="rounded-md bg-white px-2 py-1 text-xs text-stone-600 ring-1 ring-stone-200">{{ $field }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </x-ui.form-section>
            @endforeach
            </fieldset>

            @if ($this->dapatDiedit())
                <div class="flex flex-col-reverse gap-3 rounded-2xl bg-white px-5 py-4 shadow-xs ring-1 ring-stone-200 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p class="text-xs text-stone-500">Draf dapat dilanjutkan kapan saja. Dokumen yang belum lengkap tetap bisa dicetak dengan tanda “DRAF”.</p>
                    <button type="submit" class="btn-primary shrink-0">
                        <x-ui.icon name="save" class="size-4" />
                        Simpan Draf
                    </button>
                </div>
            @endif
        </form>

        {{-- Navigasi bagian versi desktop --}}
        <aside class="hidden space-y-4 lg:block">
            @include('livewire.kegiatan.partials.status-panel')

            <div class="sticky top-24 space-y-4">

                <nav class="rounded-2xl bg-white p-3 shadow-xs ring-1 ring-stone-200" aria-label="Bagian form">
                    <p class="px-2 pt-1 pb-2 text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Bagian form</p>
                    <ol class="space-y-0.5">
                        @foreach ($this->sections as $section)
                            <li>
                                <a href="#{{ $section['id'] }}"
                                   class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm transition"
                                   :class="active === @js($section['id']) ? 'bg-brand-50 font-semibold text-brand-800' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900'">
                                    <span class="grid size-5 shrink-0 place-items-center rounded-full text-[11px] font-bold"
                                          :class="active === @js($section['id']) ? 'bg-brand-700 text-white' : 'bg-stone-100 text-stone-500'">{{ $loop->iteration }}</span>
                                    {{ $section['title'] }}
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </nav>

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
</div>
