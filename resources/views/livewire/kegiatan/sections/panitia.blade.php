@php
    $madrasahProfil = \App\Support\Stub\StubData::madrasah();
    $leadingPelaksana = \App\Support\MasterKode::leading($form->leading);
@endphp

<div class="space-y-6"
     x-data
     x-on:panitia-ditambah.window="$nextTick(() => { const rows = $el.querySelectorAll('[data-panitia-row]'); rows[$event.detail?.posisi === 'awal' ? 0 : rows.length - 1]?.querySelector('input')?.focus() })">

    {{-- Penyelenggara: diisi otomatis, sesuai bagian "Penyelenggara Kegiatan" pada KAK --}}
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-xl bg-stone-50 p-4 ring-1 ring-stone-200">
            <p class="text-[11px] font-semibold tracking-wider text-stone-500 uppercase">a. Pelaksana</p>
            @if ($leadingPelaksana)
                <p class="mt-1 text-sm font-semibold text-stone-800">{{ $leadingPelaksana['jabatan_penanda_tangan'] }}</p>
                <p class="mt-0.5 text-xs text-stone-500">Leading {{ $leadingPelaksana['kode'] }} · {{ $leadingPelaksana['nama_unit'] }}.</p>
            @else
                <p class="mt-1 text-sm font-semibold text-stone-800">Pejabat leading penanggung jawab</p>
                <p class="mt-0.5 text-xs text-stone-500">Otomatis dari leading yang dipilih di bagian Kode &amp; Klasifikasi.</p>
            @endif
        </div>
        <div class="rounded-xl bg-stone-50 p-4 ring-1 ring-stone-200">
            <p class="text-[11px] font-semibold tracking-wider text-stone-500 uppercase">b. Penanggung jawab (pengarah)</p>
            <p class="mt-1 text-sm font-semibold text-stone-800">Kepala {{ $madrasahProfil['nama_singkat'] }}</p>
            <p class="mt-0.5 text-xs text-stone-500">Otomatis dari pejabat penanda tangan tahun anggaran aktif.</p>
        </div>
    </div>

    {{-- Susunan tim --}}
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="flex items-center gap-2 text-sm font-semibold text-stone-800">
                    Susunan tim pelaksana
                    <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-600">{{ count($form->panitia) }} orang</span>
                </h3>
                <p class="text-xs text-stone-500">Urutan di sini menjadi urutan cetak pada KAK — seret nomor baris untuk memindahkan.</p>
            </div>
            @if (count($form->panitia) > 0)
                <div class="flex flex-wrap items-center gap-2">
                    @if (count($form->panitia) > 1)
                        <button type="button" wire:click="urutkanPanitiaSesuaiPeran" class="btn-ghost"
                                title="Pengarah → Ketua → Wakil → Sekretaris → Bendahara → Koordinator → Narasumber → Anggota">
                            <x-ui.icon name="layers" class="size-4" />
                            Urutkan sesuai peran
                        </button>
                    @endif
                    <button type="button" wire:click="tambahPanitia" class="btn-secondary">
                        <x-ui.icon name="plus" class="size-4" />
                        Tambah anggota
                    </button>
                </div>
            @endif
        </div>

        @if ($panitiaTerhapus)
            <div class="mt-3 flex items-center justify-between gap-3 rounded-lg bg-stone-800 px-3.5 py-2.5 text-sm text-white" role="status">
                <span class="min-w-0 truncate">
                    “{{ $panitiaTerhapus['anggota']['nama'] ?: 'Anggota tanpa nama' }}” dihapus dari susunan tim.
                </span>
                <button type="button" wire:click="urungkanHapusPanitia" class="shrink-0 font-semibold text-gold-300 hover:text-gold-200">
                    Urungkan
                </button>
            </div>
        @endif

        @if (count($form->panitia) === 0)
            <div class="mt-3 rounded-xl border border-dashed border-stone-300 px-4 py-8 text-center">
                <x-ui.icon name="users" class="mx-auto size-8 text-stone-300" />
                <p class="mt-2 text-sm font-medium text-stone-700">Belum ada anggota tim</p>
                <p class="mt-0.5 text-xs text-stone-500">Mulai dari susunan baku (Ketua, Sekretaris, Bendahara, Anggota) atau tambah satu per satu.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <button type="button" wire:click="isiSusunanBaku" class="btn-primary">
                        <x-ui.icon name="layers" class="size-4" />
                        Pakai susunan baku
                    </button>
                    <button type="button" wire:click="tambahPanitia" class="btn-secondary">
                        <x-ui.icon name="plus" class="size-4" />
                        Tambah anggota
                    </button>
                </div>
            </div>
        @else
            <div class="mt-3 hidden grid-cols-[2rem_minmax(0,1fr)_minmax(0,1fr)_10rem_6.5rem] gap-3 px-1 text-xs font-semibold text-stone-500 sm:grid">
                <span>No</span><span>Nama</span><span>Jabatan</span><span>Peran</span><span class="text-right">Aksi</span>
            </div>

            @php $panitiaGanda = $form->panitiaGanda(); @endphp

            <ol class="mt-2 space-y-2" wire:sort="pindahkanPanitia">
                @foreach ($form->panitia as $i => $anggota)
                    <li wire:key="panitia-{{ $anggota['key'] }}" wire:sort:item="{{ $anggota['key'] }}" data-panitia-row
                        class="grid grid-cols-[2rem_minmax(0,1fr)] gap-x-3 gap-y-2 rounded-xl bg-white p-3 ring-1 ring-stone-200 sm:grid-cols-[2rem_minmax(0,1fr)_minmax(0,1fr)_10rem_6.5rem] sm:items-start sm:rounded-lg sm:p-1 sm:ring-0">
                        <span wire:sort:handle title="Seret untuk mengurutkan"
                              class="group/handle grid size-8 cursor-grab touch-none place-items-center rounded-lg bg-stone-100 text-xs font-bold text-stone-600 hover:bg-brand-100 hover:text-brand-800 active:cursor-grabbing sm:mt-0.5">
                            <span class="group-hover/handle:hidden">{{ $i + 1 }}</span>
                            <x-ui.icon name="grip" class="hidden size-4 group-hover/handle:block" />
                        </span>

                        <div class="min-w-0">
                            <label for="panitia-{{ $i }}-nama" class="sr-only">Nama anggota {{ $i + 1 }}</label>
                            <input type="text" id="panitia-{{ $i }}-nama" wire:model.live.blur="form.panitia.{{ $i }}.nama"
                                   maxlength="150" placeholder="Nama lengkap & gelar" autocomplete="off"
                                   class="form-input @error('form.panitia.'.$i.'.nama') ring-red-400 @enderror">
                            @error('form.panitia.'.$i.'.nama') <p class="form-error">{{ $message }}</p> @enderror
                            @if (in_array($i, $panitiaGanda, true))
                                <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-gold-700">
                                    <x-ui.icon name="alert" class="size-3.5" /> Nama ini tercantum lebih dari sekali.
                                </p>
                            @endif
                        </div>

                        <div class="col-start-2 min-w-0 sm:col-start-auto">
                            <label for="panitia-{{ $i }}-jabatan" class="sr-only">Jabatan anggota {{ $i + 1 }}</label>
                            <input type="text" id="panitia-{{ $i }}-jabatan" wire:model.live.blur="form.panitia.{{ $i }}.jabatan"
                                   maxlength="150" placeholder="Jabatan, mis. Guru Mapel IPA" autocomplete="off"
                                   class="form-input @error('form.panitia.'.$i.'.jabatan') ring-red-400 @enderror">
                            @error('form.panitia.'.$i.'.jabatan') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="col-start-2 min-w-0 sm:col-start-auto">
                            <label for="panitia-{{ $i }}-peran" class="sr-only">Peran anggota {{ $i + 1 }}</label>
                            <input type="text" id="panitia-{{ $i }}-peran" wire:model.live.blur="form.panitia.{{ $i }}.peran"
                                   list="saran-peran-panitia" maxlength="100" placeholder="Peran" autocomplete="off"
                                   class="form-input @error('form.panitia.'.$i.'.peran') ring-red-400 @enderror">
                            @error('form.panitia.'.$i.'.peran') <p class="form-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="col-start-2 flex items-center justify-end gap-0.5 sm:col-start-auto sm:mt-0.5">
                            <button type="button" wire:click="geserPanitia({{ $i }}, -1)" @disabled($loop->first)
                                    class="rounded-md p-1.5 text-stone-500 hover:bg-stone-100 hover:text-stone-800 disabled:opacity-30 disabled:hover:bg-transparent"
                                    title="Naikkan" aria-label="Naikkan anggota {{ $i + 1 }}">
                                <x-ui.icon name="arrow-up" class="size-4" />
                            </button>
                            <button type="button" wire:click="geserPanitia({{ $i }}, 1)" @disabled($loop->last)
                                    class="rounded-md p-1.5 text-stone-500 hover:bg-stone-100 hover:text-stone-800 disabled:opacity-30 disabled:hover:bg-transparent"
                                    title="Turunkan" aria-label="Turunkan anggota {{ $i + 1 }}">
                                <x-ui.icon name="arrow-down" class="size-4" />
                            </button>
                            <button type="button" wire:click="hapusPanitia({{ $i }})"
                                    class="rounded-md p-1.5 text-stone-500 hover:bg-red-50 hover:text-red-600"
                                    title="Hapus" aria-label="Hapus anggota {{ $i + 1 }}">
                                <x-ui.icon name="trash" class="size-4" />
                            </button>
                        </div>
                    </li>
                @endforeach
            </ol>

            @error('form.panitia') <p class="form-error">{{ $message }}</p> @enderror

            <button type="button" wire:click="tambahPanitia"
                    class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-stone-300 py-2.5 text-sm font-medium text-stone-600 hover:border-brand-400 hover:bg-brand-50 hover:text-brand-800">
                <x-ui.icon name="plus" class="size-4" />
                Tambah anggota
            </button>
        @endif

        <datalist id="saran-peran-panitia">
            @foreach (\App\Livewire\Forms\KegiatanForm::PERAN_PANITIA as $peran)
                <option value="{{ $peran }}"></option>
            @endforeach
        </datalist>
    </div>
</div>
