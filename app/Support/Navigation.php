<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Struktur menu samping aplikasi.
 *
 * Item yang rutenya belum terdaftar tetap ditampilkan sebagai "Segera" sehingga pengguna
 * melihat peta lengkap aplikasi sejak awal; item otomatis aktif begitu rutenya dibuat.
 */
class Navigation
{
    /**
     * @return list<array{label: string, admin_only: bool, items: list<array{label: string, icon: string, route: string, active: string, available: bool, url: string|null}>}>
     */
    public static function groups(string $role): array
    {
        $groups = [
            [
                'label' => 'Kegiatan',
                'admin_only' => false,
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard', 'active' => 'dashboard'],
                    ['label' => 'Data Kegiatan', 'icon' => 'clipboard', 'route' => 'kegiatan.create', 'active' => 'kegiatan.*'],
                    ['label' => 'Riwayat Dokumen', 'icon' => 'history', 'route' => 'dokumen.index', 'active' => 'dokumen.*'],
                    ['label' => 'Buku Dokumen', 'icon' => 'book', 'route' => 'buku.index', 'active' => 'buku.*'],
                ],
            ],
            [
                'label' => 'Admin',
                'admin_only' => true,
                'items' => [
                    ['label' => 'Monitoring Anggaran', 'icon' => 'chart', 'route' => 'monitoring.index', 'active' => 'monitoring.*'],
                    ['label' => 'Pemeriksaan', 'icon' => 'check-badge', 'route' => 'pemeriksaan.index', 'active' => 'pemeriksaan.*'],
                    ['label' => 'Master Kode', 'icon' => 'database', 'route' => 'master.index', 'active' => 'master.*'],
                ],
            ],
            [
                'label' => 'Pengaturan',
                'admin_only' => true,
                'items' => [
                    ['label' => 'Tahun Anggaran', 'icon' => 'calendar', 'route' => 'pengaturan.tahun.index', 'active' => 'pengaturan.tahun.*'],
                    ['label' => 'Profil & Pejabat', 'icon' => 'building', 'route' => 'pengaturan.madrasah.index', 'active' => 'pengaturan.madrasah.*'],
                    ['label' => 'Leading & User', 'icon' => 'users', 'route' => 'pengaturan.leading.index', 'active' => 'pengaturan.leading.*'],
                    ['label' => 'Template Dokumen', 'icon' => 'file-text', 'route' => 'pengaturan.template.index', 'active' => 'pengaturan.template.*'],
                    ['label' => 'Pengaturan AI', 'icon' => 'sparkles', 'route' => 'pengaturan.ai.index', 'active' => 'pengaturan.ai.*'],
                ],
            ],
        ];

        $visible = array_values(array_filter(
            $groups,
            fn (array $group) => ! $group['admin_only'] || $role === 'admin',
        ));

        return array_map(function (array $group) {
            $group['items'] = array_map(function (array $item) {
                $available = Route::has($item['route']);

                return $item + [
                    'available' => $available,
                    'url' => $available ? route($item['route']) : null,
                ];
            }, $group['items']);

            return $group;
        }, $visible);
    }
}
