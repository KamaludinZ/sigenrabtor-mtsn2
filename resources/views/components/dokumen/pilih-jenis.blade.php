@props(['pilihan', 'terpilih', 'model' => 'jenis'])

{{--
    Pilihan jenis dokumen (JenisDokumen::pilihan): kartu radio — label, keterangan, bagian yang ikut, master template, cap
    DRAF/siap. Radio asli (panah kiri/kanan berpindah pilihan); jenis per sumber dana nonaktif selama kegiatan tanpa
    sumber dana. Terikat ke properti Livewire $model (bawaan `jenis`).
--}}
<fieldset {{ $attributes->class('min-w-0') }} data-testid="pilih-jenis-dokumen">
    <legend class="text-sm font-semibold text-stone-900">Jenis dokumen</legend>
    <p class="mt-0.5 text-xs text-stone-500">Menentukan bagian yang disusun, master template, dan nama berkas saat dicetak.</p>
    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
        @foreach ($pilihan as $p)
            @php
                $dipilih = $terpilih === $p['kunci'];
                $master = array_values(array_filter($p['template']));
            @endphp
            <label wire:key="jenis-{{ $p['kunci'] }}"
                   @class([
                       'relative flex min-w-0 flex-col gap-1.5 rounded-xl bg-white p-3 ring-1 transition has-focus-visible:ring-2 has-focus-visible:ring-brand-600',
                       'ring-2 ring-brand-600 bg-brand-50/40' => $dipilih,
                       'ring-stone-200 hover:ring-stone-300' => ! $dipilih && $p['bisa'],
                       'cursor-not-allowed ring-stone-200 opacity-60' => ! $p['bisa'],
                       'cursor-pointer' => $p['bisa'],
                   ])
                   data-jenis="{{ $p['kunci'] }}" data-terpilih="{{ $dipilih ? 'ya' : 'tidak' }}" data-bisa="{{ $p['bisa'] ? 'ya' : 'tidak' }}">
                <input type="radio" name="jenis-dokumen" value="{{ $p['kunci'] }}" wire:model.live="{{ $model }}" class="sr-only" @disabled(! $p['bisa'])>
                <span class="flex items-start justify-between gap-2">
                    <span class="text-sm font-semibold text-stone-900">{{ $p['label'] }}</span>
                    <span @class(['grid size-4 shrink-0 place-items-center rounded-full ring-1', 'bg-brand-700 ring-brand-700 text-white' => $dipilih, 'ring-stone-300' => ! $dipilih]) aria-hidden="true">
                        @if ($dipilih)<x-ui.icon name="check" class="size-3" />@endif
                    </span>
                </span>
                <span class="text-xs text-stone-500">{{ $p['keterangan'] }}</span>
                <span class="flex flex-wrap gap-1">
                    @foreach ($p['isi'] as $isi)
                        <span class="rounded bg-stone-100 px-1.5 py-0.5 text-[11px] font-medium text-stone-600">{{ $isi }}</span>
                    @endforeach
                </span>
                <span class="mt-auto flex flex-wrap items-center justify-between gap-x-2 gap-y-1 pt-1 text-[11px]">
                    <span class="min-w-0 truncate text-stone-500" title="{{ $master === [] ? 'Susunan baku aplikasi' : implode(', ', array_column($master, 'nama')) }}">
                        {{ $master === [] ? 'Susunan baku' : 'Master: '.implode(', ', array_column($master, 'nama')) }}
                    </span>
                    <span @class(['font-semibold', 'text-red-700' => $p['draf'], 'text-brand-800' => ! $p['draf']])>{{ $p['draf'] ? 'DRAF' : 'Siap' }}</span>
                </span>
                @if ($p['alasan'])
                    <span class="text-[11px] font-medium text-gold-700">{{ $p['alasan'] }}</span>
                @endif
            </label>
        @endforeach
    </div>
</fieldset>
