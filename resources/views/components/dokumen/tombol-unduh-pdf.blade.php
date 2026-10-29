{{--
    Tombol unduh PDF langsung (Alpine `unduhPdf`, resources/js/unduh-pdf.js): ambil berkas dari `url` tanpa meninggalkan
    halaman, tampilkan kemajuan & tombol Batal, simpan dengan nama berkas baku; gagal → pesan + Coba lagi + jalan keluar
    lewat halaman cetak (`cetak`, "Simpan sebagai PDF"). `testid` = data-testid tombol utama.
--}}
@props(['url', 'nama', 'label' => 'Unduh PDF', 'cetak' => null, 'testid' => 'unduh-pdf', 'utama' => false])

<div {{ $attributes->class(['inline-flex flex-col items-start gap-1']) }} x-data="unduhPdf(@js($url), @js($nama), @js($cetak))" data-unduh-pdf>
    <div class="inline-flex items-center gap-1.5">
        <button type="button" x-on:click="unduh()" x-bind:disabled="memuat" x-bind:aria-busy="memuat ? 'true' : 'false'" data-testid="{{ $testid }}" x-bind:data-keadaan="keadaan"
                @class(['btn-primary' => $utama, 'btn-secondary' => ! $utama])>
            <x-ui.icon name="download" class="size-4" x-show="! memuat" />
            <svg x-cloak x-show="memuat" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" /><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
            <span x-show="! memuat">{{ $label }}</span>
            <span x-cloak x-show="memuat" aria-live="polite">Menyiapkan PDF<span x-show="persen !== null" x-text="' ' + persen + '%'"></span><span x-show="persen === null && diterima" x-text="' · ' + diterima"></span>…</span>
        </button>
        <button type="button" x-cloak x-show="memuat" x-on:click="batal()" class="btn-ghost px-2 py-1 text-xs" data-testid="{{ $testid }}-batal">Batal</button>
    </div>
    <div x-cloak x-show="keadaan === 'gagal'" role="alert" class="max-w-xs rounded-lg bg-red-50 px-2.5 py-1.5 text-xs text-red-800 ring-1 ring-red-200" data-testid="{{ $testid }}-gagal" x-bind:data-galat="jenisGalat">
        <p x-text="pesan"></p>
        <p class="mt-1 flex flex-wrap gap-x-3">
            <button type="button" x-on:click="unduh()" class="font-semibold underline underline-offset-2 hover:no-underline">Coba lagi</button>
            <button type="button" x-show="['sesi', 'pustaka', 'tidak_ada'].includes(jenisGalat)" x-on:click="window.location.reload()" class="font-semibold underline underline-offset-2 hover:no-underline" data-testid="{{ $testid }}-muat-ulang">Muat ulang halaman</button>
            <a x-show="tautanCetak" x-bind:href="tautanCetak" target="_blank" rel="noopener" class="font-semibold underline underline-offset-2 hover:no-underline">Simpan lewat halaman cetak</a>
        </p>
    </div>
</div>
