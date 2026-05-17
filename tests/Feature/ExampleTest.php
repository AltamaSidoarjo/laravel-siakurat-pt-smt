<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_login_when_not_authenticated(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_bridging_pendapatan_page_can_be_opened_with_preview_session(): void
    {
        $response = $this
            ->withSession([
                'auth.preview_user' => [
                    'username' => 'tester',
                ],
            ])
            ->get('/bridging/pendapatan');

        $response
            ->assertOk()
            ->assertSee('Bridging Pendapatan');
    }
}
