{{--
    Daftar akun (Akun\Daftar): formulir permohonan akun yang diaktifkan admin. Ketikan disalin Alpine `formDaftar`
    (resources/js/sandi.js) untuk syarat sandi, kecocokan sandi ulang, pratinjau NIP, dan hitungan catatan; Livewire
    memeriksa tiap kolom saat blur dan sekali lagi saat dikirim (PendaftaranAkun).
--}}
<div>
    @if ($terkirim !== null)
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-stone-200" aria-labelledby="judul-terkirim" data-testid="pendaftaran-terkirim" wire:key="terkirim">
            <span class="grid size-11 place-items-center rounded-full bg-brand-50 text-brand-700"><x-ui.icon name="check-circle" class="size-6" /></span>
            <h1 id="judul-terkirim" class="mt-4 text-xl font-bold tracking-tight text-stone-900">Permohonan terkirim</h1>
            <p class="mt-1 text-sm text-stone-600">Admin madrasah akan memeriksa data Anda lalu mengaktifkan akun. Setelah aktif, masuk dengan surel <span class="font-medium text-stone-900">{{ $terkirim['surel'] }}</span> dan sandi yang baru Anda buat.</p>

            <dl class="mt-5 divide-y divide-stone-100 rounded-xl bg-stone-50 px-4 text-sm ring-1 ring-stone-200" data-testid="ringkasan-permohonan">
                <div class="flex justify-between gap-4 py-2.5"><dt class="text-stone-500">Nama</dt><dd class="text-right font-medium text-stone-900">{{ $terkirim['nama'] }}</dd></div>
                @if ($terkirim['jabatan'])
                    <div class="flex justify-between gap-4 py-2.5"><dt class="text-stone-500">Jabatan</dt><dd class="text-right text-stone-900">{{ $terkirim['jabatan'] }}</dd></div>
                @endif
                @if ($terkirim['nip_format'])
                    <div class="flex justify-between gap-4 py-2.5"><dt class="text-stone-500">NIP</dt><dd class="text-right font-mono text-stone-900">{{ $terkirim['nip_format'] }}</dd></div>
                @endif
                <div class="flex justify-between gap-4 py-2.5">
                    <dt class="text-stone-500">Leading</dt>
                    <dd class="flex flex-wrap justify-end gap-1.5">
                        @foreach ($terkirim['leading'] as $l)
                            <span class="rounded-md bg-white px-2 py-0.5 text-xs text-stone-700 ring-1 ring-stone-200">{{ $l['kode'] }} {{ $l['nama_unit'] }}</span>
                        @endforeach
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-4 py-2.5">
                    <dt class="text-stone-500">Status</dt>
                    <dd><span class="inline-flex items-center gap-1 rounded-full bg-gold-50 px-2.5 py-0.5 text-xs font-medium text-gold-800 ring-1 ring-gold-200" data-testid="status-permohonan" data-status="{{ $terkirim['status'] }}"><x-ui.icon name="circle" class="size-3" /> {{ $terkirim['label_status'] }}</span></dd>
                </div>
                <div class="flex justify-between gap-4 py-2.5"><dt class="text-stone-500">Diajukan</dt><dd class="text-right text-stone-900">{{ \App\Support\Format::tanggal($terkirim['diajukan_pada']) }} · {{ \Illuminate\Support\Carbon::parse($terkirim['diajukan_pada'])->timezone(config('app.timezone'))->format('H.i') }}</dd></div>
            </dl>

            <a href="{{ route('akun.masuk') }}" class="btn-primary mt-6 w-full justify-center py-2.5" data-testid="ke-masuk">Ke halaman masuk</a>
        </section>
    @else
        <div wire:key="formulir">
            <h1 class="text-2xl font-bold tracking-tight text-stone-900">Daftar akun</h1>
            <p class="mt-1 text-sm text-stone-500">Ajukan akun untuk menyusun KAK/TOR & RAB. Admin madrasah memeriksa data Anda lalu mengaktifkan akunnya.</p>

            <form wire:submit="kirim" x-data="formDaftar" class="mt-8 space-y-8" data-testid="form-daftar" novalidate
                  x-on:galat-pendaftaran.window="$nextTick(() => document.getElementById('daftar-' + $event.detail.kolom)?.focus())">
                {{-- 1. Identitas --}}
                <fieldset class="space-y-4">
                    <legend class="mb-3 flex items-center gap-2 text-sm font-semibold text-stone-900"><span class="grid size-5 place-items-center rounded-full bg-brand-700 text-[11px] text-white">1</span> Identitas</legend>
                    <x-ui.field label="Nama lengkap & gelar" for="daftar-nama" error="isian.nama" required hint="Seperti tercetak di lembar pengesahan, mis. Nur Laili Fitriani, S.Pd.">
                        <input id="daftar-nama" type="text" wire:model.live.blur="isian.nama" x-on:input="nama = $event.target.value" autocomplete="name" maxlength="150"
                               class="form-input" data-testid="isian-daftar-nama" @error('isian.nama') aria-invalid="true" aria-describedby="daftar-nama-error" @enderror>
                    </x-ui.field>
                    <x-ui.field label="Jabatan (opsional)" for="daftar-jabatan" error="isian.jabatan" hint="Mis. Koordinator Literasi, Wakil Kepala Bidang Kurikulum.">
                        <input id="daftar-jabatan" type="text" wire:model.live.blur="isian.jabatan" autocomplete="organization-title" maxlength="150"
                               class="form-input" data-testid="isian-daftar-jabatan" @error('isian.jabatan') aria-invalid="true" aria-describedby="daftar-jabatan-error" @enderror>
                    </x-ui.field>
                    <x-ui.field label="NIP (opsional)" for="daftar-nip" error="isian.nip">
                        <input id="daftar-nip" type="text" wire:model.live.blur="isian.nip" x-on:input="nip = $event.target.value" inputmode="numeric" autocomplete="off" maxlength="24"
                               class="form-input font-mono" placeholder="19700101 199503 1 001" data-testid="isian-daftar-nip" @error('isian.nip') aria-invalid="true" aria-describedby="daftar-nip-error" @enderror>
                        <p class="mt-1.5 text-xs" x-show="nip.trim() !== ''" x-cloak aria-live="polite" data-testid="pratinjau-nip-daftar"
                           x-bind:class="{ 'font-medium text-gold-700': $galatNip(nip), 'text-brand-700': ! $galatNip(nip) }" x-text="pratinjauNip"></p>
                    </x-ui.field>
                </fieldset>

                {{-- 2. Leading --}}
                <fieldset id="daftar-leadings" tabindex="-1" class="focus:outline-none" @error('isian.leadings') aria-describedby="daftar-leadings-error" @enderror>
                    <legend class="mb-1 flex items-center gap-2 text-sm font-semibold text-stone-900"><span class="grid size-5 place-items-center rounded-full bg-brand-700 text-[11px] text-white">2</span> Leading yang Anda tangani <span class="text-red-600" aria-hidden="true">*</span></legend>
                    <p class="mb-3 text-xs text-stone-500">Kegiatan dan dokumen Anda tersimpan atas nama leading ini. Boleh lebih dari satu.</p>
                    <div class="grid gap-2 sm:grid-cols-2" data-testid="pilihan-leading-daftar">
                        @foreach ($leading as $l)
                            <label wire:key="leading-{{ $l['kode'] }}" class="flex cursor-pointer items-start gap-3 rounded-xl bg-white px-3 py-2.5 ring-1 ring-stone-200 hover:ring-brand-300 has-[:checked]:bg-brand-50 has-[:checked]:ring-brand-400" data-testid="pilih-leading" data-kode="{{ $l['kode'] }}">
                                <input type="checkbox" value="{{ $l['kode'] }}" wire:model.live="isian.leadings" class="mt-0.5 size-4 rounded border-stone-300 text-brand-700 focus:ring-brand-500">
                                <span class="min-w-0 text-sm leading-tight">
                                    <span class="font-mono text-xs text-stone-500">{{ $l['kode'] }}</span>
                                    <span class="block font-medium text-stone-900">{{ $l['nama_unit'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('isian.leadings')<p id="daftar-leadings-error" class="form-error" role="alert">{{ $message }}</p>@enderror
                </fieldset>

                {{-- 3. Akun untuk masuk --}}
                <fieldset class="space-y-4">
                    <legend class="mb-3 flex items-center gap-2 text-sm font-semibold text-stone-900"><span class="grid size-5 place-items-center rounded-full bg-brand-700 text-[11px] text-white">3</span> Akun untuk masuk</legend>
                    <x-ui.field label="Surel" for="daftar-surel" error="isian.surel" required hint="Dipakai untuk masuk dan menerima kabar dari admin.">
                        <input id="daftar-surel" type="email" wire:model.live.blur="isian.surel" x-on:input="surel = $event.target.value" autocomplete="email" inputmode="email" maxlength="255"
                               class="form-input" data-testid="isian-daftar-surel" @error('isian.surel') aria-invalid="true" aria-describedby="daftar-surel-error" @enderror>
                    </x-ui.field>
                    <x-ui.field label="Sandi" for="daftar-sandi" error="isian.sandi" required>
                        <div class="relative">
                            <input id="daftar-sandi" x-bind:type="lihat ? 'text' : 'password'" type="password" wire:model.live.blur="isian.sandi" x-on:input="sandi = $event.target.value" autocomplete="new-password" maxlength="64"
                                   class="form-input pr-10" aria-describedby="syarat-sandi-daftar @error('isian.sandi') daftar-sandi-error @enderror" data-testid="isian-daftar-sandi" @error('isian.sandi') aria-invalid="true" @enderror>
                            <button type="button" x-on:click="lihat = ! lihat" class="absolute top-1/2 right-2 -translate-y-1/2 rounded-md p-1 text-stone-400 hover:text-stone-700" x-bind:aria-label="lihat ? 'Sembunyikan sandi' : 'Tampilkan sandi'" x-bind:aria-pressed="lihat" data-testid="lihat-sandi-daftar">
                                <x-ui.icon name="eye" class="size-4" />
                            </button>
                        </div>
                        <div class="mt-2 flex gap-1" aria-hidden="true" data-testid="kekuatan-sandi" x-bind:data-tingkat="tingkat">
                            @foreach ([1, 2, 3] as $n)
                                <span class="h-1 flex-1 rounded-full" x-bind:data-nyala="nyala({{ $n }}) ? 'ya' : 'tidak'"
                                      x-bind:class="{ 'bg-red-500': tingkat === 'lemah' && nyala({{ $n }}), 'bg-gold-500': tingkat === 'sedang' && nyala({{ $n }}), 'bg-brand-600': tingkat === 'kuat', 'bg-stone-200': ! nyala({{ $n }}) }"></span>
                            @endforeach
                        </div>
                        <ul id="syarat-sandi-daftar" class="mt-2 space-y-1 text-xs" role="list" aria-live="polite" data-testid="syarat-sandi-daftar">
                            @foreach ($syarat as $kunci => $label)
                                <li class="flex items-center gap-1.5" data-syarat="{{ $kunci }}" x-bind:data-ok="syarat.{{ $kunci }} && sandi !== '' ? 'ya' : 'tidak'"
                                    x-bind:class="{ 'text-brand-700': syarat.{{ $kunci }} && sandi !== '', 'text-stone-500': ! (syarat.{{ $kunci }} && sandi !== '') }">
                                    <span x-show="syarat.{{ $kunci }} && sandi !== ''"><x-ui.icon name="check" class="size-3.5" /></span>
                                    <span x-show="! (syarat.{{ $kunci }} && sandi !== '')"><x-ui.icon name="circle" class="size-3.5" /></span>
                                    <span>{{ $label }}<span class="sr-only" x-text="syarat.{{ $kunci }} && sandi !== '' ? ' — terpenuhi' : ' — belum'"></span></span>
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.field>
                    <x-ui.field label="Ulangi sandi" for="daftar-sandi_ulang" error="isian.sandi_ulang" required>
                        <input id="daftar-sandi_ulang" x-bind:type="lihat ? 'text' : 'password'" type="password" wire:model.live.blur="isian.sandi_ulang" x-on:input="ulang = $event.target.value" autocomplete="new-password" maxlength="64"
                               class="form-input" data-testid="isian-daftar-sandi-ulang" @error('isian.sandi_ulang') aria-invalid="true" aria-describedby="daftar-sandi_ulang-error" @enderror>
                        <p class="mt-1.5 flex items-center gap-1 text-xs" x-show="ulang !== ''" x-cloak aria-live="polite" data-testid="sandi-cocok" x-bind:data-cocok="cocok ? 'ya' : 'tidak'"
                           x-bind:class="{ 'text-brand-700': cocok, 'text-gold-700': ! cocok }" x-text="cocok ? 'Sandi sama.' : 'Belum sama dengan sandi di atas.'"></p>
                    </x-ui.field>
                </fieldset>

                <x-ui.field label="Catatan untuk admin (opsional)" for="daftar-catatan" error="isian.catatan">
                    <x-slot:aside><span x-text="[...catatan].length + '/{{ \App\Support\Akun\PendaftaranAkun::MAKS_CATATAN }}'" data-testid="hitung-catatan"></span></x-slot:aside>
                    <textarea id="daftar-catatan" rows="3" wire:model.live.blur="isian.catatan" x-on:input="catatan = $event.target.value" maxlength="{{ \App\Support\Akun\PendaftaranAkun::MAKS_CATATAN }}"
                              class="form-input" placeholder="Mis. kegiatan yang akan Anda susun atau nomor yang bisa dihubungi admin." data-testid="isian-daftar-catatan" @error('isian.catatan') aria-invalid="true" aria-describedby="daftar-catatan-error" @enderror></textarea>
                </x-ui.field>

                <div>
                    <label class="flex items-start gap-2.5 text-sm text-stone-700">
                        <input id="daftar-setuju" type="checkbox" wire:model.live="isian.setuju" class="mt-0.5 size-4 rounded border-stone-300 text-brand-700 focus:ring-brand-500" data-testid="isian-daftar-setuju" @error('isian.setuju') aria-invalid="true" aria-describedby="daftar-setuju-error" @enderror>
                        <span>Data di atas benar, dan saya akan menjaga kerahasiaan sandi akun ini.</span>
                    </label>
                    @error('isian.setuju')<p id="daftar-setuju-error" class="form-error" role="alert">{{ $message }}</p>@enderror
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="kirim" class="btn-primary w-full justify-center py-2.5" data-testid="kirim-pendaftaran">
                    <span wire:loading.remove wire:target="kirim">Kirim permohonan</span>
                    <span wire:loading wire:target="kirim">Mengirim…</span>
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-stone-500">Sudah punya akun? <a href="{{ route('akun.masuk') }}" class="font-medium text-brand-700 hover:underline" data-testid="tautan-masuk">Masuk</a></p>
        </div>
    @endif
</div>
