@props(['angka', 'sumber', 'tautan' => null])

{{--
    Tempat total RAB tercetak di dokumen (AngkaDokumen::tempatCetak): KAK bagian 8 Pembiayaan, header RAB "Jumlah yang
    diajukan", dan baris Total keseluruhan — teksnya persis seperti tercetak, diperiksa ulang oleh PemeriksaTerbilang
    (nominal = total RAB, terbilang = ejaan nominal). $tautan (URL pratinjau; '' = halaman ini) menjadikan tiap tempat
    tautan ke jangkar #cetak-{kunci} di dokumen.
--}}
<div {{ $attributes->class(['mt-4 border-t border-stone-100 pt-3']) }} data-testid="sinkron-dokumen">
    <div class="flex items-center justify-between gap-2">
        <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Tercetak di dokumen</p>
        @if (! $angka->adaRincian())
            <span class="text-[11px] text-stone-400">menunggu rincian</span>
        @elseif ($angka->sinkron())
            <span class="text-[11px] font-semibold text-brand-700" data-testid="sinkron-dokumen-status">Sinkron</span>
        @else
            <span class="text-[11px] font-semibold text-red-600" data-testid="sinkron-dokumen-status">Tidak sinkron</span>
        @endif
    </div>

    <ul class="mt-2 space-y-1.5 text-xs">
        @foreach ($angka->tempatCetak() as $tempat)
            <li class="flex gap-1.5" data-tempat="{{ $tempat['kunci'] }}" title="{{ $tempat['teks'] }}">
                @if (! $tempat['teks'])
                    <x-ui.icon name="circle" class="mt-px size-3.5 shrink-0 text-stone-300" />
                @elseif ($tempat['temuan'] === [])
                    <x-ui.icon name="check-circle" class="mt-px size-3.5 shrink-0 text-brand-600" />
                @else
                    <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0 text-red-600" />
                @endif
                <div class="min-w-0">
                    <p class="text-stone-700">
                        @if ($tautan !== null && $tempat['teks'])
                            <a href="{{ $tautan }}#cetak-{{ $tempat['kunci'] }}" class="hover:text-brand-800 hover:underline" data-lompat-cetak><span class="font-semibold">{{ $tempat['dokumen'] }}</span> · {{ $tempat['bagian'] }}</a>
                        @else
                            <span class="font-semibold">{{ $tempat['dokumen'] }}</span> · {{ $tempat['bagian'] }}
                        @endif
                    </p>
                    @if ($tempat['teks'] && $tautan === '')
                        <p class="mt-0.5 text-stone-500" data-teks-cetak>“{{ $tempat['teks'] }}”</p>
                    @endif
                    @foreach ($tempat['temuan'] as $temuan)
                        <p class="text-red-600">{{ $temuan['masalah'] }} Tertulis “{{ $temuan['tertulis'] }}”, seharusnya “{{ $temuan['seharusnya'] }}”.</p>
                    @endforeach
                </div>
            </li>
        @endforeach
    </ul>

    <p class="mt-2 text-xs leading-snug text-stone-500">
        Nominal &amp; terbilang KAK dan RAB {{ $sumber }} diambil dari total RAB yang sama — ubah rincian, semuanya ikut berubah.
    </p>
</div>
