<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_gets_prompt_specification_and_guide(): void
    {
        $response = $this->actingAs(User::factory()->superAdmin()->create())->getJson('/api/admin/docs');

        $response->assertOk();
        $prompt = $response->json('prompt.text');

        $this->assertStringStartsWith('Necesito que generes un **dashboard HTML autocontenido**', $prompt);
        $this->assertStringContainsString('id="dashboard-manifest"', $prompt);
        $this->assertStringNotContainsString('---INICIO---', $prompt);
        $this->assertStringNotContainsString('---FIN---', $prompt);
        $this->assertStringContainsString('<p>', $response->json('prompt.intro_html'));
        $this->assertStringContainsString('<blockquote>', $response->json('prompt.example_html'));
        $this->assertStringContainsString('<h1>', $response->json('specification_html'));
        $this->assertStringContainsString('<table>', $response->json('specification_html'));
        $this->assertStringContainsString('<h2>', $response->json('guide_html'));
        $this->assertSame(config('dashboards.cdn_allowlist'), $response->json('cdn_allowlist'));
    }

    public function test_admin_can_download_the_reference_dashboard(): void
    {
        $response = $this->actingAs(User::factory()->superAdmin()->create())->get('/api/admin/docs/dashboard-referencia.html');

        $response->assertOk()->assertDownload('dashboard-referencia.html');
    }

    public function test_docs_are_admin_only(): void
    {
        $this->getJson('/api/admin/docs')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/api/admin/docs')->assertForbidden();
    }
}
