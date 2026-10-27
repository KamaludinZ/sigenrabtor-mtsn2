@use('App\Support\Pengaturan\LatarBelakangBaku')
@use('App\Support\Pengaturan\TeksBaku')

{{--
    Editor latar belakang baku: teks mentah berpenanda; pratinjau & peringatan penanda dihitung di klien saat mengetik
    (Alpine editorTeksBaku, kembaran TeksBaku). Server merapikan & memeriksa saat Simpan (LatarBelakangBaku).
--}}
<form wire:submit="simpanLatar" class="space-y-5 px-5 py-4 sm:px-6" data-testid="editor-latar"
      x-data="editorTeksBaku(@js(['tahun' => $ta, 'nilai' => $nilaiContoh]))">
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
        {{-- Tempat sisip variabel kegiatan: letakkan kursor, lalu pilih variabelnya; nilainya diambil dari tiap kegiatan. --}}
        <div class="mt-2 rounded-lg bg-stone-50 p-3 ring-1 ring-stone-200" role="group" aria-labelledby="judul-variabel-kegiatan" data-testid="pilih-variabel-kegiatan">
            <p id="judul-variabel-kegiatan" class="text-xs font-semibold text-stone-600">Sisipkan variabel kegiatan di posisi kursor</p>
            <ul class="mt-2 flex flex-wrap gap-1.5">
                @foreach (TeksBaku::VARIABEL_KEGIATAN as $penanda => $label)
                    <li>
                        <button type="button" x-on:click="sisip('penutup', @js($penanda))" data-variabel="{{ $penanda }}"
                                title="Contoh: {{ $nilaiContoh[$penanda] ?? 'belum diisi di contoh kegiatan' }}"
                                @class(['inline-flex items-center gap-1.5 rounded-md bg-white px-2 py-1 text-xs text-stone-700 ring-1 ring-stone-300 hover:bg-brand-50 hover:text-brand-800 hover:ring-brand-300',
                                    'font-semibold' => $penanda === TeksBaku::NAMA_KEGIATAN, 'font-medium' => $penanda !== TeksBaku::NAMA_KEGIATAN])>
                            <x-ui.icon name="plus" class="size-3.5" /> {{ $label }}
                            <span x-show="memuat($wire.isianLatar.kalimat_penutup, '{{ $penanda }}')" x-cloak class="text-brand-700">
                                <x-ui.icon name="check" class="size-3.5" /><span class="sr-only">(sudah dipakai)</span>
                            </span>
                        </button>
                    </li>
                @endforeach
                <li>
                    <button type="button" x-on:click="sisip('penutup', '{tahun}')" class="inline-flex items-center gap-1.5 rounded-md bg-white px-2 py-1 text-xs font-medium text-stone-700 ring-1 ring-stone-300 hover:bg-brand-50 hover:text-brand-800">
                        <x-ui.icon name="plus" class="size-3.5" /> Tahun anggaran
                    </button>
                </li>
            </ul>
            <p class="mt-2 text-xs text-stone-500">Nama kegiatan wajib ada tepat sekali. Variabel lain diisi dari Data Kegiatan — bila belum diisi, KAK/TOR menandainya untuk dilengkapi.</p>
        </div>
        <p class="mt-1 text-xs font-medium text-gold-700" x-show="asing($wire.isianLatar.kalimat_penutup, @js(TeksBaku::bolehPenutup())).length" x-cloak
           x-text="`Penanda tidak dikenal: ${asing($wire.isianLatar.kalimat_penutup, @js(TeksBaku::bolehPenutup())).join(', ')}`"></p>
    </x-ui.field>

    <div class="rounded-xl bg-stone-50 p-4 ring-1 ring-stone-200" data-testid="pratinjau-latar" aria-live="polite">
        <p class="text-xs font-semibold text-stone-500">Pratinjau saat mengetik · TA {{ $ta }} · contoh: {{ $contoh }}</p>
        <div class="mt-2 space-y-1.5 font-serif text-[13px] leading-relaxed text-stone-800">
            <p class="text-justify indent-6" data-pratinjau="paragraf"><template x-for="(b, i) in bagian($wire.isianLatar.latar_belakang)" :key="i"><span :class="{ 'rounded bg-brand-100 px-0.5 text-brand-900': b.penanda }" x-text="b.teks"></span></template></p>
            <p class="text-justify indent-6 text-stone-400 italic">[Paragraf khusus kegiatan, bila diisi leading]</p>
            <p class="text-justify indent-6" data-pratinjau="penutup"><template x-for="(b, i) in bagian($wire.isianLatar.kalimat_penutup, true)" :key="i"><span :class="{ 'rounded bg-brand-100 px-0.5 text-brand-900': b.penanda && ! b.kosong, 'rounded bg-gold-100 px-0.5 text-gold-800': b.kosong }" x-text="b.teks"></span></template></p>
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
