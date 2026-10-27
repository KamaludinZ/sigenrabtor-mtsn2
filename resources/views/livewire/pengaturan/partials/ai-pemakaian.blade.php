@use('App\Support\Kegiatan\BatasAi')

@php
    $pemakaian = $this->pemakaianHariIni();
    $saran = $this->ringkasanSaran();
    $totalHariIni = array_sum(array_column($pemakaian, 'terpakai'));
@endphp

{{-- Tab Batas pemakaian: batas permintaan per akun per hari, pemakaian hari ini, dan ringkasan riwayat saran AI. --}}
<div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" data-testid="panel-pemakaian-ai">
    <section class="min-w-0 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-pemakaian-hari-ini">
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-stone-100 px-5 py-4">
            <div>
                <h2 id="judul-pemakaian-hari-ini" class="text-sm font-semibold text-stone-900">Pemakaian hari ini</h2>
                <p class="mt-0.5 text-xs text-stone-500">{{ $totalHariIni }} permintaan dari semua akun · batas {{ BatasAi::batas() }} per akun per hari.</p>
            </div>
        </header>
        <ul class="divide-y divide-stone-100" data-testid="pemakaian-per-akun">
            @foreach ($pemakaian as $p)
                @php $persen = BatasAi::batas() ? min(100, (int) round($p['terpakai'] / BatasAi::batas() * 100)) : 0; @endphp
                <li class="px-5 py-3" data-akun="{{ $p['id'] }}">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="min-w-0 truncate font-medium text-stone-900">{{ $p['nama'] }} <span class="text-xs font-normal text-stone-500">· {{ $p['peran'] === 'admin' ? 'Admin' : 'Leading' }}</span></span>
                        <span class="shrink-0 text-xs text-stone-600 tabular-nums">{{ $p['terpakai'] }} / {{ BatasAi::batas() }}</span>
                    </div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-valuemin="0" aria-valuemax="{{ BatasAi::batas() }}" aria-valuenow="{{ $p['terpakai'] }}" aria-label="Pemakaian AI {{ $p['nama'] }}">
                        <div @class(['h-full rounded-full', 'bg-brand-600' => $p['sisa'] > 0, 'bg-gold-500' => $p['sisa'] === 0]) style="width: {{ $persen }}%"></div>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    <aside class="space-y-4">
        <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-batas-harian" data-testid="batas-harian-ai">
            <h2 id="judul-batas-harian" class="text-sm font-semibold text-stone-900">Batas per akun per hari</h2>
            <p class="mt-1 text-2xl font-semibold text-stone-900 tabular-nums">{{ BatasAi::batas() }} <span class="text-sm font-normal text-stone-500">permintaan</span></p>
            <p class="mt-1 text-xs text-stone-500">Berlaku untuk “Buat dengan AI”, minta perbaikan, dan usulan jadwal. Permintaan yang gagal tidak dihitung.</p>
            <button type="button" class="btn-secondary mt-3 px-2.5 py-1.5 text-xs" disabled title="Sedang disiapkan"><x-ui.icon name="pencil" class="size-3.5" /> Ubah batas <x-ui.segera /></button>
        </section>
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
