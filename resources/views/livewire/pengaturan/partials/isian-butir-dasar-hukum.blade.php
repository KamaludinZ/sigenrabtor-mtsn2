{{-- Isian satu butir dasar hukum (tambah/ubah): Enter menyimpan, Esc membatalkan. --}}
<form wire:submit="simpanButir" class="min-w-0 flex-1 sm:col-span-2" data-testid="isian-butir" x-data="editorTeksBaku({ tahun: {{ $ta }}, nilai: {} })">
    <label for="teks-butir" class="sr-only">{{ $label }}</label>
    <textarea id="teks-butir" x-ref="butir" wire:model="teksButir" rows="2" maxlength="{{ \App\Support\Pengaturan\DaftarDasarHukum::MAKS_KARAKTER }}" class="form-input text-sm"
              placeholder="mis. Peraturan Menteri Agama Nomor … Tahun … tentang …" autocomplete="off"
              x-init="$el.focus()" x-on:keydown.enter.prevent="$wire.simpanButir()" x-on:keydown.escape.prevent="$wire.batalButir()"
              @error('teksButir') aria-invalid="true" aria-describedby="teks-butir-galat" @else aria-describedby="teks-butir-petunjuk" @enderror></textarea>
    @error('teksButir')
        <p id="teks-butir-galat" class="mt-1 text-xs text-red-700" role="alert">{{ $message }}</p>
    @else
        <p id="teks-butir-petunjuk" class="mt-1 text-xs text-stone-500">Penanda terisi sendiri saat dicetak; tanda baca akhir diatur otomatis. Enter menyimpan, Esc membatalkan.</p>
    @enderror
    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap gap-1" role="group" aria-label="Sisipkan penanda">
            @foreach (['{tahun}' => 'Tahun', '{no_sk_rktm}' => 'Nomor SK', '{tanggal_sk_rktm}' => 'Tanggal SK'] as $penanda => $label)
                <button type="button" x-on:click="sisip('butir', '{{ $penanda }}')" class="btn-ghost px-2 py-1 text-xs" title="Sisipkan {{ $penanda }}"><x-ui.icon name="plus" class="size-3.5" /> {{ $label }}</button>
            @endforeach
        </div>
        <div class="flex gap-2">
            <button type="button" wire:click="batalButir" class="btn-ghost px-2.5 py-1 text-xs">Batal</button>
            <button type="submit" class="btn-primary px-2.5 py-1 text-xs" wire:loading.attr="disabled" wire:target="simpanButir"><x-ui.icon name="save" class="size-3.5" /> <span wire:loading.remove wire:target="simpanButir">Simpan</span><span wire:loading wire:target="simpanButir">Menyimpan…</span></button>
        </div>
    </div>
</form>
