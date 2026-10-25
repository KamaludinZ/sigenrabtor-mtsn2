@props([
    'id',
    'label',
    'model',
    'max' => 2000,
    'rows' => 3,
    'placeholder' => null,
    'hint' => null,
    'warning' => null,
    'required' => false,
])

{{--
    Input uraian kegiatan (teks panjang): tumbuh otomatis, penghitung karakter, tombol "Rapikan spasi".
    Sinkron ke server saat blur (wire:model.live.blur) sehingga validasi & normalisasi berjalan per isian.
    Slot $actions disediakan untuk tombol tambahan, mis. "Buat dengan AI".
--}}
<div {{ $attributes->merge(['class' => 'min-w-0']) }}
     x-data="{
        n: 0,
        max: {{ (int) $max }},
        init() {
            this.$nextTick(() => this.n = this.$refs.ta.value.length);
            this.$watch(() => this.$wire.$get(@js($model)), (nilai) => this.n = String(nilai ?? '').length);
        },
        rapikan() {
            const ta = this.$refs.ta;
            ta.value = ta.value.replace(/\r\n?/g, '\n').replace(/[ \t\u00A0]+/g, ' ').replace(/ *\n */g, '\n').replace(/\n{3,}/g, '\n\n').trim();
            this.n = ta.value.length;
            ta.dispatchEvent(new Event('input', { bubbles: true }));
            ta.dispatchEvent(new Event('blur'));
        },
     }">
    <x-ui.field :label="$label" :for="$id" :error="$model" :hint="$hint" :warning="$warning" :required="$required">
        <x-slot:aside>
            <span x-text="$angka(n) + ' / ' + $angka(max)"
                  :class="n > max * 0.9 ? 'font-semibold text-gold-700' : ''"></span>
        </x-slot:aside>

        <div class="rounded-lg bg-white shadow-xs ring-1 ring-stone-300 ring-inset focus-within:ring-2 focus-within:ring-brand-600
                    @error($model) ring-red-400 @enderror">
            <textarea id="{{ $id }}" x-ref="ta" wire:model.live.blur="{{ $model }}" rows="{{ $rows }}" maxlength="{{ $max }}"
                      x-on:input="n = $event.target.value.length"
                      placeholder="{{ $placeholder }}"
                      class="block w-full resize-y rounded-t-lg border-0 bg-transparent px-3 py-2 text-sm leading-relaxed text-stone-900
                             field-sizing-content placeholder:text-stone-400 focus:ring-0 focus:outline-none"
                      style="min-height: {{ max(2, (int) $rows) * 1.6 + 1 }}rem"
                      @error($model) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror></textarea>

            <div class="flex items-center justify-between gap-2 border-t border-stone-100 px-2 py-1.5">
                <div class="flex items-center gap-1">
                    {{ $actions ?? '' }}
                </div>
                <button type="button" x-on:click="rapikan()"
                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-stone-500 hover:bg-stone-100 hover:text-stone-800"
                        title="Gabungkan spasi berulang dan buang baris kosong berlebih">
                    <x-ui.icon name="text-align" class="size-3.5" />
                    Rapikan spasi
                </button>
            </div>
        </div>
    </x-ui.field>
</div>
