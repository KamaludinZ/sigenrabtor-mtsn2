@php($peringatan = $form->peringatan())

<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field class="sm:col-span-2" label="Nama kegiatan" for="nama_kegiatan" error="form.nama_kegiatan" required
                :warning="$peringatan['nama_kegiatan'] ?? null"
                hint="Tulis tanpa awalan “Kegiatan”, mis. Workshop Penyusunan Kurikulum Madrasah dan Perangkat Pembelajaran.">
        <input type="text" id="nama_kegiatan" wire:model.live.debounce.500ms="form.nama_kegiatan" maxlength="255"
               autocomplete="off" class="form-input" placeholder="Nama kegiatan sesuai cascade Perkin"
               @error('form.nama_kegiatan') aria-invalid="true" aria-describedby="nama_kegiatan-error" @enderror>
    </x-ui.field>

    <x-ui.field class="sm:col-span-2" label="Nama kegiatan (cover)" for="nama_kegiatan_cover" error="form.nama_kegiatan_cover" required
                hint="Selalu huruf kapital; tercetak di cover KAK/TOR dan judul RAB.">
        <x-slot:aside>
            @if ($form->cover_manual)
                <button type="button" wire:click="resetNamaCover" class="font-semibold text-brand-700 hover:text-brand-800">
                    Samakan dengan nama kegiatan
                </button>
            @else
                <span class="inline-flex items-center gap-1 rounded bg-brand-50 px-1.5 py-0.5 font-semibold text-brand-700">
                    <x-ui.icon name="check" class="size-3" /> Otomatis
                </span>
            @endif
        </x-slot:aside>
        <input type="text" id="nama_kegiatan_cover" wire:model.live.blur="form.nama_kegiatan_cover" maxlength="255"
               autocomplete="off" class="form-input uppercase tracking-wide" placeholder="Terisi otomatis dari nama kegiatan"
               @error('form.nama_kegiatan_cover') aria-invalid="true" aria-describedby="nama_kegiatan_cover-error" @enderror>
    </x-ui.field>

    <x-ui.field class="sm:col-span-2" label="Latar belakang (paragraf khusus)" for="latar_belakang" error="form.latar_belakang"
                hint="Paragraf baku visi madrasah dan kalimat “…adalah kegiatan {nama}” ditambahkan otomatis di atas paragraf ini.">
        <x-slot:aside><span x-data="{ n: {{ mb_strlen($form->latar_belakang) }} }" x-text="n + ' / 5000'" x-on:input.window="if ($event.target.id === 'latar_belakang') n = $event.target.value.length"></span></x-slot:aside>
        <textarea id="latar_belakang" wire:model.live.blur="form.latar_belakang" rows="4" maxlength="5000"
                  class="form-input field-sizing-content min-h-24" placeholder="Alasan khusus kegiatan ini diselenggarakan (opsional)."
                  @error('form.latar_belakang') aria-invalid="true" aria-describedby="latar_belakang-error" @enderror></textarea>
    </x-ui.field>

    <x-ui.field class="sm:col-span-2" label="Tujuan" for="tujuan" error="form.tujuan" :warning="$peringatan['tujuan'] ?? null"
                hint="Wajib untuk dokumen lengkap. Tercetak sebagai: “Kegiatan {nama} bertujuan {tujuan}”.">
        <x-slot:aside><span x-data="{ n: {{ mb_strlen($form->tujuan) }} }" x-text="n + ' / 2000'" x-on:input.window="if ($event.target.id === 'tujuan') n = $event.target.value.length"></span></x-slot:aside>
        <textarea id="tujuan" wire:model.live.blur="form.tujuan" rows="3" maxlength="2000"
                  class="form-input field-sizing-content min-h-20" placeholder="mis. menghasilkan dokumen kurikulum yang terlegitimasi sesuai prinsip partisipatif, transparan, dan akuntabel."
                  @error('form.tujuan') aria-invalid="true" aria-describedby="tujuan-error" @enderror></textarea>
    </x-ui.field>

    <x-ui.field class="sm:col-span-2" label="Sasaran" for="sasaran" error="form.sasaran" :warning="$peringatan['sasaran'] ?? null"
                hint="Wajib untuk dokumen lengkap. Tercetak sebagai: “Terwujudnya {sasaran}”.">
        <x-slot:aside><span x-data="{ n: {{ mb_strlen($form->sasaran) }} }" x-text="n + ' / 2000'" x-on:input.window="if ($event.target.id === 'sasaran') n = $event.target.value.length"></span></x-slot:aside>
        <textarea id="sasaran" wire:model.live.blur="form.sasaran" rows="3" maxlength="2000"
                  class="form-input field-sizing-content min-h-20" placeholder="mis. dokumen perencanaan yang handal dan akurat sesuai kebutuhan riil madrasah."
                  @error('form.sasaran') aria-invalid="true" aria-describedby="sasaran-error" @enderror></textarea>
    </x-ui.field>

    <x-ui.field label="Tanggal dokumen" for="tanggal_dokumen" error="form.tanggal_dokumen"
                :hint="\App\Support\Format::tanggal($form->tanggal_dokumen) ? 'Tercetak: '.\App\Support\Format::tanggal($form->tanggal_dokumen) : 'Tanggal pada lembar pengesahan.'">
        <input type="date" id="tanggal_dokumen" wire:model.live="form.tanggal_dokumen" class="form-input"
               @error('form.tanggal_dokumen') aria-invalid="true" aria-describedby="tanggal_dokumen-error" @enderror>
    </x-ui.field>

    <x-ui.field label="Tanggal pelaporan" for="tanggal_laporan" error="form.tanggal_laporan"
                :hint="\App\Support\Format::tanggal($form->tanggal_laporan) ? 'Tercetak: '.\App\Support\Format::tanggal($form->tanggal_laporan) : 'Batas penyerahan laporan ke kepala madrasah.'">
        <input type="date" id="tanggal_laporan" wire:model.live="form.tanggal_laporan" class="form-input"
               @error('form.tanggal_laporan') aria-invalid="true" aria-describedby="tanggal_laporan-error" @enderror>
    </x-ui.field>
</div>
