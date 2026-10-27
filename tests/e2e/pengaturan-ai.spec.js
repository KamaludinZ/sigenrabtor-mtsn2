import { expect, test } from '@playwright/test';

test.describe('Pengaturan AI', () => {
    test('dari Ikhtisar: tab penyedia, prompt per bagian, dan batas pemakaian tanpa gulir mendatar', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan');
        await page.getByTestId('bagian-ai').getByRole('link').first().click();
        await expect(page).toHaveURL(/\/pengaturan\/ai$/);
        await expect(page.getByRole('heading', { name: 'Pengaturan AI', level: 1 })).toBeVisible();

        await expect(page.getByTestId('penyedia-aktif')).toBeVisible();
        await expect(page.getByTestId('pilihan-penyedia-ai').locator('[data-penyedia]')).toHaveCount(4);

        const tab = page.getByTestId('tab-pengaturan-ai');
        await tab.locator('[data-tab="prompt"]').click();
        await expect(page).toHaveURL(/tab=prompt/);
        await expect(page.getByTestId('panel-prompt-ai').locator('[data-prompt]')).toHaveCount(6);

        await tab.locator('[data-tab="pemakaian"]').click();
        await expect(page.getByTestId('batas-harian-ai')).toContainText('20');
        await expect(page.getByTestId('pemakaian-per-akun').locator('[data-akun]').first()).toBeVisible();

        const lebar = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(lebar).toBeLessThanOrEqual(0);
        await page.screenshot({ path: `storage/playwright/shots/pengaturan-ai-${testInfo.project.name}.png`, fullPage: true });

        await tab.locator('[data-tab="penyedia"]').click();
        await page.screenshot({ path: `storage/playwright/shots/pengaturan-ai-penyedia-${testInfo.project.name}.png`, fullPage: true });
    });

    test('server sekolah: atur alamat & model, pakai sebagai penyedia aktif, lalu pulihkan', async ({ page }) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/ai');
        const lokal = page.getByTestId('penyedia-lokal');
        await expect(lokal.getByTestId('peran-lokal')).toHaveText('Tidak dipakai');

        await lokal.getByTestId('atur-lokal').click();
        const formulir = lokal.getByTestId('formulir-lokal');
        await formulir.getByLabel('Alamat server').fill('ftp://server-sekolah');
        await formulir.getByLabel('Model').fill('qwen2.5:7b');
        await formulir.getByText('Penyedia aktif', { exact: true }).click();
        await formulir.getByTestId('simpan-lokal').click();
        await expect(formulir).toContainText('Alamat server harus berbentuk http(s)://alamat[:port]');

        await formulir.getByLabel('Alamat server').fill('http://192.168.1.10:11434/');
        await formulir.getByTestId('simpan-lokal').click();
        await expect(page.getByText('Konfigurasi server sekolah tersimpan.')).toBeVisible();
        await expect(lokal.getByTestId('peran-lokal')).toHaveText('Penyedia aktif');
        await expect(page.getByTestId('penyedia-aktif')).toContainText('Ollama (server sekolah)');
        await expect(page.getByTestId('penyedia-aktif')).toContainText('qwen2.5:7b');
        await expect(page.getByTestId('ai-belum-dipilih')).toHaveCount(0);

        // Pulihkan: data dipakai bersama proyek lain.
        await lokal.getByTestId('atur-lokal').click();
        await formulir.getByLabel('Alamat server').fill('http://localhost:11434');
        await formulir.getByLabel('Model').fill('');
        await formulir.getByText('Tidak dipakai', { exact: true }).click();
        await formulir.getByTestId('simpan-lokal').click();
        await expect(lokal.getByTestId('peran-lokal')).toHaveText('Tidak dipakai');
        await expect(page.getByTestId('ai-belum-dipilih')).toBeVisible();
    });

    test('pemilihan penyedia: hanya yang siap bisa dipilih, alur cadangan, lalu pulihkan', async ({ page }) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/ai');
        const lokal = page.getByTestId('penyedia-lokal');

        // Siapkan server sekolah (model diisi) tanpa dipakai.
        await lokal.getByTestId('atur-lokal').click();
        await lokal.getByLabel('Model').fill('qwen2.5:7b');
        await lokal.getByTestId('simpan-lokal').click();
        await expect(page.getByText('Konfigurasi server sekolah tersimpan.')).toBeVisible();

        await page.getByTestId('pilih-aktif').click();
        const pilih = page.getByTestId('pilih-penyedia');
        const aktif = pilih.getByTestId('pilihan-aktif');
        await expect(aktif.locator('[data-pilih-penyedia="claude"]')).toHaveAttribute('data-siap', 'tidak');
        await expect(aktif.locator('[data-pilih-penyedia="claude"] input')).toBeDisabled();
        await aktif.locator('[data-pilih-penyedia="ollama"]').click();
        await expect(pilih.getByTestId('alur-penyedia')).toContainText('Ollama (server sekolah)');
        await pilih.screenshot({ path: `storage/playwright/shots/pilih-penyedia-ai-${test.info().project.name}.png` });
        await pilih.getByTestId('simpan-pilihan').click();
        await expect(page.getByText('Penyedia AI tersimpan: Ollama (server sekolah), tanpa cadangan.')).toBeVisible();
        await expect(page.getByTestId('penyedia-aktif')).toContainText('Ollama (server sekolah)');

        // Pulihkan: tanpa penyedia, model server sekolah dikosongkan lagi.
        await page.getByTestId('pilih-aktif').click();
        await page.getByTestId('pilihan-aktif').getByText('Tanpa penyedia').click();
        await page.getByTestId('simpan-pilihan').click();
        await expect(page.getByTestId('ai-belum-dipilih')).toBeVisible();
        await lokal.getByTestId('atur-lokal').click();
        await lokal.getByLabel('Model').fill('');
        await lokal.getByTestId('simpan-lokal').click();
        await expect(lokal.getByTestId('ringkasan-lokal')).toContainText('Belum diisi');
    });

    test('API cloud: kunci tersimpan hanya tampil 4 karakter terakhir, tidak dimuat ulang, lalu pulihkan', async ({ page }) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/ai');
        const claude = page.getByTestId('penyedia-cloud').locator('[data-cloud="claude"]');
        await expect(claude.getByTestId('status-kunci-claude')).toContainText('belum ada');

        await claude.getByTestId('atur-claude').click();
        const formulir = claude.getByTestId('formulir-claude');
        await formulir.getByLabel('Model').fill('claude-sonnet-5-5');
        const kunci = formulir.locator('#cloud-kunci');
        await kunci.fill('sk-ant-contoh-e2e-fiktif-0000WXYZ');
        await expect(kunci).toHaveAttribute('type', 'password');
        await formulir.getByRole('button', { name: 'Tampilkan kunci yang sedang diketik' }).click();
        await expect(kunci).toHaveAttribute('type', 'text');
        await formulir.getByTestId('simpan-cloud').click();
        await expect(page.getByText('Konfigurasi Claude (Anthropic) tersimpan.')).toBeVisible();
        await expect(claude.getByTestId('status-kunci-claude')).toContainText('•••• WXYZ');
        await expect(page.locator('body')).not.toContainText('sk-ant-contoh-e2e-fiktif');

        // Dibuka lagi: kunci tidak dimuat ke isian. Pulihkan: hapus kunci & model.
        await claude.getByTestId('atur-claude').click();
        await expect(formulir.locator('#cloud-kunci')).toHaveValue('');
        await formulir.getByLabel('Model').fill('');
        await formulir.getByTestId('hapus-kunci').check();
        await formulir.getByTestId('simpan-cloud').click();
        await expect(claude.getByTestId('status-kunci-claude')).toContainText('belum ada');
    });

    test('model & parameter: tanpa penyedia, preset kreativitas & jumlah versi disimpan, lalu dikembalikan bawaan', async ({ page }, testInfo) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/ai?tab=model');
        await expect(page.getByTestId('model-tanpa-penyedia')).toBeVisible();

        const parameter = page.getByTestId('parameter-ai');
        await parameter.locator('[data-preset="kreatif"]').click();
        await expect(parameter.locator('[data-preset="kreatif"]')).toHaveAttribute('aria-checked', 'true');
        await expect(parameter.getByTestId('keterangan-suhu')).toContainText('Versi lebih beragam');
        await parameter.getByText('2 versi').click();
        await parameter.getByTestId('simpan-parameter').click();
        await expect(page.getByText('Parameter jawaban AI tersimpan.')).toBeVisible();
        await page.screenshot({ path: `storage/playwright/shots/pengaturan-ai-model-${testInfo.project.name}.png`, fullPage: true });

        // Pulihkan bawaan (jumlah versi dipakai tes Kalimat KAK).
        await parameter.getByRole('button', { name: 'Kembalikan bawaan' }).click();
        await expect(parameter.locator('[data-preset="seimbang"]')).toHaveAttribute('aria-checked', 'true');
        await parameter.getByTestId('simpan-parameter').click();
        await page.waitForLoadState('networkidle');
        await page.reload();
        await expect(page.getByTestId('parameter-ai').locator('input[name="jumlah-versi"][value="3"]')).toBeChecked();
    });

    test('prompt per bagian: sunting instruksi dengan penanda, pratinjau prompt, simpan, lalu kembalikan bawaan', async ({ page }) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/ai?tab=prompt');
        const tujuan = page.locator('[data-prompt="tujuan"]');
        await expect(tujuan.getByTestId('status-prompt-tujuan')).toHaveText('Bawaan');

        await tujuan.getByTestId('ubah-prompt-tujuan').click();
        const formulir = tujuan.getByTestId('formulir-prompt');
        const instruksi = formulir.locator('#isian-instruksi');
        await instruksi.fill('Tulis satu frasa tujuan untuk  diawali kata kerja.');
        await instruksi.evaluate((el) => el.setSelectionRange(30, 30));
        await formulir.getByRole('button', { name: '{nama_kegiatan}' }).click();
        await expect(instruksi).toHaveValue('Tulis satu frasa tujuan untuk {nama_kegiatan} diawali kata kerja.');
        await formulir.getByTestId('pratinjau-prompt').locator('summary').click();
        await expect(formulir.getByTestId('pratinjau-prompt')).toContainText('Tugas: Tulis satu frasa tujuan untuk ');
        await expect(formulir.getByTestId('pratinjau-prompt')).not.toContainText('{nama_kegiatan}');
        await formulir.getByTestId('simpan-prompt').click();
        await expect(tujuan.getByTestId('status-prompt-tujuan')).toHaveText('Diubah');

        // Pulihkan bawaan (prompt dipakai bersama proyek lain).
        await tujuan.getByTestId('ubah-prompt-tujuan').click();
        await tujuan.getByTestId('formulir-prompt').getByRole('button', { name: 'Kembalikan bawaan' }).click();
        await tujuan.getByTestId('formulir-prompt').getByTestId('simpan-prompt').click();
        await expect(tujuan.getByTestId('status-prompt-tujuan')).toHaveText('Bawaan');
    });

    test('batas pemakaian: batas umum lewat pilihan cepat, batas khusus satu akun, lalu pulihkan', async ({ page }) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/ai?tab=pemakaian');
        const batas = page.getByTestId('batas-harian-ai');

        await batas.getByRole('radio', { name: '50' }).click();
        await expect(batas.getByTestId('isian-batas')).toHaveValue('50');
        await batas.getByTestId('simpan-batas').click();
        await expect(page.getByText('Batas umum tersimpan: 50 permintaan AI per akun per hari.')).toBeVisible();

        const akun = page.getByTestId('pemakaian-per-akun').locator('[data-akun="3"]');
        await akun.getByTestId('ubah-khusus-3').click();
        await akun.getByTestId('isian-khusus').fill('80');
        await akun.getByTestId('simpan-khusus').click();
        await expect(akun.getByTestId('khusus-3')).toHaveText('Khusus 80');
        await expect(akun.getByTestId('terpakai-3')).toContainText('/ 80');

        // Pulihkan: akun memakai batas umum, batas umum kembali 20 (dipakai tes Kalimat KAK).
        await akun.getByTestId('ubah-khusus-3').click();
        await akun.getByTestId('hapus-khusus').click();
        await expect(akun.getByTestId('khusus-3')).toHaveCount(0);
        await batas.getByRole('radio', { name: '20' }).click();
        await batas.getByTestId('simpan-batas').click();
        await expect(page.getByText('Batas umum tersimpan: 20 permintaan AI per akun per hari.')).toBeVisible();
    });

    test('proses simpan: penanda belum disimpan, konfirmasi pindah tab, tersimpan, lalu pulihkan', async ({ page }) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/ai');
        const lokal = page.getByTestId('penyedia-lokal');
        await lokal.getByTestId('atur-lokal').click();
        await expect(lokal.getByTestId('belum-disimpan-lokal')).toBeHidden();
        await lokal.getByLabel('Model').fill('qwen2.5:3b');
        await expect(lokal.getByTestId('belum-disimpan-lokal')).toBeVisible();

        // Pindah tab saat ada perubahan → konfirmasi; ditolak = tetap di tab Penyedia.
        page.once('dialog', async (dialog) => {
            expect(dialog.message()).toContain('belum disimpan');
            await dialog.dismiss();
        });
        await page.getByTestId('tab-pengaturan-ai').locator('[data-tab="model"]').click();
        await expect(page).not.toHaveURL(/tab=model/);
        await expect(lokal.getByTestId('formulir-lokal')).toBeVisible();

        // Batal → bersih → pindah tab tanpa konfirmasi.
        await lokal.getByRole('button', { name: 'Batal' }).click();
        await page.getByTestId('tab-pengaturan-ai').locator('[data-tab="model"]').click();
        await expect(page).toHaveURL(/tab=model/);

        const parameter = page.getByTestId('parameter-ai');
        await parameter.locator('[data-preset="konsisten"]').click();
        await expect(parameter.getByTestId('belum-disimpan-parameter')).toBeVisible();
        await parameter.getByTestId('simpan-parameter').click();
        await expect(parameter.getByTestId('tersimpan-parameter')).toBeVisible();
        await expect(parameter.getByTestId('belum-disimpan-parameter')).toBeHidden();
        await expect(page.getByTestId('terakhir-disimpan-ai')).toContainText('Ubah model & parameter AI');

        // Pulihkan parameter bawaan.
        await parameter.getByRole('button', { name: 'Kembalikan bawaan' }).click();
        await parameter.getByTestId('simpan-parameter').click();
        await expect(parameter.locator('[data-preset="seimbang"]')).toHaveAttribute('aria-checked', 'true');
        await expect(parameter.getByTestId('belum-disimpan-parameter')).toBeHidden();
    });

    test('simulasi kegagalan: pesan tampil di Kalimat KAK, banner peringatan, lalu dimatikan', async ({ page }) => {
        await page.goto('/demo/peran/admin');
        await page.goto('/pengaturan/ai');
        const simulasi = page.getByTestId('simulasi-ai');
        await simulasi.getByLabel('Jenis kegagalan').selectOption('tidak_terhubung');
        await expect(simulasi.getByTestId('pratinjau-pesan-gagal')).toContainText('Server AI tidak bisa dihubungi');
        await simulasi.getByText('Semua penyedia').click();
        await simulasi.getByTestId('simpan-simulasi').click();
        await expect(page.getByTestId('banner-simulasi')).toContainText('Server AI tidak bisa dihubungi (semua penyedia)');

        await page.goto('/kegiatan/1/kalimat');
        await page.getByTestId('buat-ai-tujuan').click();
        await expect(page.getByTestId('galat-ai-tujuan')).toContainText('Server AI tidak bisa dihubungi. Coba lagi beberapa saat lagi.');
        await expect(page.getByTestId('galat-ai-tujuan')).toContainText('(simulasi kegagalan dari Pengaturan AI)');
        await expect(page.getByTestId('buat-ai-tujuan')).toHaveText(/Coba lagi/);

        // Matikan simulasi (dipakai bersama tes lain).
        await page.getByTestId('galat-ai-tujuan').getByRole('link', { name: 'Buka Pengaturan AI' }).click();
        await expect(page).toHaveURL(/\/pengaturan\/ai$/);
        await page.getByTestId('banner-simulasi').getByRole('button', { name: 'Matikan' }).click();
        await expect(page.getByTestId('banner-simulasi')).toHaveCount(0);
        await expect(page.getByTestId('status-simulasi')).toHaveText('Mati');
    });
});
