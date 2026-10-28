@props(['kelengkapan', 'penampil' => false])

@use('App\Support\Dokumen\KelengkapanDokumen')

@php
    $warna = ['lengkap' => ['text-brand-700', 'bg-brand-600'], 'kurang' => ['text-gold-600', 'bg-gold-500'], 'periksa' => ['text-sky-700', 'bg-sky-600']];
    $persen = $kelengkapan->persen();
    $judulDokumen = ['cover' => null, 'kak' => 'KAK/TOR', 'rab' => null];
@endphp

{{--
    Ringkasan kelengkapan satu set dokumen (KelengkapanDokumen): persen & meter per bagian, jumlah per status, isian wajib
    yang kosong, daftar bagian bertautan ke jangkarnya di dokumen, dan cap DRAF. :penampil="true" di dalam penampil
    Pratinjau (Alpine penampilDokumen): bagian yang sedang dibaca disorot (`bagianAktif`) dan tombol "Isian kosong
    berikutnya" memakai `keKosong()`.
--}}
<section {{ $attributes->class(['rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200']) }} aria-labelledby="judul-kelengkapan" data-testid="ringkasan-kelengkapan" data-persen="{{ $persen }}">
    <div class="flex items-baseline justify-between gap-2">
        <h2 id="judul-kelengkapan" class="text-sm font-semibold text-stone-900">Kelengkapan dokumen</h2>
        <span @class(['text-sm font-bold tabular-nums', 'text-brand-700' => $persen === 100, 'text-stone-900' => $persen < 100]) data-testid="persen-kelengkapan">{{ $persen }}%</span>
    </div>
    <div class="mt-2 flex h-1.5 gap-0.5" role="img" aria-label="{{ $kelengkapan->jumlah('lengkap') }} dari {{ $kelengkapan->jumlah() }} bagian lengkap">
        @foreach ($kelengkapan->bagian as $b)
            <span class="flex-1 rounded-full first:rounded-l-full last:rounded-r-full {{ $warna[$b['status']][1] }}" aria-hidden="true"></span>
        @endforeach
    </div>
    <p class="mt-2 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-stone-600" data-testid="hitungan-kelengkapan">
        @foreach (KelengkapanDokumen::STATUS as $status => [$label])
            <span class="inline-flex items-center gap-1" data-hitung="{{ $status }}"><span class="size-1.5 rounded-full {{ $warna[$status][1] }}" aria-hidden="true"></span><span class="font-semibold text-stone-900 tabular-nums">{{ $kelengkapan->jumlah($status) }}</span> {{ mb_strtolower($label) }}</span>
        @endforeach
    </p>
    @if ($kelengkapan->wajib > 0)
        <p class="mt-2 flex items-start gap-1.5 rounded-md bg-red-50 px-2.5 py-1.5 text-xs text-red-800 ring-1 ring-red-200" data-testid="kosong-wajib">
            <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" /> {{ $kelengkapan->wajib }} isian wajib belum diisi — kegiatan belum bisa diajukan untuk diperiksa.
        </p>
    @endif

    <nav class="mt-3" aria-label="Kelengkapan per bagian">
        @foreach (['cover', 'kak', 'rab'] as $dokumen)
            @if ($judulDokumen[$dokumen])
                <p class="mt-1.5 px-2 pb-0.5 text-[11px] font-semibold tracking-wide text-stone-500 uppercase" aria-hidden="true">{{ $judulDokumen[$dokumen] }}</p>
            @endif
            <ol class="space-y-0.5">
                @foreach ($kelengkapan->dokumen($dokumen) as $b)
                    <li>
                        <a href="#{{ $b['jangkar'] }}" data-kelengkapan="{{ $b['kunci'] }}" data-status="{{ $b['status'] }}"
                           @if ($b['kurang'] !== [] || $b['periksa'] !== []) title="{{ \Illuminate\Support\Str::ucfirst(implode('; ', [...$b['kurang'], ...$b['periksa']])) }}" @endif
                           @if ($penampil)
                               x-bind:aria-current="bagianAktif === '{{ $b['kunci'] }}' ? 'location' : null"
                               x-bind:class="{ 'bg-stone-100 font-semibold text-stone-900': bagianAktif === '{{ $b['kunci'] }}', 'text-stone-700 hover:bg-stone-50': bagianAktif !== '{{ $b['kunci'] }}' }"
                           @endif
                           @class(['flex items-center gap-2 rounded-md py-1 pr-2 text-xs', 'pl-4' => $dokumen === 'kak', 'pl-2' => $dokumen !== 'kak', 'text-stone-700 hover:bg-stone-50' => ! $penampil])>
                            <x-ui.icon :name="KelengkapanDokumen::STATUS[$b['status']][1]" :class="'size-3.5 shrink-0 '.$warna[$b['status']][0]" />
                            <span class="min-w-0 flex-1 truncate">{{ $b['label'] }}</span>
                            @if ($b['kurang'] !== [])
                                <span class="shrink-0 text-[11px] font-semibold text-gold-700">{{ count($b['kurang']) }} kurang</span>
                            @elseif ($b['periksa'] !== [])
                                <span class="shrink-0 text-[11px] font-semibold text-sky-700">periksa</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ol>
        @endforeach
    </nav>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-stone-100 pt-3">
        <span @class(['inline-flex items-center gap-1 text-xs font-semibold', 'text-red-700' => $kelengkapan->draf->draf, 'text-brand-800' => ! $kelengkapan->draf->draf]) data-testid="cap-kelengkapan">
            <x-ui.icon :name="$kelengkapan->draf->draf ? 'alert' : 'check-circle'" class="size-3.5" /> {{ $kelengkapan->draf->draf ? 'Tercetak sebagai DRAF' : 'Siap dicetak' }}
        </span>
        @if ($penampil && $kelengkapan->jumlah('kurang') > 0)
            <button type="button" x-on:click="keKosong(1)" class="inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800" data-testid="ke-isian-kosong">
                Isian kosong berikutnya <x-ui.icon name="chevron-right" class="size-3.5" />
            </button>
        @endif
    </div>
</section>
