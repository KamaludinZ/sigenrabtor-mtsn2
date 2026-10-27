@use('App\Support\Master\Impor\PratinjauMaster')
@use('App\Support\Master\JenisKode')

{{--
    Pratinjau sebelum simpan: tab per jenis dalam berkas (jumlah baris), ringkasan & saringan status, lalu baris
    berkas dibandingkan master tahun ini. Status selalu ikon + teks; "hilang" = ada di master tetapi tidak di berkas.
--}}
@php
    $item = $pratinjau[$tabPratinjau] ?? null;
    $baris = $this->barisPratinjau();
    $gayaStatus = [
        'baru' => ['bg-brand-50 text-brand-800 ring-brand-200', 'plus'],
        'berubah' => ['bg-sky-50 text-sky-800 ring-sky-200', 'pencil'],
        'tetap' => ['bg-stone-100 text-stone-600 ring-stone-200', 'check'],
        'hilang' => ['bg-gold-50 text-gold-800 ring-gold-200', 'alert'],
        'gagal' => ['bg-red-50 text-red-700 ring-red-200', 'x'],
    ];
@endphp

<div data-testid="pratinjau-master">
    <nav class="flex gap-1 overflow-x-auto border-b border-stone-200 px-3 sm:px-4" role="tablist" aria-label="Jenis dalam berkas">
        @foreach ($pratinjau as $jenis => $p)
            <button type="button" role="tab" wire:click="$set('tabPratinjau', '{{ $jenis }}')" aria-selected="{{ $jenis === $tabPratinjau ? 'true' : 'false' }}" data-jenis="{{ $jenis }}"
                    @class(['-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 text-sm', 'border-brand-700 font-semibold text-brand-800' => $jenis === $tabPratinjau, 'border-transparent font-medium text-stone-500 hover:border-stone-300 hover:text-stone-800' => $jenis !== $tabPratinjau])>
                {{ JenisKode::DAFTAR[$jenis]['label'] }}
                @if (! $p['dibaca'])
                    <span class="rounded-full bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold text-stone-500 uppercase">segera</span>
                @elseif ($p['galat'] || $p['ringkasan']['gagal'] > 0)
                    <x-ui.icon name="alert" class="size-3.5 text-red-600" /><span class="sr-only">(ada galat)</span>
                @else
                    <span class="rounded-full bg-stone-100 px-1.5 py-0.5 text-[11px] font-semibold text-stone-600 tabular-nums">{{ count($p['baris']) }}</span>
                @endif
            </button>
        @endforeach
    </nav>

    @if ($item)
        <div class="space-y-4 px-5 py-5 sm:px-6" wire:key="pratinjau-{{ $tabPratinjau }}">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-sm font-semibold text-stone-900">{{ JenisKode::DAFTAR[$tabPratinjau]['judul'] }}</h3>
                <p class="text-xs text-stone-500">Dari sheet {{ $item['sheet'] }} · dibandingkan dengan master TA {{ $tahun }}</p>
            </div>

            @if (! $item['dibaca'])
                <p class="rounded-lg bg-stone-50 px-4 py-6 text-center text-sm text-stone-500 ring-1 ring-stone-200" data-testid="pratinjau-menyusul">
                    Sheet {{ $item['sheet'] }} sudah dikenali. Pratinjau {{ JenisKode::DAFTAR[$tabPratinjau]['label'] }} sedang disiapkan.
                </p>
            @elseif ($item['galat'])
                <p class="flex items-start gap-2 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200" role="alert" data-testid="galat-pratinjau">
                    <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> {{ $item['galat'] }}
                </p>
            @else
                {{-- Ringkasan sekaligus saringan status. --}}
                <div class="flex flex-wrap gap-1.5" role="group" aria-label="Saring status" data-testid="ringkasan-pratinjau">
                    <button type="button" wire:click="$set('saring', 'semua')" aria-pressed="{{ $saring === 'semua' ? 'true' : 'false' }}"
                            @class(['rounded-full px-2.5 py-1 text-xs font-semibold ring-1', 'bg-stone-900 text-white ring-stone-900' => $saring === 'semua', 'bg-white text-stone-700 ring-stone-300 hover:bg-stone-50' => $saring !== 'semua'])>
                        Semua <span class="tabular-nums">{{ count($item['baris']) }}</span>
                    </button>
                    @foreach (PratinjauMaster::STATUS as $kunci => $label)
                        @if ($item['ringkasan'][$kunci] > 0)
                            <button type="button" wire:click="$set('saring', '{{ $kunci }}')" aria-pressed="{{ $saring === $kunci ? 'true' : 'false' }}" data-status="{{ $kunci }}"
                                    @class(['inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ring-1', $gayaStatus[$kunci][0] => $saring !== $kunci, 'bg-stone-900 text-white ring-stone-900' => $saring === $kunci])>
                                <x-ui.icon :name="$gayaStatus[$kunci][1]" class="size-3.5" /> {{ $label }} <span class="tabular-nums">{{ $item['ringkasan'][$kunci] }}</span>
                            </button>
                        @endif
                    @endforeach
                </div>

                @if ($item['ringkasan']['gagal'] > 0)
                    <p class="text-xs text-red-700">{{ $item['ringkasan']['gagal'] }} baris gagal tidak ikut disimpan — perbaiki berkasnya lalu unggah ulang bila perlu.</p>
                @endif

                <ul class="divide-y divide-stone-100 rounded-xl ring-1 ring-stone-200" data-testid="baris-pratinjau">
                    @forelse ($baris as $b)
                        <li class="grid gap-x-4 gap-y-1 px-4 py-3 sm:grid-cols-[6.5rem_minmax(0,1fr)_7.5rem]" data-kode="{{ $b['kode'] }}" data-status="{{ $b['status'] }}" wire:key="pratinjau-{{ $tabPratinjau }}-{{ $loop->index }}">
                            <div class="flex items-baseline justify-between gap-2 sm:block">
                                <p class="font-semibold text-stone-900 tabular-nums">{{ $b['kode'] }}</p>
                                <p class="text-[11px] text-stone-500">{{ $b['baris'] === [] ? 'Master lama' : 'Baris '.implode(', ', array_slice($b['baris'], 0, 4)).(count($b['baris']) > 4 ? ', …' : '') }}</p>
                            </div>
                            <div class="min-w-0 text-sm">
                                <p @class(['text-stone-800', 'text-stone-500' => $b['status'] === 'hilang'])>{{ $b['tampil']['uraian'] ?? '' }}</p>
                                @if ($b['lama'])
                                    <p class="mt-0.5 text-xs text-stone-500"><span class="font-medium">Sebelumnya:</span> <del>{{ $b['lama']['uraian'] ?? '' }}</del></p>
                                @endif
                                @foreach ($b['pesan'] as $pesan)
                                    <p @class(['mt-0.5 text-xs', 'text-red-700' => $b['status'] === 'gagal', 'text-gold-800' => $b['status'] !== 'gagal'])>{{ $pesan }}</p>
                                @endforeach
                            </div>
                            <div class="flex flex-wrap items-start gap-1.5 sm:flex-col sm:items-end">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $gayaStatus[$b['status']][0] }}">
                                    <x-ui.icon :name="$gayaStatus[$b['status']][1]" class="size-3.5" /> {{ PratinjauMaster::STATUS[$b['status']] }}
                                </span>
                                @if ($b['dipakai'] > 0)
                                    <span class="text-[11px] text-stone-500">dipakai {{ $b['dipakai'] }} kegiatan</span>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-center text-sm text-stone-500">Tidak ada baris dengan status ini.</li>
                    @endforelse
                </ul>
            @endif
        </div>
    @endif
</div>
