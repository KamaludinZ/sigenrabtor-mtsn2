{{--
    Tombol unduh RAB satu sumber dana sebagai Excel (.xlsx) yang disusun di peramban (Alpine `unduhExcel`,
    resources/js/unduh-excel.js + excel-rab.js): susunan dari `kegiatan.pratinjau.data?dokumen=rab`, angka dari
    `kegiatan.rab_lengkap`; gagal → Coba lagi atau unduhan cadangan dari server (`kegiatan.rab.excel`). `testid` =
    data-testid tombol utama; `ringkas` = butir menu (tanpa gaya tombol).
--}}
@props(['kegiatanId', 'sumber', 'nama' => 'RAB.xlsx', 'label' => null, 'testid' => 'unduh-excel', 'ringkas' => false])

@php
    $urlDokumen = route('kegiatan.pratinjau.data', ['kegiatan' => $kegiatanId, 'sumber' => $sumber, 'dokumen' => 'rab']);
    $urlRab = route('kegiatan.rab_lengkap', ['kegiatan' => $kegiatanId]);
    $urlServer = route('kegiatan.rab.excel', ['kegiatan' => $kegiatanId, 'sumber' => $sumber]);
    $label ??= 'RAB '.$sumber.' — Excel';
@endphp

<div {{ $attributes->class(['flex flex-col items-start gap-1' => ! $ringkas, 'w-full' => $ringkas]) }} x-data="unduhExcel(@js($urlDokumen), @js($urlRab), @js($sumber), @js($nama), @js($urlServer))" data-unduh-excel>
    <button type="button" x-on:click="unduh()" x-bind:disabled="memuat" x-bind:aria-busy="memuat ? 'true' : 'false'" data-testid="{{ $testid }}" x-bind:data-keadaan="keadaan"
            @if ($ringkas) role="menuitem" @endif
            @class(['btn-secondary' => ! $ringkas, 'flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-stone-700 hover:bg-stone-100 disabled:opacity-60' => $ringkas])>
        <x-ui.icon name="sheet" :class="$ringkas ? 'size-4 text-stone-400' : 'size-4'" x-show="! memuat" />
        <svg x-cloak x-show="memuat" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
        <span x-show="! memuat">{{ $label }}</span>
        <span x-cloak x-show="memuat" aria-live="polite">Menyusun Excel…</span>
    </button>
    <div x-cloak x-show="keadaan === 'gagal'" role="alert" @class(['max-w-xs rounded-lg bg-red-50 px-2.5 py-1.5 text-xs text-red-800 ring-1 ring-red-200', 'mx-2.5 mb-1' => $ringkas]) data-testid="{{ $testid }}-gagal" x-bind:data-galat="jenisGalat">
        <p x-text="pesan"></p>
        <p class="mt-1 flex flex-wrap gap-x-3">
            <button type="button" x-on:click="unduh()" class="font-semibold underline underline-offset-2 hover:no-underline">Coba lagi</button>
            <button type="button" x-show="['sesi', 'pustaka', 'tidak_ada'].includes(jenisGalat)" x-on:click="window.location.reload()" class="font-semibold underline underline-offset-2 hover:no-underline" data-testid="{{ $testid }}-muat-ulang">Muat ulang halaman</button>
            <a x-bind:href="urlServer" class="font-semibold underline underline-offset-2 hover:no-underline">Unduh dari server</a>
        </p>
    </div>
</div>
