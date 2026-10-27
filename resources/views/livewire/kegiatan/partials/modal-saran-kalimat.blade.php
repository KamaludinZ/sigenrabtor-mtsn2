@php
    [$awalan, $keteranganCetak] = \App\Support\Kegiatan\BagianAi::cetakan($kunci, (string) ($dataKegiatan['nama_kegiatan'] ?? ''));
    $daftarVersi = $saran[$kunci];
    $asli = $daftarVersi[$versiDipilih]['teks'] ?? '';
    $teksSaatIni = trim((string) $form->{$b['kolom']});
    $peringatan = $this->peringatanSaran();
    $bisaTambah = in_array('tambah', \App\Livewire\Kegiatan\KalimatAi::caraPakai($kunci), true) && $teksSaatIni !== '';
    $contohInstruksi = $kunci === 'nama_cover' ? ['lebih singkat', 'lebih formal'] : ['lebih singkat', 'lebih formal', 'tambahkan moderasi beragama'];
@endphp

{{--
    Modal saran AI satu bagian ($kunci, $b): pilih salah satu 2–3 versi, sunting teksnya (state klien `suntingSaran`),
    lihat pratinjau tercetak (BagianAi::cetakan), peringatan Batas Aman (BatasAmanSaran), dan teks saat ini; Minta perbaikan
    menyusun versi baru dari teks yang disunting; Pakai versi ini (Ctrl+Enter) / Tambahkan di akhir (latar & pelaksanaan)
    mengubah isian tanpa menyimpan, atau Tolak saran.
--}}
<div class="fixed inset-0 z-50 flex" role="dialog" aria-modal="true" aria-labelledby="judul-modal-saran" data-testid="modal-saran-{{ $kunci }}" wire:key="modal-saran-{{ $kunci }}"
     x-data="{ teks: $wire.entangle('suntingSaran'), asli: @js($asli), init() { document.documentElement.classList.add('overflow-hidden') }, destroy() { document.documentElement.classList.remove('overflow-hidden') } }"
     x-on:keydown.escape.window="$wire.tutupSaran()" x-on:keydown.ctrl.enter.prevent="$wire.pakaiSaran()" x-on:keydown.meta.enter.prevent="$wire.pakaiSaran()">
    <div class="absolute inset-0 bg-stone-900/40" wire:click="tutupSaran" aria-hidden="true"></div>
    <div class="relative ml-auto flex h-full w-full max-w-xl flex-col bg-white shadow-xl" x-trap="true">
        <header class="flex items-start gap-3 border-b border-stone-200 px-4 py-3 sm:px-6">
            <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700 ring-1 ring-brand-200" aria-hidden="true">
                <x-ui.icon name="sparkles" class="size-4" />
            </span>
            <div class="min-w-0 flex-1">
                <h2 id="judul-modal-saran" class="flex flex-wrap items-center gap-2 text-sm font-semibold text-stone-900">Saran AI · {{ $b['judul'] }} <x-ai.status-kuota :status="$this->statusKuota()" ringkas /></h2>
                <p class="text-xs text-stone-500">Tercetak di {{ $b['tercetak'] }}. Hanya saran — pilih, sunting, lalu pakai atau tolak.</p>
            </div>
            <button type="button" class="btn-ghost px-2.5 py-1.5" wire:click="tutupSaran">
                <x-ui.icon name="x" class="size-4" /> Tutup
            </button>
        </header>

        <div class="flex-1 space-y-5 overflow-y-auto px-4 py-4 sm:px-6">
            @if ($galatAi[$kunci] ?? null)
                <p class="flex items-start gap-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800 ring-1 ring-red-200" role="alert" data-testid="galat-modal-saran">
                    <x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" /> {{ $galatAi[$kunci] }}
                </p>
            @endif

            {{-- Pilih versi --}}
            <fieldset>
                <legend class="text-xs font-semibold text-stone-500">Pilih versi ({{ count($daftarVersi) }})</legend>
                <div class="mt-2 grid gap-2" role="radiogroup" aria-label="Versi saran">
                    @foreach ($daftarVersi as $i => $versi)
                        <button type="button" role="radio" aria-checked="{{ $i === $versiDipilih ? 'true' : 'false' }}" wire:click="pilihVersi({{ $i }})" data-versi="{{ $i + 1 }}"
                                @class([
                                    'rounded-lg px-3 py-2 text-left ring-1 transition focus-visible:outline-2 focus-visible:outline-brand-600',
                                    'bg-brand-50 ring-2 ring-brand-600' => $i === $versiDipilih,
                                    'bg-white ring-stone-200 hover:bg-stone-50' => $i !== $versiDipilih,
                                ])>
                            <span class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="font-semibold text-stone-900">{{ $versi['judul'] }}</span>
                                @if ($versi['ciri'] !== '')
                                    <span class="rounded-full bg-stone-100 px-2 py-0.5 font-medium text-stone-600">{{ $versi['ciri'] }}</span>
                                @endif
                                @if ($i === $versiDipilih)
                                    <x-ui.icon name="check-circle" class="ml-auto size-4 text-brand-700" />
                                @endif
                            </span>
                            <span class="mt-1 line-clamp-2 block text-sm text-stone-700">{{ $versi['teks'] }}</span>
                        </button>
                    @endforeach
                </div>
            </fieldset>

            {{-- Sunting versi terpilih --}}
            <div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label for="sunting-saran" class="text-sm font-medium text-stone-800">Teks {{ mb_strtolower($daftarVersi[$versiDipilih]['judul'] ?? 'versi') }} — bisa disunting</label>
                    <span class="flex items-center gap-2 text-xs" x-show="teks !== asli" x-cloak>
                        <span class="rounded bg-gold-50 px-1.5 py-0.5 font-semibold text-gold-700 ring-1 ring-gold-200" data-testid="saran-diedit">Diedit</span>
                        <button type="button" wire:click="kembalikanTeksAi" class="font-semibold text-brand-700 hover:text-brand-800">Kembalikan teks AI</button>
                    </span>
                </div>
                @if ($kunci === 'nama_cover')
                    <input type="text" id="sunting-saran" x-model="teks" maxlength="255" autocomplete="off" class="form-input mt-1.5 uppercase tracking-wide"
                           x-on:input.capture="const [a, b] = [$el.selectionStart, $el.selectionEnd]; $el.value = $el.value.toUpperCase(); $el.setSelectionRange(a, b)">
                @else
                    <textarea id="sunting-saran" x-model="teks" rows="6" class="form-input mt-1.5 text-sm leading-relaxed" data-testid="sunting-saran"></textarea>
                @endif
                @error('suntingSaran') <p class="form-error" role="alert">{{ $message }}</p> @enderror
            </div>

            @if ($peringatan !== [])
                <div class="rounded-lg bg-gold-50 px-3 py-2 text-xs text-gold-700 ring-1 ring-gold-200" role="status" data-testid="peringatan-saran">
                    <p class="flex items-center gap-1.5 font-semibold"><x-ui.icon name="alert" class="size-3.5" /> Periksa sebelum dipakai</p>
                    <ul class="mt-1 ml-5 list-disc space-y-0.5">
                        @foreach ($peringatan as $p)
                            <li>{{ $p }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Pratinjau tercetak & teks saat ini --}}
            <div class="rounded-lg bg-stone-50 p-3 ring-1 ring-stone-200" data-testid="cetak-saran">
                <p class="text-xs font-semibold text-stone-500">Tercetak sebagai</p>
                <p class="mt-1 font-serif text-[13px] leading-relaxed whitespace-pre-line text-stone-800">{{ $awalan }}<span x-text="teks.trim() || '…'"></span></p>
                @if ($keteranganCetak)
                    <p class="mt-1.5 text-[11px] text-stone-500">{{ $keteranganCetak }}</p>
                @endif
            </div>
            <details class="rounded-lg ring-1 ring-stone-200" @if ($teksSaatIni !== '') data-testid="teks-saat-ini" @endif>
                <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-stone-600 hover:text-stone-900">Bandingkan dengan teks saat ini</summary>
                <p class="border-t border-stone-200 px-3 py-2 text-sm whitespace-pre-line text-stone-700">{{ $teksSaatIni !== '' ? $teksSaatIni : 'Isian ini masih kosong.' }}</p>
            </details>

            {{-- Instruksi perbaikan --}}
            <div data-testid="perbaikan-saran">
                <label for="instruksi-saran" class="text-sm font-medium text-stone-800">Minta perbaikan</label>
                <p class="text-xs text-stone-500">Versi baru disusun dari teks di atas (termasuk suntingan Anda).</p>
                <div class="mt-1.5 flex gap-2">
                    <input type="text" id="instruksi-saran" wire:model="instruksiSaran" maxlength="300" autocomplete="off" class="form-input min-w-0 flex-1"
                           placeholder="mis. lebih singkat, tambahkan moderasi beragama" x-on:keydown.enter.prevent="$wire.mintaPerbaikan()">
                    <button type="button" wire:click="mintaPerbaikan" wire:loading.attr="disabled" wire:target="mintaPerbaikan,mintaSaran" class="btn-secondary shrink-0" data-testid="minta-perbaikan">
                        <span wire:loading.remove wire:target="mintaPerbaikan"><x-ui.icon name="sparkles" class="size-4" /> Perbaiki</span>
                        <span wire:loading.flex wire:target="mintaPerbaikan" class="items-center gap-1.5"><x-ui.icon name="loader" class="size-4 animate-spin" /> Memperbaiki…</span>
                    </button>
                </div>
                <div class="mt-1.5 flex flex-wrap gap-1.5">
                    @foreach ($contohInstruksi as $contoh)
                        <button type="button" x-on:click="$wire.instruksiSaran = @js($contoh); $nextTick(() => document.getElementById('instruksi-saran').focus())"
                                class="rounded-full bg-stone-100 px-2.5 py-0.5 text-xs font-medium text-stone-600 hover:bg-stone-200">{{ $contoh }}</button>
                    @endforeach
                </div>
                @error('instruksiSaran') <p class="form-error" role="alert">{{ $message }}</p> @enderror
            </div>
        </div>

        <footer class="flex flex-col-reverse gap-2 border-t border-stone-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <button type="button" wire:click="tolakSaran" class="btn-ghost text-red-700 hover:bg-red-50" data-testid="tolak-saran">Tolak saran</button>
            <div class="flex flex-col-reverse gap-2 sm:flex-row">
                <button type="button" wire:click="mintaSaran('{{ $kunci }}')" wire:loading.attr="disabled" wire:target="mintaSaran" class="btn-secondary" data-testid="buat-ulang-{{ $kunci }}">
                    <span wire:loading.remove wire:target="mintaSaran"><x-ui.icon name="history" class="size-4" /> Buat ulang</span>
                    <span wire:loading.flex wire:target="mintaSaran" class="items-center gap-1.5"><x-ui.icon name="loader" class="size-4 animate-spin" /> Menyusun…</span>
                </button>
                @if ($bisaTambah)
                    <button type="button" wire:click="pakaiSaran('tambah')" wire:loading.attr="disabled" wire:target="pakaiSaran" class="btn-secondary" data-testid="tambah-saran" title="Sambungkan di akhir teks yang sudah ada">
                        <x-ui.icon name="plus" class="size-4" /> Tambahkan di akhir
                    </button>
                @endif
                <button type="button" wire:click="pakaiSaran" wire:loading.attr="disabled" wire:target="pakaiSaran" class="btn-primary" data-testid="pakai-saran" title="Pakai versi ini (Ctrl+Enter)">
                    <x-ui.icon name="check" class="size-4" /> {{ $bisaTambah ? 'Ganti dengan versi ini' : 'Pakai versi ini' }}
                </button>
            </div>
        </footer>
    </div>
</div>
