{{--
    Pemilih satu template (master KAK/TOR atau cover) untuk Format Seragam: select asli ber-`wire:model.live` (atribut
    `wire:model…` diteruskan) dengan pilihan bawaan (`bawaan`, mis. "Susunan baku"/"Cover default") lalu template tahun aktif
    (`pilihan` dari PilihanTemplate::pilihanKak/pilihanCover — label sudah memuat penanda default/khusus), penanda bila
    template tersimpan sudah dihapus (`hilang`), dan pesan galat kunci `error`. `testid` = data-testid select. Siklus hidup:
    template nonaktif (`aktif` false) tidak bisa dipilih baru — opsinya nonaktif kecuali sedang terpilih, yang lalu diberi
    penanda `{testid}-nonaktif` (aturan dilewati sampai template diaktifkan kembali atau diganti).
--}}
@props(['pilihan', 'terpilih' => null, 'bawaan', 'label', 'error' => null, 'hilang' => false, 'testid' => 'pilih-template'])

@php
    $id = 'pt-'.md5($testid.($attributes->whereStartsWith('wire:model')->first() ?? ''));
    $adaGalat = $error && $errors->has($error);
    $terpilihNonaktif = $terpilih !== null && collect($pilihan)->contains(fn (array $p) => $p['id'] === $terpilih && ! ($p['aktif'] ?? true));
@endphp

<div {{ $attributes->whereDoesntStartWith('wire:model')->class(['min-w-0']) }}>
    <label for="{{ $id }}" class="mb-1 block text-xs font-semibold text-stone-500">{{ $label }}</label>
    <select id="{{ $id }}" {{ $attributes->whereStartsWith('wire:model') }} @class(['form-input w-full py-1.5 text-sm', 'ring-red-400' => $adaGalat])
            @if ($adaGalat) aria-invalid="true" aria-describedby="{{ $id }}-galat" @endif data-testid="{{ $testid }}">
        <option value="">{{ $bawaan }}</option>
        @foreach ($pilihan as $p)
            <option value="{{ $p['id'] }}"{{ ! ($p['aktif'] ?? true) && $p['id'] !== $terpilih ? ' disabled' : '' }}>{{ $p['label'] }}</option>
        @endforeach
        @if ($hilang && $terpilih)
            <option value="{{ $terpilih }}">Template terhapus — pilih pengganti</option>
        @endif
    </select>
    @if ($terpilihNonaktif)
        <p class="mt-1 flex items-center gap-1 text-xs text-gold-800" data-testid="{{ $testid }}-nonaktif"><x-ui.icon name="alert" class="size-3" /> Template ini nonaktif — aturan ini dilewati sampai diaktifkan kembali atau diganti.</p>
    @endif
    @if ($hilang)
        <p class="mt-1 flex items-center gap-1 text-xs text-gold-800" data-testid="{{ $testid }}-hilang"><x-ui.icon name="alert" class="size-3" /> Template yang dipilih sudah dihapus.</p>
    @endif
    @if ($adaGalat)
        <p id="{{ $id }}-galat" class="mt-1 text-xs font-medium text-red-700" role="alert" data-testid="{{ $testid }}-galat">{{ $errors->first($error) }}</p>
    @endif
</div>
