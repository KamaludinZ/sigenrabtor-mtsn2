{{--
    Tombol aksi satu catatan Riwayat Dokumen: Lihat (`bukaLihat`) & Duplikat (`bukaDuplikat`) — hanya di dalam komponen
    Livewire `Dokumen\Riwayat`. `ringkas` = hanya Lihat (versi lama).
--}}
@props(['id', 'ringkas' => false])

<div {{ $attributes->class(['flex items-center gap-1']) }}>
    <button type="button" wire:click="bukaLihat('{{ $id }}')" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-50 hover:text-brand-800" data-testid="lihat-dokumen" data-id="{{ $id }}">
        <x-ui.icon name="eye" class="size-3.5" /> Lihat
    </button>
    @unless ($ringkas)
        <button type="button" wire:click="bukaDuplikat('{{ $id }}')" class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold text-stone-600 hover:bg-stone-100 hover:text-stone-900" data-testid="duplikat-dokumen" data-id="{{ $id }}" title="Salin sebagai dasar kegiatan baru">
            <x-ui.icon name="copy" class="size-3.5" /> Duplikat
        </button>
    @endunless
</div>
