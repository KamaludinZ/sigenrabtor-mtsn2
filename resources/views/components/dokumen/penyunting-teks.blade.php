@props(['kunci', 'kegiatan'])

@use('App\Support\Dokumen\SuntingDokumen')

@php
    $jenisSunting = SuntingDokumen::jenis($kunci);
@endphp

{{--
    Penyunting teks di tempat pada pratinjau KAK/TOR (SuntingDokumen): terikat ke properti Livewire `teksSunting` komponen
    pemiliknya, simpan `simpanSunting()`, Esc/Batal lewat Alpine `penyuntingTeks` (konfirmasi bila belum disimpan, lalu
    `tutupSunting()`). Draf lokal ketikan per kegiatan × bidang ditawarkan kembali bila penyunting pernah tertutup sebelum
    disimpan. Tidak ikut tercetak.
--}}
<form wire:submit="simpanSunting" x-data="penyuntingTeks({{ (int) $kegiatan }}, '{{ $kunci }}')" x-on:keydown.escape.prevent="batal()"
      class="my-1 space-y-2 rounded-lg bg-sky-50 p-3 font-sans text-sm leading-normal ring-1 ring-sky-200 print:hidden" data-testid="penyunting-{{ $kunci }}" data-sunting-kotor="tidak">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <label for="sunting-{{ $kunci }}" class="block text-xs font-semibold text-sky-900">{{ SuntingDokumen::label($kunci) }}</label>
        <span class="rounded-full bg-gold-100 px-2 py-0.5 text-[10px] font-semibold text-gold-800" x-cloak x-show="kotor" data-testid="belum-disimpan">Belum disimpan</span>
    </div>
    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md bg-white px-2.5 py-1.5 text-xs text-stone-700 ring-1 ring-gold-200" x-cloak x-show="draf" data-testid="draf-lokal">
        <span class="min-w-0 flex-1">Ada ketikan yang belum disimpan dari pukul <span x-text="waktuDraf()"></span>.</span>
        <button type="button" class="font-semibold text-brand-700 hover:text-brand-800" x-on:click="pulihkan()" data-testid="pulihkan-draf">Pulihkan</button>
        <button type="button" class="font-semibold text-stone-500 hover:text-stone-800" x-on:click="buangDraf()" data-testid="buang-draf">Buang</button>
    </div>
    @if ($jenisSunting === 'teks')
        <input type="text" id="sunting-{{ $kunci }}" wire:model="teksSunting" x-on:input="ketik($event.target.value)" maxlength="{{ SuntingDokumen::maks($kunci) }}" x-init="$el.focus()"
               class="form-input text-sm" @error('teksSunting') aria-invalid="true" aria-describedby="sunting-{{ $kunci }}-galat" @enderror>
    @else
        <textarea id="sunting-{{ $kunci }}" wire:model="teksSunting" x-on:input="ketik($event.target.value)" rows="{{ $jenisSunting === 'daftar' ? 5 : 4 }}" x-init="$el.focus()"
                  @if ($jenisSunting !== 'daftar') maxlength="{{ SuntingDokumen::maks($kunci) }}" @endif
                  class="form-input text-sm" @error('teksSunting') aria-invalid="true" aria-describedby="sunting-{{ $kunci }}-galat" @enderror></textarea>
    @endif
    @error('teksSunting')<p id="sunting-{{ $kunci }}-galat" class="form-error" role="alert">{{ $message }}</p>@enderror
    <p class="rounded-md bg-red-50 px-2.5 py-1.5 text-xs text-red-800 ring-1 ring-red-200" wire:offline data-testid="sunting-luring">
        Koneksi terputus — ketikan tetap tersimpan di peramban ini. Simpan lagi setelah tersambung.
    </p>
    <p class="text-xs text-stone-500">{{ SuntingDokumen::petunjuk($kunci) }}</p>
    <div class="flex flex-wrap items-center justify-end gap-2">
        <button type="button" class="btn-ghost text-xs" x-on:click="batal()" data-testid="batal-sunting">Batal</button>
        <button type="submit" class="btn-primary text-xs" wire:loading.attr="disabled" wire:target="simpanSunting" data-testid="simpan-sunting">
            <span wire:loading.remove wire:target="simpanSunting">Simpan</span><span wire:loading wire:target="simpanSunting">Menyimpan…</span>
        </button>
    </div>
</form>
