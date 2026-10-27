@php
    $perbaikan = $this->perbaikan();
@endphp

{{--
    Perbaikan di data (PRD §16): kegiatan dikembalikan admin → catatan pemeriksa, tempat memperbaikinya (isian aplikasi,
    bukan file Word), dan bagian tersimpan yang sudah berubah sejak dikembalikan. Pengajuan ulang lewat panel status.
--}}
@if ($perbaikan)
    <div class="rounded-xl bg-gold-50 px-4 py-3 text-sm text-stone-800 ring-1 ring-gold-200" role="status" data-testid="banner-perbaikan">
        <div class="flex items-start gap-3">
            <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0 text-gold-600" />
            <div class="min-w-0 flex-1">
                <p class="font-semibold">
                    {{ $perbaikan->aksi === 'buka_kunci' ? 'Kunci dibuka untuk perbaikan' : 'Dikembalikan untuk perbaikan' }}
                    <span class="font-normal text-stone-600">· {{ $perbaikan->oleh }}, {{ \Illuminate\Support\Carbon::parse($perbaikan->pada)->locale('id')->translatedFormat('j F Y, H.i') }}</span>
                </p>
                @if ($perbaikan->catatan)
                    <blockquote class="mt-2 rounded-lg bg-white px-3 py-2 whitespace-pre-line text-stone-800 ring-1 ring-gold-200" data-testid="catatan-perbaikan">{{ $perbaikan->catatan }}</blockquote>
                @endif
                <p class="mt-2 text-xs text-stone-600">
                    Perbaiki datanya di aplikasi — bukan di file Word — maka KAK/TOR dan RAB tersusun ulang otomatis. Setelah tersimpan, ajukan ulang dari panel status.
                </p>
                <p class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-semibold">
                    <span class="font-medium text-stone-500">Perbaiki di:</span>
                    <a href="#identitas" class="text-brand-700 hover:text-brand-800">Data Kegiatan</a>
                    <a href="{{ route('kegiatan.kode', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="text-brand-700 hover:text-brand-800">Kode & Klasifikasi</a>
                    <a href="{{ route('kegiatan.rab', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="text-brand-700 hover:text-brand-800">RAB</a>
                    <a href="{{ route('kegiatan.matrik', ['kegiatan' => $kegiatanId]) }}" wire:navigate class="text-brand-700 hover:text-brand-800">Matrik & Jadwal</a>
                </p>
                @if ($perbaikan->diubah !== null)
                    <div class="mt-2 flex flex-wrap items-center gap-1.5 border-t border-gold-200 pt-2 text-xs" data-testid="perubahan-perbaikan">
                        @if ($perbaikan->diubah === [])
                            <span class="text-stone-600">Belum ada perubahan tersimpan sejak dikembalikan.</span>
                        @else
                            <span class="font-medium text-stone-600">Sudah diubah sejak dikembalikan:</span>
                            @foreach ($perbaikan->diubah as $bagian)
                                <span class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-0.5 font-medium text-brand-800 ring-1 ring-brand-200" data-bagian-diubah>
                                    <x-ui.icon name="check" class="size-3" /> {{ $bagian }}
                                </span>
                            @endforeach
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
