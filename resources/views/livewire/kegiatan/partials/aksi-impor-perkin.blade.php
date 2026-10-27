@use('App\Support\Format')

{{--
    Aksi tahap periksa impor Perkin: Ganti berkas, Batalkan (konfirmasi inline bila ada baris siap), dan Impor yang
    membuka konfirmasi berisi ringkasan draf yang akan dibuat sebelum proses berjalan.
--}}
@if ($konfirmasiImpor)
    @php
        $r = $this->ringkasanKonfirmasi();
        $tahunAktif = \App\Support\Stub\StubData::tahunAktif()['tahun'];
    @endphp
    <div class="mt-4 rounded-xl bg-brand-50/70 p-4 ring-1 ring-brand-200" role="alertdialog" aria-labelledby="judul-konfirmasi-perkin" aria-describedby="isi-konfirmasi-perkin"
         data-testid="konfirmasi-impor-perkin" x-data x-init="$nextTick(() => $refs.ya.focus())" x-on:keydown.escape="$wire.batalKonfirmasi()">
        <h3 id="judul-konfirmasi-perkin" class="text-sm font-semibold text-brand-900">Buat {{ $r['kegiatan'] }} draf kegiatan TA {{ $tahunAktif }}?</h3>
        <ul id="isi-konfirmasi-perkin" class="mt-2 space-y-1 text-sm text-stone-700">
            <li class="flex items-start gap-2">
                <x-ui.icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-brand-700" />
                <span>{{ $r['kegiatan'] }} kegiatan berstatus Draf dengan pagu DIPA {{ Format::rupiah($r['pagu']['DIPA'] ?? 0) }} dan Komite {{ Format::rupiah($r['pagu']['Komite'] ?? 0) }}.</span>
            </li>
            @if ($r['kode_kosong'] > 0)
                <li class="flex items-start gap-2" data-testid="konfirmasi-kode-kosong">
                    <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0 text-gold-700" />
                    <span>{{ $r['kode_kosong'] }} di antaranya belum lengkap kode SK/IKSK-nya — pilih nanti di Data Kegiatan.</span>
                </li>
            @endif
            @if ($r['dilewati'] + $r['gagal'] > 0)
                <li class="flex items-start gap-2">
                    <x-ui.icon name="info" class="mt-0.5 size-4 shrink-0 text-stone-400" />
                    <span>{{ $r['dilewati'] }} baris dilewati dan {{ $r['gagal'] }} baris gagal tidak ikut diimpor.</span>
                </li>
            @endif
            <li class="flex items-start gap-2">
                <x-ui.icon name="info" class="mt-0.5 size-4 shrink-0 text-stone-400" />
                <span>Kegiatan dibuat bertahap dan prosesnya bisa dihentikan; yang sudah dibuat tetap tersimpan sebagai Draf.</span>
            </li>
        </ul>
        <div class="mt-3 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" wire:click="batalKonfirmasi" class="btn-ghost">Kembali ke pratinjau</button>
            <button type="button" wire:click="mulaiImpor" wire:loading.attr="disabled" wire:target="mulaiImpor" class="btn-primary" x-ref="ya" data-testid="ya-impor-perkin">
                <x-ui.icon name="upload" class="size-4" /> Ya, impor {{ $r['kegiatan'] }} kegiatan
            </button>
        </div>
    </div>
@elseif ($konfirmasiBatal)
    <div class="mt-4 flex flex-col gap-3 rounded-xl bg-red-50 px-4 py-3 ring-1 ring-red-200 sm:flex-row sm:items-center sm:justify-between" role="alertdialog" aria-labelledby="judul-batal-perkin"
         data-testid="konfirmasi-batal-perkin" x-data x-on:keydown.escape="$wire.lanjutkanPratinjau()">
        <p class="text-sm text-red-800" id="judul-batal-perkin">
            <span class="font-semibold">Batalkan impor?</span>
            Pratinjau {{ $siap }} kegiatan siap dibuang dan tidak ada kegiatan yang dibuat. Berkasnya bisa diunggah lagi kapan saja.
        </p>
        <div class="flex shrink-0 justify-end gap-2">
            <button type="button" wire:click="lanjutkanPratinjau" class="btn-ghost">Lanjutkan pratinjau</button>
            <button type="button" wire:click="batalkan" class="btn-danger" data-testid="ya-batal-perkin">Ya, batalkan</button>
        </div>
    </div>
@else
    <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
        <button type="button" wire:click="batalkan" class="btn-ghost text-red-700 hover:bg-red-50" data-testid="batalkan-impor-perkin">Batalkan</button>
        <div class="flex flex-col-reverse gap-2 sm:flex-row">
            <button type="button" wire:click="ulangi" class="btn-ghost">Ganti berkas</button>
            @if ($siap === 0)
                <button type="button" class="btn-primary" disabled wire:key="mulai-impor-nonaktif" data-testid="mulai-impor">
                    <x-ui.icon name="upload" class="size-4" /> Tidak ada {{ $satuan }} yang siap diimpor
                </button>
            @else
                <button type="button" wire:click="mintaKonfirmasi" class="btn-primary" wire:key="mulai-impor-aktif" data-testid="mulai-impor">
                    <x-ui.icon name="upload" class="size-4" /> Impor {{ $siap }} {{ $satuan }}
                </button>
            @endif
        </div>
    </div>
@endif
