@props(['sumber', 'hasil', 'tautanPagu' => null, 'ringkas' => false])

@use('App\Support\Format')
@use('App\Support\Rab\PeringatanPagu')

@php
    /** @var \App\Support\Rab\HasilRab $hasil */
    $peringatan = PeringatanPagu::untuk($sumber, $hasil);
    // nada → [latar & bingkai, warna ikon, warna judul, ikon]
    $gaya = [
        'bahaya' => ['bg-red-50 ring-red-200', 'text-red-600', 'text-red-800', 'alert'],
        'perhatian' => ['bg-gold-50 ring-gold-300', 'text-gold-600', 'text-stone-900', 'alert'],
        'info' => ['bg-sky-50 ring-sky-200', 'text-sky-600', 'text-sky-900', 'info'],
    ];
@endphp

{{--
    Peringatan batas pagu satu RAB (PRD F12): melebihi pagu (merah), pagu belum diisi (emas), atau di bawah pagu (info),
    lengkap dengan angka, persen pemakaian, dan — bila melebihi — item terbesar. Tidak tampil bila total tepat sama dengan
    pagu atau RAB belum berisi rincian. "ringkas" menyembunyikan daftar item (mis. di halaman Semua RAB). Slot = aksi tambahan.
--}}
@if ($peringatan)
    @php
        [$latar, $warnaIkon, $warnaJudul, $ikon] = $gaya[$peringatan->nada()];
        $itemTerbesar = $ringkas ? [] : $peringatan->itemTerbesar();
    @endphp
    <div {{ $attributes->class(['flex items-start gap-3 rounded-xl px-4 py-3 ring-1', $latar]) }}
         role="{{ $peringatan->nada() === 'bahaya' ? 'alert' : 'status' }}"
         data-testid="peringatan-pagu-{{ mb_strtolower($sumber) }}" data-status="{{ $peringatan->status->status }}">
        <x-ui.icon :name="$ikon" class="mt-0.5 size-5 shrink-0 {{ $warnaIkon }}" />
        <div class="min-w-0 flex-1 text-sm">
            <p @class(['font-semibold', $warnaJudul])>{{ $peringatan->judul() }}</p>
            <p class="mt-0.5 text-stone-700">{{ $peringatan->pesan() }}</p>

            @if ($itemTerbesar !== [])
                <div class="mt-2 max-w-xl">
                    <p class="text-xs font-semibold text-stone-500">Item terbesar</p>
                    <ol class="mt-1 space-y-0.5 text-xs text-stone-700" data-testid="item-terbesar">
                        @foreach ($itemTerbesar as $item)
                            <li class="flex items-baseline justify-between gap-3">
                                <span class="min-w-0 truncate">{{ $item['uraian'] }}</span>
                                <span class="shrink-0 font-medium tabular-nums">{{ Format::rupiah($item['jumlah']) }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            @if ($tautanPagu || $slot->isNotEmpty())
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs font-semibold">
                    {{ $slot }}
                    @if ($tautanPagu)
                        <a href="{{ $tautanPagu }}" class="text-stone-700 underline decoration-stone-300 underline-offset-2 hover:text-stone-900">
                            {{ $peringatan->status->status === 'tanpa_pagu' ? 'Isi pagu '.$sumber : 'Periksa pagu '.$sumber }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endif
