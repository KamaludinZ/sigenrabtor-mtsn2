@use('App\Support\Format')
@use('Illuminate\Support\Carbon')

@php
    $aktif = $this->aktif;
    $calon = $this->calon;
    $diaktifkan = fn (array $t) => $t['diaktifkan_pada'] ? Format::tanggal(Carbon::parse($t['diaktifkan_pada'])->toDateString()) : null;
    $status = fn (array $t) => match (true) {
        $t['is_active'] && $t['is_locked'] => ['Aktif · terkunci', 'bg-brand-50 text-brand-800 ring-brand-200'],
        $t['is_active'] => ['Aktif', 'bg-brand-50 text-brand-800 ring-brand-200'],
        $t['is_locked'] => ['Terkunci', 'bg-stone-100 text-stone-600 ring-stone-200'],
        default => ['Tidak aktif', 'bg-white text-stone-600 ring-stone-300'],
    };
@endphp

<div class="mx-auto max-w-7xl">
    <x-pengaturan.kepala judul="Tahun Anggaran"
                         deskripsi="Tahun aktif menentukan tahun di cover, judul, dasar hukum, jadwal, RAB, pejabat penanda tangan, dan master kode. Tepat satu tahun aktif; mengganti tahun tidak menghapus data tahun lain." />

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-6">
            {{-- Tahun aktif --}}
            @if ($aktif)
                <section class="rounded-2xl bg-white shadow-xs ring-1 ring-brand-200" aria-labelledby="judul-tahun-aktif" data-testid="tahun-aktif">
                    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-stone-100 px-5 py-4 sm:px-6">
                        <div>
                            <h2 id="judul-tahun-aktif" class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Tahun aktif</h2>
                            <p class="mt-1 flex items-center gap-2 text-3xl font-bold tracking-tight text-stone-900 tabular-nums">
                                {{ $aktif['tahun'] }}
                                @if ($aktif['is_locked'])
                                    <x-ui.icon name="lock" class="size-5 text-stone-400" />
                                @endif
                            </p>
                            @if ($aktif['diaktifkan_oleh'])
                                <p class="mt-1 text-xs text-stone-500">Diaktifkan oleh {{ $aktif['diaktifkan_oleh'] }}{{ $diaktifkan($aktif) ? ', '.$diaktifkan($aktif) : '' }}.</p>
                            @endif
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $status($aktif)[1] }}">
                            <span class="size-1.5 rounded-full bg-brand-600" aria-hidden="true"></span> {{ $status($aktif)[0] }}
                        </span>
                    </div>
                    <dl class="grid gap-x-6 gap-y-4 px-5 py-4 text-sm sm:grid-cols-3 sm:px-6">
                        <div>
                            <dt class="text-xs text-stone-500">SK RKTM/RKAM</dt>
                            @if ($aktif['no_sk_rktm'])
                                <dd class="font-medium text-stone-900">{{ $aktif['no_sk_rktm'] }}</dd>
                                <dd class="text-xs text-stone-500">{{ Format::tanggal($aktif['tanggal_sk_rktm']) }}</dd>
                            @else
                                <dd class="flex items-center gap-1 font-medium text-gold-700" data-testid="sk-belum-diisi">
                                    <x-ui.icon name="alert" class="size-4" /> Belum diisi
                                </dd>
                                <dd class="text-xs text-stone-500">Nomor &amp; tanggal SK tercetak di dasar hukum KAK. <a href="{{ route('pengaturan.madrasah.dasar_hukum') }}" wire:navigate class="font-semibold text-brand-700 hover:text-brand-800">Isi SK</a></dd>
                            @endif
                        </div>
                        <div>
                            <dt class="text-xs text-stone-500">Master kode</dt>
                            @if ($aktif['master_tersedia'])
                                <dd class="font-medium text-stone-900">Tersedia</dd>
                                <dd class="text-xs text-stone-500">{{ $aktif['jumlah_rktm'] }} kode RKTM dan kode lain tahun ini.</dd>
                            @else
                                <dd class="flex items-center gap-1 font-medium text-gold-700"><x-ui.icon name="alert" class="size-4" /> Belum diunggah</dd>
                                <dd class="text-xs text-stone-500">Leading belum bisa memilih kode kegiatan.</dd>
                            @endif
                        </div>
                        <div>
                            <dt class="text-xs text-stone-500">Kegiatan</dt>
                            <dd class="font-medium text-stone-900">{{ $this->jumlahKegiatan }} kegiatan tercatat</dd>
                            <dd class="text-xs text-stone-500"><a href="{{ route('kegiatan.index') }}" wire:navigate class="font-semibold text-brand-700 hover:text-brand-800">Lihat Data Kegiatan</a></dd>
                        </div>
                    </dl>
                </section>
            @else
                <p class="flex items-start gap-3 rounded-xl bg-gold-50 px-4 py-3 text-sm text-gold-700 ring-1 ring-gold-200" role="alert" data-testid="tanpa-tahun-aktif">
                    <x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                    Belum ada tahun anggaran aktif. Aktifkan satu tahun agar leading bisa mulai mengisi kegiatan.
                </p>
            @endif

            {{-- Semua tahun --}}
            <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-semua-tahun" data-testid="daftar-tahun">
                <header class="border-b border-stone-100 px-5 py-4 sm:px-6">
                    <h2 id="judul-semua-tahun" class="text-base font-semibold text-stone-900">Semua tahun anggaran</h2>
                    <p class="mt-0.5 text-sm text-stone-500">Pilih tahun yang dikerjakan saat ini. Tahun lalu biasanya dikunci agar dokumennya tidak berubah.</p>
                </header>
                <div class="hidden grid-cols-[6rem_8rem_minmax(0,1fr)_minmax(0,1fr)_11rem] gap-3 bg-stone-50 px-6 py-2 text-xs font-semibold text-stone-500 md:grid" aria-hidden="true">
                    <span>Tahun</span><span>Status</span><span>SK RKTM/RKAM</span><span>Master kode</span><span class="text-right">Aksi</span>
                </div>
                <ul class="divide-y divide-stone-100" aria-label="Daftar tahun anggaran">
                    @foreach ($this->daftar as $t)
                        <li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1.5 px-5 py-3.5 md:grid-cols-[6rem_8rem_minmax(0,1fr)_minmax(0,1fr)_11rem] md:px-6"
                            data-testid="tahun-{{ $t['tahun'] }}" data-status="{{ $t['is_active'] ? 'aktif' : ($t['is_locked'] ? 'terkunci' : 'tidak-aktif') }}" wire:key="tahun-{{ $t['tahun'] }}">
                            <p class="flex items-center gap-2 text-base font-bold text-stone-900 tabular-nums">
                                {{ $t['tahun'] }}
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 md:hidden {{ $status($t)[1] }}">{{ $status($t)[0] }}</span>
                            </p>
                            <p class="hidden md:block">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $status($t)[1] }}">
                                    @if ($t['is_locked']) <x-ui.icon name="lock" class="size-3" /> @endif
                                    {{ $status($t)[0] }}
                                </span>
                            </p>
                            <p class="col-start-1 text-xs text-stone-600 md:col-start-auto md:text-sm">
                                <span class="text-stone-400 md:hidden">SK: </span>
                                @if ($t['no_sk_rktm'])
                                    {{ $t['no_sk_rktm'] }} <span class="text-stone-400">· {{ Format::tanggal($t['tanggal_sk_rktm']) }}</span>
                                @else
                                    <span class="text-gold-700">belum diisi</span>
                                @endif
                            </p>
                            <p class="col-start-1 text-xs text-stone-600 md:col-start-auto md:text-sm">
                                <span class="text-stone-400 md:hidden">Master: </span>
                                {{ $t['master_tersedia'] ? $t['jumlah_rktm'].' kode RKTM' : 'belum diunggah' }}
                            </p>
                            <div class="col-start-2 row-span-3 row-start-1 flex flex-wrap items-center justify-end gap-1 md:col-start-auto md:row-span-1 md:row-start-auto">
                                @unless ($t['is_locked'])
                                    <button type="button" class="btn-ghost p-1.5" wire:click="bukaSalin({{ $t['tahun'] }})" data-testid="buka-salin-{{ $t['tahun'] }}"
                                            title="Salin pengaturan ke {{ $t['tahun'] }}" aria-label="Salin master &amp; pengaturan tahun lain ke {{ $t['tahun'] }}">
                                        <x-ui.icon name="copy" class="size-4 text-stone-500" />
                                    </button>
                                @endunless
                                @if ($t['is_active'])
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-brand-700"><x-ui.icon name="check" class="size-4" /> Sedang aktif</span>
                                @else
                                    <button type="button" class="btn-secondary px-3 py-1.5 text-xs" wire:click="pilih({{ $t['tahun'] }})" aria-label="Jadikan {{ $t['tahun'] }} tahun aktif">
                                        Jadikan aktif
                                    </button>
                                    <button type="button" class="btn-ghost p-1.5" wire:click="ubahKunci({{ $t['tahun'] }})" data-testid="kunci-{{ $t['tahun'] }}"
                                            title="{{ $t['is_locked'] ? 'Buka kunci' : 'Kunci' }} {{ $t['tahun'] }}" aria-label="{{ $t['is_locked'] ? 'Buka kunci' : 'Kunci' }} tahun {{ $t['tahun'] }}">
                                        <x-ui.icon name="lock" @class(['size-4', 'text-stone-700' => $t['is_locked'], 'text-stone-400' => ! $t['is_locked']]) />
                                    </button>
                                    @if ($t['boleh_dihapus'])
                                        <button type="button" class="btn-ghost p-1.5 text-red-600 hover:text-red-700" wire:click="mintaHapus({{ $t['tahun'] }})" data-testid="hapus-{{ $t['tahun'] }}"
                                                title="Hapus {{ $t['tahun'] }}" aria-label="Hapus tahun {{ $t['tahun'] }}">
                                            <x-ui.icon name="trash" class="size-4" />
                                        </button>
                                    @endif
                                @endif
                            </div>
                            @if ($this->akanDihapus === $t['tahun'])
                                <div class="col-span-full flex flex-wrap items-center justify-between gap-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800 ring-1 ring-red-200" role="alert" data-testid="konfirmasi-hapus-tahun">
                                    <span>Hapus Tahun Anggaran {{ $t['tahun'] }}? Tahun ini belum dipakai kegiatan, master kode, Data Pagu, pejabat, maupun dasar hukum.</span>
                                    <span class="flex gap-2">
                                        <button type="button" class="btn-ghost px-2 py-1 text-xs" wire:click="batalHapus">Batal</button>
                                        <button type="button" class="btn-danger px-2 py-1 text-xs" wire:click="hapusTahun" wire:loading.attr="disabled" wire:target="hapusTahun">Hapus</button>
                                    </span>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>

        <aside class="space-y-4">
            <form wire:submit="tambahTahun" class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-tambah-tahun" data-testid="tambah-tahun">
                <h2 id="judul-tambah-tahun" class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Tambah tahun anggaran</h2>
                <p class="mt-1 text-xs text-stone-500">Tahun baru tidak langsung aktif; salin atau isi master kode, pejabat, dan dasar hukumnya dulu, lalu aktifkan.</p>
                <div class="mt-3 flex gap-2">
                    <label for="tahun-baru" class="sr-only">Tahun</label>
                    <input type="text" id="tahun-baru" wire:model="tahunBaru" inputmode="numeric" maxlength="4" placeholder="mis. {{ ($aktif['tahun'] ?? now()->year) + 1 }}"
                           class="form-input w-28 tabular-nums" autocomplete="off" @error('tahunBaru') aria-invalid="true" aria-describedby="tahun-baru-error" @enderror>
                    <button type="submit" class="btn-secondary px-3 text-xs" wire:loading.attr="disabled" wire:target="tambahTahun"><x-ui.icon name="plus" class="size-4" /> Tambah</button>
                </div>
                @error('tahunBaru') <p id="tahun-baru-error" class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
            </form>

            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-aturan-tahun">
                <h2 id="judul-aturan-tahun" class="text-[11px] font-semibold tracking-wider text-stone-400 uppercase">Aturan tahun aktif</h2>
                <ul class="mt-3 space-y-2.5 text-xs text-stone-600">
                    <li class="flex items-start gap-2"><x-ui.icon name="check-circle" class="mt-px size-3.5 shrink-0 text-brand-600" /> Tepat satu tahun aktif; tahunnya tercetak di seluruh dokumen.</li>
                    <li class="flex items-start gap-2"><x-ui.icon name="check-circle" class="mt-px size-3.5 shrink-0 text-brand-600" /> Pejabat penanda tangan dan master kode mengikuti tahun aktif.</li>
                    <li class="flex items-start gap-2"><x-ui.icon name="check-circle" class="mt-px size-3.5 shrink-0 text-brand-600" /> Mengganti tahun tidak menghapus kegiatan &amp; dokumen tahun lain.</li>
                    <li class="flex items-start gap-2"><x-ui.icon name="lock" class="mt-px size-3.5 shrink-0 text-stone-400" /> Tahun terkunci hanya bisa dilihat; tahun aktif tidak bisa dikunci.</li>
                    <li class="flex items-start gap-2"><x-ui.icon name="trash" class="mt-px size-3.5 shrink-0 text-stone-400" /> Hanya tahun yang belum dipakai yang bisa dihapus.</li>
                    <li class="flex items-start gap-2"><x-ui.icon name="copy" class="mt-px size-3.5 shrink-0 text-stone-400" /> Master &amp; pengaturan bisa disalin dari tahun lain; isian yang sudah ada tidak pernah tertimpa.</li>
                </ul>
            </section>
        </aside>
    </div>

    @if ($this->salinKe !== null)
        @include('livewire.pengaturan.partials.salin-pengaturan')
    @endif

    {{-- Konfirmasi ganti tahun aktif --}}
    @if ($calon)
        <div class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" aria-labelledby="judul-konfirmasi-tahun" data-testid="konfirmasi-tahun"
             x-data x-on:keydown.escape.window="$wire.batalPilih()">
            <div class="absolute inset-0 bg-stone-900/40" wire:click="batalPilih" aria-hidden="true"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-5 shadow-xl sm:p-6" x-trap="true">
                <h2 id="judul-konfirmasi-tahun" class="text-base font-semibold text-stone-900">Aktifkan Tahun Anggaran {{ $calon['tahun'] }}?</h2>
                <ul class="mt-3 space-y-2 text-sm text-stone-600">
                    <li class="flex items-start gap-2"><x-ui.icon name="calendar" class="mt-0.5 size-4 shrink-0 text-stone-400" /> Seluruh dokumen, dasar hukum, pejabat, dan master kode memakai tahun {{ $calon['tahun'] }}.</li>
                    @if ($aktif)
                        <li class="flex items-start gap-2"><x-ui.icon name="eye" class="mt-0.5 size-4 shrink-0 text-stone-400" /> Kegiatan &amp; dokumen tahun {{ $aktif['tahun'] }} tetap tersimpan, tidak dihapus — aktifkan kembali kapan saja.</li>
                    @endif
                    @if ($calon['is_locked'])
                        <li class="flex items-start gap-2 text-stone-700" data-testid="peringatan-terkunci"><x-ui.icon name="lock" class="mt-0.5 size-4 shrink-0 text-gold-600" /> Tahun {{ $calon['tahun'] }} terkunci: datanya hanya bisa dilihat, tidak bisa diubah.</li>
                    @endif
                    @unless ($calon['master_tersedia'])
                        <li class="flex items-start gap-2 text-gold-700" data-testid="peringatan-master"><x-ui.icon name="alert" class="mt-0.5 size-4 shrink-0" /> Master kode {{ $calon['tahun'] }} belum diunggah — leading belum bisa memilih kode.</li>
                    @endunless
                </ul>
                @if ($aktif && ! $aktif['is_locked'])
                    <label class="mt-4 flex items-start gap-2.5 rounded-lg bg-stone-50 px-3 py-2.5 text-sm text-stone-700 ring-1 ring-stone-200">
                        <input type="checkbox" wire:model="kunciSebelumnya" class="mt-0.5 size-4 rounded accent-brand-700" data-testid="kunci-sebelumnya">
                        <span>Kunci TA {{ $aktif['tahun'] }} sesudah beralih <span class="block text-xs text-stone-500">Kegiatan &amp; pengaturan tahun itu hanya bisa dilihat; admin bisa membuka kuncinya kapan saja.</span></span>
                    </label>
                @endif
                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <button type="button" class="btn-ghost" wire:click="batalPilih">Batal</button>
                    <button type="button" class="btn-primary" wire:click="aktifkan" wire:loading.attr="disabled" wire:target="aktifkan">
                        <span wire:loading.remove wire:target="aktifkan">Ya, aktifkan {{ $calon['tahun'] }}</span>
                        <span wire:loading wire:target="aktifkan">Mengaktifkan…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
