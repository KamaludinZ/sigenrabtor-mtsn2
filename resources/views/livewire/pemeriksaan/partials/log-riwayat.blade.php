@use('App\Enums\StatusKegiatan')
@use('App\Livewire\Pemeriksaan\Daftar')
@use('App\Support\MasterKode')
@use('App\Support\Pemeriksaan\RiwayatPemeriksaan')
@use('Illuminate\Support\Carbon')

@php
    // Log riwayat pemeriksaan semua kegiatan tahun aktif (RiwayatPemeriksaan::log), terbaru di atas.
    $log = $this->log;
    $tampil = array_slice($log, 0, $batas);
    $nada = [
        'info' => 'bg-sky-50 text-sky-700 ring-sky-200',
        'baik' => 'bg-brand-50 text-brand-700 ring-brand-200',
        'peringatan' => 'bg-gold-50 text-gold-700 ring-gold-200',
        'netral' => 'bg-stone-100 text-stone-600 ring-stone-200',
    ];
    $adaSaringanLog = $cari !== '' || $leading !== '' || $aksi !== '';
@endphp

<section class="mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-log-riwayat" data-testid="log-riwayat">
    <header class="space-y-3 border-b border-stone-100 px-5 py-4 sm:px-6">
        <div class="min-w-0">
            <h2 id="judul-log-riwayat" class="text-base font-semibold text-stone-900">Riwayat pemeriksaan</h2>
            <p class="mt-0.5 text-sm text-stone-500">Semua pengajuan, keputusan, tanda final, dan pembukaan kunci kegiatan TA {{ $tahun }} — siapa, kapan, dan catatannya. Terbaru di atas.</p>
        </div>
        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_13rem_13rem]">
            <label>
                <span class="sr-only">Cari riwayat</span>
                <input type="search" wire:model.live.debounce.300ms="cari" placeholder="Cari kegiatan, pelaku, atau catatan…" class="form-input py-1.5" autocomplete="off">
            </label>
            <label>
                <span class="sr-only">Saring aksi</span>
                <select wire:model.live="aksi" class="form-input py-1.5" aria-label="Aksi">
                    <option value="">Semua aksi</option>
                    @foreach (array_keys(RiwayatPemeriksaan::AKSI) as $kunciAksi)
                        <option value="{{ $kunciAksi }}">{{ StatusKegiatan::labelRiwayat($kunciAksi) }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="sr-only">Saring leading</span>
                <select wire:model.live="leading" class="form-input py-1.5" aria-label="Leading">
                    <option value="">Semua leading</option>
                    @foreach (MasterKode::semuaLeading() as $l)
                        <option value="{{ $l['kode'] }}">{{ $l['kode'] }} · {{ $l['nama_unit'] }}{{ $l['is_active'] ? '' : ' (nonaktif)' }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </header>

    @if ($log === [])
        <div class="px-5 py-12 text-center sm:px-6" data-testid="log-kosong">
            <x-ui.icon name="history" class="mx-auto size-10 text-stone-300" />
            @if ($adaSaringanLog)
                <p class="mt-3 text-sm font-medium text-stone-700">Tidak ada riwayat yang cocok.</p>
                <button type="button" wire:click="hapusSaringan" class="mt-2 text-sm font-semibold text-brand-700 hover:text-brand-800">Hapus saringan</button>
            @else
                <p class="mt-3 text-sm font-medium text-stone-700">Belum ada riwayat pemeriksaan tahun ini.</p>
                <p class="mt-1 text-sm text-stone-500">Riwayat muncul begitu leading mengajukan kegiatan untuk diperiksa.</p>
            @endif
        </div>
    @else
        <ul class="divide-y divide-stone-100" wire:loading.class="opacity-60" wire:target="cari,leading,aksi">
            @foreach ($tampil as $e)
                @php
                    [$ikon, $nadaAksi] = RiwayatPemeriksaan::AKSI[$e['aksi']];
                    $pada = Carbon::parse($e['pada'])->locale('id');
                @endphp
                <li class="grid gap-x-5 gap-y-1.5 px-5 py-3.5 sm:px-6 lg:grid-cols-[9rem_minmax(0,1fr)_minmax(0,1fr)]" data-aksi="{{ $e['aksi'] }}" data-kegiatan="{{ $e['kegiatan_id'] }}" wire:key="log-{{ $e['kegiatan_id'] }}-{{ $loop->index }}-{{ $e['pada'] }}">
                    <time datetime="{{ $e['pada'] }}" class="text-xs text-stone-500 tabular-nums lg:pt-1">{{ $pada->translatedFormat('j M Y, H.i') }}</time>
                    <div class="flex min-w-0 items-start gap-2.5">
                        <span class="grid size-7 shrink-0 place-items-center rounded-full ring-1 {{ $nada[$nadaAksi] }}"><x-ui.icon :name="$ikon" class="size-3.5" /></span>
                        <div class="min-w-0 text-sm">
                            <p class="font-semibold text-stone-900">{{ StatusKegiatan::labelRiwayat($e['aksi']) }}</p>
                            <a href="{{ route('pemeriksaan.periksa', ['kegiatan' => $e['kegiatan_id']]) }}" wire:navigate class="line-clamp-2 text-stone-700 hover:text-brand-800">{{ $e['kegiatan'] }}</a>
                            <p class="text-xs text-stone-500">{{ $e['leading'] ? $e['leading'].' '.($e['nama_leading'] ?? '') : 'Leading belum dipilih' }} · {{ $e['oleh'] }}{{ $e['peran'] ? ' ('.($e['peran'] === 'admin' ? 'admin' : 'leading').')' : '' }}</p>
                        </div>
                    </div>
                    <div class="min-w-0 text-xs lg:pt-1">
                        @if ($e['catatan'])
                            <p class="rounded-lg bg-stone-50 px-2.5 py-1.5 whitespace-pre-line text-stone-700 ring-1 ring-stone-100" data-catatan>{{ $e['catatan'] }}</p>
                        @else
                            <p class="hidden text-stone-400 lg:block">Tanpa catatan</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
        <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-stone-100 px-5 py-3 text-xs text-stone-500 sm:px-6">
            <span data-testid="jumlah-log">Menampilkan {{ count($tampil) }} dari {{ count($log) }} peristiwa</span>
            @if (count($log) > $batas)
                <button type="button" wire:click="tampilkanLebih" class="btn-ghost px-2.5 py-1 text-xs">Tampilkan {{ min(Daftar::PER_HALAMAN, count($log) - $batas) }} lagi</button>
            @endif
        </footer>
    @endif
</section>
