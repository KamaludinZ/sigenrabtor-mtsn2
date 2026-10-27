@use('App\Support\Pengaturan\AturanPenyediaAi')
@use('App\Support\Pengaturan\PenyediaAi')

@php
    $cloud = array_filter(PenyediaAi::DAFTAR, fn ($p) => $p['jenis'] === 'cloud');
@endphp

{{--
    Penyedia API cloud (Claude, OpenAI, Gemini): ringkasan tiap penyedia (model, kunci tersimpan — hanya 4 karakter
    terakhir, peran) dan formulir model, kunci API (terenkripsi, tidak pernah ditampilkan lagi; kosong = tetap), alamat
    API opsional, dan peran. Uji koneksi menyusul.
--}}
<section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-penyedia-cloud" data-testid="penyedia-cloud">
    <header class="flex items-start gap-3 border-b border-stone-100 px-5 py-4">
        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700 ring-1 ring-brand-100" aria-hidden="true"><x-ui.icon name="lock" class="size-5" /></span>
        <div class="min-w-0">
            <h2 id="judul-penyedia-cloud" class="text-sm font-semibold text-stone-900">API cloud</h2>
            <p class="mt-0.5 text-xs text-stone-500">Kunci API disimpan terenkripsi dan tidak pernah ditampilkan lagi — hanya 4 karakter terakhirnya untuk dikenali.</p>
        </div>
    </header>

    <ul class="divide-y divide-stone-100">
        @foreach ($cloud as $kunci => $p)
            @php
                $konfigurasi = $ai['penyedia'][$kunci] ?? [];
                $peran = AturanPenyediaAi::peranDari($ai, $kunci);
                $terbuka = $cloudTerbuka === $kunci;
                $adaKunci = (bool) ($konfigurasi['ada_kunci'] ?? false);
            @endphp
            <li class="px-5 py-4" data-cloud="{{ $kunci }}" wire:key="cloud-{{ $kunci }}-{{ $terbuka ? 'ubah' : 'lihat' }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-stone-900">{{ $p['nama'] }}</p>
                        <p class="mt-0.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-stone-500">
                            <span>Model: <span class="font-mono text-stone-700">{{ ($konfigurasi['model'] ?? '') ?: 'belum diisi' }}</span></span>
                            <span data-testid="status-kunci-{{ $kunci }}">Kunci: {!! $adaKunci ? '<span class="font-mono text-stone-700">•••• '.e($konfigurasi['kunci_akhir'] ?? '').'</span>' : 'belum ada' !!}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span @class([
                                'rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                                'bg-brand-50 text-brand-800 ring-brand-200' => $peran !== 'tidak',
                                'bg-stone-100 text-stone-600 ring-stone-200' => $peran === 'tidak',
                            ]) data-testid="peran-{{ $kunci }}">{{ AturanPenyediaAi::PERAN[$peran] }}</span>
                        @unless ($terbuka)
                            <button type="button" wire:click="bukaCloud('{{ $kunci }}')" class="btn-secondary px-2.5 py-1.5 text-xs" data-testid="atur-{{ $kunci }}"><x-ui.icon name="pencil" class="size-3.5" /> Atur</button>
                        @endunless
                    </div>
                </div>

                @if ($terbuka)
                    <form wire:submit="simpanCloud" class="mt-4 space-y-4 rounded-xl bg-stone-50/70 p-4 ring-1 ring-stone-200" data-testid="formulir-{{ $kunci }}" x-data x-on:keydown.escape="$wire.batalCloud()">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Model" for="cloud-model" error="isianCloud.model" :hint="'Nama model dari dasbor penyedia ('.$p['contoh_model'].').'">
                                <input type="text" id="cloud-model" wire:model="isianCloud.model" maxlength="100" class="form-input font-mono text-sm" autocomplete="off"
                                       @error('isianCloud.model') aria-invalid="true" aria-describedby="cloud-model-error" @enderror>
                            </x-ui.field>
                            <x-ui.field label="Kunci API" for="cloud-kunci" error="isianCloud.kunci"
                                        :hint="$adaKunci ? 'Tersimpan •••• '.($konfigurasi['kunci_akhir'] ?? '').' — kosongkan bila tidak diganti.' : 'Salin dari dasbor penyedia; disimpan terenkripsi.'">
                                <div class="relative" x-data="{ lihat: false }">
                                    <input x-bind:type="lihat ? 'text' : 'password'" type="password" id="cloud-kunci" wire:model="isianCloud.kunci" class="form-input pr-16 font-mono text-sm"
                                           autocomplete="new-password" spellcheck="false" placeholder="{{ $adaKunci ? '•••• '.($konfigurasi['kunci_akhir'] ?? '') : (AturanPenyediaAi::AWALAN_KUNCI[$kunci] ?? '').'…' }}"
                                           @error('isianCloud.kunci') aria-invalid="true" aria-describedby="cloud-kunci-error" @enderror>
                                    <button type="button" x-on:click="lihat = ! lihat" class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-stone-500 hover:text-stone-800"
                                            x-text="lihat ? 'Sembunyi' : 'Lihat'" x-bind:aria-pressed="lihat" aria-label="Tampilkan kunci yang sedang diketik">Lihat</button>
                                </div>
                            </x-ui.field>
                        </div>
                        @if ($adaKunci)
                            <label class="flex items-center gap-2 text-sm text-stone-700">
                                <input type="checkbox" wire:model="isianCloud.hapus_kunci" class="size-4 rounded border-stone-300 text-red-600 focus:ring-red-600" data-testid="hapus-kunci">
                                Hapus kunci tersimpan
                            </label>
                        @endif

                        <details class="rounded-lg bg-white ring-1 ring-stone-200" @if ($isianCloud['base_url'] !== '' || $errors->has('isianCloud.base_url')) open @endif>
                            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-stone-600 hover:text-stone-900">Lanjutan: alamat API khusus</summary>
                            <div class="border-t border-stone-200 p-3">
                                <x-ui.field label="Alamat API (opsional)" for="cloud-base-url" error="isianCloud.base_url" hint="Kosongkan untuk memakai alamat resmi penyedia; isi bila madrasah memakai proxy/gateway.">
                                    <input type="url" id="cloud-base-url" wire:model="isianCloud.base_url" class="form-input font-mono text-sm" autocomplete="off" placeholder="https://…">
                                </x-ui.field>
                            </div>
                        </details>

                        <fieldset>
                            <legend class="text-sm font-medium text-stone-800">Pakai sebagai</legend>
                            <div class="mt-1.5 flex flex-wrap gap-2" role="radiogroup">
                                @foreach (AturanPenyediaAi::PERAN as $nilai => $label)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-lg bg-white px-3 py-1.5 text-sm ring-1 ring-stone-200 has-checked:bg-brand-50 has-checked:ring-2 has-checked:ring-brand-600">
                                        <input type="radio" name="peran-cloud" value="{{ $nilai }}" wire:model="isianCloud.peran" class="size-4 border-stone-300 text-brand-700 focus:ring-brand-600"> {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>

                        <p class="flex items-start gap-2 text-xs text-stone-500"><x-ui.icon name="info" class="mt-px size-3.5 shrink-0 text-stone-400" /> {{ $p['kekurangan'] }} NIP dan nama pejabat tetap tidak pernah dikirim.</p>

                        @if ($peringatanCloud !== [])
                            <div class="rounded-lg bg-gold-50 px-3 py-2 text-xs text-gold-700 ring-1 ring-gold-200" role="alert" data-testid="peringatan-cloud">
                                @foreach ($peringatanCloud as $pesan)
                                    <p class="flex items-start gap-1.5"><x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" /> {{ $pesan }}</p>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex flex-col-reverse gap-2 border-t border-stone-200 pt-3 sm:flex-row sm:items-center sm:justify-between">
                            <button type="button" class="btn-ghost text-xs" disabled title="Sedang disiapkan">Uji koneksi <x-ui.segera /></button>
                            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                                <button type="button" wire:click="batalCloud" class="btn-ghost">Batal</button>
                                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="simpanCloud" data-testid="simpan-cloud">
                                    <x-ui.icon name="save" class="size-4" /> {{ $peringatanCloud !== [] ? 'Tetap simpan' : 'Simpan' }}
                                </button>
                            </div>
                        </div>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
</section>
