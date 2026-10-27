<header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-x-3 border-b border-stone-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
    <button type="button" class="-m-2 rounded-md p-2 text-stone-600 hover:bg-stone-100 lg:hidden" @click="sidebarOpen = true">
        <span class="sr-only">Buka menu</span>
        <x-ui.icon name="menu" />
    </button>

    <div class="flex min-w-0 flex-1 items-center gap-3">
        {{-- Admin: chip tahun menuju Pengaturan › Tahun Anggaran untuk mengganti tahun aktif. --}}
        @php($kelolaTahun = $currentUser['role'] === 'admin' && Route::has('pengaturan.tahun.index'))
        <{{ $kelolaTahun ? 'a' : 'span' }} @if ($kelolaTahun) href="{{ route('pengaturan.tahun.index') }}" wire:navigate title="Kelola tahun anggaran" @endif
            data-testid="chip-tahun-aktif"
            @class(['inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-800 ring-1 ring-brand-200 ring-inset', 'hover:bg-brand-100' => $kelolaTahun])>
            <x-ui.icon name="calendar" class="size-3.5" />
            <span class="hidden sm:inline">Tahun Anggaran</span> {{ $tahunAktif['tahun'] }}
            @if ($tahunAktif['is_locked'])
                <x-ui.icon name="lock" class="size-3.5" />
                <span class="sr-only">(terkunci)</span>
            @endif
        </{{ $kelolaTahun ? 'a' : 'span' }}>
        <span class="hidden truncate text-sm text-stone-500 md:block">{{ $madrasah['nama'] }}</span>
    </div>

    <livewire:notifikasi />

    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
        <button type="button" class="flex items-center gap-x-2 rounded-lg p-1.5 hover:bg-stone-100" @click="open = ! open" :aria-expanded="open">
            <span class="grid size-8 place-items-center rounded-full bg-brand-100 text-xs font-bold text-brand-800">
                {{ \Illuminate\Support\Str::of($currentUser['name'])->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
            </span>
            <span class="hidden text-left leading-tight sm:block">
                <span class="block text-sm font-semibold text-stone-800">{{ $currentUser['name'] }}</span>
                <span class="block text-xs text-stone-500">{{ $currentUser['role'] === 'admin' ? 'Admin' : 'Leading' }} · {{ $currentUser['jabatan'] }}</span>
            </span>
            <x-ui.icon name="chevron-down" class="hidden size-4 text-stone-400 sm:block" />
        </button>

        <div x-cloak x-show="open" x-transition.origin.top.right
             class="absolute right-0 mt-2 w-60 rounded-xl bg-white p-1.5 shadow-lg ring-1 ring-stone-200">
            <div class="border-b border-stone-100 px-2.5 pt-1.5 pb-2.5 sm:hidden">
                <p class="text-sm font-semibold text-stone-800">{{ $currentUser['name'] }}</p>
                <p class="text-xs text-stone-500">{{ $currentUser['jabatan'] }}</p>
            </div>
            @if (Route::has('demo.peran'))
                <p class="px-2.5 pt-2 pb-1 text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Mode demo · lihat sebagai</p>
                @foreach (['admin' => 'Admin', 'leading' => 'Leading'] as $role => $label)
                    <a href="{{ route('demo.peran', $role) }}"
                       class="flex items-center justify-between rounded-lg px-2.5 py-2 text-sm text-stone-700 hover:bg-stone-100">
                        {{ $label }}
                        @if ($currentUser['role'] === $role)
                            <x-ui.icon name="check" class="size-4 text-brand-700" />
                        @endif
                    </a>
                @endforeach
            @endif
        </div>
    </div>
</header>
