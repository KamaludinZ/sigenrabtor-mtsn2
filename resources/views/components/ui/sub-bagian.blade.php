@props(['huruf', 'judul', 'keterangan' => null, 'kelengkapan' => null])

@php
    $penanda = [
        'lengkap' => ['Lengkap', 'text-brand-700'],
        'sebagian' => ['Sebagian', 'text-gold-700'],
        'kosong' => ['Belum diisi', 'text-stone-500'],
        'opsional' => ['Opsional', 'text-stone-400'],
    ][$kelengkapan] ?? null;
@endphp

{{-- Judul kelompok isian di dalam satu kartu bagian (A, B, C …) beserta status kelengkapannya. --}}
<div {{ $attributes->class(['flex items-start justify-between gap-3']) }}>
    <div class="flex min-w-0 items-start gap-2.5">
        <span class="grid size-6 shrink-0 place-items-center rounded-md bg-stone-100 text-xs font-bold text-stone-600" aria-hidden="true">{{ $huruf }}</span>
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-stone-800">{{ $judul }}</h3>
            @if ($keterangan)
                <p class="text-xs text-stone-500">{{ $keterangan }}</p>
            @endif
        </div>
    </div>
    @if ($penanda)
        <span class="mt-0.5 inline-flex shrink-0 items-center gap-1 text-[11px] font-semibold {{ $penanda[1] }}" data-kelengkapan="{{ $kelengkapan }}">
            @if ($kelengkapan === 'lengkap')
                <x-ui.icon name="check" class="size-3.5" />
            @endif
            {{ $penanda[0] }}
        </span>
    @endif
</div>
