@use('App\Support\Format')
@use('App\Support\Pengaturan\KontrolTahunAktif')

@php
    $aktif = $this->aktif();
    $tahun = $aktif['tahun'] ?? \App\Support\Stub\StubData::tahunAktif()['tahun'];
    $admin = $this->admin();
    $lain = array_values(array_filter($this->pilihan, fn ($t) => ! $t['is_active']));
@endphp

{{-- Kontrol tahun anggaran aktif (KontrolTahunAktif): satu tahun untuk semua dokumen & data. --}}
<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false" data-testid="kontrol-tahun">
    <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-haspopup="true" data-testid="chip-tahun-aktif"
            class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-800 ring-1 ring-brand-200 ring-inset hover:bg-brand-100"
            title="Tahun anggaran aktif — dipakai seluruh dokumen">
        <x-ui.icon name="calendar" class="size-3.5" />
        <span class="hidden sm:inline">Tahun Anggaran</span> {{ $tahun }}
        @if ($aktif['is_locked'] ?? false)
            <x-ui.icon name="lock" class="size-3.5" />
            <span class="sr-only">(terkunci)</span>
        @endif
        <x-ui.icon name="chevron-down" class="size-3 opacity-60" />
    </button>

    <div x-cloak x-show="open" x-transition.origin.top.left
         class="fixed inset-x-4 top-16 z-40 max-h-[calc(100vh-5rem)] overflow-y-auto rounded-xl bg-white shadow-lg ring-1 ring-stone-200 sm:absolute sm:inset-x-auto sm:top-auto sm:left-0 sm:mt-2 sm:w-[24rem]"
         role="region" aria-label="Tahun anggaran aktif" data-testid="panel-tahun-aktif">
        <div class="border-b border-stone-100 px-4 py-3">
            <p class="flex items-center gap-2 text-sm font-semibold text-stone-900">
                Tahun Anggaran {{ $tahun }}
                <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800 ring-1 ring-brand-200 ring-inset">Aktif</span>
                @if ($aktif['is_locked'] ?? false)
                    <span class="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-semibold text-stone-600"><x-ui.icon name="lock" class="size-3" /> Terkunci</span>
                @endif
            </p>
            @if ($aktif['diaktifkan_oleh'] ?? null)
                <p class="mt-0.5 text-xs text-stone-500">Diaktifkan {{ Format::tanggal($aktif['diaktifkan_pada']) }} oleh {{ $aktif['diaktifkan_oleh'] }}.</p>
            @endif
        </div>

        <div class="px-4 py-3">
            <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Satu tahun untuk semua</p>
            <ul class="mt-2 space-y-2" data-testid="dipakai-tahun">
                @foreach (KontrolTahunAktif::dipakai() as $d)
                    <li class="flex gap-2 text-xs" data-dipakai="{{ $d['kunci'] }}" data-siap="{{ $d['siap'] === false ? 'tidak' : 'ya' }}">
                        <x-ui.icon :name="$d['siap'] === false ? 'alert' : 'check-circle'" class="mt-px size-4 shrink-0 {{ $d['siap'] === false ? 'text-gold-600' : 'text-brand-600' }}" />
                        <div class="min-w-0">
                            <p class="font-semibold text-stone-800">{{ $d['judul'] }}</p>
                            <p class="text-stone-500">{{ $d['keterangan'] }}</p>
                            @if ($d['catatan'])
                                <p class="mt-0.5 text-gold-700">{{ $d['catatan'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($admin && $lain !== [])
            <div class="border-t border-stone-100 px-4 py-3" data-testid="ganti-tahun">
                <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Ganti tahun aktif</p>
                <ul class="mt-2 space-y-1.5">
                    @foreach ($lain as $t)
                        <li wire:key="pilihan-tahun-{{ $t['tahun'] }}" data-tahun="{{ $t['tahun'] }}">
                            @if ($akanDiaktifkan === $t['tahun'])
                                <div class="rounded-lg bg-stone-50 p-3 ring-1 ring-stone-200" data-testid="konfirmasi-ganti-tahun">
                                    <p class="text-sm font-semibold text-stone-900">Aktifkan Tahun Anggaran {{ $t['tahun'] }}?</p>
                                    <p class="mt-0.5 text-xs text-stone-600">Seluruh layar dan dokumen akan memakai tahun {{ $t['tahun'] }}. Data TA {{ $tahun }} disembunyikan, tidak dihapus.</p>
                                    @if ($t['is_locked'])
                                        <p class="mt-1 text-xs text-gold-700">TA {{ $t['tahun'] }} terkunci — datanya hanya bisa dilihat.</p>
                                    @endif
                                    <div class="mt-2.5 flex gap-2">
                                        <button type="button" wire:click="batal" class="btn-ghost flex-1 justify-center px-2.5 py-1.5 text-xs">Batal</button>
                                        <button type="button" wire:click="aktifkan" wire:loading.attr="disabled" wire:target="aktifkan" class="btn-primary flex-1 justify-center px-2.5 py-1.5 text-xs">Ya, aktifkan {{ $t['tahun'] }}</button>
                                    </div>
                                </div>
                            @else
                                <button type="button" wire:click="pilih({{ $t['tahun'] }})" class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-sm hover:bg-stone-50" aria-label="Aktifkan Tahun Anggaran {{ $t['tahun'] }}">
                                    <span>
                                        <span class="font-semibold text-stone-800">TA {{ $t['tahun'] }}</span>
                                        <span class="block text-xs text-stone-500">
                                            {{ $t['is_locked'] ? 'Terkunci · ' : '' }}{{ $t['jumlah_kegiatan'] }} kegiatan · master kode {{ $t['master_tersedia'] ? 'tersedia' : 'belum ada' }}
                                        </span>
                                    </span>
                                    <span class="text-xs font-semibold text-brand-700">Aktifkan</span>
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="border-t border-stone-100 px-4 py-2.5 text-xs">
            @if ($kelola = $this->kelola())
                <a href="{{ $kelola }}" wire:navigate class="inline-flex items-center gap-1 font-semibold text-brand-700 hover:text-brand-800">Kelola tahun anggaran <x-ui.icon name="chevron-right" class="size-3.5" /></a>
            @else
                <p class="text-stone-500">Tahun anggaran aktif diatur admin.</p>
            @endif
        </div>
    </div>
</div>
