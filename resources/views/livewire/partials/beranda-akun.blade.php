{{--
    Bagian pribadi Dashboard (App\Support\Akun\BerandaAkun::untuk): perlu tindakan, kegiatan terakhir diubah,
    aktivitas terbaru, pintasan per peran, dan ringkasan akun yang masuk. $beranda = hasil BerandaAkun::untuk().
--}}
@php
    $nada = [
        'bahaya' => 'bg-red-50 text-red-800 ring-red-200',
        'perlu' => 'bg-gold-50 text-gold-800 ring-gold-200',
        'siap' => 'bg-brand-50 text-brand-800 ring-brand-200',
        'info' => 'bg-stone-100 text-stone-700 ring-stone-200',
    ];
    $akun = $beranda['akun'];
    $sebelumnya = $akun['riwayat_masuk'][1]['waktu'] ?? null;
    $admin = ($akun['peran'] ?? null) === 'admin';
@endphp

<div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,20rem)]" data-testid="beranda-akun">
    <div class="min-w-0 space-y-6">
        <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-perlu-tindakan" data-testid="perlu-tindakan" data-jumlah="{{ $beranda['jumlah_tindakan'] }}">
            <div class="flex items-start justify-between gap-3 border-b border-stone-100 px-5 py-4 sm:px-6">
                <div class="min-w-0">
                    <h2 id="judul-perlu-tindakan" class="flex items-center gap-2 text-base font-semibold text-stone-900">
                        Perlu tindakan Anda
                        @if ($beranda['jumlah_tindakan'] > 0)
                            <span class="rounded-full bg-brand-700 px-2 py-0.5 text-xs font-semibold text-white" data-testid="jumlah-tindakan">{{ $beranda['jumlah_tindakan'] }}</span>
                        @endif
                    </h2>
                    <p class="mt-0.5 text-sm text-stone-500">{{ $admin ? 'Pengajuan yang menunggu keputusan dan pengaturan yang perlu dilengkapi.' : 'Kegiatan TA '.$beranda['tahun'].' yang menunggu langkah Anda berikutnya.' }}</p>
                </div>
            </div>

            @if ($beranda['tindakan'] === [])
                <div class="flex items-start gap-3 px-5 py-6 sm:px-6" data-testid="tindakan-kosong">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-700"><x-ui.icon name="check-circle" class="size-5" /></span>
                    <div class="text-sm">
                        <p class="font-medium text-stone-900">Tidak ada yang menunggu Anda.</p>
                        <p class="mt-0.5 text-stone-500">
                            @if ($admin)
                                Antrean pemeriksaan kosong dan pengaturan wajib tahun ini sudah siap.
                            @elseif ($beranda['jumlah_kegiatan'] === 0)
                                Belum ada kegiatan di TA {{ $beranda['tahun'] }}. <a href="{{ route('kegiatan.create') }}" wire:navigate class="font-medium text-brand-700 hover:underline">Buat kegiatan pertama</a>.
                            @else
                                Semua kegiatan Anda sedang diperiksa atau sudah final.
                            @endif
                        </p>
                    </div>
                </div>
            @else
                <ul class="divide-y divide-stone-100" role="list">
                    @foreach ($beranda['tindakan'] as $t)
                        <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:px-6" data-testid="tindakan" data-jenis="{{ $t['jenis'] }}" @if ($t['kegiatan_id']) data-kegiatan="{{ $t['kegiatan_id'] }}" @endif>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset {{ $nada[$t['nada']] ?? $nada['info'] }}">{{ $t['label'] }}</span>
                                    @if ($t['status'])
                                        <x-ui.status-badge :status="$t['status']" />
                                    @endif
                                </div>
                                <p class="mt-1.5 truncate text-sm font-semibold text-stone-900">{{ $t['judul'] }}</p>
                                <p class="mt-0.5 line-clamp-2 text-sm text-stone-600">{{ $t['keterangan'] }}</p>
                                @if ($t['subjudul'])
                                    <p class="mt-0.5 text-xs text-stone-500">{{ $t['subjudul'] }}</p>
                                @endif
                            </div>
                            <a href="{{ $t['tautan'] }}" wire:navigate class="btn-secondary shrink-0 self-start sm:self-center" data-testid="buka-tindakan">{{ $t['label_tautan'] }} <x-ui.icon name="chevron-right" class="size-4" /></a>
                        </li>
                    @endforeach
                </ul>
                @if ($beranda['jumlah_tindakan'] > count($beranda['tindakan']))
                    <div class="border-t border-stone-100 px-5 py-3 text-sm sm:px-6">
                        <a href="{{ $admin ? route('pemeriksaan.index') : route('kegiatan.index') }}" wire:navigate class="font-medium text-brand-700 hover:underline" data-testid="tindakan-lainnya">
                            {{ $beranda['jumlah_tindakan'] - count($beranda['tindakan']) }} lainnya di {{ $admin ? 'Pemeriksaan' : 'Data Kegiatan' }}
                        </a>
                    </div>
                @endif
            @endif
        </section>

        <div class="grid gap-6 md:grid-cols-2">
            <section class="min-w-0 rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-terakhir-diubah" data-testid="terakhir-diubah">
                <h2 id="judul-terakhir-diubah" class="text-base font-semibold text-stone-900">Terakhir diubah</h2>
                @if ($beranda['terakhir'] === [])
                    <p class="mt-3 text-sm text-stone-500">Belum ada kegiatan di TA {{ $beranda['tahun'] }}.</p>
                @else
                    <ul class="mt-3 space-y-1" role="list">
                        @foreach ($beranda['terakhir'] as $k)
                            <li>
                                <a href="{{ $k['tautan'] }}" wire:navigate class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-stone-50" data-testid="kegiatan-terakhir" data-kegiatan="{{ $k['id'] }}">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-stone-900">{{ $k['nama'] }}</span>
                                        <span class="block text-xs text-stone-500">{{ $k['waktu'] ?? 'Belum pernah disimpan' }}</span>
                                    </span>
                                    <x-ui.status-badge :status="$k['status']" class="shrink-0" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="min-w-0 rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-aktivitas" data-testid="aktivitas-terbaru">
                <h2 id="judul-aktivitas" class="text-base font-semibold text-stone-900">Aktivitas terbaru</h2>
                @if ($beranda['aktivitas'] === [])
                    <p class="mt-3 text-sm text-stone-500">Belum ada pengajuan, keputusan, atau dokumen yang dicetak tahun ini.</p>
                @else
                    <ol class="mt-3 space-y-3" role="list">
                        @foreach ($beranda['aktivitas'] as $a)
                            <li class="flex gap-3" data-testid="aktivitas" data-jenis="{{ $a['jenis'] }}">
                                <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-full bg-stone-100 text-stone-500"><x-ui.icon :name="$a['ikon']" class="size-3.5" /></span>
                                <div class="min-w-0 flex-1 text-sm">
                                    <p class="truncate font-medium text-stone-900">{{ $a['teks'] }}</p>
                                    @if ($a['tautan'])
                                        <a href="{{ $a['tautan'] }}" wire:navigate class="block truncate text-stone-600 hover:underline" title="{{ $a['nama'] }}">{{ $a['nama'] }}</a>
                                    @else
                                        <p class="truncate text-stone-600" title="{{ $a['nama'] }}">{{ $a['nama'] }}</p>
                                    @endif
                                    <p class="truncate text-xs text-stone-500">{{ $a['oleh'] ? $a['oleh'].' · ' : '' }}{{ $a['waktu'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    </div>

    <aside class="min-w-0 space-y-6">
        <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-pintasan" data-testid="pintasan">
            <h2 id="judul-pintasan" class="text-base font-semibold text-stone-900">Pintasan</h2>
            <ul class="mt-3 space-y-1" role="list">
                @foreach ($beranda['pintasan'] as $p)
                    <li>
                        <a href="{{ $p['tautan'] }}" wire:navigate class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-stone-50" data-testid="pintasan-{{ $p['kunci'] }}">
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700"><x-ui.icon :name="$p['ikon']" class="size-4" /></span>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-stone-900">{{ $p['label'] }}</span>
                                <span class="block truncate text-xs text-stone-500">{{ $p['keterangan'] }}</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        @if ($akun)
            <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-akun-ringkas" data-testid="akun-ringkas">
                <h2 id="judul-akun-ringkas" class="sr-only">Akun Anda</h2>
                <div class="flex items-center gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-100 text-sm font-bold text-brand-800">{{ \Illuminate\Support\Str::of($akun['nama'])->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-stone-900">{{ $akun['nama'] }}</p>
                        <p class="truncate text-xs text-stone-500">{{ $akun['label_peran'] }}{{ $akun['jabatan'] ? ' · '.$akun['jabatan'] : '' }}</p>
                    </div>
                </div>
                @if ($akun['leading'] !== [])
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($akun['leading'] as $l)
                            <span @class(['rounded-md px-2 py-0.5 text-xs ring-1', 'bg-stone-50 text-stone-700 ring-stone-200' => $l['aktif'], 'bg-stone-50 text-stone-400 line-through ring-stone-200' => ! $l['aktif']])>{{ $l['kode'] }} {{ $l['nama_unit'] }}</span>
                        @endforeach
                    </div>
                @endif
                <p class="mt-3 text-xs text-stone-500" data-testid="masuk-sebelumnya">
                    @if ($sebelumnya)
                        Masuk sebelumnya {{ \App\Support\Format::tanggal($sebelumnya) }} · {{ \Illuminate\Support\Carbon::parse($sebelumnya)->timezone(config('app.timezone'))->format('H.i') }}
                    @else
                        Ini pertama kalinya Anda masuk.
                    @endif
                </p>
                <a href="{{ route('akun.profil') }}" wire:navigate class="mt-3 inline-flex text-sm font-medium text-brand-700 hover:underline" data-testid="ke-akun-saya">Akun saya</a>
            </section>
        @endif
    </aside>
</div>
