@use('App\Livewire\Kegiatan\SalinImpor')
@use('App\Support\Format')
@use('App\Support\Kegiatan\Duplikat\DuplikatKegiatan')
@use('App\Support\MasterKode')

@php
    $tahunAktif = \App\Support\Stub\StubData::tahunAktif()['tahun'];
    $tahunDipakai = $this->tahunDipakai();
    $sumber = $tab === 'duplikat' ? $this->sumberTampil() : [];
    $pengguna = \App\Support\Stub\StubData::currentUser();
    $pilihanLeading = $pengguna['role'] === 'admin' ? MasterKode::semuaLeading() : array_values(array_filter(MasterKode::semuaLeading(), fn ($l) => in_array($l['kode'], $pengguna['leadings'], true)));
    $kolomPerkin = [
        ['Kode SK', 'Sasaran Kegiatan Perjanjian Kinerja (SK.n); kosong → diambil dari induk IKSK'],
        ['Kode IKSK', 'Indikator kinerja di bawah SK tersebut (IKSK n.m); kode yang tidak ada di master TA '.$tahunAktif.' dikosongkan'],
        ['Nama Kegiatan', 'Wajib; nama yang sudah ada di TA '.$tahunAktif.' dilewati'],
        ['Leading', 'Kode leading, mis. 1.6'],
        ['Pagu DIPA / Pagu Komite', 'Minimal salah satu; menjadi sumber dana kegiatan'],
        ['Bulan Pelaksanaan', 'Nama atau nomor bulan, boleh lebih dari satu'],
    ];
@endphp

<div class="mx-auto max-w-7xl">
    {{-- Kepala halaman --}}
    <div class="min-w-0">
        <nav class="flex items-center gap-1 text-xs font-medium text-stone-500" aria-label="Breadcrumb">
            <a href="{{ route('kegiatan.index') }}" wire:navigate class="hover:text-stone-800">Data Kegiatan</a>
            <x-ui.icon name="chevron-right" class="size-3.5" />
            <span class="text-stone-700">Duplikat &amp; Impor</span>
        </nav>
        <div class="mt-1.5 flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-bold tracking-tight text-stone-900 sm:text-2xl">Duplikat &amp; Impor</h1>
            <span class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-800 ring-1 ring-brand-200 ring-inset">
                <x-ui.icon name="calendar" class="size-3.5" /> Menjadi draf TA {{ $tahunAktif }}
            </span>
        </div>
        <p class="mt-1 max-w-3xl text-sm text-stone-500">Buat banyak draf kegiatan sekaligus: salin kegiatan tahun lalu sebagai dasar, atau impor dari sheet cascade Perkin. Semua hasilnya berstatus Draf dan bisa disunting seperti kegiatan biasa.</p>
    </div>

    {{-- Tab --}}
    <nav class="mt-5 -mb-px flex gap-1 overflow-x-auto border-b border-stone-200" aria-label="Cara membuat draf" data-testid="tab-salin-impor">
        @foreach (SalinImpor::TAB as $kunci => $label)
            <button type="button" wire:click="$set('tab', '{{ $kunci }}')" aria-pressed="{{ $tab === $kunci ? 'true' : 'false' }}" data-tab="{{ $kunci }}"
                    @class(['flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm transition', 'border-brand-700 font-semibold text-brand-800' => $tab === $kunci, 'border-transparent font-medium text-stone-500 hover:border-stone-300 hover:text-stone-800' => $tab !== $kunci])>
                <x-ui.icon :name="$kunci === 'duplikat' ? 'copy' : 'sheet'" class="size-4" /> {{ $label }}
            </button>
        @endforeach
    </nav>

    @if ($tab === 'duplikat')
        @if ($hasilDuplikat !== [])
            @include('livewire.kegiatan.partials.hasil-duplikat')
        @endif
        @if ($dialogDuplikat)
            @include('livewire.kegiatan.partials.dialog-duplikat', ['rencana' => $this->rencanaDuplikat()])
        @endif

        <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" data-testid="panel-duplikat" wire:key="panel-duplikat">
            <section class="min-w-0 rounded-2xl bg-white shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-sumber-duplikat">
                <header class="space-y-3 border-b border-stone-100 px-5 py-4 sm:px-6">
                    <div>
                        <h2 id="judul-sumber-duplikat" class="text-base font-semibold text-stone-900">Pilih kegiatan {{ $tahunDipakai ? 'TA '.$tahunDipakai : 'tahun lalu' }}</h2>
                        <p class="mt-0.5 text-sm text-stone-500">Kegiatan terpilih disalin menjadi draf TA {{ $tahunAktif }}; kegiatan aslinya tidak berubah.</p>
                    </div>
                    @if ($tahunDipakai)
                        <div class="grid gap-2 sm:grid-cols-[9rem_minmax(0,1fr)_13rem]">
                            <label>
                                <span class="sr-only">Tahun sumber</span>
                                <select wire:model.live="tahunSumber" class="form-input py-1.5" aria-label="Tahun sumber">
                                    @foreach (DuplikatKegiatan::tahunSumber() as $t)
                                        <option value="{{ $t === DuplikatKegiatan::tahunSumber()[0] ? 0 : $t }}">TA {{ $t }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span class="sr-only">Cari kegiatan sumber</span>
                                <input type="search" wire:model.live.debounce.300ms="cari" placeholder="Cari nama kegiatan…" class="form-input py-1.5" autocomplete="off">
                            </label>
                            <label>
                                <span class="sr-only">Saring leading</span>
                                <select wire:model.live="leading" class="form-input py-1.5" aria-label="Leading">
                                    <option value="">Semua leading</option>
                                    @foreach ($pilihanLeading as $l)
                                        <option value="{{ $l['kode'] }}">{{ $l['kode'] }} · {{ $l['nama_unit'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    @endif
                </header>

                @if ($tahunDipakai === null)
                    <p class="px-5 py-12 text-center text-sm text-stone-500 sm:px-6" data-testid="tanpa-tahun-sumber">Belum ada tahun anggaran lain sebagai sumber duplikat.</p>
                @elseif ($sumber === [])
                    <div class="px-5 py-12 text-center sm:px-6" data-testid="sumber-kosong">
                        <x-ui.icon name="copy" class="mx-auto size-10 text-stone-300" />
                        <p class="mt-3 text-sm font-medium text-stone-700">{{ $this->semuaSumber === [] ? 'Belum ada kegiatan TA '.$tahunDipakai.' yang bisa diduplikat.' : 'Tidak ada kegiatan yang cocok.' }}</p>
                    </div>
                @else
                    <x-kegiatan.daftar-tahun-lalu :baris="$sumber" :pilihan="$pilihan" :tahun-aktif="$tahunAktif" wire:loading.class="opacity-60" wire:target="tahunSumber,cari,leading" />
                @endif

                <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-stone-100 px-5 py-3 sm:px-6">
                    <p class="text-sm text-stone-600" data-testid="jumlah-pilihan">{{ count($pilihan) }} kegiatan dipilih</p>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($sumber !== [])
                            <button type="button" wire:click="pilihSemua" class="btn-ghost px-2.5 py-1.5 text-sm">Pilih semua</button>
                        @endif
                        @if ($pilihan !== [])
                            <button type="button" wire:click="kosongkanPilihan" class="btn-ghost px-2.5 py-1.5 text-sm">Kosongkan</button>
                        @endif
                        @if ($pilihan === [])
                            <button type="button" class="btn-primary" disabled wire:key="duplikat-nonaktif"><x-ui.icon name="copy" class="size-4" /> Duplikat ke TA {{ $tahunAktif }}</button>
                        @else
                            <button type="button" wire:click="bukaDuplikat" class="btn-primary" wire:key="duplikat-aktif" data-testid="buka-duplikat"><x-ui.icon name="copy" class="size-4" /> Duplikat {{ count($pilihan) }} ke TA {{ $tahunAktif }}</button>
                        @endif
                    </div>
                </footer>
            </section>

            <aside class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-perubahan-duplikat" data-testid="perubahan-duplikat">
                <h2 id="judul-perubahan-duplikat" class="text-sm font-semibold text-stone-900">Diperbarui otomatis</h2>
                <ul class="mt-3 space-y-2.5">
                    @foreach (DuplikatKegiatan::PERUBAHAN as $kunci => [$judul, $keterangan])
                        <li class="flex gap-2 text-xs" data-perubahan="{{ $kunci }}">
                            <x-ui.icon name="check-circle" class="mt-px size-4 shrink-0 text-brand-600" />
                            <span><span class="font-semibold text-stone-800">{{ $judul }}</span><span class="block text-stone-500">{{ str_replace('{tahun}', $tahunAktif, $keterangan) }}</span></span>
                        </li>
                    @endforeach
                </ul>
            </aside>
        </div>
    @endif

    {{--
        Panel Perkin selalu dirender (disembunyikan di tab lain) agar pratinjau impor yang sedang dibuka tidak hilang saat
        berganti tab. Tabel pratinjau butuh lebar penuh: kartu samping turun ke bawah sampai layar 2xl.
    --}}
    <div @class(['mt-6 grid items-start gap-6 2xl:grid-cols-[minmax(0,1fr)_20rem]', 'hidden' => $tab !== 'perkin']) data-testid="panel-perkin" wire:key="panel-perkin">
        <div class="min-w-0 space-y-6">
            <livewire:kegiatan.impor-perkin wire:key="impor-perkin" />

            <section class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-stone-200 sm:p-6" aria-labelledby="judul-kolom-perkin">
                <h2 id="judul-kolom-perkin" class="text-sm font-semibold text-stone-900">Kolom yang dibaca</h2>
                <p class="mt-0.5 text-xs text-stone-500">Sheet bernama Perkin/Cascade dibaca lebih dulu; judul kolom boleh di baris mana pun di 15 baris teratas. Sel Kode SK &amp; IKSK yang digabung berlaku untuk kegiatan di bawahnya.</p>
                <table class="mt-2 w-full text-left text-sm" data-testid="kolom-perkin">
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($kolomPerkin as [$kolom, $keterangan])
                            <tr>
                                <th scope="row" class="py-2 pr-4 align-top text-xs font-semibold whitespace-nowrap text-stone-800">{{ $kolom }}</th>
                                <td class="py-2 text-xs text-stone-500">{{ $keterangan }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        </div>

        <aside class="grid items-start gap-4 sm:grid-cols-2 2xl:grid-cols-1">
            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-hasil-perkin">
                <h2 id="judul-hasil-perkin" class="text-sm font-semibold text-stone-900">Aman diimpor ulang</h2>
                <p class="mt-1 text-xs text-stone-500">Nama kegiatan yang sudah ada di TA {{ $tahunAktif }} dilewati, jadi berkas yang sama boleh diunggah lagi setelah diperbarui.</p>
            </section>
            <section class="rounded-2xl bg-white p-4 shadow-xs ring-1 ring-stone-200" aria-labelledby="judul-template-sederhana">
                <h2 id="judul-template-sederhana" class="text-sm font-semibold text-stone-900">Tidak memakai sheet Perkin?</h2>
                <p class="mt-1 text-xs text-stone-500">Gunakan template sederhana (nama kegiatan, leading, pagu, bulan) dari Data Kegiatan › Impor dari Excel.</p>
                <a href="{{ route('kegiatan.index') }}" wire:navigate class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800">Buka Data Kegiatan <x-ui.icon name="chevron-right" class="size-3.5" /></a>
            </section>
        </aside>
    </div>
</div>
