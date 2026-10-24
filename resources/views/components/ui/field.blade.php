@props([
    'label',
    'for',
    'error' => null,
    'hint' => null,
    'warning' => null,
    'required' => false,
])

{{-- Pembungkus satu isian: label, kontrol (slot), lalu pesan error/peringatan/petunjuk. --}}
<div {{ $attributes->class(['min-w-0']) }}>
    <div class="flex items-baseline justify-between gap-3">
        <label for="{{ $for }}" class="form-label">
            {{ $label }}
            @if ($required)
                <span class="text-red-600" aria-hidden="true">*</span>
            @endif
        </label>
        @isset($aside)
            <div class="mb-1.5 text-xs text-stone-500">{{ $aside }}</div>
        @endisset
    </div>

    {{ $slot }}

    @if ($error)
        @error($error)
            <p class="form-error" id="{{ $for }}-error" role="alert">{{ $message }}</p>
        @enderror
    @endif

    @if ($warning)
        <p class="mt-1.5 flex items-start gap-1.5 text-xs font-medium text-gold-700">
            <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" />
            {{ $warning }}
        </p>
    @endif

    @if ($hint)
        <p class="form-hint">{{ $hint }}</p>
    @endif
</div>
