@include('errors.kerangka', [
    'kode' => 404,
    'judul' => 'Halaman tidak ditemukan',
    'pesan' => 'Alamat ini tidak ada atau datanya sudah dihapus — periksa tautannya, atau mulai dari Dashboard.',
    'tombol' => [['Ke Dashboard', route('dashboard'), 'galat-dashboard'], ['Kembali', null, 'galat-kembali']],
])
