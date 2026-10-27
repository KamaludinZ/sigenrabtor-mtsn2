@use('App\Support\Master\Impor\BerkasMaster')
@use('App\Support\Master\JenisKode')

@php
    $dikenali = $this->sheetDikenali();
    $diganti = $this->akanDiganti();
@endphp

<section class="rounded-2xl bg-white shadow-xs ring-1 ring-brand-200" aria-labelledby="judul-unggah-master" data-testid="unggah-master">
    <header class="flex items-start justify-between gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
        <div class="min-w-0">
            <h2 id="judul-unggah-master" class="text-base font-semibold text-stone-900">Unggah master kode TA {{ $tahun }}</h2>
            <p class="mt-0.5 text-sm text-stone-500">Isi berkas diperiksa dan dipratinjau dulu; master baru tersimpan setelah Anda menyetujuinya.</p>
        </div>
        <button type="button" wire:click="tutup" class="btn-ghost shrink-0 p-2" aria-label="Tutup unggah master"><x-ui.icon name="x" class="size-4" /></button>
    </header>

    <div class="space-y-5 px-5 py-5 sm:px-6">
        {{-- Cakupan: satu berkas master berisi semua sheet, atau satu jenis saja. --}}
        <fieldset>
            <legend class="form-label">Yang diunggah</legend>
            <div class="mt-1 flex flex-wrap gap-2" data-testid="cakupan-unggah">
                @foreach (BerkasMaster::cakupan() as $kunci => $label)
                    <label @class(['inline-flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm ring-1', 'bg-brand-50 font-semibold text-brand-800 ring-brand-300' => $cakupan === $kunci, 'bg-white text-stone-700 ring-stone-300 hover:bg-stone-50' => $cakupan !== $kunci])>
                        <input type="radio" wire:model.live="cakupan" value="{{ $kunci }}" class="sr-only">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <p class="mt-1.5 text-xs text-stone-500">
                @if ($cakupan === BerkasMaster::SEMUA)
                    Satu berkas .xlsx dengan sheet Perkin (SK &amp; IKSK), SNP (RKTM), EDM, dan 5 Budaya Mutu — sheet yang tidak ada tidak mengubah jenisnya.
                @else
                    {{ JenisKode::DAFTAR[$cakupan]['asal'] }} Berkas .xlsx/.xls atau .csv.
                @endif
            </p>
        </fieldset>

        @if ($diganti !== [])
            <div class="rounded-lg bg-gold-50 px-3 py-2.5 text-sm text-gold-800 ring-1 ring-gold-200" data-testid="akan-diganti">
                <p class="flex items-start gap-2 font-medium"><x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> Master TA {{ $tahun }} yang ada akan diganti isi berkas setelah pratinjau disetujui:</p>
                <ul class="mt-1 space-y-0.5 pl-6 text-xs">
                    @foreach ($diganti as $jenis => $d)
                        <li data-jenis="{{ $jenis }}">{{ JenisKode::DAFTAR[$jenis]['label'] }} — {{ $d['kode'] }} kode{{ $d['kegiatan'] > 0 ? ' · dipakai '.$d['kegiatan'].' kegiatan' : '' }}</li>
                    @endforeach
                </ul>
                @if (array_sum(array_column($diganti, 'kegiatan')) > 0)
                    <p class="mt-1.5 pl-6 text-xs">Kode yang dipakai kegiatan tetapi tidak ada di berkas akan ditandai di pratinjau.</p>
                @endif
            </div>
        @endif

        {{-- Pilih berkas: klik atau seret-lepas. wire:key per keadaan agar status Alpine unggah tidak terbawa. --}}
        <div wire:key="berkas-{{ $terpilih ? 'terpilih' : 'kosong' }}"
             x-data="{ unggah: false, persen: 0, seret: false }"
             x-on:livewire-upload-start="unggah = true; persen = 0"
             x-on:livewire-upload-progress="persen = $event.detail.progress"
             x-on:livewire-upload-finish="unggah = false"
             x-on:livewire-upload-error="unggah = false">
            <input type="file" id="berkas-master" x-ref="input" wire:model="berkas" accept=".xlsx,.xls,.csv" class="sr-only" data-testid="input-berkas-master">

            @if (! $terpilih)
                <label for="berkas-master" data-testid="zona-berkas-master"
                       x-on:dragover.prevent="seret = true" x-on:dragleave.prevent="seret = false"
                       x-on:drop.prevent="seret = false; $refs.input.files = $event.dataTransfer.files; $refs.input.dispatchEvent(new Event('change', { bubbles: true }))"
                       x-bind:class="{ 'border-brand-500 bg-brand-50/60': seret, 'border-stone-300': ! seret }"
                       class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-8 text-center transition hover:border-brand-400 hover:bg-brand-50/40">
                    <x-ui.icon name="upload" class="size-8 text-stone-400" />
                    <span class="text-sm font-semibold text-stone-800">Pilih atau seret berkas master ke sini</span>
                    <span class="text-xs text-stone-500">.xlsx, .xls{{ $cakupan === BerkasMaster::SEMUA ? '' : ', atau .csv' }} · maks. {{ BerkasMaster::MAKS_KB / 1024 }} MB</span>
                </label>
            @else
                <div class="rounded-xl bg-stone-50 p-4 ring-1 ring-stone-200" data-testid="berkas-master-terpilih">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <p class="flex min-w-0 items-start gap-2.5">
                            <x-ui.icon name="sheet" class="mt-0.5 size-5 shrink-0 text-brand-700" />
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-stone-900" data-testid="nama-berkas-master">{{ $terpilih['nama'] }}</span>
                                <span class="block text-xs text-stone-500">{{ BerkasMaster::ukuran($terpilih['ukuran']) }} · {{ count($terpilih['lembar']) }} sheet: {{ implode(', ', $terpilih['lembar']) }}</span>
                            </span>
                        </p>
                        <label for="berkas-master" class="btn-ghost shrink-0 cursor-pointer px-2.5 py-1 text-xs"><x-ui.icon name="upload" class="size-3.5" /> Ganti berkas</label>
                    </div>
                    <ul class="mt-3 grid gap-1.5 sm:grid-cols-2" aria-label="Sheet yang dikenali" data-testid="sheet-dikenali">
                        @foreach ($dikenali as $jenis => $sheet)
                            <li @class(['flex items-start gap-2 rounded-lg px-2.5 py-1.5 text-xs ring-1', 'bg-white text-stone-700 ring-stone-200' => $sheet, 'bg-gold-50 text-gold-800 ring-gold-200' => ! $sheet]) data-jenis="{{ $jenis }}" data-dikenali="{{ $sheet ? 'ya' : 'tidak' }}">
                                <x-ui.icon :name="$sheet ? 'check-circle' : 'alert'" @class(['mt-px size-3.5 shrink-0', 'text-brand-600' => $sheet, 'text-gold-600' => ! $sheet]) />
                                <span><span class="font-semibold">{{ JenisKode::DAFTAR[$jenis]['label'] }}</span> — {{ $sheet ? 'sheet '.$sheet : 'sheet tidak ditemukan, tidak berubah' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-3" x-show="unggah" x-cloak>
                <div class="h-1.5 overflow-hidden rounded-full bg-stone-100"><div class="h-full rounded-full bg-brand-600 transition-all" x-bind:style="`width: ${persen}%`"></div></div>
                <p class="mt-1 text-xs text-stone-500">Mengunggah… <span x-text="persen"></span>%</p>
            </div>
            <p class="mt-3 flex items-center gap-2 text-sm text-stone-600" wire:loading.flex wire:target="berkas">
                <x-ui.icon name="loader" class="size-4 animate-spin" /> Membaca daftar sheet…
            </p>
        </div>

        @if ($galat)
            <p class="flex items-start gap-2 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200" role="alert" data-testid="galat-unggah-master">
                <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> {{ $galat }}
            </p>
        @endif
    </div>

    <footer class="flex flex-col-reverse gap-2 border-t border-stone-100 px-5 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <p class="text-xs text-stone-500">Kode tidak diketik ulang: leading memilihnya dari master ini di Kode &amp; Klasifikasi.</p>
        <div class="flex justify-end gap-2">
            @if ($terpilih)
                <button type="button" wire:click="gantiBerkas" class="btn-ghost">Batal pilih</button>
            @else
                <button type="button" wire:click="tutup" class="btn-ghost">Tutup</button>
            @endif
            @if ($this->siapDiperiksa())
                <button type="button" class="btn-primary" disabled title="Sedang disiapkan" wire:key="lanjut-siap"><x-ui.icon name="eye" class="size-4" /> Lanjut ke pratinjau <x-ui.segera /></button>
            @else
                <button type="button" class="btn-primary" disabled wire:key="lanjut-belum"><x-ui.icon name="eye" class="size-4" /> Lanjut ke pratinjau</button>
            @endif
        </div>
    </footer>
</section>
