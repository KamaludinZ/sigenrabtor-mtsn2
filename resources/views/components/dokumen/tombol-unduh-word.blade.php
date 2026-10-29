{{--
    Tombol unduh KAK/TOR sebagai Word (.docx) yang disusun di peramban (Alpine `unduhWord`, resources/js/unduh-word.js +
    word-kak.js): data cover & KAK/TOR dari `kegiatan.pratinjau.data`, nama berkas baku berakhiran .docx; bila penyusun
    peramban gagal, berkas diambil dari server `kegiatan.kak.word` (WordKakTor). `testid` = data-testid tombol utama.
--}}
@props(['kegiatanId', 'sumber' => null, 'nama' => 'KAK-TOR.pdf', 'label' => 'Word KAK/TOR', 'testid' => 'unduh-word'])

@php
    $url = route('kegiatan.pratinjau.data', array_filter(['kegiatan' => $kegiatanId, 'sumber' => $sumber, 'dokumen' => 'cover,kak']));
    $urlServer = route('kegiatan.kak.word', array_filter(['kegiatan' => $kegiatanId, 'sumber' => $sumber]));
@endphp

<div {{ $attributes->class(['inline-flex flex-col items-start gap-1']) }} x-data="unduhWord(@js($url), @js($nama), @js($urlServer))" data-unduh-word data-url-server="{{ $urlServer }}">
    <button type="button" x-on:click="unduh()" x-bind:disabled="memuat" x-bind:aria-busy="memuat ? 'true' : 'false'" data-testid="{{ $testid }}" x-bind:data-keadaan="keadaan" class="btn-secondary">
        <x-ui.icon name="file-text" class="size-4" x-show="! memuat" />
        <svg x-cloak x-show="memuat" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
        <span x-show="! memuat">{{ $label }}</span>
        <span x-cloak x-show="memuat" aria-live="polite">Menyusun Word…</span>
    </button>
    <div x-cloak x-show="keadaan === 'gagal'" role="alert" class="max-w-xs rounded-lg bg-red-50 px-2.5 py-1.5 text-xs text-red-800 ring-1 ring-red-200" data-testid="{{ $testid }}-gagal" x-bind:data-galat="jenisGalat">
        <p x-text="pesan"></p>
        <p class="mt-1 flex flex-wrap gap-x-3"><button type="button" x-on:click="unduh()" class="font-semibold underline underline-offset-2 hover:no-underline">Coba lagi</button> <button type="button" x-show="['sesi', 'pustaka', 'tidak_ada'].includes(jenisGalat)" x-on:click="window.location.reload()" class="font-semibold underline underline-offset-2 hover:no-underline" data-testid="{{ $testid }}-muat-ulang">Muat ulang halaman</button></p>
    </div>
</div>
