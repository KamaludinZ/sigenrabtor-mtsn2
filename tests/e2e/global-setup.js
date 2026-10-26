import { rmSync } from 'node:fs';
import { E2E_MATRIK_FILE, E2E_RAB_FILE, E2E_STUB_FILE } from '../../playwright.config.js';

// Mulai setiap putaran E2E dari data tiruan bersih (contoh kegiatan dibuat ulang otomatis).
export default function globalSetup() {
    for (const berkas of [E2E_STUB_FILE, E2E_RAB_FILE, E2E_MATRIK_FILE]) {
        rmSync(`storage/app/private/${berkas}`, { force: true });
    }
}
