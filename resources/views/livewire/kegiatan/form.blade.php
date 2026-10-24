<div class="mx-auto max-w-7xl"
     x-data="{
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
                <span class="text-stone-700">Kegiatan Baru</span>
            </nav>
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Kegiatan Baru</h1>
                <x-ui.status-badge :status="$status" />
            </div>
            <p class="mt-1 text-sm text-stone-500">
                Isi data sekali — cover, KAK/TOR, RAB, dan lembar pengesahan Tahun Anggaran {{ $this->tahunAktif['tahun'] }} disusun dari sini.
            </p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <button type="button" class="btn-secondary" disabled title="Pratinjau tersedia setelah kegiatan disimpan">
                <x-ui.icon name="eye" class="size-4" />
                Pratinjau
            </button>
            <button type="button" class="btn-primary" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft">
                <x-ui.icon name="save" class="size-4" />
                <span wire:loading.remove wire:target="saveDraft">Simpan Draf</span>
                <span wire:loading wire:target="saveDraft">Menyimpan…</span>
            </button>
        </div>
    </div>

    <div class="mt-5">
        <x-kegiatan.workspace-tabs active="data" />
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
        <form wire:submit="saveDraft" class="space-y-6">
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

            <div class="flex flex-col-reverse gap-3 rounded-2xl bg-white px-5 py-4 shadow-xs ring-1 ring-stone-200 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p class="text-xs text-stone-500">Draf dapat dilanjutkan kapan saja. Dokumen yang belum lengkap tetap bisa dicetak dengan tanda “DRAF”.</p>
                <button type="submit" class="btn-primary shrink-0">
                    <x-ui.icon name="save" class="size-4" />
                    Simpan Draf
                </button>
            </div>
        </form>

        {{-- Navigasi bagian versi desktop --}}
        <aside class="hidden lg:block">
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
