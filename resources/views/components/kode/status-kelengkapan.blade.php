@props(['status'])

@php
    // Notifikasi kelengkapan kode: hijau bila lengkap, merah bila kode SK (wajib untuk pengajuan) kosong, emas bila sebagian.
    [$ikon, $warna, $warnaIkon] = [
        'lengkap' => ['check-circle', 'bg-brand-50 text-brand-900 ring-brand-200', 'text-brand-600'],
        'wajib' => ['alert', 'bg-red-50 text-red-900 ring-red-200', 'text-red-600'],
        'sebagian' => ['info', 'bg-gold-50 text-stone-800 ring-gold-200', 'text-gold-600'],
    ][$status['status']];
@endphp

<div {{ $attributes->class(['flex items-start gap-3 rounded-xl px-4 py-3 text-sm ring-1', $warna]) }}
     role="status" aria-live="polite" data-testid="status-kelengkapan-kode" data-status="{{ $status['status'] }}">
    <x-ui.icon :name="$ikon" @class(['mt-0.5 size-4 shrink-0', $warnaIkon]) />
    <div class="min-w-0 flex-1">
        <p class="font-medium">{{ $status['pesan'] }}</p>
        @if ($status['kosong'])
            <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs">
                <span class="opacity-80">Belum dipilih:</span>
                @foreach ($status['kosong'] as $kosong)
                    <a href="#{{ $kosong['kartu'] }}" data-kode-kosong="{{ $kosong['kunci'] }}"
                       class="rounded-full bg-white px-2 py-0.5 font-medium ring-1 ring-stone-300/70 ring-inset hover:bg-stone-50">{{ $kosong['label'] }}</a>
                @endforeach
            </div>
        @endif
    </div>
    <span class="mt-0.5 shrink-0 text-xs font-semibold tabular-nums" data-testid="status-kelengkapan-jumlah">{{ $status['terisi'] }}/{{ $status['total'] }}</span>
</div>
