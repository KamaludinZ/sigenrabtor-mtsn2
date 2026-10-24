@use('App\Livewire\Forms\ItemBelanjaForm')

{{-- Form tambah item belanja: jumlah = volume × harga satuan dihitung seketika di layar dan diulang di server. --}}
<form wire:submit="simpanItem" data-testid="form-item"
      class="border-t border-stone-100 bg-stone-50/70 px-5 py-5 sm:px-6"
      x-data="{
          get jumlah() {
              let t = String($wire.item.volume ?? '').replace(/\s/g, '');
              t = t.includes(',') ? t.replace(/\./g, '').replace(',', '.') : (/^\d{1,3}(\.\d{3})+$/.test(t) ? t.replace(/\./g, '') : t);
              const v = parseFloat(t);
              const h = parseInt(String($wire.item.harga_satuan ?? '').replace(/\D/g, ''), 10);
              // Sama dengan KalkulatorRab::jumlah: volume × 100 dibulatkan, lalu dibulatkan setengah-ke-atas ke rupiah.
              return (v > 0 && h > 0) ? Math.floor((Math.round(v * 100) * h + 50) / 100) : null;
          },
      }"
      x-on:form-item-dibuka.window="$nextTick(() => $refs.uraian?.focus())"
      x-on:keydown.escape="$wire.tutupFormItem()">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-stone-800">
            @if ($itemDiedit)
                Ubah item no. {{ collect($this->kelompokRincian)->pluck('item')->flatten(1)->firstWhere('id', $itemDiedit)['nomor'] ?? '' }} · RAB {{ $sumber }}
            @else
                Item belanja baru · RAB {{ $sumber }}
            @endif
        </h3>
        <button type="button" wire:click="tutupFormItem" class="rounded-md p-1 text-stone-400 hover:bg-stone-200 hover:text-stone-700" aria-label="Tutup form item">
            <x-ui.icon name="x" class="size-4" />
        </button>
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-6">
        <x-ui.field class="sm:col-span-6" label="Uraian" for="item_uraian" error="item.uraian" required>
            <input type="text" id="item_uraian" x-ref="uraian" wire:model.live.blur="item.uraian" list="saran-uraian-item"
                   maxlength="255" autocomplete="off" placeholder="mis. Honorarium Narasumber" class="form-input">
            <datalist id="saran-uraian-item">
                @foreach (ItemBelanjaForm::SARAN_URAIAN as $saran)
                    <option value="{{ $saran }}"></option>
                @endforeach
            </datalist>
        </x-ui.field>

        <div class="sm:col-span-4">
            <p class="form-label">Kelompok belanja <span class="text-red-600" aria-hidden="true">*</span></p>
            <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Kelompok belanja">
                @foreach (ItemBelanjaForm::LABEL_KATEGORI as $kunci => $label)
                    <label @class([
                            'cursor-pointer rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition has-focus-visible:outline-2 has-focus-visible:outline-brand-600',
                            'bg-brand-700 text-white ring-brand-700' => $item->kategori === $kunci,
                            'bg-white text-stone-700 ring-stone-300 hover:bg-stone-50' => $item->kategori !== $kunci,
                        ])>
                        <input type="radio" name="item_kategori" value="{{ $kunci }}" wire:model.live="item.kategori" class="sr-only">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            @error('item.kategori') <p class="form-error">{{ $message }}</p> @enderror
            <p class="form-hint">{{ $item->kategori_manual ? 'Dipilih manual.' : 'Ditebak otomatis dari uraian; klik untuk mengubah.' }} Dipakai untuk rekap per kelompok belanja.</p>
        </div>

        <x-ui.field class="sm:col-span-2" label="Kode akun" for="item_kode" error="item.kode" hint="Opsional, mis. 521211.">
            <input type="text" id="item_kode" wire:model.live.blur="item.kode" list="saran-kode-akun" maxlength="20" autocomplete="off"
                   placeholder="521211" class="form-input font-mono">
            <datalist id="saran-kode-akun">
                @foreach (ItemBelanjaForm::KODE_AKUN as $kode => $nama)
                    <option value="{{ $kode }}">{{ $nama }}</option>
                @endforeach
            </datalist>
        </x-ui.field>

        <x-ui.field class="sm:col-span-1" label="Volume" for="item_volume" error="item.volume" required>
            <input type="text" id="item_volume" wire:model.live.blur="item.volume" inputmode="decimal" autocomplete="off"
                   placeholder="0" class="form-input text-right tabular-nums">
        </x-ui.field>

        <x-ui.field class="sm:col-span-1" label="Satuan" for="item_satuan" error="item.satuan" required>
            <input type="text" id="item_satuan" wire:model.live.blur="item.satuan" list="saran-satuan" maxlength="30" autocomplete="off"
                   placeholder="OJ" class="form-input">
            <datalist id="saran-satuan">
                @foreach (ItemBelanjaForm::SATUAN as $satuan)
                    <option value="{{ $satuan }}"></option>
                @endforeach
            </datalist>
        </x-ui.field>

        <x-ui.field class="sm:col-span-2" label="Harga satuan" for="item_harga_satuan" error="item.harga_satuan" required>
            <x-ui.rupiah id="item_harga_satuan" model="item.harga_satuan" />
        </x-ui.field>

        <div class="sm:col-span-2">
            <p class="form-label">Jumlah</p>
            <output for="item_volume item_harga_satuan" aria-live="polite" data-testid="jumlah-item"
                    class="flex min-h-9.5 items-center justify-end rounded-lg bg-white px-3 py-2 text-sm font-bold text-stone-900 tabular-nums ring-1 ring-stone-200 ring-inset"
                    x-text="jumlah === null ? '—' : 'Rp' + jumlah.toLocaleString('id-ID')">—</output>
            <p class="form-hint text-right">Volume × harga satuan</p>
        </div>
    </div>

    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button type="button" wire:click="tutupFormItem" class="btn-ghost">Batal</button>
        @if ($itemDiedit)
            <button type="submit" wire:loading.attr="disabled" wire:target="simpanItem" class="btn-primary">
                <x-ui.icon name="save" class="size-4" />
                Simpan perubahan
            </button>
        @else
            <button type="button" wire:click="simpanItem(true)" wire:loading.attr="disabled" wire:target="simpanItem" class="btn-secondary">
                Simpan &amp; tambah lagi
            </button>
            <button type="submit" wire:loading.attr="disabled" wire:target="simpanItem" class="btn-primary">
                <x-ui.icon name="plus" class="size-4" />
                Simpan item
            </button>
        @endif
    </div>
</form>
