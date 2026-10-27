@use('App\Enums\StatusKegiatan')
@use('App\Support\Kegiatan\RiwayatStatus')

@php
    $pilihan = $this->keputusan();
    $keadaan = [
        'diperiksa' => ['Keputusan pemeriksaan', 'Kembalikan bila ada yang perlu diperbaiki — leading memperbaiki data, bukan file Word.'],
        'draf' => ['Belum diajukan', 'Leading masih menyusun kegiatan ini. Pemeriksaan dibuka setelah diajukan.'],
        'dikembalikan' => ['Menunggu perbaikan', 'Leading sedang memperbaiki data sesuai catatan, lalu mengajukan ulang.'],
        'disetujui' => ['Menunggu arsip final', 'Dokumen siap dicetak dan ditandatangani; leading mengunggah PDF bertanda tangan per sumber dana.'],
        'final' => ['Terkunci', 'Arsip final tersimpan dan data dikunci. Hanya admin yang dapat membuka kunci (tercatat siapa & kapan).'],
    ][$status->value];
    $belumLengkap = collect($this->kelengkapan)->sum(fn ($k) => ($k['kak'] > 0 ? 1 : 0) + ($k['rab'] > 0 ? 1 : 0));
    $jumlahDokumen = count($this->kelengkapan) * 2;
    $melebihi = collect($this->anggaran)->filter(fn ($a) => $a->keteranganPagu()->melebihi())->map(fn ($a) => 'RAB '.$a->sumber)->values()->all();
    $berikutnya = $status === StatusKegiatan::Diperiksa ? null : $this->berikutnya();
    $ulang = $status === StatusKegiatan::Diperiksa && $this->perbaikan?->diajukanUlang ? $this->perbaikan : null;
    $teksKonfirmasi = [
        'kembalikan' => ['Kembalikan ke leading?', 'Leading dapat memperbaiki data sesuai catatan lalu mengajukan ulang.'],
        'setujui' => ['Setujui kegiatan ini?', 'Dokumen siap dicetak untuk ditandatangani; leading lalu mengunggah arsip final.'],
    ];
@endphp

<section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 lg:col-start-2 lg:row-start-1" aria-labelledby="judul-keputusan" data-testid="panel-keputusan" data-status="{{ $status->value }}">
    <h2 id="judul-keputusan" class="flex items-center gap-1.5 text-sm font-semibold text-stone-900">
        @if ($status === StatusKegiatan::Final)
            <x-ui.icon name="lock" class="size-4 text-brand-700" />
        @elseif ($status === StatusKegiatan::Disetujui)
            <x-ui.icon name="check-circle" class="size-4 text-brand-700" />
        @endif
        {{ $keadaan[0] }}
    </h2>
    <p class="mt-0.5 text-xs text-stone-500">{{ $keadaan[1] }}</p>
    @if ($status === StatusKegiatan::Final && ($kunci = \App\Support\Kegiatan\KunciDokumen::dari($this->dataKegiatan)))
        <p class="mt-1 text-xs font-medium text-stone-700" data-testid="jejak-kunci">{{ $kunci->kalimat() }}</p>
    @endif

    @if ($ulang)
        {{-- Pengajuan ulang: catatan sebelumnya & bagian data yang berubah sejak dikembalikan (PerbaikanKegiatan). --}}
        <div class="mt-3 rounded-lg bg-sky-50 px-2.5 py-2 text-xs text-stone-700 ring-1 ring-sky-200" data-testid="pengajuan-ulang">
            <p class="font-semibold text-sky-900">Pengajuan ulang</p>
            <p class="mt-0.5">{{ $ulang->aksi === 'buka_kunci' ? 'Kunci dibuka' : 'Dikembalikan' }} {{ $waktu($ulang->pada) }} oleh {{ $ulang->oleh }}{{ $ulang->catatan ? ' dengan catatan:' : '.' }}</p>
            @if ($ulang->catatan)
                <p class="mt-1 line-clamp-4 rounded-md bg-white px-2 py-1 whitespace-pre-line ring-1 ring-sky-100">{{ $ulang->catatan }}</p>
            @endif
            @if ($ulang->diubah === [])
                <p class="mt-1.5 font-medium text-gold-700">Tidak ada data yang diubah sejak dikembalikan.</p>
            @elseif ($ulang->diubah !== null)
                <p class="mt-1.5 flex flex-wrap items-center gap-1">
                    <span class="font-medium">Diubah:</span>
                    @foreach ($ulang->diubah as $bagian)
                        <span class="rounded-full bg-white px-2 py-0.5 font-medium text-sky-900 ring-1 ring-sky-200" data-bagian-diubah>{{ $bagian }}</span>
                    @endforeach
                </p>
            @endif
        </div>
    @endif

    @if (in_array($status, [StatusKegiatan::Disetujui, StatusKegiatan::Final], true) && $this->arsip !== [])
        {{-- Arsip final per sumber dana (diunggah leading di Data Kegiatan). --}}
        <ul class="mt-3 space-y-1.5 text-xs" data-testid="arsip-pemeriksaan">
            @foreach ($this->arsip as $s => $a)
                <li class="flex items-center justify-between gap-2 rounded-lg bg-stone-50 px-2.5 py-1.5 ring-1 ring-stone-100" data-arsip="{{ $s }}">
                    <span class="min-w-0">
                        <span class="font-semibold text-stone-800">Arsip RAB {{ $s }}</span>
                        @if ($a)
                            <span class="block truncate text-stone-500">{{ $a['diunggah_oleh'] }} · {{ $waktu($a['diunggah_pada']) }}</span>
                        @else
                            <span class="block text-gold-700">Belum diunggah</span>
                        @endif
                    </span>
                    @if ($a)
                        <a href="{{ route('kegiatan.arsip', ['kegiatan' => $kegiatanId, 'sumber' => $s, 'v' => $a['diunggah_pada']]) }}" target="_blank" rel="noopener" class="shrink-0 font-semibold text-brand-700 hover:text-brand-800">Lihat PDF</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($pilihan !== [])
        @if ($status === StatusKegiatan::Diperiksa && ($jumlahDokumen === 0 || $belumLengkap > 0 || $melebihi !== []))
            <div class="mt-3 flex gap-2 rounded-lg bg-gold-50 px-2.5 py-2 text-xs text-stone-700 ring-1 ring-gold-200" data-testid="peringatan-keputusan">
                <x-ui.icon name="alert" class="mt-px size-4 shrink-0 text-gold-600" />
                <p>
                    @if ($jumlahDokumen === 0)
                        Sumber dana belum dipilih, jadi KAK/TOR dan RAB belum bisa disusun.
                    @endif
                    @if ($belumLengkap > 0)
                        {{ $belumLengkap }} dari {{ $jumlahDokumen }} dokumen belum lengkap.
                    @endif
                    @if ($melebihi !== [])
                        {{ implode(' dan ', $melebihi) }} melebihi pagu.
                    @endif
                    Selama itu dokumen tetap tercetak dengan cap DRAF.
                </p>
            </div>
        @endif

        @if ($status === StatusKegiatan::Disetujui)
            <p class="mt-3 border-t border-stone-100 pt-3 text-xs font-semibold text-stone-700">Perlu perbaikan lagi?</p>
        @endif

        <label class="mt-3 block">
            <span class="text-xs font-medium text-stone-600">
                Catatan untuk leading
                <span class="font-normal text-stone-400">{{ isset($pilihan['setujui']) ? '— wajib bila dikembalikan' : '— wajib' }}</span>
            </span>
            <textarea wire:model="catatan" rows="4" maxlength="{{ RiwayatStatus::MAKS_CATATAN }}" id="catatan-pemeriksaan"
                      @class(['form-input mt-1 text-sm', 'ring-red-300' => $errors->has('catatan')])
                      placeholder="Mis. Sasaran belum terukur; sesuaikan honor narasumber dengan SBM."
                      @error('catatan') aria-invalid="true" aria-describedby="catatan-pemeriksaan-error" @enderror></textarea>
        </label>
        @error('catatan')
            <p id="catatan-pemeriksaan-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror

        @if ($konfirmasi && isset($pilihan[$konfirmasi]))
            <div class="mt-3 rounded-lg bg-stone-50 p-3 ring-1 ring-stone-200" data-testid="konfirmasi-keputusan" wire:key="konfirmasi-{{ $konfirmasi }}">
                <p class="text-sm font-semibold text-stone-900">{{ $teksKonfirmasi[$konfirmasi][0] }}</p>
                <p class="mt-0.5 text-xs text-stone-600">{{ $teksKonfirmasi[$konfirmasi][1] }}</p>
                <div class="mt-3 flex gap-2">
                    <button type="button" wire:click="batalKonfirmasi" class="btn-ghost flex-1 justify-center px-2.5 py-1.5 text-xs">Batal</button>
                    <button type="button" wire:click="putuskan('{{ $konfirmasi }}')" wire:loading.attr="disabled" wire:target="putuskan"
                            @class(['flex-1 justify-center px-2.5 py-1.5 text-xs', 'btn-primary' => $konfirmasi === 'setujui', 'btn-secondary' => $konfirmasi === 'kembalikan'])>
                        Ya, {{ $konfirmasi === 'setujui' ? 'setujui' : 'kembalikan' }}
                    </button>
                </div>
            </div>
        @else
            <div @class(['mt-3 grid gap-2', 'grid-cols-2' => count($pilihan) === 2])>
                @isset($pilihan['kembalikan'])
                    <button type="button" wire:click="minta('kembalikan')" wire:loading.attr="disabled" wire:target="minta" class="btn-secondary justify-center text-sm">
                        <x-ui.icon name="arrow-down" class="size-4" /> Kembalikan
                    </button>
                @endisset
                @isset($pilihan['setujui'])
                    <button type="button" wire:click="minta('setujui')" wire:loading.attr="disabled" wire:target="minta" class="btn-primary justify-center text-sm">
                        <x-ui.icon name="check" class="size-4" /> Setujui
                    </button>
                @endisset
            </div>
        @endif
    @endif

    @if ($berikutnya)
        <a href="{{ route('pemeriksaan.periksa', ['kegiatan' => $berikutnya]) }}" wire:navigate class="btn-primary mt-3 w-full justify-center text-sm" data-testid="periksa-berikutnya">
            Periksa pengajuan berikutnya <x-ui.icon name="chevron-right" class="size-4" />
        </a>
    @elseif ($status !== StatusKegiatan::Diperiksa && $status !== StatusKegiatan::Draf)
        <a href="{{ route('pemeriksaan.index') }}" wire:navigate class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800">
            Kembali ke antrean <x-ui.icon name="chevron-right" class="size-3.5" />
        </a>
    @endif
</section>
