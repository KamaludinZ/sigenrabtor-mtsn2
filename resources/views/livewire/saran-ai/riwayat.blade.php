@use('App\Support\Kegiatan\BagianAi')
@use('App\Support\Kegiatan\RiwayatSaranAi')
@use('Illuminate\Support\Carbon')

@php
    $semua = $this->permintaan;
    $ringkasan = RiwayatSaranAi::ringkasan($semua);
    $tampil = array_slice($semua, 0, $batas);
    $pengguna = $this->namaPengguna();
    $pilihanKegiatan = $this->pilihanKegiatan();
    $adaSaringan = $kegiatanId || $bagian !== '' || $status !== '' || $cari !== '';
    $persenDipakai = $ringkasan['permintaan'] ? (int) round($ringkasan['dipakai'] / $ringkasan['permintaan'] * 100) : 0;
@endphp

{{--
    Riwayat Saran AI (PRD F25): ringkasan, saringan (URL), lalu kartu per permintaan — bagian, kegiatan, pengguna, waktu,
    penyedia, instruksi, versi beserta keputusannya (teks yang dipakai bila diedit), dan bahan yang terkirim ke AI.
--}}
<div class="mx-auto max-w-5xl">
    <div class="min-w-0">
        <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Riwayat Saran AI</h1>
        <p class="mt-1 max-w-3xl text-sm text-stone-500">Setiap saran AI tercatat: bagian, bahan yang dikirim, teks saran, keputusan, pengguna, dan waktu. NIP dan nama pejabat tidak pernah ikut terkirim.</p>
    </div>

    {{-- Ringkasan --}}
    <dl class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4" data-testid="ringkasan-saran-ai">
        @foreach ([
            ['Permintaan', $ringkasan['permintaan'], $ringkasan['versi'].' versi saran'],
            ['Saran dipakai', $ringkasan['dipakai'], $persenDipakai.'% permintaan'],
            ['Versi ditolak', $ringkasan['status']['ditolak'], 'termasuk versi yang tidak dipilih'],
            ['Belum diputuskan', $ringkasan['status']['menunggu'], 'versi tanpa keputusan'],
        ] as [$label, $nilai, $ket])
            <div class="rounded-xl bg-white px-4 py-3 shadow-xs ring-1 ring-stone-200">
                <dt class="text-xs font-medium text-stone-500">{{ $label }}</dt>
                <dd class="mt-0.5 text-xl font-semibold text-stone-900 tabular-nums">{{ $nilai }}</dd>
                <dd class="text-[11px] text-stone-400">{{ $ket }}</dd>
            </div>
        @endforeach
    </dl>

    {{-- Saringan --}}
    <div class="mt-5 grid gap-3 rounded-xl bg-white p-3 shadow-xs ring-1 ring-stone-200 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.4fr)_repeat(3,minmax(0,1fr))]" data-testid="saringan-saran-ai">
        <label class="relative block">
            <span class="sr-only">Cari kegiatan atau teks saran</span>
            <x-ui.icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-stone-400" />
            <input type="search" wire:model.live.debounce.300ms="cari" placeholder="Cari kegiatan atau teks saran…" class="form-input py-1.5 pl-9" autocomplete="off">
        </label>
        <label class="block">
            <span class="sr-only">Kegiatan</span>
            <select wire:model.live="kegiatanId" class="form-input py-1.5" aria-label="Kegiatan">
                <option value="0">Semua kegiatan</option>
                @foreach ($pilihanKegiatan as $id => $nama)
                    <option value="{{ $id }}">{{ $nama }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="sr-only">Bagian</span>
            <select wire:model.live="bagian" class="form-input py-1.5" aria-label="Bagian">
                <option value="">Semua bagian</option>
                @foreach (BagianAi::BAGIAN as $kunci => $b)
                    <option value="{{ $kunci }}">{{ $b['judul'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="sr-only">Keputusan</span>
            <select wire:model.live="status" class="form-input py-1.5" aria-label="Keputusan">
                <option value="">Semua keputusan</option>
                @foreach (RiwayatSaranAi::STATUS as $kunci => [$label])
                    <option value="{{ $kunci }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>

    {{-- Daftar permintaan --}}
    <div class="mt-5 space-y-4" data-testid="daftar-saran-ai">
        @forelse ($tampil as $p)
            @php
                $b = BagianAi::BAGIAN[$p['bagian']] ?? ['judul' => $p['bagian'], 'kolom' => null];
                $dipakai = collect($p['versi'])->first(fn ($v) => in_array($v['status'], ['diterima', 'diedit'], true));
                $semuaDitolak = collect($p['versi'])->every(fn ($v) => $v['status'] === 'ditolak');
                $tautan = $p['bagian'] === 'jadwal' ? route('kegiatan.matrik', ['kegiatan' => $p['kegiatan_id']]) : route('kegiatan.kalimat', ['kegiatan' => $p['kegiatan_id']]).'#kalimat-'.$p['bagian'];
            @endphp
            <article class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" data-permintaan="{{ $p['id'] }}" data-bagian="{{ $p['bagian'] }}" wire:key="saran-{{ $p['id'] }}">
                <header class="flex flex-col gap-2 border-b border-stone-100 px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:px-5">
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800 ring-1 ring-brand-200 ring-inset">
                                <x-ui.icon name="sparkles" class="size-3" /> {{ $b['judul'] }}
                            </span>
                            <a href="{{ $tautan }}" wire:navigate class="min-w-0 truncate text-sm font-semibold text-stone-900 hover:text-brand-800">{{ $p['nama_kegiatan'] }}</a>
                        </p>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-stone-500">
                            <span>{{ $pengguna[$p['user_id']] ?? 'Pengguna tidak dikenal' }}</span>
                            <span class="text-stone-300" aria-hidden="true">·</span>
                            <time datetime="{{ $p['pada'] }}">{{ Carbon::parse($p['pada'])->locale('id')->translatedFormat('j M Y, H.i') }}</time>
                            <span class="text-stone-300" aria-hidden="true">·</span>
                            <span>{{ \App\Support\Pengaturan\PenyediaAi::nama($p['penyedia']) ?? 'AI tiruan' }}</span>
                            @if ($p['instruksi'] !== '')
                                <span class="rounded bg-stone-100 px-1.5 py-px text-stone-600">Instruksi: {{ $p['instruksi'] }}</span>
                            @endif
                        </p>
                    </div>
                    <span @class([
                            'inline-flex shrink-0 items-center gap-1 self-start rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                            'bg-brand-50 text-brand-800 ring-brand-200' => $dipakai,
                            'bg-red-50 text-red-700 ring-red-200' => ! $dipakai && $semuaDitolak,
                            'bg-stone-100 text-stone-600 ring-stone-200' => ! $dipakai && ! $semuaDitolak,
                        ]) data-testid="keputusan-saran">
                        {{ $dipakai ? 'Dipakai: '.$dipakai['judul'].($dipakai['status'] === 'diedit' ? ' (diedit)' : '') : ($semuaDitolak ? 'Semua ditolak' : 'Belum diputuskan') }}
                    </span>
                </header>

                <ol class="divide-y divide-stone-100">
                    @foreach ($p['versi'] as $v)
                        <li class="px-4 py-3 sm:px-5" data-status="{{ $v['status'] }}">
                            <p class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="font-semibold text-stone-900">{{ $v['judul'] }}</span>
                                @if ($v['ciri'] !== '')
                                    <span class="text-stone-500">{{ $v['ciri'] }}</span>
                                @endif
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ RiwayatSaranAi::STATUS[$v['status']][1] }}">{{ RiwayatSaranAi::STATUS[$v['status']][0] }}</span>
                            </p>
                            <p @class(['mt-1 text-sm whitespace-pre-line', 'text-stone-800' => $v['status'] !== 'ditolak', 'text-stone-500' => $v['status'] === 'ditolak'])>{{ $v['teks'] }}</p>
                            @if ($v['teks_dipakai'])
                                <div class="mt-2 rounded-lg bg-gold-50/60 px-3 py-2 ring-1 ring-gold-200" data-testid="teks-dipakai">
                                    <p class="text-[11px] font-semibold text-gold-700">Teks yang dipakai setelah disunting</p>
                                    <p class="mt-0.5 text-sm whitespace-pre-line text-stone-800">{{ $v['teks_dipakai'] }}</p>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>

                <details class="border-t border-stone-100">
                    <summary class="cursor-pointer px-4 py-2 text-xs font-semibold text-stone-600 hover:text-stone-900 sm:px-5">Bahan yang dikirim ke AI</summary>
                    <pre class="mx-4 mb-3 overflow-x-auto rounded-lg bg-stone-50 p-3 font-mono text-[11px] leading-relaxed whitespace-pre-wrap text-stone-700 ring-1 ring-stone-200 sm:mx-5" data-testid="prompt-saran">{{ $p['prompt'] }}</pre>
                </details>
            </article>
        @empty
            <div class="rounded-2xl bg-white px-6 py-10 text-center shadow-xs ring-1 ring-stone-200" data-testid="saran-ai-kosong">
                <x-ui.icon name="sparkles" class="mx-auto size-8 text-stone-300" />
                @if ($adaSaringan)
                    <p class="mt-2 text-sm font-medium text-stone-700">Tidak ada saran yang cocok dengan saringan.</p>
                    <button type="button" wire:click="aturUlang" class="btn-ghost mt-2 text-sm">Hapus saringan</button>
                @else
                    <p class="mt-2 text-sm font-medium text-stone-700">Belum ada saran AI tercatat.</p>
                    <p class="mt-1 text-xs text-stone-500">Saran muncul di sini setelah tombol “Buat dengan AI” dipakai di tab Kalimat KAK atau usulan jadwal di Matrik &amp; Jadwal.</p>
                @endif
            </div>
        @endforelse

        @if (count($semua) > $batas)
            <div class="flex justify-center">
                <button type="button" wire:click="tampilkanLebih" class="btn-secondary" data-testid="tampilkan-lebih-saran">Tampilkan lebih ({{ count($semua) - $batas }} lagi)</button>
            </div>
        @endif
    </div>
</div>
