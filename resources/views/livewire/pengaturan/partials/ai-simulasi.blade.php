@use('App\Support\Kegiatan\KegagalanAi')

@php
    $aktif = $ai['simulasi_gagal'] ?? null;
@endphp

{{--
    Simulasi kegagalan penyedia: admin memilih jenis kegagalan (KegagalanAi) dan cakupannya untuk melihat pesan yang
    diterima leading — tanpa mematikan server — serta daftar pesan per jenis. RantaiPenyediaAi menerapkannya.
--}}
<section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-simulasi-ai" data-testid="simulasi-ai">
    <header class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-100 px-5 py-4">
        <div class="min-w-0">
            <h2 id="judul-simulasi-ai" class="text-sm font-semibold text-stone-900">Simulasi kegagalan penyedia</h2>
            <p class="mt-0.5 text-xs text-stone-500">Coba bagaimana pesan kegagalan tampil bagi pengguna dan apakah penyedia cadangan mengambil alih — tanpa mematikan server.</p>
        </div>
        <span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset', 'bg-gold-50 text-gold-700 ring-gold-200' => $aktif, 'bg-stone-100 text-stone-600 ring-stone-200' => ! $aktif]) data-testid="status-simulasi">{{ $aktif ? 'Aktif' : 'Mati' }}</span>
    </header>

    <form wire:submit="simpanSimulasi" class="grid gap-4 px-5 py-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]" x-data="formAi('isianSimulasi', 'simulasi')">
        <x-ui.field label="Jenis kegagalan" for="simulasi-jenis" error="isianSimulasi.jenis">
            <select id="simulasi-jenis" wire:model.live="isianSimulasi.jenis" class="form-input">
                <option value="">Tidak ada — permintaan berjalan normal</option>
                @foreach (KegagalanAi::JENIS as $kunci => [$judul])
                    <option value="{{ $kunci }}">{{ $judul }}</option>
                @endforeach
            </select>
        </x-ui.field>
        <fieldset>
            <legend class="text-sm font-medium text-stone-800">Yang gagal</legend>
            <div class="mt-1.5 space-y-1.5 text-sm" role="radiogroup">
                <label class="flex items-start gap-2"><input type="radio" value="aktif" wire:model="isianSimulasi.cakupan" class="mt-0.5 size-4 border-stone-300 text-brand-700 focus:ring-brand-600"> <span>Penyedia aktif saja <span class="block text-xs text-stone-500">Menguji penyedia cadangan (bila ada) mengambil alih.</span></span></label>
                <label class="flex items-start gap-2"><input type="radio" value="semua" wire:model="isianSimulasi.cakupan" class="mt-0.5 size-4 border-stone-300 text-brand-700 focus:ring-brand-600"> <span>Semua penyedia <span class="block text-xs text-stone-500">Pengguna melihat pesan kegagalan.</span></span></label>
            </div>
        </fieldset>

        @if ($isianSimulasi['jenis'] !== '')
            <div class="rounded-lg bg-stone-50 p-3 text-xs ring-1 ring-stone-200 md:col-span-2" data-testid="pratinjau-pesan-gagal">
                <p class="font-semibold text-stone-700">Pesan yang dilihat pengguna</p>
                <p class="mt-1 flex items-start gap-2 rounded-md bg-red-50 px-2.5 py-1.5 text-red-800 ring-1 ring-red-200"><x-ui.icon name="alert" class="mt-px size-3.5 shrink-0" /> {{ KegagalanAi::pesan($isianSimulasi['jenis']) }}</p>
                <p class="mt-1.5 text-stone-500">Saran untuk admin: {{ KegagalanAi::saranAdmin($isianSimulasi['jenis']) }} {{ KegagalanAi::bisaCobaLagi($isianSimulasi['jenis']) ? '' : 'Mencoba lagi tidak membantu sampai pengaturannya diperbaiki.' }}</p>
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-end gap-2 md:col-span-2">
            @include('livewire.pengaturan.partials.ai-status-simpan', ['bagian' => 'simulasi'])
            @if ($aktif)
                <button type="button" wire:click="matikanSimulasi" class="btn-ghost" data-testid="matikan-simulasi">Matikan simulasi</button>
            @endif
            <button type="submit" class="btn-secondary" wire:loading.attr="disabled" wire:target="simpanSimulasi" data-testid="simpan-simulasi">{{ $isianSimulasi['jenis'] === '' ? 'Simpan (normal)' : 'Terapkan simulasi' }}</button>
        </div>
    </form>

    <details class="border-t border-stone-100" data-testid="daftar-pesan-gagal">
        <summary class="cursor-pointer px-5 py-3 text-xs font-semibold text-stone-600 hover:text-stone-900">Semua pesan kegagalan ({{ count(KegagalanAi::JENIS) }})</summary>
        <ul class="divide-y divide-stone-100 border-t border-stone-100">
            @foreach (KegagalanAi::JENIS as $kunci => [$judul, $pesan, $saran, $coba])
                <li class="grid gap-1 px-5 py-2.5 text-xs sm:grid-cols-[11rem_minmax(0,1fr)]" data-jenis-gagal="{{ $kunci }}">
                    <span class="font-medium text-stone-900">{{ $judul }} <span class="block font-normal text-stone-500">{{ $coba ? 'Bisa dicoba lagi' : 'Perlu admin' }}</span></span>
                    <span class="text-stone-700">{{ $pesan }} <span class="block text-stone-500">Admin: {{ $saran }}</span></span>
                </li>
            @endforeach
        </ul>
    </details>
</section>
