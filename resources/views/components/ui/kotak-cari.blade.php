{{--
    Kotak pencarian kata kunci (dipakai bersama): input `type=search` berikon dengan tombol hapus, pintasan "/" untuk fokus
    (kecuali sedang mengetik di isian lain), Esc mengosongkan, dan keterangan hasil di bawahnya (`jumlah` dari `total`).
    Atribut `wire:model…` diteruskan ke input; `model` = nama properti Livewire untuk tombol hapus & Esc, `nilai` = isinya
    saat dirender (keterangan hasil & tombol hapus).
--}}
@props(['model', 'nilai' => '', 'label' => 'Cari', 'placeholder' => 'Cari…', 'jumlah' => null, 'total' => null, 'satuan' => 'hasil', 'testid' => 'kotak-cari'])

@php
    $nilai = trim((string) $nilai);
@endphp

<div {{ $attributes->whereDoesntStartWith('wire:model')->class(['min-w-0']) }} x-data x-on:keydown.window="if ($event.key === '/' && ! ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName) && ! document.activeElement?.isContentEditable) { $event.preventDefault(); $refs.cari.focus() }">
    <label class="relative block">
        <span class="sr-only">{{ $label }}</span>
        <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-400" />
        <input type="search" x-ref="cari" {{ $attributes->whereStartsWith('wire:model') }} placeholder="{{ $placeholder }}" autocomplete="off" enterkeyhint="search"
               x-on:keydown.escape.prevent="$wire.set('{{ $model }}', ''); $el.blur()" aria-describedby="{{ $testid }}-hasil"
               class="form-input py-2 pr-16 pl-9 [&::-webkit-search-cancel-button]:hidden" data-testid="{{ $testid }}">
        <span class="pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 rounded border border-stone-300 px-1.5 font-mono text-[10px] text-stone-400 sm:block" aria-hidden="true" @if ($nilai !== '') style="display:none" @endif>/</span>
        @if ($nilai !== '')
            <button type="button" wire:click="$set('{{ $model }}', '')" class="absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-1 text-stone-400 hover:bg-stone-100 hover:text-stone-700" data-testid="{{ $testid }}-hapus">
                <x-ui.icon name="x" class="size-4" /><span class="sr-only">Hapus pencarian</span>
            </button>
        @endif
    </label>
    <p id="{{ $testid }}-hasil" class="mt-1.5 min-h-4 text-xs text-stone-500" aria-live="polite" data-testid="{{ $testid }}-hasil">
        @if ($nilai !== '' && $jumlah !== null)
            <span class="font-semibold text-stone-700">{{ $jumlah }}</span> {{ $satuan }} cocok dengan “{{ $nilai }}”@if ($total !== null) dari {{ $total }}@endif.
        @endif
    </p>
</div>
