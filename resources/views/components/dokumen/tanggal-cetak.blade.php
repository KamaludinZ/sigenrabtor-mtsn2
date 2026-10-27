@props(['tanggal', 'tautan' => ''])

{{--
    Tanggal tercetak di dokumen (TanggalDokumen::dari): teks persis seperti di KAK/TOR & RAB, temuan tahun/urutan sebagai
    peringatan (tidak memblokir cetak). Tiap tempat menautkan jangkar #cetak-{kunci} ($tautan = URL pratinjau; '' = halaman ini).
--}}
<div {{ $attributes->class(['mt-4 border-t border-stone-100 pt-3']) }} data-testid="tanggal-cetak">
    <div class="flex items-center justify-between gap-2">
        <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Tanggal tercetak</p>
        @php
            $adaTemuan = collect($tanggal)->contains(fn ($t) => $t['temuan'] !== []);
        @endphp
        <span @class(['text-[11px] font-semibold', 'text-gold-700' => $adaTemuan, 'text-brand-700' => ! $adaTemuan]) data-testid="tanggal-cetak-status">{{ $adaTemuan ? 'Perlu dicek' : 'Sesuai' }}</span>
    </div>

    <ul class="mt-2 space-y-1.5 text-xs">
        @foreach ($tanggal as $t)
            <li class="flex gap-1.5" data-tanggal="{{ $t['kunci'] }}">
                @if (! $t['teks'])
                    <x-ui.icon name="circle" class="mt-px size-3.5 shrink-0 text-stone-300" />
                @elseif ($t['temuan'] === [])
                    <x-ui.icon name="check-circle" class="mt-px size-3.5 shrink-0 text-brand-600" />
                @else
                    <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0 text-gold-600" />
                @endif
                <div class="min-w-0">
                    <p class="text-stone-700">
                        @if ($t['teks'])
                            <a href="{{ $tautan }}#cetak-{{ $t['kunci'] }}" class="hover:text-brand-800 hover:underline" data-lompat-cetak><span class="font-semibold">{{ $t['dokumen'] }}</span> · {{ $t['bagian'] }}</a>
                        @else
                            <span class="font-semibold">{{ $t['dokumen'] }}</span> · {{ $t['bagian'] }}
                        @endif
                    </p>
                    <p class="mt-0.5 text-stone-500" data-teks-cetak>{{ $t['teks'] ? '“'.$t['teks'].'”' : 'Belum diisi' }}</p>
                    @foreach ($t['temuan'] as $temuan)
                        <p class="text-gold-700">{{ $temuan['pesan'] }}</p>
                    @endforeach
                </div>
            </li>
        @endforeach
    </ul>
</div>
