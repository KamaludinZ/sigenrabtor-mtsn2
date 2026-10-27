@use('App\Support\Kegiatan\BatasAi')

@php
    $pemakaian = $this->pemakaianHariIni();
    $saran = $this->ringkasanSaran();
    $totalHariIni = array_sum(array_column($pemakaian, 'terpakai'));
    $kapasitas = array_sum(array_column($pemakaian, 'batas'));
    $batasUmum = BatasAi::batas();
    [$minBatas, $maksBatas] = BatasAi::RENTANG;
@endphp

{{--
    Tab Batas pemakaian: batas umum permintaan AI per akun per hari (preset + isian), batas khusus per akun (pengecualian),
    pemakaian hari ini tiap akun aktif terhadap batasnya, perkiraan kapasitas harian, dan ringkasan riwayat saran AI.
--}}
<div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" data-testid="panel-pemakaian-ai">
    <section class="min-w-0 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-pemakaian-hari-ini">
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 px-5 py-4">
            <div>
                <h2 id="judul-pemakaian-hari-ini" class="text-sm font-semibold text-stone-900">Pemakaian hari ini</h2>
                <p class="mt-0.5 text-xs text-stone-500">{{ $totalHariIni }} permintaan dari semua akun · batas umum {{ $batasUmum }} per akun per hari.</p>
            </div>
        </header>
        <ul class="divide-y divide-stone-100" data-testid="pemakaian-per-akun">
            @foreach ($pemakaian as $p)
                @php $persen = $p['batas'] ? min(100, (int) round($p['terpakai'] / $p['batas'] * 100)) : 0; @endphp
                <li class="px-5 py-3" data-akun="{{ $p['id'] }}" wire:key="akun-ai-{{ $p['id'] }}-{{ $khususTerbuka === $p['id'] ? 'ubah' : 'lihat' }}">
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-sm">
                        <span class="min-w-0 truncate font-medium text-stone-900">{{ $p['nama'] }} <span class="text-xs font-normal text-stone-500">· {{ $p['peran'] === 'admin' ? 'Admin' : 'Leading' }}</span></span>
                        <span class="flex shrink-0 items-center gap-2 text-xs">
                            @if ($p['khusus'] !== null)
                                <span class="rounded-full bg-gold-50 px-2 py-0.5 font-semibold text-gold-700 ring-1 ring-gold-200 ring-inset" data-testid="khusus-{{ $p['id'] }}">Khusus {{ $p['khusus'] }}</span>
                            @endif
                            <span class="text-stone-600 tabular-nums" data-testid="terpakai-{{ $p['id'] }}">{{ $p['terpakai'] }} / {{ $p['batas'] }}</span>
                            @unless ($khususTerbuka === $p['id'])
                                <button type="button" wire:click="ubahKhusus({{ $p['id'] }})" class="font-semibold text-brand-700 hover:text-brand-800" data-testid="ubah-khusus-{{ $p['id'] }}">Atur</button>
                            @endunless
                        </span>
                    </div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $p['batas'] }}" aria-valuenow="{{ $p['terpakai'] }}" aria-label="Pemakaian AI {{ $p['nama'] }}">
                        <div @class(['h-full rounded-full', 'bg-brand-600' => $p['sisa'] > 0, 'bg-gold-500' => $p['sisa'] === 0]) style="width: {{ $persen }}%"></div>
                    </div>

                    @if ($khususTerbuka === $p['id'])
                        <form wire:submit="simpanKhusus" class="mt-3 flex flex-wrap items-end gap-2 rounded-lg bg-stone-50 p-3 ring-1 ring-stone-200" data-testid="formulir-khusus" x-data x-on:keydown.escape="$wire.batalKhusus()">
                            <label class="block">
                                <span class="text-xs font-medium text-stone-700">Batas khusus per hari</span>
                                <input type="number" wire:model="isianKhusus" min="{{ $minBatas }}" max="{{ $maksBatas }}" class="form-input mt-1 w-28 tabular-nums" data-testid="isian-khusus">
                            </label>
                            <button type="submit" class="btn-primary px-2.5 py-1.5 text-xs" data-testid="simpan-khusus">Simpan</button>
                            @if ($p['khusus'] !== null)
                                <button type="button" wire:click="hapusKhusus({{ $p['id'] }})" class="btn-ghost px-2.5 py-1.5 text-xs" data-testid="hapus-khusus">Pakai batas umum</button>
                            @endif
                            <button type="button" wire:click="batalKhusus" class="btn-ghost px-2.5 py-1.5 text-xs">Batal</button>
                            @error('isianKhusus') <p class="form-error w-full" role="alert">{{ $message }}</p> @enderror
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>

    <aside class="space-y-4">
        <form wire:submit="simpanBatas" class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-batas-harian" data-testid="batas-harian-ai">
            <h2 id="judul-batas-harian" class="text-sm font-semibold text-stone-900">Batas umum per akun per hari</h2>
            <p class="mt-1 text-xs text-stone-500">Berlaku untuk “Buat dengan AI”, minta perbaikan, dan usulan jadwal. Permintaan yang gagal tidak dihitung.</p>
            <div class="mt-3 grid grid-cols-3 gap-1 rounded-lg bg-stone-100 p-0.5 text-xs font-semibold" role="radiogroup" aria-label="Pilihan cepat batas">
                @foreach (BatasAi::PRESET as $n)
                    <button type="button" role="radio" aria-checked="{{ (int) $isianBatas === $n ? 'true' : 'false' }}" wire:click="pilihPresetBatas({{ $n }})"
                            @class(['rounded-md px-2 py-1.5 tabular-nums', 'bg-white text-stone-900 shadow-xs' => (int) $isianBatas === $n, 'text-stone-500 hover:text-stone-800' => (int) $isianBatas !== $n])>{{ $n }}</button>
                @endforeach
            </div>
            <label class="mt-3 flex items-center gap-2 text-sm">
                <input type="number" wire:model.live.debounce.400ms="isianBatas" min="{{ $minBatas }}" max="{{ $maksBatas }}" class="form-input w-24 tabular-nums" aria-label="Batas umum per akun per hari" data-testid="isian-batas">
                <span class="text-stone-600">permintaan per akun per hari</span>
            </label>
            @error('isianBatas') <p class="form-error" role="alert">{{ $message }}</p> @enderror
            <p class="mt-2 text-xs text-stone-500" data-testid="kapasitas-ai">Kapasitas harian saat ini: ± {{ $kapasitas }} permintaan untuk {{ count($pemakaian) }} akun aktif.</p>
            <button type="submit" class="btn-primary mt-3 w-full" wire:loading.attr="disabled" wire:target="simpanBatas" data-testid="simpan-batas">
                <x-ui.icon name="save" class="size-4" /> Simpan batas umum
            </button>
        </form>
        <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-ringkasan-saran" data-testid="ringkasan-saran-pengaturan">
            <h2 id="judul-ringkasan-saran" class="text-sm font-semibold text-stone-900">Riwayat saran AI</h2>
            <dl class="mt-2 grid grid-cols-2 gap-2 text-xs">
                <div><dt class="text-stone-500">Permintaan</dt><dd class="text-lg font-semibold text-stone-900 tabular-nums">{{ $saran['permintaan'] }}</dd></div>
                <div><dt class="text-stone-500">Saran dipakai</dt><dd class="text-lg font-semibold text-stone-900 tabular-nums">{{ $saran['dipakai'] }}</dd></div>
            </dl>
            <a href="{{ route('saran_ai.index') }}" wire:navigate class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800">Buka Riwayat Saran AI <x-ui.icon name="chevron-right" class="size-3.5" /></a>
        </section>
    </aside>
</div>
