@props([
    'baris',
    'judul' => 'Ringkasan kode & klasifikasi',
    'kolom' => false,
])

@use('App\Support\Kegiatan\RingkasanKode')

@php
    $terisi = RingkasanKode::jumlahTerisi($baris);
    $total = count($baris);
@endphp

{{-- Ringkasan kode terpilih + uraian lengkap (bisa dibuka), status per baris, dan salin sebagai teks. --}}
<div {{ $attributes->class(['min-w-0']) }} data-testid="ringkasan-kode-terpilih" x-data="{ tersalin: false }">
    <div class="flex items-center justify-between gap-2">
        <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">{{ $judul }}</p>
        <span @class([
                'rounded-full px-2 py-0.5 text-[11px] font-semibold',
                'bg-brand-50 text-brand-800' => $terisi === $total,
                'bg-stone-100 text-stone-600' => $terisi !== $total,
            ]) data-testid="ringkasan-kode-jumlah">{{ $terisi }}/{{ $total }} dipilih</span>
    </div>

    <dl @class(['mt-3', 'space-y-2.5' => ! $kolom, 'grid gap-x-6 gap-y-3 sm:grid-cols-2' => $kolom])>
        @foreach ($baris as $b)
            <div class="flex gap-2" data-kode="{{ $b['kunci'] }}" x-data="{ buka: false }">
                @if ($b['kode'])
                    <x-ui.icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-brand-600" />
                @else
                    <x-ui.icon name="circle" class="mt-0.5 size-4 shrink-0 text-stone-300" />
                @endif
                <div class="min-w-0 flex-1 text-sm">
                    <dt class="flex flex-wrap items-baseline gap-x-1.5 text-xs text-stone-500">
                        {{ $b['label'] }}
                        @if ($b['kode'])
                            <span class="font-mono font-semibold text-stone-700">{{ $b['kode'] }}</span>
                        @endif
                    </dt>
                    @if ($b['kode'])
                        <dd>
                            <button type="button" class="block w-full text-left text-stone-900 hover:text-brand-800" x-on:click="buka = ! buka" :aria-expanded="buka" title="Tampilkan/sembunyikan uraian lengkap">
                                <span :class="buka ? '' : 'line-clamp-2'">{{ $b['uraian'] }}</span>
                            </button>
                        </dd>
                        @if ($b['detail'])
                            <dd class="mt-0.5 text-xs text-stone-500" x-show="buka" x-cloak>{{ $b['detail'] }}</dd>
                        @endif
                    @else
                        <dd class="text-stone-400 italic">belum dipilih</dd>
                    @endif
                </div>
            </div>
        @endforeach
    </dl>

    <button type="button" @class(['btn-ghost mt-3 justify-center text-xs', 'w-full' => ! $kolom])
            data-teks="{{ RingkasanKode::teks($baris) }}" data-testid="salin-ringkasan-kode"
            x-show="navigator.clipboard" x-on:click="navigator.clipboard.writeText($el.dataset.teks).then(() => { tersalin = true; setTimeout(() => tersalin = false, 1500) })">
        <x-ui.icon name="copy" class="size-3.5" />
        <span x-text="tersalin ? 'Tersalin' : 'Salin ringkasan'">Salin ringkasan</span>
    </button>
</div>
