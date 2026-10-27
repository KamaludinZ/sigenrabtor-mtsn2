{{-- Hasil duplikat terakhir: draf baru dengan tautan & catatan yang perlu dicek. --}}
<section class="mt-6 rounded-2xl bg-brand-50/60 p-4 ring-1 ring-brand-200 sm:p-5" aria-labelledby="judul-hasil-duplikat" data-testid="hasil-duplikat" role="status">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <h2 id="judul-hasil-duplikat" class="flex items-center gap-2 text-sm font-semibold text-brand-900">
            <x-ui.icon name="check-circle" class="size-4" /> {{ count($hasilDuplikat) }} draf baru TA {{ $tahunAktif }}
        </h2>
        <button type="button" wire:click="tutupHasil" class="btn-ghost px-2 py-1 text-xs">Tutup</button>
    </div>
    <ul class="mt-2 space-y-2 text-sm">
        @foreach ($hasilDuplikat as $h)
            <li class="rounded-lg bg-white px-3 py-2 ring-1 ring-brand-100" data-draf-baru="{{ $h['id'] }}">
                <a href="{{ route('kegiatan.edit', ['kegiatan' => $h['id']]) }}" wire:navigate class="font-medium text-stone-900 hover:text-brand-800">{{ $h['nama'] }}</a>
                @foreach ($h['catatan'] as $c)
                    <p @class(['mt-0.5 text-xs', 'text-gold-700' => $c['jenis'] !== 'pagu', 'text-stone-500' => $c['jenis'] === 'pagu'])>{{ $c['teks'] }}</p>
                @endforeach
                @if (in_array('kode', array_column($h['catatan'], 'jenis'), true))
                    <a href="{{ route('kegiatan.kode', ['kegiatan' => $h['id']]) }}" wire:navigate class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800" data-testid="pilih-ulang-kode">
                        Pilih ulang kode <x-ui.icon name="chevron-right" class="size-3.5" />
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</section>
