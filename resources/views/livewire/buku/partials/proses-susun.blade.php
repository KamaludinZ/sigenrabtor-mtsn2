@php
    // Penyusunan buku bertahap (PengambilanBuku): klien memanggil lanjutkanSusun() berulang selama tahap "ambil".
    $jumlah = count($proses['antrean']);
    $persen = $jumlah ? (int) round($proses['posisi'] / $jumlah * 100) : 100;
    $siap = count(array_filter($proses['hasil'], fn ($h) => $h['hasil'] === 'siap'));
    $draf = count(array_filter($proses['hasil'], fn ($h) => $h['hasil'] === 'draf'));
    $gagal = count(array_filter($proses['hasil'], fn ($h) => $h['hasil'] === 'gagal'));
    $lencana = [
        'siap' => ['Siap', 'bg-brand-50 text-brand-800 ring-brand-200'],
        'draf' => ['DRAF', 'bg-red-50 text-red-700 ring-red-200'],
        'gagal' => ['Gagal', 'bg-stone-100 text-stone-600 ring-stone-200'],
    ];
@endphp

<section class="mt-6 rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200 sm:p-5" aria-labelledby="judul-proses-buku" data-testid="proses-buku" data-tahap="{{ $proses['tahap'] }}" wire:key="proses-{{ $proses['tahap'] }}">
    @if ($proses['tahap'] === 'ambil')
        <div x-data="{ jalan: true }" x-init="(async () => { while (jalan && await $wire.lanjutkanSusun()) {} })()">
            <div class="flex items-center justify-between gap-3 text-sm">
                <h2 id="judul-proses-buku" class="flex items-center gap-2 font-medium text-stone-800">
                    <x-ui.icon name="loader" class="size-4 animate-spin text-brand-700" />
                    Mengambil kegiatan {{ min($proses['posisi'] + 1, $jumlah) }} dari {{ $jumlah }}…
                </h2>
                <span class="font-semibold text-stone-900 tabular-nums" data-testid="persen-susun">{{ $persen }}%</span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-stone-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $persen }}" aria-label="Kemajuan penyusunan buku">
                <div class="h-full rounded-full bg-brand-600 transition-all duration-300" style="width: {{ $persen }}%"></div>
            </div>
            <div class="mt-3 flex justify-end">
                <button type="button" class="btn-secondary" x-on:click="jalan = false; $wire.hentikanSusun()">Hentikan</button>
            </div>
        </div>
    @else
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 id="judul-proses-buku" class="flex items-center gap-2 text-sm font-semibold text-stone-900">
                    <x-ui.icon :name="$proses['tahap'] === 'selesai' ? 'check-circle' : 'alert'" class="size-4 {{ $proses['tahap'] === 'selesai' ? 'text-brand-700' : 'text-gold-600' }}" />
                    {{ $proses['tahap'] === 'selesai' ? 'Buku tersusun' : 'Penyusunan dihentikan' }}
                </h2>
                <p class="mt-0.5 text-xs text-stone-500" data-testid="ringkasan-susun">
                    {{ count($proses['hasil']) }} dari {{ $jumlah }} kegiatan diambil · {{ $siap }} siap · {{ $draf }} DRAF{{ $gagal ? ' · '.$gagal.' gagal' : '' }} · ± {{ $proses['total_halaman'] }} halaman.
                    @if ($proses['tahap'] === 'selesai')
                        Tercatat di riwayat; berkas PDF digabung setelah mesin dokumen tersedia.
                    @else
                        Tidak dicatat di riwayat.
                    @endif
                </p>
            </div>
            <button type="button" wire:click="tutupSusun" class="btn-ghost px-2.5 py-1 text-xs">Tutup</button>
        </div>
    @endif

    @if ($proses['hasil'] !== [])
        <ol class="mt-3 max-h-56 space-y-1.5 overflow-auto text-xs" aria-live="polite" data-testid="log-susun">
            @foreach (array_reverse($proses['hasil']) as $h)
                <li class="flex items-start gap-2" data-hasil="{{ $h['hasil'] }}" data-kegiatan="{{ $h['id'] }}">
                    <span class="inline-flex shrink-0 rounded-full px-1.5 py-px text-[10px] font-semibold ring-1 ring-inset {{ $lencana[$h['hasil']][1] }}">{{ $lencana[$h['hasil']][0] }}</span>
                    <span class="min-w-0">
                        <span class="font-medium text-stone-800">{{ $h['nama'] }}</span>
                        <span class="text-stone-500">· hal. {{ $h['halaman'] ?? '…' }} — {{ $h['pesan'] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    @endif
</section>
