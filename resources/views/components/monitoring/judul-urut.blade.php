@props(['kolom', 'label', 'urut', 'arah', 'aksi' => 'urutkan', 'rata' => 'left'])

{{--
    Judul kolom tabel Monitoring yang bisa diurutkan: klik memanggil $aksi('kolom') di komponen Livewire (kolom yang sama
    membalik arah). aria-sort & panah menunjukkan urutan aktif; panah kolom lain disembunyikan tanpa menggeser label.
--}}
@php
    $aktif = $urut === $kolom;
@endphp

<th scope="col" {{ $attributes->class(['px-3 py-2', 'text-left' => $rata === 'left', 'text-right' => $rata === 'right']) }}
    aria-sort="{{ $aktif ? ($arah === 'asc' ? 'ascending' : 'descending') : 'none' }}">
    <button type="button" wire:click="{{ $aksi }}('{{ $kolom }}')" @class(['inline-flex items-center gap-1 whitespace-nowrap hover:text-stone-800', 'flex-row-reverse' => $rata === 'right', 'text-stone-900' => $aktif])>
        {{ $label }}
        <x-ui.icon :name="$aktif && $arah === 'asc' ? 'arrow-up' : 'arrow-down'" class="size-3 {{ $aktif ? '' : 'opacity-0' }}" />
    </button>
</th>
