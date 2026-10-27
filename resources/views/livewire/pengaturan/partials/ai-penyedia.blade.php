@use('App\Support\Pengaturan\PenyediaAi')
@use('App\Support\Pengaturan\AturanPenyediaAi')

@php
    $ai = $this->pengaturan();
    $kartu = [
        ['aktif', 'Penyedia aktif', 'Dipakai untuk setiap permintaan AI.', $ai['provider_aktif'], $ai['nama_aktif']],
        ['cadangan', 'Penyedia cadangan', 'Dipakai otomatis bila penyedia aktif gagal atau tidak menjawab.', $ai['provider_cadangan'], $ai['nama_cadangan']],
    ];
@endphp

{{-- Tab Penyedia: status penyedia aktif & cadangan, perbandingan pilihan, dan data yang dikirim ke AI. --}}
<div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" data-testid="panel-penyedia-ai">
    <div class="min-w-0 space-y-6">
        @if ($ai['provider_aktif'] === null)
            <p class="flex items-start gap-2 rounded-xl bg-gold-50 px-4 py-3 text-sm text-gold-700 ring-1 ring-gold-200" role="status" data-testid="ai-belum-dipilih">
                <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                Penyedia AI belum dipilih — tombol “Buat dengan AI” sementara memakai saran contoh (tiruan) yang disusun dari data kegiatan.
            </p>
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($kartu as [$kunci, $judul, $keterangan, $penyedia, $nama])
                <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-penyedia-{{ $kunci }}" data-testid="penyedia-{{ $kunci }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h2 id="judul-penyedia-{{ $kunci }}" class="text-sm font-semibold text-stone-900">{{ $judul }}</h2>
                            <p class="mt-0.5 text-xs text-stone-500">{{ $keterangan }}</p>
                        </div>
                        <span @class([
                                'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                                'bg-brand-50 text-brand-800 ring-brand-200' => $penyedia,
                                'bg-stone-100 text-stone-600 ring-stone-200' => ! $penyedia,
                            ])>{{ $penyedia ? (PenyediaAi::JENIS[PenyediaAi::DAFTAR[$penyedia]['jenis'] ?? ''] ?? 'Terpilih') : 'Belum dipilih' }}</span>
                    </div>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-stone-500">Penyedia</dt><dd class="text-right font-medium text-stone-900">{{ $nama ?? '—' }}</dd></div>
                        @if ($kunci === 'aktif')
                            <div class="flex justify-between gap-3"><dt class="text-stone-500">Model</dt><dd class="text-right font-mono text-xs text-stone-800">{{ $ai['model'] ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-stone-500">Alamat server</dt><dd class="min-w-0 truncate text-right font-mono text-xs text-stone-800">{{ $ai['base_url'] ?? '—' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-stone-500">Kunci API</dt><dd class="text-right text-stone-800">{{ ($ai['ada_kunci'] ?? false) ? '•••• tersimpan (terenkripsi)' : 'Belum diisi' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-stone-500">Koneksi</dt><dd class="text-right text-stone-500">Belum diuji</dd></div>
                        @endif
                    </dl>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" class="btn-secondary px-2.5 py-1.5 text-xs" disabled title="Sedang disiapkan"><x-ui.icon name="pencil" class="size-3.5" /> {{ $penyedia ? 'Ubah' : 'Pilih penyedia' }} <x-ui.segera /></button>
                        @if ($kunci === 'aktif')
                            <button type="button" class="btn-ghost px-2.5 py-1.5 text-xs" disabled title="Sedang disiapkan">Uji koneksi <x-ui.segera /></button>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        @include('livewire.pengaturan.partials.ai-lokal', ['ai' => $ai])

        <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-pilihan-penyedia" data-testid="pilihan-penyedia-ai">
            <header class="border-b border-stone-100 px-5 py-4">
                <h2 id="judul-pilihan-penyedia" class="text-sm font-semibold text-stone-900">Pilihan penyedia</h2>
                <p class="mt-0.5 text-xs text-stone-500">Server sekolah gratis dan datanya tetap di madrasah; API cloud lebih cepat dan rapi tetapi berbayar.</p>
            </header>
            <ul class="divide-y divide-stone-100">
                @foreach (PenyediaAi::DAFTAR as $kunci => $p)
                    <li class="grid gap-1 px-5 py-3 text-sm sm:grid-cols-[12rem_minmax(0,1fr)]" data-penyedia="{{ $kunci }}">
                        <div>
                            <p class="font-medium text-stone-900">{{ $p['nama'] }}</p>
                            <p class="text-xs text-stone-500">{{ PenyediaAi::JENIS[$p['jenis']] }} · {{ $p['butuh_kunci'] ? 'perlu kunci API' : 'tanpa kunci API' }}</p>
                        </div>
                        <div class="text-xs">
                            <p class="flex items-start gap-1.5 text-stone-700"><x-ui.icon name="check" class="mt-px size-3.5 shrink-0 text-brand-600" /> {{ $p['kelebihan'] }}</p>
                            <p class="mt-0.5 flex items-start gap-1.5 text-stone-500"><x-ui.icon name="info" class="mt-px size-3.5 shrink-0 text-stone-400" /> {{ $p['kekurangan'] }}</p>
                            <p class="mt-0.5 text-stone-400">Model: {{ $p['contoh_model'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <aside class="space-y-4">
        <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-data-ai" data-testid="data-dikirim-ai">
            <h2 id="judul-data-ai" class="text-sm font-semibold text-stone-900">Data yang dikirim ke AI</h2>
            <ul class="mt-2 space-y-1.5 text-xs text-stone-600">
                <li class="flex items-start gap-2"><x-ui.icon name="check" class="mt-px size-3.5 shrink-0 text-brand-600" /> Nama kegiatan, program RKTM, IKSK, EDM, waktu, tempat, peserta, dan catatan konteks.</li>
                <li class="flex items-start gap-2"><x-ui.icon name="check" class="mt-px size-3.5 shrink-0 text-brand-600" /> Teks bagian yang sedang dibuat atau diperbaiki.</li>
                <li class="flex items-start gap-2"><x-ui.icon name="lock" class="mt-px size-3.5 shrink-0 text-stone-400" /> Tidak pernah: NIP, nama pejabat, pagu dan rincian RAB.</li>
            </ul>
        </section>
        <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-antrean-ai">
            <h2 id="judul-antrean-ai" class="text-sm font-semibold text-stone-900">Antrean</h2>
            <p class="mt-1 text-xs text-stone-500">Permintaan AI berjalan di belakang lewat antrean agar layar tidak macet; penyedia cadangan dipakai bila penyedia aktif gagal.</p>
        </section>
    </aside>
</div>
