import { rmSync } from 'node:fs';
import { E2E_STUB_FILE } from '../../playwright.config.js';

// Mulai setiap putaran E2E dari data tiruan bersih (contoh kegiatan dibuat ulang otomatis).
export default function globalSetup() {
    rmSync(`storage/app/private/${E2E_STUB_FILE}`, { force: true });
}
