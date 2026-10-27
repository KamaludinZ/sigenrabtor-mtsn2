@props(['grup'])

@use('App\Support\Dokumen\ChecklistIsian')

@php
    // Checklist isian wajib per tempat mengisinya (ChecklistIsian); grup yang masih kurang terbuka.
    $ringkasan = ChecklistIsian::ringkasan($grup);
@endphp

<section {{ $attributes->class(['rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200']) }} aria-labelledby="judul-checklist" data-testid="checklist-isian">
    <div class="flex items-baseline justify-between gap-2">
        <h2 id="judul-checklist" class="text-sm font-semibold text-stone-900">Isian wajib</h2>
        <span @class(['text-xs font-semibold tabular-nums', 'text-brand-700' => $ringkasan['terisi'] === $ringkasan['jumlah'], 'text-stone-600' => $ringkasan['terisi'] < $ringkasan['jumlah']]) data-testid="ringkasan-checklist">{{ $ringkasan['terisi'] }}/{{ $ringkasan['jumlah'] }} lengkap</span>
    </div>
    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-label="Kelengkapan isian wajib" aria-valuemin="0" aria-valuemax="{{ $ringkasan['jumlah'] }}" aria-valuenow="{{ $ringkasan['terisi'] }}">
        <div class="h-full rounded-full bg-brand-600" style="width: {{ $ringkasan['jumlah'] ? round($ringkasan['terisi'] / $ringkasan['jumlah'] * 100) : 0 }}%"></div>
    </div>
    <p class="mt-1.5 text-xs text-stone-500" data-testid="syarat-ajukan">
        @if ($ringkasan['ajukan_kurang'] > 0)
            <span class="font-semibold text-red-700">{{ $ringkasan['ajukan_kurang'] }} syarat pengajuan belum terpenuhi.</span>
        @else
            <span class="font-semibold text-brand-700">Syarat pengajuan terpenuhi.</span>
        @endif
        Isian lain yang kosong membuat dokumen tercetak dengan cap DRAF.
    </p>

    <div class="mt-3 space-y-2">
        @foreach ($grup as $g)
            @php
                $kurang = count(array_filter($g['butir'], fn ($b) => ! $b['terisi']));
            @endphp
            <details @if ($kurang > 0) open @endif class="group rounded-xl ring-1 ring-stone-200" data-grup="{{ $g['kunci'] }}" data-kurang="{{ $kurang }}" wire:key="grup-{{ $g['kunci'] }}">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-3 py-2 text-xs">
                    <span class="flex items-center gap-1.5 font-semibold text-stone-800">
                        <x-ui.icon :name="$kurang === 0 ? 'check-circle' : 'circle'" class="size-4 {{ $kurang === 0 ? 'text-brand-600' : 'text-stone-300' }}" />
                        {{ $g['judul'] }}
                    </span>
                    <span class="flex items-center gap-1.5 text-stone-500">
                        <span class="tabular-nums">{{ count($g['butir']) - $kurang }}/{{ count($g['butir']) }}</span>
                        <x-ui.icon name="chevron-down" class="size-3.5 transition group-open:rotate-180" />
                    </span>
                </summary>
                <ul class="space-y-1.5 border-t border-stone-100 px-3 py-2">
                    @foreach ($g['butir'] as $b)
                        <li class="flex gap-1.5 text-xs" data-butir="{{ $b['kunci'] }}" data-terisi="{{ $b['terisi'] ? 'ya' : 'tidak' }}">
                            @if ($b['terisi'])
                                <x-ui.icon name="check" class="mt-px size-3.5 shrink-0 text-brand-600" />
                            @else
                                <x-ui.icon name="circle" class="mt-px size-3.5 shrink-0 {{ $b['ajukan'] ? 'text-red-500' : 'text-gold-500' }}" />
                            @endif
                            <span class="min-w-0">
                                <span @class(['text-stone-700' => $b['terisi'], 'font-medium text-stone-900' => ! $b['terisi']])>{{ $b['label'] }}</span>
                                @if (! $b['terisi'] && $b['ajukan'])
                                    <span class="ml-1 rounded bg-red-50 px-1 py-px text-[10px] font-semibold text-red-700 ring-1 ring-red-200">Wajib diajukan</span>
                                @endif
                                @if ($b['keterangan'])
                                    <span class="block text-stone-500">{{ $b['keterangan'] }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
                @if ($kurang > 0)
                    <div class="border-t border-stone-100 px-3 py-1.5 text-xs">
                        @if ($g['tautan'])
                            <a href="{{ $g['tautan'] }}" @if (! str_contains($g['tautan'], '#')) wire:navigate @endif class="inline-flex items-center gap-1 font-semibold text-brand-700 hover:text-brand-800">Lengkapi <x-ui.icon name="chevron-right" class="size-3.5" /></a>
                        @else
                            <span class="text-stone-500">Diatur admin di Pengaturan.</span>
                        @endif
                    </div>
                @endif
            </details>
        @endforeach
    </div>
</section>
