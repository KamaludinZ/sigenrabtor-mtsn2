@props(['kunci'])

@use('App\Support\Dokumen\SuntingDokumen')

@php
    $jenisSunting = SuntingDokumen::jenis($kunci);
@endphp

{{--
    Penyunting teks di tempat pada pratinjau KAK/TOR (SuntingDokumen): terikat ke properti Livewire `teksSunting` komponen
    pemiliknya, simpan `simpanSunting()`, Esc/Batal `tutupSunting()`. Tidak ikut tercetak.
--}}
<form wire:submit="simpanSunting" x-on:keydown.escape.prevent="$wire.tutupSunting()" class="my-1 space-y-2 rounded-lg bg-sky-50 p-3 font-sans text-sm leading-normal ring-1 ring-sky-200 print:hidden" data-testid="penyunting-{{ $kunci }}">
    <label for="sunting-{{ $kunci }}" class="block text-xs font-semibold text-sky-900">{{ SuntingDokumen::label($kunci) }}</label>
    @if ($jenisSunting === 'teks')
        <input type="text" id="sunting-{{ $kunci }}" wire:model="teksSunting" maxlength="{{ SuntingDokumen::maks($kunci) }}" x-init="$el.focus()"
               class="form-input text-sm" @error('teksSunting') aria-invalid="true" aria-describedby="sunting-{{ $kunci }}-galat" @enderror>
    @else
        <textarea id="sunting-{{ $kunci }}" wire:model="teksSunting" rows="{{ $jenisSunting === 'daftar' ? 5 : 4 }}" x-init="$el.focus()"
                  @if ($jenisSunting !== 'daftar') maxlength="{{ SuntingDokumen::maks($kunci) }}" @endif
                  class="form-input text-sm" @error('teksSunting') aria-invalid="true" aria-describedby="sunting-{{ $kunci }}-galat" @enderror></textarea>
    @endif
    @error('teksSunting')<p id="sunting-{{ $kunci }}-galat" class="form-error" role="alert">{{ $message }}</p>@enderror
    <p class="text-xs text-stone-500">{{ SuntingDokumen::petunjuk($kunci) }}</p>
    <div class="flex flex-wrap items-center justify-end gap-2">
        <button type="button" class="btn-ghost text-xs" wire:click="tutupSunting">Batal</button>
        <button type="submit" class="btn-primary text-xs" wire:loading.attr="disabled" wire:target="simpanSunting" data-testid="simpan-sunting">
            <span wire:loading.remove wire:target="simpanSunting">Simpan</span><span wire:loading wire:target="simpanSunting">Menyimpan…</span>
        </button>
    </div>
</form>
