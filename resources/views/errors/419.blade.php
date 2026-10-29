@include('errors.kerangka', [
    'kode' => 419,
    'judul' => 'Halaman kedaluwarsa',
    'pesan' => 'Halaman ini terlalu lama dibiarkan terbuka sehingga sesinya berakhir. Muat ulang halaman lalu ulangi tindakan Anda.',
    'tombol' => [['Muat ulang', url()->previous(), 'galat-muat-ulang'], ['Masuk', route('akun.masuk'), 'galat-masuk']],
])
