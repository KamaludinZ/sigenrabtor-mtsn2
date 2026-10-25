{{--
    Menu "Aksi lain" satu baris RAB: salin & ubah, naik/turun urutan cetak (mode urutan), pindahkan ke RAB sumber dana lain.
    Variabel: $baris, $sumberLain, $kelompok, $jumlahItem, $ringkas (kartu layar kecil).
--}}
<div class="relative inline-block text-left" x-data="{ buka: false }" x-on:keydown.escape.stop="buka = false">
    <button type="button" x-ref="tombol" x-on:click="buka = ! buka" x-bind:aria-expanded="buka" aria-haspopup="menu"
            title="Aksi lain" aria-label="Aksi lain item {{ $baris['nomor'] }}"
            @class(['rounded-md text-stone-400 hover:bg-stone-100 hover:text-stone-800', 'p-1.5' => ! $ringkas, 'btn-ghost px-2 py-1 text-xs text-stone-600' => $ringkas])>
        <x-ui.icon name="more" class="{{ $ringkas ? 'size-3.5' : 'size-4' }}" />@if ($ringkas) Lainnya @endif
    </button>
    <div x-show="buka" x-cloak x-anchor.bottom-end.offset.4="$refs.tombol" x-on:click.outside="buka = false" x-transition.opacity
         role="menu" aria-label="Aksi item {{ $baris['nomor'] }}" data-testid="menu-item-{{ $baris['nomor'] }}"
         class="z-40 w-60 rounded-xl bg-white p-1 text-sm shadow-lg ring-1 ring-stone-200">
        <button type="button" role="menuitem" wire:click="salinItem('{{ $baris['id'] }}')" x-on:click="buka = false"
                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-stone-700 hover:bg-stone-100">
            <x-ui.icon name="copy" class="size-4 text-stone-400" /> Salin &amp; ubah
        </button>
        @if ($kelompok === 'urutan')
            <button type="button" role="menuitem" wire:click="geserItem('{{ $baris['id'] }}', -1)" x-on:click="buka = false" @disabled($baris['nomor'] <= 1)
                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-stone-700 hover:bg-stone-100 disabled:pointer-events-none disabled:opacity-40">
                <x-ui.icon name="arrow-up" class="size-4 text-stone-400" /> Naikkan urutan
            </button>
            <button type="button" role="menuitem" wire:click="geserItem('{{ $baris['id'] }}', 1)" x-on:click="buka = false" @disabled($baris['nomor'] >= $jumlahItem)
                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-stone-700 hover:bg-stone-100 disabled:pointer-events-none disabled:opacity-40">
                <x-ui.icon name="arrow-down" class="size-4 text-stone-400" /> Turunkan urutan
            </button>
        @endif
        @foreach ($sumberLain as $lain)
            <button type="button" role="menuitem" wire:click="pindahSumberItem('{{ $baris['id'] }}', '{{ $lain }}')" x-on:click="buka = false"
                    class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-stone-700 hover:bg-stone-100">
                <x-ui.icon name="wallet" class="size-4 text-stone-400" /> Pindahkan ke <x-rab.lencana-sumber :sumber="$lain" awalan="RAB" class="text-[11px]" />
            </button>
        @endforeach
    </div>
</div>
