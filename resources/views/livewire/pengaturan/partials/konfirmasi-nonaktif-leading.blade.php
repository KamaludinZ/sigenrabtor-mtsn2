{{-- Konfirmasi menonaktifkan leading beserta dampaknya; $kolom = colspan di tabel, null = di kartu layar sempit. --}}
@php
    $dampak = $this->dampakNonaktif($l['kode']);
    $isi = 'Leading nonaktif tidak bisa dipilih untuk kegiatan baru.'
        .($dampak['kegiatan'] > 0 ? ' '.$dampak['kegiatan'].' kegiatan tetap memakainya.' : '')
        .($dampak['akun'] !== [] ? ' Akun yang hanya tertaut ke leading ini ('.implode(', ', $dampak['akun']).') tidak bisa membuat kegiatan baru.' : '');
@endphp
@if ($kolom)
    <tr class="bg-red-50" data-testid="konfirmasi-nonaktif-leading" wire:key="konfirmasi-{{ $l['kode'] }}">
        <td colspan="{{ $kolom }}" class="px-5 py-3 sm:px-6">
@else
    <div class="mt-2 rounded-lg bg-red-50 px-3 py-2 ring-1 ring-red-200" data-testid="konfirmasi-nonaktif-leading">
@endif
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between" role="group" aria-label="Konfirmasi nonaktifkan leading {{ $l['kode'] }}">
                <p class="text-sm text-red-800"><span class="font-semibold">Nonaktifkan {{ $l['kode'] }} {{ $l['nama_unit'] }}?</span> {{ $isi }}</p>
                <span class="flex shrink-0 justify-end gap-1.5">
                    <button type="button" class="btn-ghost px-2 py-1 text-xs" wire:click="$set('konfirmasiNonaktif', null)">Batal</button>
                    <button type="button" class="btn-danger px-2.5 py-1 text-xs" wire:click="ubahStatusLeading(@js($l['kode']))">Nonaktifkan</button>
                </span>
            </div>
@if ($kolom)
        </td>
    </tr>
@else
    </div>
@endif
