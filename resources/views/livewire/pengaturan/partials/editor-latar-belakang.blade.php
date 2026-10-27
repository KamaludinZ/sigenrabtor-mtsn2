@use('App\Support\Pengaturan\LatarBelakangBaku')

{{--
    Editor latar belakang baku: teks mentah berpenanda; pratinjau & peringatan penanda dihitung di klien saat mengetik
    (Alpine editorTeksBaku, kembaran TeksBaku). Server merapikan & memeriksa saat Simpan (LatarBelakangBaku).
--}}
<form wire:submit="simpanLatar" class="space-y-5 px-5 py-4 sm:px-6" data-testid="editor-latar"
      x-data="editorTeksBaku(@js(['tahun' => $ta, 'contoh' => $contoh]))">
    <x-ui.field label="Paragraf baku" for="latar-paragraf" error="isianLatar.latar_belakang" required>
        <x-slot:aside><span class="tabular-nums" x-text="`${($wire.isianLatar.latar_belakang ?? '').length} / {{ LatarBelakangBaku::MAKS_PARAGRAF }}`"></span></x-slot:aside>
        <textarea id="latar-paragraf" x-ref="paragraf" wire:model="isianLatar.latar_belakang" rows="7" maxlength="{{ LatarBelakangBaku::MAKS_PARAGRAF }}"
                  class="form-input text-sm leading-relaxed" x-init="$el.focus()"
                  @error('isianLatar.latar_belakang') aria-invalid="true" aria-describedby="latar-paragraf-error" @enderror></textarea>
        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1">
            <button type="button" x-on:click="sisip('paragraf', '{tahun}')" class="btn-ghost px-2 py-1 text-xs">
                <x-ui.icon name="plus" class="size-3.5" /> Sisipkan <code class="font-mono text-[11px]">{tahun}</code>
            </button>
            <span class="text-xs text-stone-500">Satu paragraf; baris baru dirapatkan saat disimpan.</span>
        </div>
        <p class="mt-1 text-xs font-medium text-gold-700" x-show="asing($wire.isianLatar.latar_belakang, ['{tahun}']).length" x-cloak
           x-text="`Penanda tidak dikenal: ${asing($wire.isianLatar.latar_belakang, ['{tahun}']).join(', ')} — hanya {tahun} yang bisa dipakai di sini.`"></p>
    </x-ui.field>

    <x-ui.field label="Kalimat penutup tiap kegiatan" for="latar-penutup" error="isianLatar.kalimat_penutup" required>
        <x-slot:aside><span class="tabular-nums" x-text="`${($wire.isianLatar.kalimat_penutup ?? '').length} / {{ LatarBelakangBaku::MAKS_PENUTUP }}`"></span></x-slot:aside>
        <textarea id="latar-penutup" x-ref="penutup" wire:model="isianLatar.kalimat_penutup" rows="3" maxlength="{{ LatarBelakangBaku::MAKS_PENUTUP }}"
                  class="form-input text-sm leading-relaxed"
                  @error('isianLatar.kalimat_penutup') aria-invalid="true" aria-describedby="latar-penutup-error" @enderror></textarea>
        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1">
            <button type="button" x-on:click="sisip('penutup', '{nama_kegiatan}')" class="btn-ghost px-2 py-1 text-xs">
                <x-ui.icon name="plus" class="size-3.5" /> Sisipkan <code class="font-mono text-[11px]">{nama_kegiatan}</code>
            </button>
            <button type="button" x-on:click="sisip('penutup', '{tahun}')" class="btn-ghost px-2 py-1 text-xs">
                <x-ui.icon name="plus" class="size-3.5" /> Sisipkan <code class="font-mono text-[11px]">{tahun}</code>
            </button>
            <span class="text-xs text-stone-500">Wajib memuat <code class="font-mono text-[11px]">{nama_kegiatan}</code> sekali — nama kegiatan tercetak di situ.</span>
        </div>
        <p class="mt-1 text-xs font-medium text-gold-700" x-show="asing($wire.isianLatar.kalimat_penutup, ['{tahun}', '{nama_kegiatan}']).length" x-cloak
           x-text="`Penanda tidak dikenal: ${asing($wire.isianLatar.kalimat_penutup, ['{tahun}', '{nama_kegiatan}']).join(', ')}`"></p>
    </x-ui.field>

    <div class="rounded-xl bg-stone-50 p-4 ring-1 ring-stone-200" data-testid="pratinjau-latar" aria-live="polite">
        <p class="text-xs font-semibold text-stone-500">Pratinjau saat mengetik · TA {{ $ta }} · contoh: {{ $contoh }}</p>
        <div class="mt-2 space-y-1.5 font-serif text-[13px] leading-relaxed text-stone-800">
            <p class="text-justify indent-6" data-pratinjau="paragraf"><template x-for="(b, i) in bagian($wire.isianLatar.latar_belakang)" :key="i"><span :class="{ 'rounded bg-brand-100 px-0.5 text-brand-900': b.penanda }" x-text="b.teks"></span></template></p>
            <p class="text-justify indent-6 text-stone-400 italic">[Paragraf khusus kegiatan, bila diisi leading]</p>
            <p class="text-justify indent-6" data-pratinjau="penutup"><template x-for="(b, i) in bagian($wire.isianLatar.kalimat_penutup, true)" :key="i"><span :class="{ 'rounded bg-brand-100 px-0.5 text-brand-900': b.penanda }" x-text="b.teks"></span></template></p>
        </div>
    </div>

    <div class="flex flex-col-reverse gap-2 border-t border-stone-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
        <button type="button" wire:click="pakaiBawaanLatar" class="btn-ghost self-start" title="Isi ulang dengan teks bawaan; tersimpan setelah Simpan">
            <x-ui.icon name="history" class="size-4" /> Pakai teks bawaan
        </button>
        <div class="flex justify-end gap-2">
            <button type="button" wire:click="batalLatar" class="btn-ghost">Batal</button>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="simpanLatar"><x-ui.icon name="save" class="size-4" /> Simpan</button>
        </div>
    </div>
</form>
