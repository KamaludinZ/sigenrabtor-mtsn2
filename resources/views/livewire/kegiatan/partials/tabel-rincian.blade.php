@use('App\Support\Format')
@use('App\Livewire\Forms\ItemBelanjaForm')
@use('App\Support\Rab\KelompokRincian')

@php
    $berkelompok = $kelompok !== 'urutan';
    $bisaUbah = $this->dapatDiedit();
    $kolom = $bisaUbah ? 8 : 7;
    $seret = $bisaUbah && $kelompok === 'kategori';
    $kunciSimpan = "rab-{$kegiatanId}-{$sumber}-{$kelompok}-";
@endphp

{{-- Pilihan tampilan rincian --}}
<div class="flex flex-wrap items-center gap-2 border-b border-stone-100 px-5 py-2.5 sm:px-6" role="group" aria-label="Tampilan rincian">
    <span class="text-xs font-medium text-stone-500">Tampilkan:</span>
    @foreach (KelompokRincian::MODE as $mode => $label)
        <button type="button" wire:click="$set('kelompok', '{{ $mode }}')" aria-pressed="{{ $kelompok === $mode ? 'true' : 'false' }}"
                @class([
                    'rounded-md px-2.5 py-1 text-xs font-semibold transition',
                    'bg-stone-800 text-white' => $kelompok === $mode,
                    'text-stone-600 hover:bg-stone-100' => $kelompok !== $mode,
                ])>{{ $label }}</button>
    @endforeach

    @if ($berkelompok)
        <span class="ml-auto flex items-center gap-1">
            <button type="button" x-on:click="$dispatch('rab-grup', { buka: false })" class="rounded-md px-2 py-1 text-xs font-medium text-stone-500 hover:bg-stone-100 hover:text-stone-800">Ciutkan semua</button>
            <button type="button" x-on:click="$dispatch('rab-grup', { buka: true })" class="rounded-md px-2 py-1 text-xs font-medium text-stone-500 hover:bg-stone-100 hover:text-stone-800">Buka semua</button>
        </span>
    @endif
</div>

@if ($seret)
    <p class="flex items-center gap-1.5 border-b border-stone-100 bg-brand-50/40 px-5 py-2 text-xs text-brand-900 sm:px-6">
        <x-ui.icon name="grip" class="size-3.5" />
        <span class="hidden sm:inline">Seret nomor item ke kelompok lain untuk memindahkan kategorinya.</span>
        <span class="sm:hidden">Ubah kelompok lewat pilihan di tiap item.</span>
    </p>
@endif

{{-- Tabel (layar sedang ke atas) --}}
<div class="hidden overflow-x-auto sm:block">
    <table class="w-full text-sm">
        <thead class="bg-stone-50 text-xs font-semibold text-stone-500">
            <tr>
                <th scope="col" class="w-10 px-4 py-2.5 text-left">No</th>
                <th scope="col" class="px-2 py-2.5 text-left">Kode</th>
                <th scope="col" class="px-2 py-2.5 text-left">Uraian</th>
                <th scope="col" class="px-2 py-2.5 text-right">Vol</th>
                <th scope="col" class="px-2 py-2.5 text-left">Satuan</th>
                <th scope="col" class="px-2 py-2.5 text-right">Harga satuan (Rp)</th>
                <th scope="col" class="px-4 py-2.5 text-right">Jumlah (Rp)</th>
                @if ($bisaUbah)
                    <th scope="col" class="w-20 py-2.5 pr-3 text-right"><span class="sr-only">Aksi</span></th>
                @endif
            </tr>
        </thead>
        @foreach ($this->kelompokRincian as $grup)
            <tbody class="divide-y divide-stone-100" data-kelompok="{{ $grup['kunci'] }}" data-kelompok-anchor="{{ $grup['kunci'] }}"
                   wire:key="grup-{{ $kelompok }}-{{ $grup['kunci'] ?: 'tanpa' }}"
                   @if ($berkelompok)
                       x-data="{ buka: $persist(true).as(@js($kunciSimpan.($grup['kunci'] ?: 'tanpa'))) }"
                       x-on:rab-grup.window="buka = $event.detail.buka"
                   @endif
                   @if ($seret)
                       wire:sort="pindahKategoriItem" wire:sort:group="kategori-rab" wire:sort:group-id="{{ $grup['kunci'] }}"
                   @endif>
                @if ($berkelompok)
                    <tr class="bg-brand-50/60">
                        <th scope="colgroup" colspan="{{ $kolom }}" class="px-2 py-1.5 text-left text-xs font-semibold text-brand-900">
                            <button type="button" x-on:click="buka = ! buka" :aria-expanded="buka"
                                    class="flex w-full items-center gap-2 rounded-md px-2 py-0.5 text-left hover:bg-brand-100/60">
                                <x-ui.icon name="chevron-down" class="size-3.5 transition-transform" x-bind:class="buka ? '' : '-rotate-90'" />
                                {{ $grup['label'] }}
                                <span class="font-normal text-brand-700">· {{ count($grup['item']) }} item</span>
                                <span class="ml-auto font-semibold tabular-nums" x-show="! buka" x-cloak>{{ Format::rupiah($grup['subtotal']) }}</span>
                            </button>
                        </th>
                    </tr>
                @endif
                @if ($grup['item'] === [])
                    <tr x-show="buka">
                        <td colspan="{{ $kolom }}" class="px-4 py-3 text-center text-xs text-stone-400">
                            Belum ada item{{ $seret ? ' — seret item ke sini untuk memindahkannya ke kelompok ini' : '' }}.
                        </td>
                    </tr>
                @endif
                @foreach ($grup['item'] as $baris)
                    <tr wire:key="item-{{ $baris['id'] ?? $baris['nomor'] }}" data-item="{{ $baris['nomor'] }}"
                        @if ($berkelompok) x-show="buka" @endif
                        @if ($seret) wire:sort:item="{{ $baris['id'] }}" @endif
                        @class(['align-top', 'bg-gold-50 ring-1 ring-gold-300 ring-inset' => $itemDiedit === ($baris['id'] ?? null), 'hover:bg-stone-50/60' => $itemDiedit !== ($baris['id'] ?? null)])>
                        @if ($seret)
                            <td class="px-4 py-2.5 text-stone-500 tabular-nums">
                                <span wire:sort:handle class="group/pegang -mx-1.5 inline-flex cursor-grab items-center gap-1 rounded px-1.5 hover:bg-brand-100 hover:text-brand-800 active:cursor-grabbing"
                                      title="Seret ke kelompok lain" aria-label="Seret item {{ $baris['nomor'] }}">
                                    <x-ui.icon name="grip" class="size-3.5 opacity-40 group-hover/pegang:opacity-100" />{{ $baris['nomor'] }}.
                                </span>
                            </td>
                        @else
                            <td class="px-4 py-2.5 text-stone-500 tabular-nums">{{ $baris['nomor'] }}.</td>
                        @endif
                        <td class="px-2 py-2.5 font-mono text-xs text-stone-500">{{ $baris['kode'] }}</td>
                        <td class="px-2 py-2.5 text-stone-800">
                            {{ $baris['uraian'] }}
                            @unless ($kelompok === 'kategori')
                                <span class="ml-1 rounded bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold text-stone-500 uppercase">{{ ItemBelanjaForm::LABEL_KATEGORI[$baris['kategori']] ?? $baris['kategori'] }}</span>
                            @endunless
                        </td>
                        <td class="px-2 py-2.5 text-right tabular-nums">{{ Format::angka($baris['volume']) }}</td>
                        <td class="px-2 py-2.5 text-stone-600">{{ $baris['satuan'] }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums">{{ Format::angka($baris['harga_satuan']) }}</td>
                        <td class="px-4 py-2.5 text-right font-medium tabular-nums">{{ Format::angka($baris['jumlah']) }}</td>
                        @if ($bisaUbah)
                            <td class="py-1.5 pr-3 text-right whitespace-nowrap">
                                <button type="button" wire:click="ubahItem('{{ $baris['id'] }}')" class="rounded-md p-1.5 text-stone-400 hover:bg-stone-100 hover:text-stone-800"
                                        title="Ubah" aria-label="Ubah item {{ $baris['nomor'] }}">
                                    <x-ui.icon name="pencil" class="size-4" />
                                </button>
                                <button type="button" wire:click="hapusItem('{{ $baris['id'] }}')" class="rounded-md p-1.5 text-stone-400 hover:bg-red-50 hover:text-red-600"
                                        title="Hapus" aria-label="Hapus item {{ $baris['nomor'] }}">
                                    <x-ui.icon name="trash" class="size-4" />
                                </button>
                            </td>
                        @endif
                    </tr>
                @endforeach
                @if ($berkelompok)
                    <tr class="bg-stone-50/80" data-testid="subtotal-{{ $grup['kunci'] ?: 'tanpa-kode' }}" x-show="buka">
                        <th scope="row" colspan="6" class="px-4 py-2 text-right text-xs font-semibold text-stone-600">
                            Subtotal {{ $grup['label'] }}
                            <span class="font-normal text-stone-400">({{ Format::angka($grup['persen']) }}%)</span>
                        </th>
                        <td class="px-4 py-2 text-right font-semibold tabular-nums">{{ Format::angka($grup['subtotal']) }}</td>
                        @if ($bisaUbah)<td></td>@endif
                    </tr>
                @endif
            </tbody>
        @endforeach
        <tfoot>
            <tr class="border-t-2 border-stone-200 bg-stone-50">
                <th scope="row" colspan="6" class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-stone-600 uppercase">Total keseluruhan</th>
                <td class="px-4 py-3 text-right text-base font-bold text-stone-900 tabular-nums" data-testid="total-rab">{{ Format::angka($this->total) }}</td>
                @if ($bisaUbah)<td></td>@endif
            </tr>
        </tfoot>
    </table>
</div>

{{-- Kartu (layar kecil) --}}
<div class="sm:hidden" data-testid="rincian-kartu">
    @foreach ($this->kelompokRincian as $grup)
        @if ($berkelompok)
            <p class="bg-brand-50/60 px-5 py-2 text-xs font-semibold text-brand-900" data-kelompok-anchor="{{ $grup['kunci'] }}">{{ $grup['label'] }} <span class="font-normal text-brand-700">· {{ count($grup['item']) }} item</span></p>
        @endif
        <ol class="divide-y divide-stone-100">
            @foreach ($grup['item'] as $baris)
                <li wire:key="kartu-{{ $baris['id'] ?? $baris['nomor'] }}" @class(['px-5 py-3', 'bg-gold-50' => $itemDiedit === ($baris['id'] ?? null)])>
                    <div class="flex items-start justify-between gap-3">
                        <p class="min-w-0 text-sm text-stone-800"><span class="text-stone-400">{{ $baris['nomor'] }}.</span> {{ $baris['uraian'] }}</p>
                        <x-ui.uang :nilai="$baris['jumlah']" class="shrink-0 text-sm font-semibold" />
                    </div>
                    <p class="mt-0.5 text-xs text-stone-500 tabular-nums">
                        {{ Format::angka($baris['volume']) }} {{ $baris['satuan'] }} × {{ Format::rupiah($baris['harga_satuan']) }}@if ($baris['kode']) · {{ $baris['kode'] }}@endif
                    </p>
                    @if ($bisaUbah)
                        <div class="mt-2 flex items-center justify-end gap-1">
                            <label class="sr-only" for="kategori-{{ $baris['id'] }}">Kelompok belanja item {{ $baris['nomor'] }}</label>
                            <select id="kategori-{{ $baris['id'] }}" wire:change="ubahKategoriItem('{{ $baris['id'] }}', $event.target.value)"
                                    class="mr-auto rounded-md border-0 bg-stone-100 py-1 pr-7 pl-2 text-xs font-medium text-stone-700 focus:ring-2 focus:ring-brand-600">
                                @foreach (ItemBelanjaForm::LABEL_KATEGORI as $kunciKategori => $labelKategori)
                                    <option value="{{ $kunciKategori }}" @selected($baris['kategori'] === $kunciKategori)>{{ $labelKategori }}</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="ubahItem('{{ $baris['id'] }}')" class="btn-ghost px-2 py-1 text-xs" aria-label="Ubah item {{ $baris['nomor'] }}">
                                <x-ui.icon name="pencil" class="size-3.5" /> Ubah
                            </button>
                            <button type="button" wire:click="hapusItem('{{ $baris['id'] }}')" class="btn-ghost px-2 py-1 text-xs text-red-600 hover:bg-red-50 hover:text-red-700" aria-label="Hapus item {{ $baris['nomor'] }}">
                                <x-ui.icon name="trash" class="size-3.5" /> Hapus
                            </button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
        @if ($berkelompok)
            <p class="flex items-center justify-between border-t border-stone-100 bg-stone-50/80 px-5 py-2 text-xs font-semibold text-stone-600">
                <span>Subtotal ({{ Format::angka($grup['persen']) }}%)</span>
                <span class="tabular-nums">{{ Format::rupiah($grup['subtotal']) }}</span>
            </p>
        @endif
    @endforeach
    <p class="flex items-center justify-between bg-stone-50 px-5 py-3">
        <span class="text-xs font-semibold text-stone-600 uppercase">Total</span>
        <span class="text-base font-bold tabular-nums">{{ Format::rupiah($this->total) }}</span>
    </p>
</div>
