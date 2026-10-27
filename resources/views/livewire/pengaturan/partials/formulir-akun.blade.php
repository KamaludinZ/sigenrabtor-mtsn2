{{-- Formulir tambah/ubah akun: peran menentukan tautan leading (akun leading wajib ≥ 1 leading aktif). --}}
@php
    $leadingAktif = array_values(array_filter($this->leading, fn (array $l) => $l['is_active']));
    $tertautLama = $suntingAkun === 'baru' ? [] : (collect($this->akun)->firstWhere('id', (int) $suntingAkun)['leadings'] ?? []);
    // Tautan lama ke leading yang kini nonaktif tetap tampil agar bisa dilepas.
    $opsiLeading = array_merge($leadingAktif, array_values(array_filter($this->leading, fn (array $l) => ! $l['is_active'] && in_array($l['kode'], $tertautLama, true))));
    $diriSendiri = $suntingAkun !== 'baru' && (int) $suntingAkun === \App\Support\Stub\StubData::currentUser()['id'];
@endphp
<form wire:submit="simpanAkun" class="mb-6 rounded-2xl bg-white shadow-xs ring-1 ring-brand-200" data-testid="formulir-akun" wire:key="formulir-akun-{{ $suntingAkun }}">
    <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
        <h2 class="text-base font-semibold text-stone-900">{{ $suntingAkun === 'baru' ? 'Tambah akun' : 'Ubah akun' }}</h2>
        <p class="mt-0.5 text-sm text-stone-500">Masuk sementara masih lewat sakelar peran demo; sandi diatur saat autentikasi nyata dibangun.</p>
    </header>
    <div class="grid gap-5 px-5 py-5 sm:grid-cols-2 sm:px-6">
        <x-ui.field label="Nama & gelar" for="akun-nama" error="isianAkun.name" required>
            <input type="text" id="akun-nama" wire:model="isianAkun.name" maxlength="{{ \App\Support\Pengaturan\AturanAkun::MAKS_NAMA }}" autocomplete="off" class="form-input" x-init="$el.focus()"
                   @error('isianAkun.name') aria-invalid="true" aria-describedby="akun-nama-error" @enderror>
        </x-ui.field>
        <x-ui.field label="Surel" for="akun-surel" error="isianAkun.email" required hint="Dipakai untuk masuk.">
            <input type="email" id="akun-surel" wire:model="isianAkun.email" maxlength="190" autocomplete="off" class="form-input"
                   @error('isianAkun.email') aria-invalid="true" aria-describedby="akun-surel-error" @enderror>
        </x-ui.field>

        <fieldset class="sm:col-span-2">
            <legend class="form-label">Peran <span class="text-red-600" aria-hidden="true">*</span></legend>
            <div class="mt-1 grid gap-2 sm:grid-cols-2" role="radiogroup">
                @foreach (\App\Livewire\Pengaturan\LeadingUser::PERAN as $kunci => $p)
                    <label @class(['flex cursor-pointer items-start gap-3 rounded-xl p-3 ring-1', 'bg-brand-50 ring-2 ring-brand-600' => $isianAkun['role'] === $kunci, 'bg-white ring-stone-200 hover:ring-stone-300' => $isianAkun['role'] !== $kunci, 'cursor-not-allowed opacity-60' => $diriSendiri && $isianAkun['role'] !== $kunci])>
                        <input type="radio" wire:model.live="isianAkun.role" value="{{ $kunci }}" class="mt-1" @disabled($diriSendiri && $isianAkun['role'] !== $kunci)>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-stone-900">{{ $p['label'] }}</span>
                            <span class="block text-xs text-stone-500">{{ $p['hak'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @if ($diriSendiri)
                <p class="mt-1 text-xs text-stone-500">Peran akun Anda sendiri tidak bisa diubah.</p>
            @endif
            @error('isianAkun.role') <p class="form-error" role="alert">{{ $message }}</p> @enderror
        </fieldset>

        @if ($isianAkun['role'] === 'leading')
            <fieldset class="sm:col-span-2" data-testid="tautan-leading">
                <legend class="form-label">Leading yang ditautkan <span class="text-red-600" aria-hidden="true">*</span></legend>
                <p class="text-xs text-stone-500">Akun hanya melihat & mengisi kegiatan leading yang dicentang.</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($opsiLeading as $l)
                        <label @class(['inline-flex cursor-pointer items-center gap-2 rounded-lg px-3 py-1.5 text-sm ring-1', 'bg-brand-50 font-medium text-brand-800 ring-brand-300' => in_array($l['kode'], $isianAkun['leadings'], true), 'bg-white text-stone-700 ring-stone-300 hover:bg-stone-50' => ! in_array($l['kode'], $isianAkun['leadings'], true)])>
                            <input type="checkbox" wire:model.live="isianAkun.leadings" value="{{ $l['kode'] }}" class="rounded">
                            <span class="tabular-nums">{{ $l['kode'] }}</span> {{ $l['nama_unit'] }}
                            @unless ($l['is_active'])
                                <span class="rounded bg-stone-200 px-1 text-[10px] font-semibold text-stone-600 uppercase">nonaktif</span>
                            @endunless
                        </label>
                    @endforeach
                </div>
                @error('isianAkun.leadings') <p class="form-error" role="alert">{{ $message }}</p> @enderror
            </fieldset>
        @endif

        <x-ui.field label="Jabatan" for="akun-jabatan" error="isianAkun.jabatan" hint="Opsional, tampil di daftar akun.">
            <input type="text" id="akun-jabatan" wire:model="isianAkun.jabatan" maxlength="{{ \App\Support\Pengaturan\AturanAkun::MAKS_JABATAN }}" autocomplete="off" class="form-input"
                   @error('isianAkun.jabatan') aria-invalid="true" aria-describedby="akun-jabatan-error" @enderror>
        </x-ui.field>
        <x-ui.field label="NIP" for="akun-nip" error="isianAkun.nip" hint="Opsional. Tidak pernah dikirim ke AI.">
            <input type="text" id="akun-nip" wire:model="isianAkun.nip" inputmode="numeric" maxlength="24" autocomplete="off" class="form-input tabular-nums" placeholder="19700101 199503 1 001"
                   @error('isianAkun.nip') aria-invalid="true" aria-describedby="akun-nip-error" @else aria-describedby="akun-nip-pratinjau" @enderror>
            <p id="akun-nip-pratinjau" class="mt-1 text-xs" x-data
               x-text="$wire.isianAkun.nip ? ($galatNip($wire.isianAkun.nip) ?? ('Tercetak: ' + $nip($wire.isianAkun.nip))) : ''"
               x-bind:class="{ 'text-gold-700': $wire.isianAkun.nip && $galatNip($wire.isianAkun.nip), 'text-stone-500': ! ($wire.isianAkun.nip && $galatNip($wire.isianAkun.nip)) }"></p>
        </x-ui.field>
    </div>
    <div class="flex justify-end gap-2 border-t border-stone-100 px-5 py-3 sm:px-6">
        <button type="button" wire:click="batalAkun" class="btn-ghost">Batal</button>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="simpanAkun"><x-ui.icon name="save" class="size-4" /> <span wire:loading.remove wire:target="simpanAkun">Simpan</span><span wire:loading wire:target="simpanAkun">Menyimpan…</span></button>
    </div>
</form>
