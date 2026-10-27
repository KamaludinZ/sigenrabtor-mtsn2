@props(['leading', 'sumber', 'draf' => false])

@use('App\Support\Dokumen\BukuDokumen')
@use('App\Support\Dokumen\CoverDokumen')

@php
    // Halaman cover buku: desain cover yang sama dengan cover kegiatan (CoverDokumen) berisi nilai buku (BukuDokumen::nilaiCover).
    $desain = CoverDokumen::desain();
@endphp

<x-dokumen.cover :gambar="$desain['gambar']" :teks="$desain['teks']" :nilai="BukuDokumen::nilaiCover($leading, $sumber)" :draf="$draf" :jangkar="false"
                 {{ $attributes->merge(['data-cover-buku' => $leading.'|'.$sumber]) }} />
