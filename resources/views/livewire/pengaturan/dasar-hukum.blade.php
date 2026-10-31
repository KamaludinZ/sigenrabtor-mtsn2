@use('App\Livewire\Pengaturan\ProfilMadrasah')

@php
    $dasarHukum = $this->dasarHukum;
    $latar = $this->latarBelakang;
    $sisip = $this->kalimatSisip();
    $ta = $this->tahunDilihat();
    $tahunAktif = \App\Support\Stub\StubData::tahunAktif()['tahun'];
    $kegiatanContoh = $this->contohKegiatan();
    $nilaiContoh = \App\Support\Pengaturan\TeksBaku::nilaiKegiatan($kegiatanContoh);
    $namaContoh = $nilaiContoh[\App\Support\Pengaturan\TeksBaku::NAMA_KEGIATAN];
    $susunan = $this->susunanPratinjau();
    $kurangPratinjau = [...($susunan->kurangPerBagian[1] ?? []), ...($susunan->kurangPerBagian[2] ?? [])];
    $kesiapan = $this->kesiapan();
    $pengaturan = $this->pengaturan;
    $bolehUbah = ! $this->tahunTerkunci();
    $bisaUrut = $bolehUbah && $suntingButir === null && count($dasarHukum) > 1;
@endphp

<div class="mx-auto max-w-7xl">
    <x-pengaturan.kepala judul="Profil & Pejabat"
                         deskripsi="Dasar hukum dan paragraf latar belakang baku tercetak di bagian 1–2 setiap KAK/TOR. Diatur per tahun anggaran; leading menambahkan paragraf khusus kegiatannya sendiri di Data Kegiatan.">
        <x-slot:tab>
            <nav class="-mb-px flex gap-1 overflow-x-auto border-b border-stone-200" aria-label="Bagian profil & pejabat">
                @foreach (ProfilMadrasah::TAB as $kunci => $label)
                    @if ($kunci === 'dasar_hukum')
                        <span aria-current="page" class="flex shrink-0 items-center gap-2 border-b-2 border-brand-700 px-3 py-2.5 text-sm font-semibold text-brand-800" data-tab="{{ $kunci }}">{{ $label }}</span>
                    @else
                        <a href="{{ route('pengaturan.madrasah.index', $kunci === 'profil' ? [] : ['tab' => $kunci]) }}" wire:navigate data-tab="{{ $kunci }}"
                           class="flex shrink-0 items-center gap-2 border-b-2 border-transparent px-3 py-2.5 text-sm font-medium text-stone-500 hover:border-stone-300 hover:text-stone-800">{{ $label }}</a>
                    @endif
                @endforeach
            </nav>
        </x-slot:tab>
    </x-pengaturan.kepala>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-6">
            {{-- Pemilih tahun: dasar hukum & latar belakang disimpan per tahun anggaran; dokumen memakai tahun aktif. --}}
            <div class="flex flex-wrap items-center justify-between gap-3" data-testid="pemilih-tahun-dasar-hukum">
                <div class="inline-flex rounded-lg bg-stone-100 p-1" role="group" aria-label="Tahun anggaran">
                    @foreach ($this->daftarTahun() as $t)
                        @php
                            $dipilih = $t['tahun'] === $ta;
                        @endphp
                        <button type="button" wire:click="$set('tahun', '{{ $t['is_active'] ? '' : $t['tahun'] }}')" aria-pressed="{{ $dipilih ? 'true' : 'false' }}" data-tahun="{{ $t['tahun'] }}"
                                @class(['inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium', 'bg-white text-stone-900 shadow-xs' => $dipilih, 'text-stone-500 hover:text-stone-800' => ! $dipilih])>
                            TA {{ $t['tahun'] }}
                            @if ($t['is_active'])
                                <span class="rounded bg-brand-50 px-1 text-[10px] font-semibold text-brand-800">aktif</span>
                            @elseif ($t['is_locked'])
                                <x-ui.icon name="lock" class="size-3.5 text-stone-400" />
                            @endif
                        </button>
                    @endforeach
                </div>
                <p class="text-xs text-stone-500">
                    Dokumen tahun aktif memakai dasar hukum TA {{ $tahunAktif }}.
                    @if ($pengaturan['diperbarui_pada'])
                        <span class="text-stone-400">· TA {{ $ta }} diperbarui {{ \App\Support\Format::tanggal($pengaturan['diperbarui_pada']) }}</span>
                    @endif
                </p>
            </div>

            @if ($this->tahunTerkunci())
                <p class="flex items-start gap-2 rounded-xl bg-stone-100 px-4 py-3 text-sm text-stone-700 ring-1 ring-stone-200" data-testid="dasar-hukum-terkunci">
                    <x-ui.icon name="lock" class="mt-0.5 size-4 shrink-0" /> Tahun Anggaran {{ $ta }} terkunci — dasar hukum &amp; latar belakangnya hanya bisa dilihat.
                </p>
            @endif

            {{-- Dasar hukum: butir ditambah/diubah/dihapus satu per satu dan langsung tersimpan. --}}
            <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-dasar-hukum" data-testid="daftar-dasar-hukum">
                <header class="flex flex-col gap-3 border-b border-stone-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                    <div class="min-w-0">
                        <h2 id="judul-dasar-hukum" class="text-base font-semibold text-stone-900">Dasar hukum</h2>
                        <p class="mt-0.5 text-sm text-stone-500">{{ count($dasarHukum) }} butir · tercetak bernomor di bagian 2 KAK/TOR sesuai urutan ini{{ $bisaUrut ? ' — seret nomor untuk memindahkan' : '' }}.</p>
                    </div>
                    @if ($bolehUbah)
                        <div class="flex shrink-0 flex-wrap gap-2 self-start">
                            <button type="button" wire:click="tambahButir" class="btn-secondary" @disabled($suntingButir === 'baru' || count($dasarHukum) >= \App\Support\Pengaturan\DaftarDasarHukum::MAKS_BUTIR)>
                                <x-ui.icon name="plus" class="size-4" /> Tambah butir
                            </button>
                        </div>
                    @endif
                </header>
                @if ($butirTerhapus && $butirTerhapus['tahun'] === $ta)
                    <div class="flex items-center justify-between gap-3 border-b border-stone-100 bg-stone-800 px-5 py-2.5 text-sm text-white sm:px-6" role="status" data-testid="butir-terhapus">
                        <span class="min-w-0 truncate">Butir {{ $butirTerhapus['posisi'] + 1 }} dihapus: “{{ \App\Support\Pengaturan\DaftarDasarHukum::rapikan($butirTerhapus['butir']['teks']) }}”</span>
                        <button type="button" wire:click="urungkanHapusButir" class="shrink-0 font-semibold text-gold-300 hover:text-gold-200">Urungkan</button>
                    </div>
                @endif
                @if ($dasarHukum === [] && $suntingButir !== 'baru')
                    <p class="px-5 py-8 text-center text-sm text-stone-500 sm:px-6" data-testid="dasar-hukum-kosong">
                        Belum ada dasar hukum TA {{ $ta }}.
                        @unless ($pengaturan['diatur'])
                            <span class="block text-xs">Tahun ini belum diatur — paragraf latar belakang memakai teks bawaan.</span>
                        @endunless
                    </p>
                @else
                    {{-- Seret pegangan nomor atau pakai naik/turun; setiap pindah langsung tersimpan. Fokus dikembalikan ke tombol yang sama. --}}
                    <ol class="divide-y divide-stone-100 transition-opacity" @if ($bisaUrut) wire:sort="pindahkanButir" @endif
                        wire:loading.class="opacity-60" wire:loading.attr="aria-busy" wire:target="geserButir,pindahkanButir,hapusButir,urungkanHapusButir"
                        x-data x-on:butir-dipindah.window="$nextTick(() => { const li = $el.querySelector(`li[data-id='${CSS.escape($event.detail.id)}']`); (li?.querySelector(`[data-geser='${$event.detail.arah}']:not([disabled])`) ?? li?.querySelector('[data-geser]:not([disabled])'))?.focus() })">
                        @foreach ($dasarHukum as $i => $butir)
                            <li class="grid grid-cols-[1.5rem_minmax(0,1fr)] items-start gap-x-3 gap-y-2 px-5 py-3 sm:grid-cols-[1.5rem_minmax(0,1fr)_auto] sm:px-6"
                                data-butir="{{ $i + 1 }}" data-id="{{ $butir['id'] }}" wire:key="butir-{{ $butir['id'] }}" @if ($bisaUrut) wire:sort:item="{{ $butir['id'] }}" @endif>
                                @if ($bisaUrut)
                                    <span wire:sort:handle title="Seret untuk mengurutkan" aria-hidden="true"
                                          class="group/handle mt-0.5 grid size-6 cursor-grab touch-none place-items-center rounded-full bg-stone-100 text-xs font-semibold text-stone-600 tabular-nums hover:bg-brand-100 hover:text-brand-800 active:cursor-grabbing">
                                        <span class="group-hover/handle:hidden">{{ $i + 1 }}</span>
                                        <x-ui.icon name="grip" class="hidden size-3.5 group-hover/handle:block" />
                                    </span>
                                @else
                                    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-stone-100 text-xs font-semibold text-stone-600 tabular-nums" aria-hidden="true">{{ $i + 1 }}</span>
                                @endif
                                @if ($suntingButir === $butir['id'])
                                    @include('livewire.pengaturan.partials.isian-butir-dasar-hukum', ['label' => 'Ubah butir '.($i + 1)])
                                @else
                                    <p class="min-w-0 text-sm text-stone-800">{{ $butir['teks'] }}</p>
                                    @if ($bolehUbah && $suntingButir === null)
                                        <div class="col-start-2 -my-1 flex justify-end gap-0.5 sm:col-start-auto">
                                            <button type="button" wire:click="geserButir(@js($butir['id']), -1)" @disabled($loop->first) data-geser="naik"
                                                    class="rounded-md p-1.5 text-stone-500 hover:bg-stone-100 hover:text-stone-800 disabled:opacity-30 disabled:hover:bg-transparent"
                                                    title="Naikkan" aria-label="Naikkan butir {{ $i + 1 }}"><x-ui.icon name="arrow-up" class="size-4" /></button>
                                            <button type="button" wire:click="geserButir(@js($butir['id']), 1)" @disabled($loop->last) data-geser="turun"
                                                    class="rounded-md p-1.5 text-stone-500 hover:bg-stone-100 hover:text-stone-800 disabled:opacity-30 disabled:hover:bg-transparent"
                                                    title="Turunkan" aria-label="Turunkan butir {{ $i + 1 }}"><x-ui.icon name="arrow-down" class="size-4" /></button>
                                            <button type="button" wire:click="ubahButir(@js($butir['id']))" class="rounded-md p-1.5 text-stone-500 hover:bg-stone-100 hover:text-stone-800" title="Ubah" aria-label="Ubah butir {{ $i + 1 }}"><x-ui.icon name="pencil" class="size-4" /></button>
                                            <button type="button" wire:click="hapusButir(@js($butir['id']))" class="rounded-md p-1.5 text-stone-500 hover:bg-red-50 hover:text-red-600" title="Hapus" aria-label="Hapus butir {{ $i + 1 }}"><x-ui.icon name="trash" class="size-4" /></button>
                                        </div>
                                    @endif
                                @endif
                            </li>
                        @endforeach
                        @if ($suntingButir === 'baru')
                            <li class="flex items-start gap-3 bg-brand-50/40 px-5 py-3 sm:px-6" wire:key="butir-baru" data-testid="butir-baru">
                                <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-800 tabular-nums" aria-hidden="true">{{ count($dasarHukum) + 1 }}</span>
                                @include('livewire.pengaturan.partials.isian-butir-dasar-hukum', ['label' => 'Butir baru'])
                            </li>
                        @endif
                    </ol>
                    <p class="sr-only" role="status">{{ $pengumuman }}</p>
                @endif
                <p class="border-t border-stone-100 px-5 py-3 text-xs text-stone-500 sm:px-6">Tanda baca akhir disusun otomatis seperti di dokumen: titik koma (;), butir terakhir titik (.). Tulis <code class="rounded bg-stone-100 px-1 font-mono text-[11px]">{tahun}</code> agar tahun anggaran terisi sendiri, <code class="rounded bg-stone-100 px-1 font-mono text-[11px]">{no_sk_rktm}</code>/<code class="rounded bg-stone-100 px-1 font-mono text-[11px]">{tanggal_sk_rktm}</code> untuk SK RKTM/RKAM.</p>
            </section>

            {{-- SK RKTM/RKAM: disimpan pada tahun anggaran, tercetak di butir dasar hukum lewat penanda. --}}
            @php
                $sk = \App\Support\Pengaturan\SkRktm::untuk($ta);
                $butirSk = $this->butirMemakaiSk();
                $peringatanSk = \App\Support\Pengaturan\SkRktm::peringatanTanggal($sk['tanggal'], $ta);
            @endphp
            <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-sk-rktm" data-testid="sk-rktm">
                <header class="flex flex-col gap-3 border-b border-stone-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                    <div class="min-w-0">
                        <h2 id="judul-sk-rktm" class="text-base font-semibold text-stone-900">SK RKTM/RKAM TA {{ $ta }}</h2>
                        <p class="mt-0.5 text-sm text-stone-500">Nomor &amp; tanggal SK penetapan rencana kerja tahunan, disimpan pada tahun anggaran.</p>
                    </div>
                    @if ($bolehUbah && ! $suntingSk)
                        <button type="button" wire:click="ubahSk" class="btn-secondary shrink-0 self-start"><x-ui.icon name="pencil" class="size-4" /> {{ $sk['nomor'] ? 'Ubah' : 'Isi SK' }}</button>
                    @endif
                </header>
                @if ($suntingSk)
                    <form wire:submit="simpanSk" class="px-5 py-4 sm:px-6" data-testid="isian-sk">
                        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_12rem]">
                            <x-ui.field label="Nomor SK" for="sk-nomor" error="isianSk.nomor">
                                <input type="text" id="sk-nomor" wire:model="isianSk.nomor" maxlength="{{ \App\Support\Pengaturan\SkRktm::MAKS_NOMOR }}" class="form-input" autocomplete="off"
                                       placeholder="mis. 123/Mts.13.25.2/PP.00/12/{{ $ta - 1 }}" x-init="$el.focus()"
                                       @error('isianSk.nomor') aria-invalid="true" aria-describedby="sk-nomor-error" @enderror>
                            </x-ui.field>
                            <x-ui.field label="Tanggal SK" for="sk-tanggal" error="isianSk.tanggal">
                                <input type="date" id="sk-tanggal" wire:model="isianSk.tanggal" class="form-input"
                                       @error('isianSk.tanggal') aria-invalid="true" aria-describedby="sk-tanggal-error" @enderror>
                            </x-ui.field>
                        </div>
                        <p class="mt-2 text-xs text-stone-500">Kosongkan keduanya untuk menghapus SK. Biasanya ditetapkan akhir TA {{ $ta - 1 }} atau awal TA {{ $ta }}.</p>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" wire:click="batalSk" class="btn-ghost">Batal</button>
                            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="simpanSk"><x-ui.icon name="save" class="size-4" /> <span wire:loading.remove wire:target="simpanSk">Simpan</span><span wire:loading wire:target="simpanSk">Menyimpan…</span></button>
                        </div>
                    </form>
                @else
                    <div class="space-y-3 px-5 py-4 sm:px-6">
                        @if ($sk['nomor'])
                            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-[minmax(0,1fr)_12rem]">
                                <div class="min-w-0"><dt class="text-xs text-stone-500">Nomor</dt><dd class="font-medium break-words text-stone-900" data-sk="nomor">{{ $sk['nomor'] }}</dd></div>
                                <div><dt class="text-xs text-stone-500">Tanggal</dt><dd class="font-medium text-stone-900" data-sk="tanggal">{{ \App\Support\Format::tanggal($sk['tanggal']) }}</dd></div>
                            </dl>
                        @else
                            <p class="flex items-center gap-1.5 text-sm font-medium text-gold-700" data-testid="sk-kosong"><x-ui.icon name="alert" class="size-4" /> Nomor &amp; tanggal SK TA {{ $ta }} belum diisi.</p>
                        @endif
                        @if ($peringatanSk)
                            <p class="flex items-start gap-1.5 text-xs text-gold-700"><x-ui.icon name="alert" class="mt-0.5 size-3.5 shrink-0" /> {{ $peringatanSk }}</p>
                        @endif
                        <p class="text-xs text-stone-500" data-testid="sk-dipakai">
                            @if ($butirSk !== [])
                                Tercetak di butir dasar hukum nomor {{ implode(', ', $butirSk) }}{{ $sk['nomor'] ? '' : ' — sementara tercetak "…" dan KAK/TOR ditandai belum lengkap' }}.
                            @else
                                Belum dipakai di dasar hukum — tulis <code class="rounded bg-stone-100 px-1 font-mono text-[11px]">{no_sk_rktm}</code> dan <code class="rounded bg-stone-100 px-1 font-mono text-[11px]">{tanggal_sk_rktm}</code> di butir SK RKTM agar nomor &amp; tanggalnya terisi sendiri.
                            @endif
                        </p>
                    </div>
                @endif
            </section>

            {{-- Latar belakang baku --}}
            <section class="rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-latar-baku" data-testid="latar-belakang-baku">
                <header class="flex flex-col gap-3 border-b border-stone-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                    <div class="min-w-0">
                        <h2 id="judul-latar-baku" class="text-base font-semibold text-stone-900">Latar belakang baku</h2>
                        <p class="mt-0.5 text-sm text-stone-500">Paragraf pembuka bagian 1 KAK/TOR semua kegiatan (visi madrasah).</p>
                    </div>
                    @if ($bolehUbah && ! $suntingLatar)
                        <button type="button" wire:click="ubahLatar" class="btn-secondary shrink-0 self-start"><x-ui.icon name="pencil" class="size-4" /> Ubah</button>
                    @endif
                </header>
                @if ($suntingLatar)
                    @include('livewire.pengaturan.partials.editor-latar-belakang')
                @else
                <div class="space-y-4 px-5 py-4 sm:px-6">
                    <div>
                        <h3 class="text-xs font-semibold text-stone-500">Paragraf baku</h3>
                        <p class="mt-1 text-sm leading-relaxed text-stone-800" data-testid="paragraf-baku">{{ $latar }}</p>
                        <p class="mt-1 text-xs text-stone-500">Tahun anggaran tersisip otomatis (TA {{ $ta }}).</p>
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold text-stone-500">Kalimat penutup tiap kegiatan</h3>
                        <p class="mt-1 rounded-lg bg-stone-50 px-3 py-2 text-sm leading-relaxed text-stone-800 ring-1 ring-stone-200" data-testid="kalimat-sisip">
                            @foreach ($sisip as $potong)@if ($potong['penanda'])<mark class="rounded bg-brand-100 px-1 font-medium text-brand-900" data-variabel="{{ $potong['penanda'] }}">{{ mb_strtolower(\App\Support\Pengaturan\TeksBaku::VARIABEL_KEGIATAN[$potong['penanda']]) }}</mark>@else{{ $potong['teks'] }}@endif@endforeach
                        </p>
                        <p class="mt-1 text-xs text-stone-500">Bagian bertanda diisi data tiap kegiatan (nama tanpa awalan "Kegiatan"); variabel yang belum diisi kegiatan ditandai di pratinjau KAK. Di antara paragraf baku dan kalimat ini, leading dapat menambah paragraf khusus kegiatannya.</p>
                    </div>
                </div>
                @endif
            </section>

            {{-- Pratinjau dokumen hasil otomatis: susunan & komponen yang sama dengan pratinjau Data Kegiatan (SusunanKak). --}}
            <section id="pratinjau-dokumen" class="scroll-mt-6 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-pratinjau-dasar" data-testid="pratinjau-dasar-hukum">
                <header class="flex flex-col gap-3 border-b border-stone-100 px-5 py-4 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                    <div class="min-w-0">
                        <h2 id="judul-pratinjau-dasar" class="text-base font-semibold text-stone-900">Pratinjau dokumen</h2>
                        <p class="mt-0.5 text-sm text-stone-500">Bagian 1–2 KAK/TOR yang dihasilkan otomatis untuk {{ $namaContoh }} · TA {{ $ta }}.</p>
                    </div>
                    <label class="w-full shrink-0 sm:w-72">
                        <span class="form-label">Contoh kegiatan</span>
                        <select wire:model.live="contoh" class="form-input py-1.5" data-testid="pilih-contoh">
                            <option value="">Otomatis — data terlengkap</option>
                            @foreach ($this->opsiContoh() as $opsi)
                                <option value="{{ $opsi['id'] }}">{{ $opsi['nama'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </header>
                @if ($kurangPratinjau !== [])
                    <div class="space-y-1 border-b border-gold-200 bg-gold-50 px-5 py-2 text-xs text-stone-700 sm:px-6" data-testid="pratinjau-dasar-kurang">
                        @foreach ($kurangPratinjau as $apa)
                            <p class="flex items-start gap-1.5"><x-ui.icon name="alert" class="mt-px size-3.5 shrink-0 text-gold-600" /> Lengkapi {{ $apa }}</p>
                        @endforeach
                    </div>
                @endif
                <div class="rounded-b-2xl bg-stone-50 p-3 sm:p-6" wire:loading.class="opacity-60" wire:target="contoh">
                    <x-dokumen.kak-tor :susunan="$susunan" :bagian="$susunan->nomorUntuk('latar_belakang', 'dasar_hukum')" />
                    <p class="mx-auto mt-3 max-w-[210mm] text-center text-xs text-stone-500">
                        Paragraf khusus di antara paragraf baku dan kalimat penutup diisi leading di Data Kegiatan; pratinjau lengkap ada di tombol <em>Pratinjau</em> tiap kegiatan.
                    </p>
                </div>
            </section>
        </div>

        <aside class="min-w-0 space-y-6">
            <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-variabel-dasar" data-testid="variabel-dasar-hukum">
                <h2 id="judul-variabel-dasar" class="text-sm font-semibold text-stone-900">Tercetak di KAK/TOR</h2>
                <p class="mt-0.5 text-xs text-stone-500">Bagian 1–2 setiap KAK/TOR, juga lewat variabel master template. <a href="#pratinjau-dokumen" class="font-semibold text-brand-700 hover:text-brand-800">Lihat pratinjau dokumen</a></p>
                <dl class="mt-4 space-y-1.5 text-xs" aria-label="Variabel template">
                    @foreach (['latar_belakang' => 'Paragraf baku + paragraf khusus + kalimat penutup', 'dasar_hukum' => 'Daftar bernomor'] as $variabel => $arti)
                        <div class="flex items-baseline justify-between gap-2">
                            <dt><code class="rounded bg-stone-100 px-1 py-0.5 font-mono text-[11px] text-stone-700">${{ '{'.$variabel.'}' }}</code></dt>
                            <dd class="text-right text-stone-500">{{ $arti }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            @if ($kesiapan)
                <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-kesiapan-dasar" data-testid="kesiapan-dasar-hukum">
                    <h2 id="judul-kesiapan-dasar" class="text-sm font-semibold text-stone-900">Kesiapan TA {{ $tahunAktif }}</h2>
                    <p class="mt-2 flex items-start gap-2 text-sm" data-siap="{{ $kesiapan['siap'] ? 'ya' : 'tidak' }}">
                        <x-ui.icon :name="$kesiapan['siap'] ? 'check-circle' : 'alert'" @class(['mt-0.5 size-4 shrink-0', 'text-brand-600' => $kesiapan['siap'], 'text-gold-600' => ! $kesiapan['siap']]) />
                        <span class="min-w-0">
                            <span class="block font-medium text-stone-800">{{ $kesiapan['judul'] }}</span>
                            <span class="block text-xs text-stone-500">{{ $kesiapan['keterangan'] }}</span>
                        </span>
                    </p>
                </section>
            @endif
        </aside>
    </div>
</div>
