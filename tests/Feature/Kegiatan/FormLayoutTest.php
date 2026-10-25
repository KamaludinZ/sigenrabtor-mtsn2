<?php

namespace Tests\Feature\Kegiatan;

use App\Livewire\Kegiatan\Form;
use App\Support\Kegiatan\FormSections;
use Livewire\Livewire;
use Tests\TestCase;

class FormLayoutTest extends TestCase
{
    public function test_root_redirects_to_kegiatan_form(): void
    {
        $this->get('/')->assertRedirect('/kegiatan/buat');
    }

    public function test_form_page_renders_layout_and_all_sections(): void
    {
        $response = $this->get(route('kegiatan.create'));

        $response->assertOk()
            ->assertSee('SIRABTOR')
            ->assertSee('Tahun Anggaran')
            ->assertSee('2026')
            ->assertSee('Kegiatan Baru')
            ->assertSee('Simpan Draf');

        foreach (FormSections::all() as $section) {
            $response->assertSee($section['title']);
            $response->assertSee('id="'.$section['id'].'"', false);
        }
    }

    public function test_save_draft_dispatches_notification(): void
    {
        Livewire::test(Form::class)
            ->call('saveDraft')
            ->assertDispatched('notify');
    }

    public function test_admin_menu_is_hidden_for_leading_and_shown_for_admin(): void
    {
        $this->get(route('kegiatan.create'))
            ->assertDontSee('Monitoring Anggaran');

        $this->withSession(['stub_role' => 'admin'])
            ->get(route('kegiatan.create'))
            ->assertSee('Monitoring Anggaran')
            ->assertSee('Pengaturan AI');
    }

    public function test_demo_role_switch_only_accepts_known_roles(): void
    {
        $this->get('/demo/peran/admin')->assertRedirect();
        $this->assertSame('admin', session('stub_role'));

        $this->get('/demo/peran/superuser')->assertNotFound();
    }
}
