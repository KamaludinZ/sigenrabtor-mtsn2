{{--
    Tombol aksi Cetak & Unduh satu kegiatan (PRD §6 Cetak & Unduh, F12/F16): menu per sumber dana — cetak/simpan PDF set
    dokumen (halaman cetak `kegiatan.cetak`, dialog cetak otomatis, tab baru), RAB Excel/PDF selama RAB berisi, Word
    (Segera) — ditambah pratinjau dokumen dan halaman Cetak & Unduh untuk mengatur lembar. `sumber` = daftar {sumber, item}
    (null = dibaca dari data tersimpan kegiatan); kegiatan tanpa sumber dana hanya mencetak cover + KAK/TOR.
    `ringkas` = tombol kecil untuk baris tabel; `kunci` membedakan id menu bila satu kegiatan tampil dua kali di halaman.
--}}
@props(['kegiatanId', 'sumber' => null, 'ringkas' => false, 'label' => 'Cetak', 'kunci' => 'utama'])

@use('App\Contracts\KegiatanRepository')
@use('App\Support\Dokumen\CetakDokumen')
@use('App\Support\Rab\AnggaranKegiatan')

@php
    if ($sumber === null) {
        $tersimpan = app(KegiatanRepository::class)->temukan((int) $kegiatanId) ?? [];
        $sumber = array_map(fn ($a) => ['sumber' => $a->sumber, 'item' => $a->jumlahItem()], AnggaranKegiatan::untuk($tersimpan));
    }
    $daftar = $sumber === [] ? [['sumber' => null, 'item' => 0]] : array_values($sumber);
    $pertama = $daftar[0]['sumber'];
    $idMenu = 'aksi-cetak-'.$kegiatanId.'-'.$kunci;
@endphp

<div class="relative inline-block text-left" x-data="{ buka: false }" x-on:keydown.escape.stop="buka = false; $refs.tombol.focus()" data-testid="aksi-cetak" data-kegiatan="{{ $kegiatanId }}">
    <button type="button" x-ref="tombol" x-on:click="buka = ! buka" x-bind:aria-expanded="buka" aria-haspopup="menu" aria-controls="{{ $idMenu }}"
            aria-label="{{ $label }} &amp; unduh dokumen kegiatan {{ $kegiatanId }}" data-testid="buka-aksi-cetak"
            @class([
                'inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-50 hover:text-brand-800' => $ringkas,
                'btn-secondary py-1.5' => ! $ringkas,
            ])>
        <x-ui.icon name="printer" :class="$ringkas ? 'size-3.5' : 'size-4'" /> {{ $label }}
        <x-ui.icon name="chevron-down" class="size-3 opacity-60" />
    </button>
    <div id="{{ $idMenu }}" x-show="buka" x-cloak x-anchor.bottom-end.offset.4="$refs.tombol" x-on:click.outside="buka = false" x-transition.opacity
         role="menu" aria-label="Cetak &amp; unduh" data-testid="menu-aksi-cetak"
         class="z-40 w-72 max-w-[calc(100vw-2rem)] rounded-xl bg-white p-1.5 text-sm shadow-lg ring-1 ring-stone-200">
        @foreach ($daftar as $s)
            @php
                $kunci = $s['sumber'] ?? 'kak';
                $lembar = $s['sumber'] ? ['cover', 'kak', 'rab'] : ['cover', 'kak'];
            @endphp
            <div @class(['py-1', 'border-t border-stone-100' => ! $loop->first]) data-sumber-aksi="{{ $kunci }}">
                <p class="flex items-center gap-1.5 px-2.5 pt-1 pb-1.5 text-[11px] font-semibold tracking-wide text-stone-500 uppercase">
                    @if ($s['sumber'])
                        <x-rab.lencana-sumber :sumber="$s['sumber']" awalan="RAB" class="normal-case" />
                    @else
                        Tanpa sumber dana
                    @endif
                </p>
                <a href="{{ CetakDokumen::tautan((int) $kegiatanId, $s['sumber'], $lembar, otomatis: true) }}" target="_blank" rel="noopener" role="menuitem" x-on:click="buka = false"
                   class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-stone-700 hover:bg-stone-100" data-testid="aksi-cetak-set-{{ $kunci }}">
                    <x-ui.icon name="printer" class="size-4 text-brand-700" />
                    <span><span class="block font-semibold text-stone-900">Cetak / Simpan PDF</span><span class="block text-xs text-stone-500">{{ $s['sumber'] ? 'Cover, KAK/TOR, RAB' : 'Cover, KAK/TOR' }}</span></span>
                </a>
                @if ($s['sumber'] && $s['item'] > 0)
                    <a href="{{ route('kegiatan.rab.excel', ['kegiatan' => $kegiatanId, 'sumber' => $s['sumber']]) }}" role="menuitem" x-on:click="buka = false"
                       class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-stone-700 hover:bg-stone-100" data-testid="aksi-rab-excel-{{ $kunci }}">
                        <x-ui.icon name="sheet" class="size-4 text-stone-400" /> RAB {{ $s['sumber'] }} — Excel
                    </a>
                    <a href="{{ route('kegiatan.rab.pdf', ['kegiatan' => $kegiatanId, 'sumber' => $s['sumber']]) }}" role="menuitem" x-on:click="buka = false"
                       class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-stone-700 hover:bg-stone-100" data-testid="aksi-rab-pdf-{{ $kunci }}">
                        <x-ui.icon name="download" class="size-4 text-stone-400" /> RAB {{ $s['sumber'] }} — PDF
                    </a>
                @elseif ($s['sumber'])
                    <p class="px-2.5 py-1.5 text-xs text-stone-400">RAB {{ $s['sumber'] }} belum berisi rincian — unduhan RAB menyusul.</p>
                @endif
            </div>
        @endforeach
        <div class="border-t border-stone-100 py-1">
            <span class="flex cursor-not-allowed items-center gap-2 rounded-lg px-2.5 py-2 text-stone-400" role="menuitem" aria-disabled="true">
                <x-ui.icon name="file-text" class="size-4" /> Word (.docx) <x-ui.segera class="ml-auto" />
            </span>
            <a href="{{ route('kegiatan.pratinjau', array_filter(['kegiatan' => $kegiatanId, 'sumber' => $pertama])) }}" wire:navigate role="menuitem"
               class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-stone-700 hover:bg-stone-100" data-testid="aksi-pratinjau">
                <x-ui.icon name="eye" class="size-4 text-stone-400" /> Pratinjau dokumen
            </a>
            <a href="{{ route('cetak.index', ['pilih' => $kegiatanId.'|'.($pertama ?? '')]) }}" wire:navigate role="menuitem"
               class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-stone-700 hover:bg-stone-100" data-testid="aksi-atur-cetak">
                <x-ui.icon name="settings" class="size-4 text-stone-400" /> Atur lembar di Cetak &amp; Unduh
            </a>
        </div>
    </div>
</div>
