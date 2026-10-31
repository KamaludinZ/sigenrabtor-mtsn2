{{-- Dialog salin master & pengaturan dari tahun lain (SalinPengaturanTahun) untuk tahun $this->salinKe. --}}
@php
    $rencana = $this->rencanaSalin;
    $pilihanSumber = collect($this->daftar)->pluck('tahun')->reject(fn (int $t) => $t === $this->salinKe)->values();
    $nadaStatus = [
        'salin' => 'bg-brand-50 text-brand-800 ring-brand-200',
        'sebagian' => 'bg-brand-50 text-brand-800 ring-brand-200',
        'terisi' => 'bg-stone-100 text-stone-600 ring-stone-200',
        'kosong' => 'bg-white text-stone-500 ring-stone-200',
    ];
    $nadaHasil = [
        'disalin' => ['check-circle', 'text-brand-700'],
        'dilewati' => ['circle', 'text-stone-400'],
        'gagal' => ['alert', 'text-red-600'],
    ];
@endphp

<div class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" aria-labelledby="judul-salin-pengaturan" data-testid="dialog-salin-pengaturan"
     x-data x-on:keydown.escape.window="$wire.tutupSalin()">
    <div class="absolute inset-0 bg-stone-900/40" wire:click="tutupSalin" aria-hidden="true"></div>
    <div class="relative flex max-h-[90vh] w-full max-w-2xl flex-col rounded-2xl bg-white shadow-xl" x-trap.noscroll="true">
        <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
            <h2 id="judul-salin-pengaturan" class="text-base font-semibold text-stone-900">Salin master &amp; pengaturan ke TA {{ $this->salinKe }}</h2>
            <p class="mt-0.5 text-sm text-stone-500">Hanya bagian yang di TA {{ $this->salinKe }} masih kosong yang diisi — isian yang sudah ada tidak pernah tertimpa.</p>
        </header>

        <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4 sm:px-6">
            @if ($pilihanSumber->isEmpty())
                <p class="rounded-lg bg-stone-50 px-3 py-2.5 text-sm text-stone-600 ring-1 ring-stone-200" data-testid="salin-tanpa-sumber">Belum ada tahun anggaran lain untuk disalin.</p>
            @else
                <div class="flex flex-wrap items-center gap-2">
                    <label for="salin-dari" class="text-sm font-medium text-stone-700">Salin dari</label>
                    <select id="salin-dari" wire:model.live="salinDari" class="form-select w-36 tabular-nums" data-testid="salin-dari">
                        @if ($this->salinDari === null)
                            <option value="">Pilih tahun</option>
                        @endif
                        @foreach ($pilihanSumber as $tahun)
                            <option value="{{ $tahun }}">TA {{ $tahun }}</option>
                        @endforeach
                    </select>
                    @if ($rencana && $rencana['jumlah_bisa'] === 0 && ! $this->hasilSalin)
                        <span class="text-xs text-stone-500" data-testid="salin-tidak-ada">Tidak ada yang bisa disalin dari TA {{ $rencana['dari'] }}.</span>
                    @endif
                </div>

                @if ($this->hasilSalin)
                    <section class="rounded-xl bg-stone-50 px-4 py-3 ring-1 ring-stone-200" aria-labelledby="judul-hasil-salin" data-testid="hasil-salin" aria-live="polite">
                        <h3 id="judul-hasil-salin" class="text-sm font-semibold text-stone-900">{{ $this->hasilSalin['pesan'] }}</h3>
                        <ul class="mt-2 space-y-1.5 text-sm">
                            @foreach ($this->hasilSalin['hasil'] as $h)
                                <li class="flex items-start gap-2" data-testid="hasil-salin-bagian" data-kunci="{{ $h['kunci'] }}" data-status="{{ $h['status'] }}">
                                    <x-ui.icon :name="$nadaHasil[$h['status']][0]" class="mt-0.5 size-4 shrink-0 {{ $nadaHasil[$h['status']][1] }}" />
                                    <span><span class="font-medium text-stone-800">{{ $h['label'] }}</span> <span class="text-stone-500">— {{ $h['label_status'] }}{{ $h['pesan'] !== '' ? ': '.$h['pesan'] : '' }}</span></span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                @if ($rencana)
                    <fieldset>
                        <legend class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Bagian yang disalin</legend>
                        <ul class="mt-2 divide-y divide-stone-100 rounded-xl ring-1 ring-stone-200">
                            @foreach ($rencana['bagian'] as $b)
                                <li class="flex items-start gap-3 px-3 py-2.5" data-testid="bagian-salin" data-kunci="{{ $b['kunci'] }}" data-status="{{ $b['status'] }}" wire:key="bagian-salin-{{ $b['kunci'] }}">
                                    <input type="checkbox" id="pilih-salin-{{ $b['kunci'] }}" value="{{ $b['kunci'] }}" wire:model="salinBagian" @disabled(! $b['bisa'])
                                           class="mt-0.5 size-4 rounded accent-brand-700 disabled:opacity-40" data-testid="pilih-salin-{{ $b['kunci'] }}">
                                    <label for="pilih-salin-{{ $b['kunci'] }}" class="min-w-0 flex-1 text-sm">
                                        <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="font-medium {{ $b['bisa'] ? 'text-stone-900' : 'text-stone-500' }}">{{ $b['label'] }}</span>
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $nadaStatus[$b['status']] }}">{{ $b['label_status'] }}</span>
                                        </span>
                                        <span class="mt-0.5 block text-xs text-stone-500">{{ $b['keterangan'] }}</span>
                                        <span class="mt-1 block text-xs text-stone-600 tabular-nums">
                                            TA {{ $rencana['dari'] }}: {{ $b['sumber'] ?? 'kosong' }}
                                            <span class="text-stone-400">→</span>
                                            TA {{ $rencana['ke'] }}: {{ $b['tujuan'] ?? 'kosong' }}
                                        </span>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                        @error('salinBagian') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </fieldset>
                    <p class="text-xs text-stone-500">SK RKTM/RKAM, kegiatan, Data Pagu, dan nomor urut dokumen tidak ikut disalin — semuanya milik tahun masing-masing.</p>
                @endif
            @endif
        </div>

        <footer class="flex flex-wrap justify-end gap-2 border-t border-stone-100 px-5 py-3 sm:px-6">
            <button type="button" class="btn-ghost" wire:click="tutupSalin" data-testid="tutup-salin">{{ $this->hasilSalin ? 'Selesai' : 'Batal' }}</button>
            @if ($rencana && $rencana['jumlah_bisa'] > 0)
                <button type="button" class="btn-primary" wire:click="salinPengaturan" wire:loading.attr="disabled" wire:target="salinPengaturan" data-testid="jalankan-salin">
                    <x-ui.icon name="copy" class="size-4" />
                    <span wire:loading.remove wire:target="salinPengaturan">Salin dari TA {{ $rencana['dari'] }}</span>
                    <span wire:loading wire:target="salinPengaturan">Menyalin…</span>
                </button>
            @endif
        </footer>
    </div>
</div>
