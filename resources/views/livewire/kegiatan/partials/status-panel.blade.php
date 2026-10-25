@php
    $statusEnum = $this->statusKegiatan();
    $langkahAktif = $statusEnum->langkah();
    $gayaTombol = ['primary' => 'btn-primary', 'secondary' => 'btn-secondary', 'danger' => 'btn-danger'];
@endphp

{{-- Panel status dokumen: posisi di alur, keterangan, aksi sesuai peran, dan riwayat. --}}
<div class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" data-testid="panel-status">
    <div class="flex items-center justify-between gap-2">
        <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Status dokumen</p>
        <x-ui.status-badge :status="$status" />
    </div>

    <ol class="mt-3 grid grid-cols-4 gap-1" aria-label="Alur status dokumen">
        @foreach (['Draf', 'Diperiksa', 'Disetujui', 'Final'] as $i => $label)
            <li class="flex flex-col gap-1" @if ($i === $langkahAktif) aria-current="step" @endif>
                <span @class([
                    'h-1.5 rounded-full',
                    'bg-gold-400' => $i === 0 && $statusEnum === \App\Enums\StatusKegiatan::Dikembalikan,
                    'bg-brand-600' => $i <= $langkahAktif && ! ($i === 0 && $statusEnum === \App\Enums\StatusKegiatan::Dikembalikan),
                    'bg-stone-200' => $i > $langkahAktif,
                ])></span>
                <span @class(['text-[10px] font-medium', 'text-brand-800' => $i === $langkahAktif, 'text-stone-400' => $i !== $langkahAktif])>{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    <p class="mt-3 text-xs leading-relaxed text-stone-600">{{ $statusEnum->deskripsi() }}</p>

    @if ($kegiatanId === null)
        <p class="mt-2 text-xs text-stone-500">Simpan draf terlebih dahulu untuk mengajukan pemeriksaan.</p>
    @endif

    @if ($this->aksiStatus !== [])
        <div class="mt-3 space-y-2">
            @foreach ($this->aksiStatus as $kunci => $aksi)
                <div x-data="{ yakin: false }" wire:key="aksi-{{ $kunci }}-{{ $status }}">
                    <button type="button" x-show="! yakin" x-on:click="yakin = true" class="{{ $gayaTombol[$aksi['gaya']] }} w-full">
                        <x-ui.icon :name="$aksi['ikon']" class="size-4" />
                        {{ $aksi['label'] }}
                    </button>
                    <div x-cloak x-show="yakin" class="rounded-lg bg-stone-50 p-2.5 ring-1 ring-stone-200">
                        <p class="text-xs text-stone-600">{{ $aksi['konfirmasi'] }}</p>
                        <div class="mt-2 flex gap-2">
                            {{-- Konfirmasi ditutup saat dikirim: bila aksi ditolak validasi, tombol aksi kembali tersedia. --}}
                            <button type="button" wire:click="ubahStatus('{{ $kunci }}')" x-on:click="yakin = false" wire:loading.attr="disabled"
                                    class="{{ $gayaTombol[$aksi['gaya']] }} flex-1 px-2.5 py-1.5 text-xs">
                                Ya, {{ mb_strtolower($aksi['label']) }}
                            </button>
                            <button type="button" x-on:click="yakin = false" class="btn-ghost px-2.5 py-1.5 text-xs">Batal</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($riwayatStatus !== [])
        <div class="mt-4 border-t border-stone-100 pt-3">
            <p class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Riwayat</p>
            <ol class="mt-2 space-y-2">
                @foreach (array_slice(array_reverse($riwayatStatus), 0, 4) as $jejak)
                    <li class="flex gap-2 text-xs">
                        <span class="mt-1 size-1.5 shrink-0 rounded-full bg-stone-300"></span>
                        <span class="min-w-0">
                            <span class="font-medium text-stone-700">{{ \App\Enums\StatusKegiatan::labelRiwayat($jejak['aksi']) }}</span>
                            <span class="block text-stone-500">
                                {{ $jejak['oleh'] }} · {{ \Illuminate\Support\Carbon::parse($jejak['pada'])->locale('id')->translatedFormat('j M Y, H.i') }}
                            </span>
                        </span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</div>
