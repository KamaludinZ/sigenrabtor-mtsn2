@props(['bagian', 'keadaan', 'href' => null])

{{--
    Tombol "Buat dengan AI" satu bagian (keadaan dari App\Support\Kegiatan\TombolAi::keadaan): siap/gagal memanggil
    mintaSaran('{bagian}') dengan penanda memuat per bagian (tombol AI lain ikut nonaktif selama menunggu), ada saran
    membuka/menutup panel saran, nonaktif menjelaskan alasannya, tautan menuju tempat lain ($href).
--}}
@php
    $kelas = 'btn-secondary px-2.5 py-1.5 text-xs';
    $target = "mintaSaran('{$bagian}')";
@endphp
<span class="inline-flex flex-col items-end gap-0.5" data-tombol-ai="{{ $bagian }}" data-keadaan="{{ $keadaan['status'] }}">
    @switch($keadaan['status'])
        @case('tautan')
            <a href="{{ $href }}" wire:navigate class="{{ $kelas }}" title="{{ $keadaan['keterangan'] }}" data-testid="buat-ai-{{ $bagian }}">
                <x-ui.icon name="sparkles" class="size-3.5 text-brand-600" /> {{ $keadaan['label'] }}
            </a>
            @break

        @case('nonaktif')
            <button type="button" class="{{ $kelas }}" disabled aria-describedby="alasan-ai-{{ $bagian }}" wire:key="ai-{{ $bagian }}-nonaktif" data-testid="buat-ai-{{ $bagian }}">
                <x-ui.icon name="sparkles" class="size-3.5" /> {{ $keadaan['label'] }}
            </button>
            <span id="alasan-ai-{{ $bagian }}" class="max-w-56 text-right text-[11px] leading-snug text-stone-500">{{ $keadaan['keterangan'] }}</span>
            @break

        @case('ada')
            <button type="button" class="{{ $kelas }}" wire:click="bukaSaran('{{ $bagian }}')" wire:key="ai-{{ $bagian }}-ada" data-testid="buat-ai-{{ $bagian }}">
                <x-ui.icon name="sparkles" class="size-3.5 text-brand-600" /> {{ $keadaan['label'] }}
            </button>
            @break

        @default
            <button type="button" wire:click="{{ $target }}" wire:loading.attr="disabled" wire:target="mintaSaran" wire:key="ai-{{ $bagian }}-{{ $keadaan['status'] }}"
                    @class([$kelas, 'text-red-700 ring-red-200' => $keadaan['status'] === 'gagal']) title="{{ $keadaan['keterangan'] }}" data-testid="buat-ai-{{ $bagian }}">
                <span class="inline-flex items-center gap-1.5" wire:loading.remove wire:target="{{ $target }}">
                    <x-ui.icon name="sparkles" class="size-3.5 text-brand-600" /> {{ $keadaan['label'] }}
                </span>
                <span class="inline-flex items-center gap-1.5" wire:loading.flex wire:target="{{ $target }}" role="status">
                    <x-ui.icon name="loader" class="size-3.5 animate-spin" /> Menyusun saran…
                </span>
            </button>
    @endswitch
</span>
