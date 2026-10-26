@use('App\Livewire\Pengaturan\TemplateDokumen')

<div class="mx-auto max-w-7xl">
    <x-pengaturan.kepala judul="Template Dokumen"
                         deskripsi="Tampilan cover, isi KAK/TOR, daftar isi buku, dan nomor halaman yang dipakai semua kegiatan tahun ini. Selama belum diatur, dokumen memakai susunan baku aplikasi.">
        <x-slot:tab>
            <nav class="-mb-px flex gap-1 overflow-x-auto border-b border-stone-200" aria-label="Jenis template">
                @foreach (TemplateDokumen::TAB as $kunci => $label)
                    @if ($kunci === $tab)
                        <span aria-current="page" class="flex shrink-0 items-center gap-2 border-b-2 border-brand-700 px-3 py-2.5 text-sm font-semibold text-brand-800">{{ $label }}</span>
                    @elseif (in_array($kunci, TemplateDokumen::TAB_TERSEDIA, true))
                        <button type="button" wire:click="$set('tab', '{{ $kunci }}')" class="flex shrink-0 items-center gap-2 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-stone-500 hover:border-stone-300 hover:text-stone-800">{{ $label }}</button>
                    @else
                        <span class="flex shrink-0 cursor-not-allowed items-center gap-2 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-stone-400" title="Sedang disiapkan">
                            {{ $label }} <span class="rounded bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-stone-500 uppercase">Segera</span>
                        </span>
                    @endif
                @endforeach
            </nav>
        </x-slot:tab>
    </x-pengaturan.kepala>

    @if ($tab === 'kak')
        @include('livewire.pengaturan.partials.template-kak')
    @elseif ($tab === 'daftar_isi')
        <livewire:pengaturan.template-daftar-isi />
    @elseif ($tab === 'nomor_halaman')
        <livewire:pengaturan.template-nomor-halaman />
    @else
        @include('livewire.pengaturan.partials.template-cover')
    @endif
</div>
