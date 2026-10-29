@include('errors.kerangka', [
    'kode' => 403,
    'judul' => 'Akses ditolak',
    'pesan' => ($exception?->getMessage() ?: 'Halaman ini tidak tersedia untuk akun Anda.').' Bila menurut Anda ini keliru, hubungi admin madrasah.',
    'tombol' => [['Ke Dashboard', route('dashboard'), 'galat-dashboard'], ['Kembali', null, 'galat-kembali']],
])
