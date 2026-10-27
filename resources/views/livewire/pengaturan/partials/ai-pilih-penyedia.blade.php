@use('App\Support\Pengaturan\PenyediaAi')

@php
    $kesiapan = $this->kesiapanPenyedia();
    $kolom = [
        'aktif' => ['Penyedia aktif', 'Dipakai untuk setiap permintaan AI.', 'Tanpa penyedia — saran contoh (tiruan)'],
        'cadangan' => ['Penyedia cadangan', 'Dipakai otomatis bila penyedia aktif gagal.', 'Tanpa cadangan'],
    ];
@endphp

{{--
    Pemilihan penyedia aktif & cadangan: dua daftar pilihan (penyedia yang belum siap nonaktif dengan alasannya), tombol
    tukar, dan alur cadangan. Simpan lewat simpanPilihan() (AturanPenyediaAi::pilih).
--}}
<section class="rounded-2xl bg-white shadow-xs ring-2 ring-brand-200" aria-labelledby="judul-pilih-penyedia" data-testid="pilih-penyedia" wire:key="pilih-penyedia"
         x-data="formAi('pilihan', 'pilihan')" x-on:keydown.escape="$wire.batalPilihan()">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-100 px-5 py-4">
        <div class="min-w-0">
            <h2 id="judul-pilih-penyedia" class="text-sm font-semibold text-stone-900">Pilih penyedia aktif &amp; cadangan</h2>
            <p class="mt-0.5 text-xs text-stone-500">Hanya penyedia yang sudah dikonfigurasi yang bisa dipilih.</p>
        </div>
        <button type="button" wire:click="tukarPilihan" class="btn-ghost px-2.5 py-1.5 text-xs" @disabled(! $pilihan['aktif'] || ! $pilihan['cadangan']) data-testid="tukar-penyedia">
            <x-ui.icon name="arrow-down" class="size-3.5" /><x-ui.icon name="arrow-up" class="-ml-1.5 size-3.5" /> Tukar aktif ↔ cadangan
        </button>
    </header>

    <div class="grid gap-5 px-5 py-4 md:grid-cols-2">
        @foreach ($kolom as $peran => [$judul, $ket, $tanpa])
            <fieldset data-testid="pilihan-{{ $peran }}">
                <legend class="text-sm font-medium text-stone-800">{{ $judul }}</legend>
                <p class="text-xs text-stone-500">{{ $ket }}</p>
                <div class="mt-2 space-y-1.5" role="radiogroup">
                    <label class="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm ring-1 ring-stone-200 has-checked:bg-brand-50 has-checked:ring-2 has-checked:ring-brand-600">
                        <input type="radio" name="pilih-{{ $peran }}" value="" wire:model.live="pilihan.{{ $peran }}" class="size-4 border-stone-300 text-brand-700 focus:ring-brand-600">
                        <span class="text-stone-700">{{ $tanpa }}</span>
                    </label>
                    @foreach (PenyediaAi::DAFTAR as $kunci => $p)
                        @php
                            $siap = $kesiapan[$kunci]['siap'];
                            $dipakaiLain = $pilihan[$peran === 'aktif' ? 'cadangan' : 'aktif'] === $kunci;
                        @endphp
                        <label @class([
                                'flex items-start gap-2 rounded-lg px-3 py-2 text-sm ring-1 ring-stone-200',
                                'cursor-pointer has-checked:bg-brand-50 has-checked:ring-2 has-checked:ring-brand-600' => $siap,
                                'cursor-not-allowed bg-stone-50 opacity-70' => ! $siap,
                            ]) data-pilih-penyedia="{{ $kunci }}" data-siap="{{ $siap ? 'ya' : 'tidak' }}">
                            <input type="radio" name="pilih-{{ $peran }}" value="{{ $kunci }}" wire:model.live="pilihan.{{ $peran }}" @disabled(! $siap)
                                   class="mt-0.5 size-4 border-stone-300 text-brand-700 focus:ring-brand-600 disabled:opacity-50">
                            <span class="min-w-0">
                                <span class="block font-medium text-stone-900">{{ $p['nama'] }}</span>
                                <span class="block text-xs {{ $siap ? 'text-stone-500' : 'text-gold-700' }}">
                                    @if (! $siap)
                                        Perlu {{ implode(', ', $kesiapan[$kunci]['kurang']) }}{{ $p['butuh_kunci'] ? ' — atur di kartu API cloud' : ' — atur di kartu Server sekolah' }}
                                    @elseif ($dipakaiLain)
                                        Dipilih sebagai {{ $peran === 'aktif' ? 'cadangan' : 'aktif' }} — memilihnya di sini akan ditolak
                                    @else
                                        {{ PenyediaAi::JENIS[$p['jenis']] }} · siap dipakai
                                    @endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('pilihan.'.$peran) <p class="form-error" role="alert">{{ $message }}</p> @enderror
            </fieldset>
        @endforeach
    </div>

    <ol class="mx-5 flex flex-wrap items-center gap-x-2 gap-y-1 rounded-lg bg-stone-50 px-3 py-2 text-xs text-stone-600 ring-1 ring-stone-200" aria-label="Alur permintaan AI" data-testid="alur-penyedia">
        <li class="font-medium text-stone-800">Permintaan</li>
        <li aria-hidden="true"><x-ui.icon name="chevron-right" class="size-3.5 text-stone-300" /></li>
        <li>{{ PenyediaAi::nama($pilihan['aktif'] ?: null) ?? 'Saran contoh (tiruan)' }}</li>
        @if ($pilihan['aktif'] && $pilihan['cadangan'])
            <li aria-hidden="true"><x-ui.icon name="chevron-right" class="size-3.5 text-stone-300" /></li>
            <li>bila gagal: {{ PenyediaAi::nama($pilihan['cadangan']) }}</li>
        @endif
        @if ($pilihan['aktif'])
            <li aria-hidden="true"><x-ui.icon name="chevron-right" class="size-3.5 text-stone-300" /></li>
            <li>bila {{ $pilihan['cadangan'] ? 'keduanya' : '' }} gagal: pesan di layar + Coba lagi</li>
        @endif
    </ol>

    <div class="mt-4 flex flex-col-reverse gap-2 border-t border-stone-100 px-5 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
        @include('livewire.pengaturan.partials.ai-status-simpan', ['bagian' => 'pilihan'])
        <button type="button" wire:click="batalPilihan" class="btn-ghost">Batal</button>
        <button type="button" wire:click="simpanPilihan" wire:loading.attr="disabled" wire:target="simpanPilihan" class="btn-primary" data-testid="simpan-pilihan">
            <x-ui.icon name="save" class="size-4" /> Simpan pilihan
        </button>
    </div>
</section>
