@props([
    'id',
    'model',
    'opsi',
    'placeholder' => 'Pilih…',
    'cariPlaceholder' => 'Ketik kode atau uraian…',
    'disabled' => false,
])

{{--
    Combobox bisa-dicari untuk daftar panjang (mis. 138 kode RKTM). Opsi: list of
    ['nilai' => string, 'label' => string, 'keterangan' => ?string, 'grup' => ?string].
    Nilai tersambung ke Livewire lewat $wire.entangle(...).live sehingga validasi server tetap berjalan per pilihan.
    wire:key memuat sidik opsi: bila daftar opsi berubah (mis. IKSK mengikuti SK), komponen dibuat ulang.
--}}
<div {{ $attributes->class(['relative min-w-0']) }}
     wire:key="pilih-cari-{{ $id }}-{{ md5(json_encode($opsi)) }}"
     x-data="{
        buka: false,
        cari: '',
        aktif: -1,
        nilai: $wire.entangle(@js($model)).live,
        opsi: @js(array_values($opsi)),
        get tersaring() {
            const q = this.cari.trim().toLowerCase();
            if (q === '') return this.opsi;
            return this.opsi.filter((o) => [o.nilai, o.label, o.keterangan, o.grup].join(' ').toLowerCase().includes(q));
        },
        get terpilih() { return this.opsi.find((o) => String(o.nilai) === String(this.nilai ?? '')) ?? null },
        bukaDaftar() {
            if (@js((bool) $disabled)) return;
            this.buka = true;
            this.aktif = Math.max(0, this.tersaring.findIndex((o) => o === this.terpilih));
            this.$nextTick(() => { this.$refs.cari.focus(); this.gulirKeAktif(); });
        },
        tutup(fokusTombol = true) {
            this.buka = false;
            this.cari = '';
            if (fokusTombol) this.$nextTick(() => this.$refs.tombol.focus());
        },
        pilih(o) { this.nilai = o.nilai; this.tutup(); },
        kosongkan() { this.nilai = ''; },
        gerak(arah) {
            const jumlah = this.tersaring.length;
            if (jumlah === 0) return;
            this.aktif = (this.aktif + arah + jumlah) % jumlah;
            this.$nextTick(() => this.gulirKeAktif());
        },
        gulirKeAktif() { this.$refs.daftar?.querySelector('[data-aktif=true]')?.scrollIntoView({ block: 'nearest' }) },
     }"
     x-on:click.outside="if (buka) tutup(false)"
     x-on:keydown.escape.prevent.stop="if (buka) tutup()">

    {{-- Tombol tampilan (tertutup) --}}
    <div class="flex min-w-0 items-stretch gap-1">
        <button type="button" id="{{ $id }}" x-ref="tombol" x-on:click="buka ? tutup() : bukaDaftar()"
                x-on:keydown.arrow-down.prevent="bukaDaftar()"
                role="combobox" aria-haspopup="listbox" :aria-expanded="buka" aria-controls="{{ $id }}-daftar"
                @disabled($disabled)
                class="form-input flex min-h-9.5 min-w-0 flex-1 items-center justify-between gap-2 text-left disabled:cursor-not-allowed">
            <span class="min-w-0 truncate" x-show="terpilih">
                <span class="font-mono text-xs font-semibold text-stone-500" x-text="terpilih?.nilai"></span>
                <span class="text-stone-900" x-text="terpilih ? '— ' + terpilih.label : ''"></span>
            </span>
            <span class="text-stone-400" x-show="! terpilih">{{ $placeholder }}</span>
            <x-ui.icon name="chevron-down" class="size-4 shrink-0 text-stone-400" />
        </button>
        <button type="button" x-show="terpilih && ! @js((bool) $disabled)" x-cloak x-on:click="kosongkan()"
                class="rounded-lg px-2 text-stone-400 ring-1 ring-stone-300 ring-inset hover:bg-stone-50 hover:text-stone-700"
                aria-label="Kosongkan pilihan" title="Kosongkan">
            <x-ui.icon name="x" class="size-4" />
        </button>
    </div>

    {{-- Panel daftar --}}
    <div x-cloak x-show="buka" x-transition.opacity.duration.100ms
         class="absolute inset-x-0 z-30 mt-1 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-stone-200">
        <div class="border-b border-stone-100 p-2">
            <label for="{{ $id }}-cari" class="sr-only">Cari</label>
            <div class="relative">
                <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-stone-400" />
                <input type="text" id="{{ $id }}-cari" x-ref="cari" x-model="cari" autocomplete="off" placeholder="{{ $cariPlaceholder }}"
                       role="searchbox" aria-controls="{{ $id }}-daftar"
                       :aria-activedescendant="aktif >= 0 ? '{{ $id }}-opsi-' + aktif : null"
                       x-on:input="aktif = 0"
                       x-on:keydown.arrow-down.prevent="gerak(1)"
                       x-on:keydown.arrow-up.prevent="gerak(-1)"
                       x-on:keydown.enter.prevent="tersaring[aktif] && pilih(tersaring[aktif])"
                       x-on:keydown.tab="tutup(false)"
                       class="form-input pl-8">
            </div>
        </div>
        <ul id="{{ $id }}-daftar" x-ref="daftar" role="listbox" class="max-h-72 overflow-y-auto py-1" aria-label="Pilihan">
            <template x-for="(o, i) in tersaring" :key="o.nilai">
                <li role="none">
                    <p x-show="o.grup && (i === 0 || tersaring[i - 1].grup !== o.grup)"
                       class="sticky top-0 bg-stone-50 px-3 py-1 text-[11px] font-semibold tracking-wide text-stone-500 uppercase" x-text="o.grup"></p>
                    <div :id="'{{ $id }}-opsi-' + i" role="option" :aria-selected="String(o.nilai) === String(nilai ?? '')"
                         :data-aktif="i === aktif" :data-nilai="o.nilai"
                         x-on:click="pilih(o)" x-on:mousemove="aktif = i"
                         :class="i === aktif ? 'bg-brand-50' : ''"
                         class="flex cursor-pointer items-start gap-2 px-3 py-2 text-sm">
                        <span class="mt-px w-14 shrink-0 font-mono text-xs font-semibold text-stone-500" x-text="o.nilai"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-stone-800" x-text="o.label"></span>
                            <span class="block text-xs text-stone-500" x-show="o.keterangan" x-text="o.keterangan"></span>
                        </span>
                        <x-ui.icon name="check" class="mt-0.5 size-4 shrink-0 text-brand-700" x-show="String(o.nilai) === String(nilai ?? '')" />
                    </div>
                </li>
            </template>
            <li x-show="tersaring.length === 0" class="px-3 py-6 text-center text-sm text-stone-500">Tidak ada yang cocok dengan “<span x-text="cari"></span>”.</li>
        </ul>
        <p class="border-t border-stone-100 px-3 py-1.5 text-[11px] text-stone-400">
            <span x-text="tersaring.length"></span> dari <span x-text="opsi.length"></span> · ↑↓ pilih · Enter tetapkan · Esc tutup
        </p>
    </div>
</div>
