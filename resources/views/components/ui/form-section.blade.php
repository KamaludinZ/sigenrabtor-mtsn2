@props(['id', 'title', 'description' => null, 'icon' => null, 'number' => null])

{{-- Kartu satu bagian form; atribut data-section dipakai navigasi bagian (scrollspy). --}}
<section id="{{ $id }}" data-section="{{ $id }}" {{ $attributes->merge(['class' => 'rounded-2xl bg-white shadow-xs ring-1 ring-stone-200']) }}>
    <header class="flex items-start gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
        @if ($icon)
            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700 ring-1 ring-brand-100">
                <x-ui.icon :name="$icon" class="size-5" />
            </span>
        @endif
        <div class="min-w-0 flex-1">
            <h2 class="text-base font-semibold text-stone-900">
                @if ($number)<span class="text-stone-400">{{ $number }}.</span>@endif
                {{ $title }}
            </h2>
            @if ($description)
                <p class="mt-0.5 text-sm text-stone-500">{{ $description }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="shrink-0">{{ $actions }}</div>
        @endisset
    </header>
    <div class="px-5 py-5 sm:px-6">
        {{ $slot }}
    </div>
</section>
