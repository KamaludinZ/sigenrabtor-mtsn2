{{-- Konfirmasi menonaktifkan akun beserta dampaknya; $kolom = colspan di tabel, null = di kartu layar sempit. --}}
@php
    $buatan = $this->kegiatanBuatan($a['id']);
    $isi = 'Akun tidak bisa masuk lagi.'.($buatan > 0 ? ' '.$buatan.' kegiatan buatannya tetap ada dan tetap tercatat atas namanya.' : '');
@endphp
@if ($kolom)
    <tr class="bg-red-50" data-testid="konfirmasi-nonaktif-akun" wire:key="konfirmasi-akun-{{ $a['id'] }}">
        <td colspan="{{ $kolom }}" class="px-5 py-3 sm:px-6">
@else
    <div class="mt-2 rounded-lg bg-red-50 px-3 py-2 ring-1 ring-red-200" data-testid="konfirmasi-nonaktif-akun">
@endif
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between" role="group" aria-label="Konfirmasi nonaktifkan akun {{ $a['name'] }}">
                <p class="text-sm text-red-800"><span class="font-semibold">Nonaktifkan {{ $a['name'] }}?</span> {{ $isi }}</p>
                <span class="flex shrink-0 justify-end gap-1.5">
                    <button type="button" class="btn-ghost px-2 py-1 text-xs" wire:click="$set('konfirmasiNonaktifAkun', null)">Batal</button>
                    <button type="button" class="btn-danger px-2.5 py-1 text-xs" wire:click="ubahStatusAkun({{ $a['id'] }})">Nonaktifkan</button>
                </span>
            </div>
@if ($kolom)
        </td>
    </tr>
@else
    </div>
@endif
