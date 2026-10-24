@php($groups = \App\Support\Navigation::groups($currentUser['role']))

<div class="flex grow flex-col gap-y-6 overflow-y-auto bg-brand-900 px-4 pb-4">
    <a href="{{ url('/') }}" class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-1">
        <span class="grid size-9 place-items-center rounded-lg bg-gold-400 text-sm font-bold text-brand-950">SG</span>
        <span class="leading-tight">
            <span class="block text-sm font-bold tracking-wide text-white">SIGENRABTOR</span>
            <span class="block text-xs text-brand-200">{{ $madrasah['nama_singkat'] }}</span>
        </span>
    </a>

    <nav class="flex flex-1 flex-col gap-y-6" aria-label="Menu utama">
        @foreach ($groups as $group)
            <div>
                <p class="px-2 text-[11px] font-semibold tracking-wider text-brand-300 uppercase">{{ $group['label'] }}</p>
                <ul role="list" class="mt-2 space-y-0.5">
                    @foreach ($group['items'] as $item)
                        @php($isActive = $item['available'] && request()->routeIs($item['active']))
                        <li>
                            @if ($item['available'])
                                <a href="{{ $item['url'] }}" wire:navigate
                                   @class([
                                       'group flex items-center gap-x-3 rounded-lg px-2 py-2 text-sm font-medium transition',
                                       'bg-white/10 text-white' => $isActive,
                                       'text-brand-100 hover:bg-white/5 hover:text-white' => ! $isActive,
                                   ])
                                   @if ($isActive) aria-current="page" @endif>
                                    <x-ui.icon :name="$item['icon']" @class(['size-5 shrink-0', 'text-gold-300' => $isActive, 'text-brand-300 group-hover:text-brand-100' => ! $isActive]) />
                                    {{ $item['label'] }}
                                </a>
                            @else
                                <span class="flex cursor-not-allowed items-center gap-x-3 rounded-lg px-2 py-2 text-sm font-medium text-brand-300/60"
                                      title="Fitur ini sedang disiapkan">
                                    <x-ui.icon :name="$item['icon']" class="size-5 shrink-0" />
                                    <span class="flex-1">{{ $item['label'] }}</span>
                                    <span class="rounded bg-white/5 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide uppercase">Segera</span>
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="rounded-lg bg-white/5 p-3 text-xs text-brand-200">
        <p class="font-semibold text-white">Tahun Anggaran {{ $tahunAktif['tahun'] }}</p>
        <p class="mt-0.5">Semua dokumen memakai tahun, pejabat, dan master kode tahun ini.</p>
    </div>
</div>
