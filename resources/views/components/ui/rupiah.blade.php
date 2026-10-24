@props(['id', 'model', 'disabled' => false, 'aktifJika' => null, 'placeholder' => '0'])

{{--
    Input nominal rupiah: titik ribuan ditambahkan saat mengetik; server menyimpan angka bulat.
    aktifJika: path state $wire (mis. "form.dana_dipa") agar input aktif seketika tanpa menunggu respons server.
    Format dijalankan pada fase capture (sebelum wire:model membaca nilai) sehingga nilai klien sudah sama dengan hasil
    normalisasi server; bila berbeda, respons request lain bisa menimpa ketikan terbaru (lihat CLAUDE.md).
--}}
<div class="relative">
    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm font-medium text-stone-500">Rp</span>
    <input type="text" id="{{ $id }}" inputmode="numeric" autocomplete="off" placeholder="{{ $placeholder }}"
           wire:model.live.blur="{{ $model }}"
           x-on:input.capture="$el.value = $rapikanKetikan($el.value)"
           @disabled($disabled)
           @if ($aktifJika) x-bind:disabled="! $wire.{{ $aktifJika }}" @endif
           {{ $attributes->merge(['class' => 'form-input pl-10 text-right tabular-nums']) }}
           @error($model) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>
</div>
