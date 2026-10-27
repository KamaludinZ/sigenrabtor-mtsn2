@props(['hilang' => [], 'tautan' => null])

{{--
    Penanda kode master yang hilang (App\Support\Kegiatan\KodeHilang::dari): daftar kode yang tidak ada di master tahun
    kegiatan atau dikosongkan saat diduplikat/diimpor, dengan tautan "Pilih ulang" ke bagian kodenya. $tautan = URL
    halaman Kode & Klasifikasi bila ditampilkan di halaman lain (null = jangkar di halaman ini).
--}}
@if ($hilang !== [])
    <section {{ $attributes->class(['rounded-xl bg-gold-50 px-4 py-3 ring-1 ring-gold-200']) }} role="status" aria-labelledby="judul-kode-hilang" data-testid="penanda-kode-hilang">
        <h2 id="judul-kode-hilang" class="flex items-start gap-2 text-sm font-semibold text-stone-900">
            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0 text-gold-700" />
            {{ count($hilang) }} kode master perlu dipilih ulang
        </h2>
        <ul class="mt-1.5 ml-6 space-y-1 text-sm">
            @foreach ($hilang as $h)
                <li class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5" data-kode-hilang="{{ $h['kolom'] }}" data-asal="{{ $h['asal'] }}">
                    <span class="min-w-0 text-stone-700">
                        <span class="font-medium text-stone-900">{{ $h['label'] }}</span>
                        <span class="font-mono text-xs text-gold-700 line-through" title="Kode lama">{{ $h['kode'] }}</span>
                        <span class="text-stone-600">{{ $h['sebab'] }}.</span>
                    </span>
                    <a href="{{ ($tautan ?? '').'#'.$h['jangkar'] }}" @if ($tautan) wire:navigate @endif class="shrink-0 text-xs font-semibold text-brand-700 hover:text-brand-800">Pilih ulang</a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
