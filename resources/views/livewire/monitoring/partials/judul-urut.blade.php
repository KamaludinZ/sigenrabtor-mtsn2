{{-- Judul kolom rincian kegiatan yang bisa diurutkan (klik lagi untuk membalik arah). --}}
<th scope="col" @class(['px-3 py-2', 'text-left' => $judul['rata'] === 'left', 'text-right' => $judul['rata'] === 'right']) aria-sort="{{ $judul['aria'] }}">
    <button type="button" wire:click="urutkan('{{ $kolom }}')" @class(['inline-flex items-center gap-1 hover:text-stone-800', 'text-stone-900' => $judul['aktif']])>
        {{ $judul['label'] }}
        <x-ui.icon :name="$judul['ikon'] ?? 'arrow-down'" class="size-3 {{ $judul['aktif'] ? '' : 'opacity-0' }}" />
    </button>
</th>
