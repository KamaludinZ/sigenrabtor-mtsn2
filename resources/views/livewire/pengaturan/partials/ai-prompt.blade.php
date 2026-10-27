@use('App\Support\Pengaturan\PromptAi')
@use('App\Support\Kegiatan\BagianAi')

@php
    [$minInstruksi, $maksInstruksi] = PromptAi::PANJANG_INSTRUKSI;
@endphp

{{--
    Tab Prompt per bagian: gaya bahasa baku (bisa diubah), batas aman (selalu disertakan), dan template tiap bagian —
    instruksi dengan penanda {nama_kegiatan}/{tahun}, gaya bahasa khusus opsional, status aktif (nonaktif = tombol
    "Buat dengan AI" bagian itu mati), serta pratinjau prompt lengkap untuk contoh kegiatan.
--}}
<div class="mt-6 space-y-6" data-testid="panel-prompt-ai">
    <section class="grid gap-4 rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200 md:grid-cols-2" aria-label="Aturan umum prompt">
        <div data-testid="gaya-bahasa-baku">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-stone-900">Gaya bahasa baku</h2>
                @unless ($gayaTerbuka)
                    <button type="button" wire:click="ubahGaya" class="btn-ghost px-2 py-1 text-xs" data-testid="ubah-gaya"><x-ui.icon name="pencil" class="size-3.5" /> Ubah</button>
                @endunless
            </div>
            @if ($gayaTerbuka)
                <form wire:submit="simpanGaya" class="mt-2 space-y-2" x-data="formAi('isianGaya', 'gaya')" x-on:keydown.escape="$wire.batalGaya()">
                    <textarea wire:model="isianGaya" rows="3" maxlength="{{ PromptAi::MAKS_GAYA }}" class="form-input text-sm" aria-label="Gaya bahasa baku" data-testid="isian-gaya"></textarea>
                    @error('isianGaya') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <button type="button" wire:click="pakaiGayaBawaan" class="btn-ghost px-2 py-1 text-xs">Pakai bawaan</button>
                        <div class="flex flex-wrap items-center gap-2">
                            @include('livewire.pengaturan.partials.ai-status-simpan', ['bagian' => 'gaya'])
                            <button type="button" wire:click="batalGaya" class="btn-ghost px-2.5 py-1.5 text-xs">Batal</button>
                            <button type="submit" class="btn-primary px-2.5 py-1.5 text-xs" data-testid="simpan-gaya">Simpan</button>
                        </div>
                    </div>
                </form>
            @else
                <p class="mt-1 text-sm text-stone-700" data-testid="gaya-bahasa-ai">{{ PromptAi::gayaBahasa() }}</p>
            @endif
        </div>
        <div>
            <h2 class="text-sm font-semibold text-stone-900">Batas aman (selalu disertakan)</h2>
            <p class="mt-1 text-sm text-stone-700">{{ PromptAi::BATAS_AMAN }}</p>
        </div>
    </section>

    <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-prompt-bagian">
        <header class="border-b border-stone-100 px-5 py-4">
            <h2 id="judul-prompt-bagian" class="text-sm font-semibold text-stone-900">Instruksi per bagian</h2>
            <p class="mt-0.5 text-xs text-stone-500">Disusun bersama data kegiatan dan catatan konteks saat leading menekan “Buat dengan AI”.</p>
        </header>
        <ol class="divide-y divide-stone-100">
            @foreach ($this->prompt() as $p)
                @php $terbuka = $promptTerbuka === $p['bagian']; @endphp
                <li class="px-5 py-3" data-prompt="{{ $p['bagian'] }}" wire:key="prompt-{{ $p['bagian'] }}-{{ $terbuka ? 'ubah' : 'lihat' }}">
                    <div class="grid gap-1 sm:grid-cols-[12rem_minmax(0,1fr)_auto] sm:items-start">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-medium text-stone-900">{{ $p['judul'] }}</span>
                            <span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold', 'bg-stone-100 text-stone-600' => $p['bawaan'], 'bg-gold-50 text-gold-700' => ! $p['bawaan']]) data-testid="status-prompt-{{ $p['bagian'] }}">{{ $p['bawaan'] ? 'Bawaan' : 'Diubah' }}</span>
                            @unless ($p['aktif'])
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700" data-testid="nonaktif-{{ $p['bagian'] }}">Nonaktif</span>
                            @endunless
                        </div>
                        <div class="min-w-0 text-sm text-stone-700">
                            <p>{{ $p['instruksi'] }}</p>
                            @if ($p['gaya_bahasa'])
                                <p class="mt-0.5 text-xs text-stone-500">Gaya khusus: {{ $p['gaya_bahasa'] }}</p>
                            @endif
                        </div>
                        @unless ($terbuka)
                            <button type="button" wire:click="ubahPrompt('{{ $p['bagian'] }}')" class="btn-ghost justify-self-start px-2 py-1 text-xs sm:justify-self-end" data-testid="ubah-prompt-{{ $p['bagian'] }}"><x-ui.icon name="pencil" class="size-3.5" /> Ubah</button>
                        @endunless
                    </div>

                    @if ($terbuka)
                        <form wire:submit="simpanPromptBagian" class="mt-3 space-y-3 rounded-xl bg-stone-50/70 p-4 ring-1 ring-stone-200" data-testid="formulir-prompt" x-data="formAi('isianPrompt', 'prompt')" x-on:keydown.escape="$wire.batalPrompt()">
                            <div>
                                <label for="isian-instruksi" class="text-sm font-medium text-stone-800">Instruksi untuk AI</label>
                                <textarea id="isian-instruksi" wire:model.live.debounce.500ms="isianPrompt.instruksi" rows="4" maxlength="{{ $maksInstruksi }}" class="form-input mt-1.5 text-sm" x-ref="instruksi"
                                          @error('isianPrompt.instruksi') aria-invalid="true" @enderror></textarea>
                                <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs">
                                    <span class="text-stone-500">Sisipkan:</span>
                                    @foreach (PromptAi::PENANDA as $penanda => $ket)
                                        <button type="button" title="{{ $ket }}" class="rounded-full bg-white px-2 py-0.5 font-mono text-stone-700 ring-1 ring-stone-200 hover:bg-stone-100"
                                                x-on:click="const t = $refs.instruksi, a = t.selectionStart, b = t.selectionEnd; t.setRangeText(@js($penanda), a, b, 'end'); t.dispatchEvent(new Event('input')); t.focus()">{{ $penanda }}</button>
                                    @endforeach
                                    <span class="ml-auto text-stone-400">{{ $minInstruksi }}–{{ $maksInstruksi }} karakter</span>
                                </div>
                                @error('isianPrompt.instruksi') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                            </div>

                            <label class="flex items-center gap-2 text-sm text-stone-700">
                                <input type="checkbox" wire:model.live="isianPrompt.gaya_khusus" class="size-4 rounded border-stone-300 text-brand-700 focus:ring-brand-600"> Pakai gaya bahasa khusus untuk bagian ini
                            </label>
                            @if ($isianPrompt['gaya_khusus'])
                                <div>
                                    <textarea wire:model.live.debounce.500ms="isianPrompt.gaya_bahasa" rows="2" maxlength="{{ PromptAi::MAKS_GAYA }}" class="form-input text-sm" aria-label="Gaya bahasa khusus" placeholder="{{ PromptAi::gayaBahasa() }}"></textarea>
                                    @error('isianPrompt.gaya_bahasa') <p class="form-error" role="alert">{{ $message }}</p> @enderror
                                </div>
                            @endif

                            <label class="flex items-center gap-2 text-sm text-stone-700">
                                <input type="checkbox" wire:model="isianPrompt.aktif" class="size-4 rounded border-stone-300 text-brand-700 focus:ring-brand-600" data-testid="aktif-prompt"> Aktif — tombol “Buat dengan AI” bagian ini bisa dipakai
                            </label>

                            <details class="rounded-lg bg-white ring-1 ring-stone-200" data-testid="pratinjau-prompt">
                                <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-stone-600 hover:text-stone-900">Pratinjau prompt untuk contoh kegiatan</summary>
                                <pre class="overflow-x-auto border-t border-stone-200 p-3 font-mono text-[11px] leading-relaxed whitespace-pre-wrap text-stone-700">{{ $this->pratinjauPrompt() }}</pre>
                            </details>

                            <div class="flex flex-col-reverse gap-2 border-t border-stone-200 pt-3 sm:flex-row sm:items-center sm:justify-between">
                                <button type="button" wire:click="pakaiPromptBawaan" class="btn-ghost text-xs">Kembalikan bawaan</button>
                                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                                    @include('livewire.pengaturan.partials.ai-status-simpan', ['bagian' => 'prompt'])
                                    <button type="button" wire:click="batalPrompt" class="btn-ghost">Batal</button>
                                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="simpanPromptBagian" data-testid="simpan-prompt"><x-ui.icon name="save" class="size-4" /> Simpan</button>
                                </div>
                            </div>
                        </form>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
</div>
