{{-- Isian satu butir dasar hukum (tambah/ubah): Enter menyimpan, Esc membatalkan. --}}
<form wire:submit="simpanButir" class="min-w-0 flex-1" data-testid="isian-butir">
    <label for="teks-butir" class="sr-only">{{ $label }}</label>
    <textarea id="teks-butir" wire:model="teksButir" rows="2" maxlength="{{ \App\Support\Pengaturan\DaftarDasarHukum::MAKS_KARAKTER }}" class="form-input text-sm"
              placeholder="mis. Peraturan Menteri Agama Nomor … Tahun … tentang …" autocomplete="off"
              x-init="$el.focus()" x-on:keydown.enter.prevent="$wire.simpanButir()" x-on:keydown.escape.prevent="$wire.batalButir()"
              @error('teksButir') aria-invalid="true" aria-describedby="teks-butir-galat" @else aria-describedby="teks-butir-petunjuk" @enderror></textarea>
    @error('teksButir')
        <p id="teks-butir-galat" class="mt-1 text-xs text-red-700" role="alert">{{ $message }}</p>
    @else
        <p id="teks-butir-petunjuk" class="mt-1 text-xs text-stone-500">Tulis <code class="rounded bg-stone-100 px-1 font-mono text-[11px]">{tahun}</code> untuk tahun anggaran. Tanda baca akhir diatur otomatis. Enter menyimpan, Esc membatalkan.</p>
    @enderror
    <div class="mt-2 flex justify-end gap-2">
        <button type="button" wire:click="batalButir" class="btn-ghost px-2.5 py-1 text-xs">Batal</button>
        <button type="submit" class="btn-primary px-2.5 py-1 text-xs" wire:loading.attr="disabled" wire:target="simpanButir"><x-ui.icon name="save" class="size-3.5" /> Simpan</button>
    </div>
</form>
