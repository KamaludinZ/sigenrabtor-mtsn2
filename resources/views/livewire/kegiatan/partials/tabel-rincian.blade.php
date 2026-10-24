@use('App\Support\Format')
@use('App\Livewire\Forms\ItemBelanjaForm')
@use('App\Support\Rab\KelompokRincian')

@php
    $berkelompok = $kelompok !== 'urutan';
    $bisaUbah = $this->dapatDiedit();
    $kolom = $bisaUbah ? 8 : 7;
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
</div>

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
                <th scope="col" class="px-2 py-2.5 text-right">Harga satuan</th>
                <th scope="col" class="px-4 py-2.5 text-right">Jumlah</th>
                @if ($bisaUbah)
                    <th scope="col" class="w-20 py-2.5 pr-3 text-right"><span class="sr-only">Aksi</span></th>
                @endif
            </tr>
        </thead>
        @foreach ($this->kelompokRincian as $grup)
            <tbody class="divide-y divide-stone-100" data-kelompok="{{ $grup['kunci'] }}">
                @if ($berkelompok)
                    <tr class="bg-brand-50/60">
                        <th scope="colgroup" colspan="{{ $kolom }}" class="px-4 py-2 text-left text-xs font-semibold text-brand-900">
                            {{ $grup['label'] }}
                            <span class="font-normal text-brand-700">· {{ count($grup['item']) }} item</span>
                        </th>
                    </tr>
                @endif
                @foreach ($grup['item'] as $baris)
                    <tr wire:key="item-{{ $baris['id'] ?? $baris['nomor'] }}" data-item="{{ $baris['nomor'] }}"
                        @class(['align-top', 'bg-gold-50 ring-1 ring-gold-300 ring-inset' => $itemDiedit === ($baris['id'] ?? null), 'hover:bg-stone-50/60' => $itemDiedit !== ($baris['id'] ?? null)])>
                        <td class="px-4 py-2.5 text-stone-500 tabular-nums">{{ $baris['nomor'] }}.</td>
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
                    <tr class="bg-stone-50/80" data-testid="subtotal-{{ $grup['kunci'] ?: 'tanpa-kode' }}">
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
            <p class="bg-brand-50/60 px-5 py-2 text-xs font-semibold text-brand-900">{{ $grup['label'] }} <span class="font-normal text-brand-700">· {{ count($grup['item']) }} item</span></p>
        @endif
        <ol class="divide-y divide-stone-100">
            @foreach ($grup['item'] as $baris)
                <li wire:key="kartu-{{ $baris['id'] ?? $baris['nomor'] }}" @class(['px-5 py-3', 'bg-gold-50' => $itemDiedit === ($baris['id'] ?? null)])>
                    <div class="flex items-start justify-between gap-3">
                        <p class="min-w-0 text-sm text-stone-800"><span class="text-stone-400">{{ $baris['nomor'] }}.</span> {{ $baris['uraian'] }}</p>
                        <p class="shrink-0 text-sm font-semibold tabular-nums">{{ Format::angka($baris['jumlah']) }}</p>
                    </div>
                    <p class="mt-0.5 text-xs text-stone-500 tabular-nums">
                        {{ Format::angka($baris['volume']) }} {{ $baris['satuan'] }} × {{ Format::rupiah($baris['harga_satuan']) }}@if ($baris['kode']) · {{ $baris['kode'] }}@endif
                    </p>
                    @if ($bisaUbah)
                        <div class="mt-2 flex justify-end gap-1">
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
