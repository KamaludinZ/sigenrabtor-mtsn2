@props(['model' => 'isianPejabat', 'awalan' => 'pejabat', 'nipWajib' => false, 'jabatanBawaan' => null])

{{--
    Isian satu pejabat penanda tangan (jabatan tercetak, nama & gelar, NIP) untuk Kepala Madrasah, Ketua Komite, dan
    pejabat leading. Terikat ke array Livewire $model (kunci jabatan/nama/nip; wire:model tanpa request per ketukan —
    dirapikan saat disimpan). NIP diperiksa langsung di klien dengan $nip/$galatNip (kembaran App\Support\Pengaturan\Nip)
    sehingga bentuk cetaknya terlihat sebelum disimpan. Kolom berdampingan bila wadahnya cukup lebar (container query).
--}}
<div {{ $attributes->class(['@container']) }} data-komponen="input-pejabat">
    <div class="grid gap-4 @md:grid-cols-2">
        <x-ui.field class="@md:col-span-2" label="Jabatan tercetak" for="{{ $awalan }}-jabatan" error="{{ $model }}.jabatan" required
                    :hint="$jabatanBawaan ? 'Bawaan: '.$jabatanBawaan.'.' : null">
            <input type="text" id="{{ $awalan }}-jabatan" wire:model="{{ $model }}.jabatan" maxlength="150" class="form-input" autocomplete="off"
                   @error($model.'.jabatan') aria-invalid="true" aria-describedby="{{ $awalan }}-jabatan-error" @enderror>
        </x-ui.field>
        <x-ui.field label="Nama lengkap & gelar" for="{{ $awalan }}-nama" error="{{ $model }}.nama" required hint="Persis seperti di tanda tangan, mis. Dr. H. Ahmad Fauzi, M.Pd.">
            <input type="text" id="{{ $awalan }}-nama" wire:model="{{ $model }}.nama" maxlength="150" class="form-input" autocomplete="off"
                   @error($model.'.nama') aria-invalid="true" aria-describedby="{{ $awalan }}-nama-error" @enderror>
        </x-ui.field>
        <x-ui.field label="NIP{{ $nipWajib ? '' : ' (opsional)' }}" for="{{ $awalan }}-nip" error="{{ $model }}.nip" :required="$nipWajib"
                    hint="18 angka; boleh ditulis dengan spasi.">
            <input type="text" id="{{ $awalan }}-nip" wire:model="{{ $model }}.nip" maxlength="24" inputmode="numeric" autocomplete="off" class="form-input font-mono"
                   placeholder="19700101 199503 1 001" @error($model.'.nip') aria-invalid="true" aria-describedby="{{ $awalan }}-nip-error" @enderror>
            <p class="mt-1.5 text-xs" x-data x-show="($wire.{{ $model }}.nip ?? '').trim() !== ''" x-cloak aria-live="polite" data-testid="{{ $awalan }}-nip-pratinjau"
               x-bind:class="{ 'font-medium text-gold-700': $galatNip($wire.{{ $model }}.nip), 'text-brand-700': ! $galatNip($wire.{{ $model }}.nip) }"
               x-text="$galatNip($wire.{{ $model }}.nip) ?? ('Tercetak: NIP. ' + $nip($wire.{{ $model }}.nip))"></p>
        </x-ui.field>
    </div>
</div>
