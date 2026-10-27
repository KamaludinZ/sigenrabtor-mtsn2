@use('App\Support\Pengaturan\AturanPenyediaAi')

@php
    $lokal = $ai['penyedia']['ollama'] ?? [];
    $peran = AturanPenyediaAi::peranDari($ai, 'ollama');
    [$minWaktu, $maksWaktu] = AturanPenyediaAi::BATAS_WAKTU;
@endphp

{{--
    Konfigurasi penyedia lokal (Ollama di server sekolah): ringkasan tersimpan + formulir alamat server, model, batas waktu,
    dan peran (aktif/cadangan/tidak dipakai). Uji koneksi menyusul.
--}}
<section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-penyedia-lokal" data-testid="penyedia-lokal" wire:key="penyedia-lokal-{{ $lokalTerbuka ? 'ubah' : 'lihat' }}">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-100 px-5 py-4">
        <div class="flex min-w-0 items-start gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700 ring-1 ring-brand-100" aria-hidden="true"><x-ui.icon name="database" class="size-5" /></span>
            <div class="min-w-0">
                <h2 id="judul-penyedia-lokal" class="text-sm font-semibold text-stone-900">Server sekolah (Ollama)</h2>
                <p class="mt-0.5 text-xs text-stone-500">Model berjalan di server madrasah — gratis dan data tidak keluar dari madrasah.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span @class([
                    'rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                    'bg-brand-50 text-brand-800 ring-brand-200' => $peran !== 'tidak',
                    'bg-stone-100 text-stone-600 ring-stone-200' => $peran === 'tidak',
                ]) data-testid="peran-lokal">{{ AturanPenyediaAi::PERAN[$peran] }}</span>
            @unless ($lokalTerbuka)
                <button type="button" wire:click="bukaLokal" class="btn-secondary px-2.5 py-1.5 text-xs" data-testid="atur-lokal"><x-ui.icon name="pencil" class="size-3.5" /> Atur</button>
            @endunless
        </div>
    </header>

    @if ($lokalTerbuka)
        <form wire:submit="simpanLokal" class="space-y-4 px-5 py-4" data-testid="formulir-lokal" x-data="formAi('isianLokal', 'lokal')" x-on:keydown.escape="$wire.batalLokal()">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field class="sm:col-span-2" label="Alamat server" for="lokal-base-url" error="isianLokal.base_url" required
                            hint="Alamat & port Ollama di jaringan madrasah, mis. http://192.168.1.10:11434 (bawaan Ollama: port 11434).">
                    <input type="url" id="lokal-base-url" wire:model="isianLokal.base_url" class="form-input font-mono text-sm" autocomplete="off" placeholder="http://localhost:11434"
                           @error('isianLokal.base_url') aria-invalid="true" aria-describedby="lokal-base-url-error" @enderror>
                </x-ui.field>
                <x-ui.field label="Model" for="lokal-model" error="isianLokal.model" hint="Nama model yang sudah diunduh di server (ollama pull …).">
                    <input type="text" id="lokal-model" wire:model="isianLokal.model" list="contoh-model-lokal" maxlength="100" class="form-input font-mono text-sm" autocomplete="off" placeholder="qwen2.5:7b"
                           @error('isianLokal.model') aria-invalid="true" aria-describedby="lokal-model-error" @enderror>
                    <datalist id="contoh-model-lokal">
                        @foreach (AturanPenyediaAi::CONTOH_MODEL_LOKAL as $model)
                            <option value="{{ $model }}"></option>
                        @endforeach
                    </datalist>
                </x-ui.field>
                <x-ui.field label="Batas waktu (detik)" for="lokal-batas-waktu" error="isianLokal.batas_waktu" hint="Server tanpa GPU butuh waktu lebih lama; {{ $minWaktu }}–{{ $maksWaktu }} detik.">
                    <input type="number" id="lokal-batas-waktu" wire:model="isianLokal.batas_waktu" min="{{ $minWaktu }}" max="{{ $maksWaktu }}" step="1" class="form-input tabular-nums"
                           @error('isianLokal.batas_waktu') aria-invalid="true" aria-describedby="lokal-batas-waktu-error" @enderror>
                </x-ui.field>
            </div>

            <fieldset>
                <legend class="text-sm font-medium text-stone-800">Pakai sebagai</legend>
                <div class="mt-1.5 grid gap-2 sm:grid-cols-3" role="radiogroup">
                    @foreach (AturanPenyediaAi::PERAN as $kunci => $label)
                        <label class="flex cursor-pointer items-start gap-2 rounded-lg px-3 py-2 text-sm ring-1 ring-stone-200 has-checked:bg-brand-50 has-checked:ring-2 has-checked:ring-brand-600">
                            <input type="radio" name="peran-lokal" value="{{ $kunci }}" wire:model="isianLokal.peran" class="mt-0.5 size-4 border-stone-300 text-brand-700 focus:ring-brand-600">
                            <span>
                                <span class="block font-medium text-stone-900">{{ $label }}</span>
                                <span class="block text-xs text-stone-500">{{ ['aktif' => 'Dipakai untuk setiap permintaan.', 'cadangan' => 'Dipakai bila penyedia aktif gagal.', 'tidak' => 'Konfigurasi disimpan, tidak dipakai.'][$kunci] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('isianLokal.peran') <p class="form-error" role="alert">{{ $message }}</p> @enderror
            </fieldset>

            <div class="flex flex-col-reverse gap-2 border-t border-stone-100 pt-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <button type="button" class="btn-ghost text-xs" disabled title="Sedang disiapkan">Uji koneksi <x-ui.segera /></button>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
                    @include('livewire.pengaturan.partials.ai-status-simpan', ['bagian' => 'lokal'])
                    <button type="button" wire:click="batalLokal" class="btn-ghost">Batal</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="simpanLokal" data-testid="simpan-lokal">
                        <span wire:loading.remove wire:target="simpanLokal" class="inline-flex items-center gap-1.5"><x-ui.icon name="save" class="size-4" /> Simpan</span>
                        <span wire:loading.flex wire:target="simpanLokal" class="items-center gap-1.5"><x-ui.icon name="loader" class="size-4 animate-spin" /> Menyimpan…</span>
                    </button>
                </div>
            </div>
        </form>
    @else
        <dl class="grid gap-3 px-5 py-4 text-sm sm:grid-cols-3" data-testid="ringkasan-lokal">
            <div><dt class="text-xs text-stone-500">Alamat server</dt><dd class="mt-0.5 truncate font-mono text-xs text-stone-800">{{ $lokal['base_url'] ?: '—' }}</dd></div>
            <div><dt class="text-xs text-stone-500">Model</dt><dd class="mt-0.5 font-mono text-xs text-stone-800">{{ ($lokal['model'] ?? '') ?: 'Belum diisi' }}</dd></div>
            <div><dt class="text-xs text-stone-500">Batas waktu</dt><dd class="mt-0.5 text-stone-800 tabular-nums">{{ $lokal['batas_waktu'] ?? 60 }} detik</dd></div>
        </dl>
    @endif
</section>
