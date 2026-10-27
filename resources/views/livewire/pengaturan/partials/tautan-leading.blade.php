{{-- Panel tautan akun ↔ leading dari sisi leading; $kolom = colspan di tabel, null = di kartu layar sempit. --}}
@php
    $tertaut = collect($this->akun)->filter(fn (array $a) => in_array($l['kode'], $a['leadings'], true))->sortBy([['is_active', 'desc'], ['name', 'asc']])->values();
    $calon = $this->calonTautan($l['kode']);
@endphp
@if ($kolom)
    <tr class="bg-brand-50/40" data-testid="panel-tautan-leading" wire:key="tautan-{{ $l['kode'] }}">
        <td colspan="{{ $kolom }}" class="px-5 py-4 sm:px-6">
@else
    <div class="mt-2 rounded-lg bg-brand-50/40 p-3 ring-1 ring-brand-100" data-testid="panel-tautan-leading">
@endif
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-semibold text-stone-900">Akun tertaut ke {{ $l['kode'] }} {{ $l['nama_unit'] }}</p>
                <button type="button" wire:click="tutupTautan" class="btn-ghost -my-1 p-1.5" aria-label="Tutup panel tautan {{ $l['kode'] }}"><x-ui.icon name="x" class="size-4" /></button>
            </div>
            <ul class="mt-2 space-y-1.5" aria-label="Akun tertaut">
                @forelse ($tertaut as $a)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-stone-200" data-tautan-akun="{{ $a['id'] }}">
                        <span class="min-w-0">
                            <span @class(['font-medium', 'text-stone-900' => $a['is_active'], 'text-stone-400 line-through' => ! $a['is_active']])>{{ $a['name'] }}</span>
                            <span class="text-xs text-stone-500">{{ $a['email'] }}{{ count($a['leadings']) > 1 ? ' · juga '.implode(', ', array_diff($a['leadings'], [$l['kode']])) : ' · hanya leading ini' }}</span>
                        </span>
                        <button type="button" wire:click="lepasTautan({{ $a['id'] }})" class="btn-ghost px-2 py-1 text-xs text-red-700 hover:bg-red-50" aria-label="Lepas {{ $a['name'] }} dari {{ $l['kode'] }}">Lepas</button>
                    </li>
                @empty
                    <li class="text-sm text-gold-700">Belum ada akun — kegiatan {{ $l['nama_unit'] }} hanya bisa diisi admin.</li>
                @endforelse
            </ul>
            @if ($l['is_active'])
                <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-start">
                    <label class="min-w-0 flex-1">
                        <span class="sr-only">Akun yang ditautkan ke {{ $l['kode'] }}</span>
                        <select wire:model="akunDitautkan" class="form-input py-1.5" aria-label="Pilih akun untuk {{ $l['kode'] }}" @disabled($calon === [])>
                            <option value="">{{ $calon === [] ? 'Semua akun leading sudah tertaut' : 'Pilih akun leading…' }}</option>
                            @foreach ($calon as $a)
                                <option value="{{ $a['id'] }}">{{ $a['name'] }}{{ $a['is_active'] ? '' : ' (nonaktif)' }}{{ $a['leadings'] ? ' — '.implode(', ', $a['leadings']) : ' — belum tertaut' }}</option>
                            @endforeach
                        </select>
                        @error('akunDitautkan') <span class="form-error block" role="alert">{{ $message }}</span> @enderror
                    </label>
                    <button type="button" wire:click="tautkanAkun" class="btn-secondary shrink-0" @disabled($calon === [])><x-ui.icon name="plus" class="size-4" /> <span wire:loading.remove wire:target="tautkanAkun">Tautkan</span><span wire:loading wire:target="tautkanAkun">Menautkan…</span></button>
                </div>
                <p class="mt-1.5 text-xs text-stone-500">Admin tidak perlu ditautkan — admin melihat semua leading. Akun baru dibuat di tab Akun.</p>
            @else
                <p class="mt-2 text-xs text-stone-500">Leading nonaktif tidak bisa diberi tautan baru; tautan lama bisa dilepas.</p>
            @endif
@if ($kolom)
        </td>
    </tr>
@else
    </div>
@endif
