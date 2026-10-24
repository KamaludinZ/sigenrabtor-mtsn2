@props(['status' => 'draf'])

@php
    $styles = [
        'draf' => ['Draf', 'bg-stone-100 text-stone-700 ring-stone-300'],
        'diperiksa' => ['Diperiksa', 'bg-sky-50 text-sky-800 ring-sky-200'],
        'dikembalikan' => ['Dikembalikan', 'bg-amber-50 text-amber-800 ring-amber-200'],
        'disetujui' => ['Disetujui', 'bg-brand-50 text-brand-800 ring-brand-200'],
        'final' => ['Final', 'bg-brand-800 text-white ring-brand-800'],
    ];
    [$label, $classes] = $styles[$status] ?? $styles['draf'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {$classes}"]) }}>
    @if ($status === 'final')
        <x-ui.icon name="lock" class="size-3" />
    @endif
    {{ $label }}
</span>
