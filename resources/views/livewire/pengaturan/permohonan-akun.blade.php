{{--
    Permohonan pendaftaran akun (Pengaturan\PermohonanAkun): menunggu → Setujui / Tolak beralasan; riwayat keputusan.
--}}
@php
    $daftar = $this->daftar;
    $waktu = fn (?string $iso) => $iso ? \App\Support\Format::tanggal(substr($iso, 0, 10)).' · '.\Carbon\CarbonImmutable::parse($iso)->setTimezone(config('app.timezone'))->format('H.i') : '—';
@endphp

<div>
    @if ($daftar['jumlah_menunggu'] > 0 || $daftar['diputuskan'] !== [])
        <section class="mb-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-permohonan-akun" data-testid="permohonan-akun" data-jumlah="{{ $daftar['jumlah_menunggu'] }}">
            <header class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 px-5 py-4">
                <div class="min-w-0">
                    <h2 id="judul-permohonan-akun" class="flex items-center gap-2 text-sm font-semibold text-stone-900">
                        Permohonan pendaftaran
                        @if ($daftar['jumlah_menunggu'] > 0)
                            <span class="rounded-full bg-gold-500 px-2 py-0.5 text-[11px] font-semibold text-white">{{ $daftar['jumlah_menunggu'] }} menunggu</span>
                        @endif
                    </h2>
                    <p class="mt-0.5 text-xs text-stone-500">Setujui untuk membuat akun leading dengan leading yang diminta; pemohon masuk dengan sandi yang dibuatnya sendiri.</p>
                </div>
            </header>

            @if ($daftar['menunggu'] === [])
                <p class="px-5 py-4 text-sm text-stone-500" data-testid="permohonan-kosong">Tidak ada permohonan yang menunggu.</p>
            @else
                <ul class="divide-y divide-stone-100" role="list">
                    @foreach ($daftar['menunggu'] as $p)
                        <li class="px-5 py-4" wire:key="permohonan-{{ $p['id'] }}" data-testid="permohonan" data-id="{{ $p['id'] }}">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0 text-sm">
                                    <p class="font-semibold text-stone-900">{{ $p['nama'] }}</p>
                                    <p class="truncate text-xs text-stone-500">{{ $p['surel'] }}{{ $p['jabatan'] ? ' · '.$p['jabatan'] : '' }}{{ $p['nip_format'] ? ' · NIP '.$p['nip_format'] : '' }}</p>
                                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                                        @foreach ($p['leading'] as $l)
                                            <span class="rounded-md bg-stone-50 px-2 py-0.5 text-xs text-stone-700 ring-1 ring-stone-200">{{ $l['kode'] }} {{ $l['nama_unit'] }}</span>
                                        @endforeach
                                    </div>
                                    @if ($p['catatan'])
                                        <p class="mt-1.5 text-xs text-stone-600 italic">“{{ $p['catatan'] }}”</p>
                                    @endif
                                    <p class="mt-1 text-[11px] text-stone-400">Diajukan {{ $waktu($p['diajukan_pada']) }}</p>
                                </div>
                                @if ($tolakId !== $p['id'])
                                    <div class="flex shrink-0 gap-2">
                                        <button type="button" wire:click="mulaiTolak('{{ $p['id'] }}')" class="btn-ghost" data-testid="tolak-permohonan">Tolak</button>
                                        <button type="button" wire:click="setujui('{{ $p['id'] }}')" wire:loading.attr="disabled" wire:target="setujui('{{ $p['id'] }}')" class="btn-primary" data-testid="setujui-permohonan">Setujui</button>
                                    </div>
                                @endif
                            </div>
                            @if ($tolakId === $p['id'])
                                <form wire:submit="tolak" class="mt-3 space-y-2" data-testid="form-tolak-permohonan" x-on:keydown.escape.prevent="$wire.batalTolak()">
                                    <label for="alasan-tolak" class="form-label">Alasan penolakan</label>
                                    <textarea id="alasan-tolak" wire:model="alasan" rows="2" maxlength="{{ \App\Support\Akun\KeputusanPendaftaran::MAKS_ALASAN }}" class="form-input" autofocus
                                              placeholder="Mis. surel bukan surel madrasah — ajukan ulang dengan surel resmi." data-testid="alasan-tolak" @error('alasan') aria-invalid="true" aria-describedby="alasan-tolak-error" @enderror></textarea>
                                    @error('alasan')<p id="alasan-tolak-error" class="form-error" role="alert">{{ $message }}</p>@enderror
                                    <div class="flex justify-end gap-2">
                                        <button type="button" wire:click="batalTolak" class="btn-ghost">Batal</button>
                                        <button type="submit" class="btn-danger" data-testid="kirim-tolak">Tolak permohonan</button>
                                    </div>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($daftar['diputuskan'] !== [])
                <details class="border-t border-stone-100 px-5 py-3 text-sm" data-testid="riwayat-permohonan">
                    <summary class="cursor-pointer text-xs font-semibold text-stone-600">Keputusan terakhir ({{ count($daftar['diputuskan']) }})</summary>
                    <ul class="mt-2 space-y-1.5" role="list">
                        @foreach ($daftar['diputuskan'] as $p)
                            <li class="flex flex-wrap items-baseline justify-between gap-2 text-xs" data-status="{{ $p['status'] }}">
                                <span class="text-stone-700">{{ $p['nama'] }} <span class="text-stone-400">{{ $p['surel'] }}</span></span>
                                <span @class(['font-medium', 'text-brand-700' => $p['status'] === 'disetujui', 'text-red-700' => $p['status'] === 'ditolak'])>{{ $p['label_status'] }} · {{ $waktu($p['diputuskan_pada']) }}{{ $p['alasan'] ? ' — '.$p['alasan'] : '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </section>
    @endif
</div>
