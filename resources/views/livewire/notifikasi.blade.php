@use('Illuminate\Support\Carbon')

@php
    $daftar = $this->daftar;
    $baru = $this->jumlahBaru();
    $nada = [
        'info' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'baik' => 'bg-brand-50 text-brand-700 ring-brand-200',
        'peringatan' => 'bg-gold-50 text-gold-700 ring-gold-200',
        'netral' => 'bg-stone-100 text-stone-600 ring-stone-200',
    ];
@endphp

{{-- Notifikasi status dokumen: lencana belum dibaca + panel terbaru (App\Support\Notifikasi\NotifikasiStatus). Di layar sempit panel menempel ke tepi layar, bukan ke lonceng. --}}
<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false" wire:poll.visible.60s data-testid="notifikasi">
    <button type="button" class="relative rounded-lg p-2 text-stone-600 hover:bg-stone-100 hover:text-stone-900"
            x-on:click="open = ! open; if (open) $wire.buka()" x-bind:aria-expanded="open" aria-haspopup="true"
            aria-label="Notifikasi{{ $baru ? ' — '.$baru.' belum dibaca' : '' }}">
        <x-ui.icon name="bell" class="size-5" />
        @if ($baru)
            <span class="absolute top-1 right-1 grid min-w-4 place-items-center rounded-full bg-red-600 px-1 text-[10px] leading-4 font-bold text-white tabular-nums ring-2 ring-white" data-testid="jumlah-notifikasi">{{ $baru > 9 ? '9+' : $baru }}</span>
        @endif
    </button>

    <div x-cloak x-show="open" x-transition.origin.top.right
         class="fixed inset-x-4 top-16 z-40 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-stone-200 sm:absolute sm:inset-x-auto sm:top-auto sm:right-0 sm:mt-2 sm:w-[22rem]"
         role="region" aria-label="Notifikasi status dokumen" data-testid="panel-notifikasi">
        <div class="border-b border-stone-100 px-4 py-3">
            <p class="text-sm font-semibold text-stone-900">Notifikasi</p>
            <p class="text-xs text-stone-500">Perubahan status dokumen kegiatan Tahun Anggaran {{ \App\Support\Stub\StubData::tahunAktif()['tahun'] }}.</p>
        </div>

        @if ($daftar === [])
            <div class="px-4 py-8 text-center">
                <x-ui.icon name="bell" class="mx-auto size-8 text-stone-300" />
                <p class="mt-2 text-sm text-stone-500">Belum ada notifikasi.</p>
            </div>
        @else
            <ul class="max-h-[min(28rem,70vh)] divide-y divide-stone-100 overflow-y-auto">
                @foreach ($daftar as $n)
                    @php
                        $tautan = $this->tautan($n);
                    @endphp
                    <li wire:key="notifikasi-{{ $n['id'] }}" data-aksi="{{ $n['aksi'] }}" data-kegiatan="{{ $n['kegiatan_id'] }}" @if ($n['baru']) data-baru @endif>
                        <a @if ($tautan) href="{{ $tautan }}" wire:navigate @endif
                           @class(['flex gap-3 px-4 py-3 hover:bg-stone-50', 'bg-brand-50/40' => $n['baru']])>
                            <span class="grid size-8 shrink-0 place-items-center rounded-full ring-1 {{ $nada[$n['nada']] }}">
                                <x-ui.icon :name="$n['ikon']" class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1 text-xs">
                                <span class="flex items-start justify-between gap-2">
                                    <span class="font-semibold text-stone-900">{{ $n['judul'] }}</span>
                                    @if ($n['baru'])
                                        <span class="mt-1 size-2 shrink-0 rounded-full bg-brand-600" aria-label="Baru"></span>
                                    @endif
                                </span>
                                <span class="mt-0.5 block line-clamp-2 text-stone-700">{{ $n['kegiatan'] }}</span>
                                @if ($n['catatan'])
                                    <span class="mt-1 block line-clamp-2 rounded-md bg-stone-50 px-2 py-1 text-stone-600 ring-1 ring-stone-100">“{{ $n['catatan'] }}”</span>
                                @endif
                                <span class="mt-1 block text-stone-500">{{ $n['oleh'] }} · <time datetime="{{ $n['pada'] }}" title="{{ Carbon::parse($n['pada'])->locale('id')->translatedFormat('j F Y, H.i') }}">{{ Carbon::parse($n['pada'])->locale('id')->diffForHumans() }}</time></span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
