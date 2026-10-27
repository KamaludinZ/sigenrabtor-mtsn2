@props(['item' => null])

{{-- Penanda kode hilang di bawah satu isian kode (satu butir KodeHilang::dari): kode lama dicoret beserta sebabnya. --}}
@if ($item)
    <p {{ $attributes->class(['mt-1.5 flex items-start gap-1.5 text-xs text-gold-700']) }} data-kode-hilang-isian="{{ $item['kolom'] }}">
        <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" />
        <span>Sebelumnya <span class="font-mono line-through">{{ $item['kode'] }}</span> — {{ $item['sebab'] }}. Pilih kode pengganti.</span>
    </p>
@endif
