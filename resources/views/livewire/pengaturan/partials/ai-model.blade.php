@use('App\Support\Pengaturan\ParameterAi')
@use('App\Support\Pengaturan\PenyediaAi')

@php
    $ai = $this->pengaturan();
    $peran = array_filter(['aktif' => $ai['provider_aktif'], 'cadangan' => $ai['provider_cadangan']]);
    $presetAktif = is_numeric($isianParameter['suhu']) ? ParameterAi::preset((float) $isianParameter['suhu']) : null;
@endphp

{{--
    Tab Model & parameter: model penyedia aktif/cadangan dipilih dari daftar model yang dimuat (DaftarModelAi), lalu
    parameter jawaban untuk semua penyedia — kreativitas (preset + geser), panjang jawaban, jumlah versi saran.
--}}
<div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]" data-testid="panel-model-ai">
    <div class="min-w-0 space-y-4">
        @forelse ($peran as $sebagai => $penyedia)
            @php
                $konfigurasi = $ai['penyedia'][$penyedia] ?? [];
                $dimuat = $daftarModel[$penyedia] ?? null;
            @endphp
            <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-model-{{ $penyedia }}" data-testid="model-{{ $sebagai }}" wire:key="model-{{ $penyedia }}">
                <header class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-100 px-5 py-4">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-stone-500">{{ $sebagai === 'aktif' ? 'Penyedia aktif' : 'Penyedia cadangan' }}</p>
                        <h2 id="judul-model-{{ $penyedia }}" class="text-sm font-semibold text-stone-900">{{ PenyediaAi::nama($penyedia) }}</h2>
                        <p class="mt-0.5 text-xs text-stone-500">Model dipakai: <span class="font-mono text-stone-800" data-testid="model-dipakai-{{ $penyedia }}">{{ ($konfigurasi['model'] ?? '') ?: 'belum diisi' }}</span></p>
                    </div>
                    <button type="button" wire:click="muatModel('{{ $penyedia }}')" wire:loading.attr="disabled" wire:target="muatModel('{{ $penyedia }}')" class="btn-secondary px-2.5 py-1.5 text-xs" data-testid="muat-model-{{ $penyedia }}">
                        <span wire:loading.remove wire:target="muatModel('{{ $penyedia }}')" class="inline-flex items-center gap-1.5"><x-ui.icon name="download" class="size-3.5" /> {{ $dimuat ? 'Muat ulang' : 'Muat daftar model' }}</span>
                        <span wire:loading.flex wire:target="muatModel('{{ $penyedia }}')" class="items-center gap-1.5"><x-ui.icon name="loader" class="size-3.5 animate-spin" /> Memuat…</span>
                    </button>
                </header>

                @if ($dimuat === null)
                    <p class="px-5 py-4 text-xs text-stone-500">Muat daftar model untuk melihat model yang tersedia di {{ PenyediaAi::nama($penyedia) }}.</p>
                @elseif ($dimuat['galat'])
                    <p class="m-5 flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800 ring-1 ring-red-200" role="alert" data-testid="galat-model-{{ $penyedia }}">
                        <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" /> {{ $dimuat['galat'] }}
                    </p>
                @elseif ($dimuat['model'] === [])
                    <p class="px-5 py-4 text-xs text-stone-500">Penyedia ini tidak menyediakan daftar model — tulis nama modelnya di tab Penyedia.</p>
                @else
                    <ul class="divide-y divide-stone-100" data-testid="daftar-model-{{ $penyedia }}">
                        @foreach ($dimuat['model'] as $m)
                            @php $dipakai = ($konfigurasi['model'] ?? '') === $m['nama']; @endphp
                            <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3" data-model="{{ $m['nama'] }}">
                                <div class="min-w-0">
                                    <p class="flex flex-wrap items-center gap-2 text-sm">
                                        <span class="font-mono font-semibold text-stone-900">{{ $m['nama'] }}</span>
                                        @if ($m['ukuran'])
                                            <span class="text-xs text-stone-500">{{ $m['ukuran'] }}</span>
                                        @endif
                                        @if ($m['disarankan'])
                                            <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800 ring-1 ring-brand-200 ring-inset">Disarankan</span>
                                        @endif
                                    </p>
                                    <p class="mt-0.5 text-xs text-stone-500">{{ $m['keterangan'] }}</p>
                                </div>
                                @if ($dipakai)
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-brand-700"><x-ui.icon name="check-circle" class="size-4" /> Dipakai</span>
                                @else
                                    <button type="button" wire:click="pakaiModel('{{ $penyedia }}', '{{ $m['nama'] }}')" class="btn-ghost px-2.5 py-1 text-xs" data-testid="pakai-model">Pakai</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @empty
            <div class="rounded-2xl bg-white px-6 py-8 text-center shadow-xs ring-1 ring-stone-200" data-testid="model-tanpa-penyedia">
                <x-ui.icon name="sparkles" class="mx-auto size-7 text-stone-300" />
                <p class="mt-2 text-sm font-medium text-stone-700">Belum ada penyedia aktif.</p>
                <p class="mt-1 text-xs text-stone-500">Pilih penyedia di tab Penyedia untuk mengatur modelnya; parameter jawaban di samping tetap bisa diatur.</p>
                <button type="button" wire:click="$set('tab', 'penyedia')" class="btn-secondary mt-3 text-xs">Buka tab Penyedia</button>
            </div>
        @endforelse
    </div>

    {{-- Parameter jawaban --}}
    <form wire:submit="simpanParameter" class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-parameter-ai" data-testid="parameter-ai" x-data="formAi('isianParameter', 'parameter')">
        <h2 id="judul-parameter-ai" class="text-sm font-semibold text-stone-900">Parameter jawaban</h2>
        <p class="mt-0.5 text-xs text-stone-500">Berlaku untuk semua penyedia: “Buat dengan AI”, minta perbaikan, dan usulan jadwal.</p>

        <fieldset class="mt-4">
            <legend class="text-sm font-medium text-stone-800">Kreativitas</legend>
            <div class="mt-1.5 grid grid-cols-3 gap-1 rounded-lg bg-stone-100 p-0.5 text-xs font-semibold" role="radiogroup" aria-label="Preset kreativitas">
                @foreach (ParameterAi::PRESET_SUHU as $kunci => [$nilai, $label])
                    <button type="button" role="radio" aria-checked="{{ $presetAktif === $kunci ? 'true' : 'false' }}" wire:click="pilihPresetSuhu('{{ $kunci }}')"
                            @class(['rounded-md px-2 py-1.5', 'bg-white text-stone-900 shadow-xs' => $presetAktif === $kunci, 'text-stone-500 hover:text-stone-800' => $presetAktif !== $kunci])
                            data-preset="{{ $kunci }}">{{ $label }}</button>
                @endforeach
            </div>
            <p class="mt-1.5 text-xs text-stone-500" data-testid="keterangan-suhu">{{ $presetAktif ? ParameterAi::PRESET_SUHU[$presetAktif][2] : 'Diatur sendiri.' }}</p>
            <div class="mt-2 flex items-center gap-3">
                <input type="range" min="0" max="1" step="0.1" wire:model.live="isianParameter.suhu" class="w-full accent-brand-700" aria-label="Kreativitas (0 = konsisten, 1 = kreatif)" data-testid="geser-suhu">
                <span class="w-8 shrink-0 text-right text-sm font-semibold text-stone-900 tabular-nums">{{ is_numeric($isianParameter['suhu']) ? number_format((float) $isianParameter['suhu'], 1, ',', '') : '—' }}</span>
            </div>
            @error('isianParameter.suhu') <p class="form-error" role="alert">{{ $message }}</p> @enderror
        </fieldset>

        <x-ui.field class="mt-4" label="Panjang jawaban maksimal" for="maks-token" error="isianParameter.maks_token">
            <select id="maks-token" wire:model="isianParameter.maks_token" class="form-input">
                @foreach (ParameterAi::MAKS_TOKEN as $nilai => $label)
                    <option value="{{ $nilai }}">{{ $label }}</option>
                @endforeach
            </select>
        </x-ui.field>

        <fieldset class="mt-4">
            <legend class="text-sm font-medium text-stone-800">Jumlah versi saran</legend>
            <div class="mt-1.5 flex gap-2" role="radiogroup">
                @foreach (ParameterAi::JUMLAH_VERSI as $n)
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-1.5 text-sm ring-1 ring-stone-200 has-checked:bg-brand-50 has-checked:ring-2 has-checked:ring-brand-600">
                        <input type="radio" name="jumlah-versi" value="{{ $n }}" wire:model="isianParameter.jumlah_versi" class="size-4 border-stone-300 text-brand-700 focus:ring-brand-600"> {{ $n }} versi
                    </label>
                @endforeach
            </div>
            @error('isianParameter.jumlah_versi') <p class="form-error" role="alert">{{ $message }}</p> @enderror
        </fieldset>

        <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-stone-100 pt-4">
            @include('livewire.pengaturan.partials.ai-status-simpan', ['bagian' => 'parameter'])
        </div>
        <div class="mt-2 flex flex-wrap-reverse items-center justify-between gap-2">
            <button type="button" wire:click="kembalikanParameter" class="btn-ghost text-xs whitespace-nowrap">Kembalikan bawaan</button>
            <button type="submit" class="btn-primary whitespace-nowrap" wire:loading.attr="disabled" wire:target="simpanParameter" data-testid="simpan-parameter">
                <x-ui.icon name="save" class="size-4" /> Simpan parameter
            </button>
        </div>
    </form>
</div>
