<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class IndikatorKinerjaNavigationTest extends TestCase
{
    public function test_sidebar_contains_indicator_links(): void
    {
        $user = User::factory()->make();

        $response = $this->actingAs($user)->get(route('home'));
        $response->assertOk()
            ->assertSee(route('dashboard.indikator-kinerja.index'))
            ->assertSee(route('setting-indikator-kinerja.index'));
    }

    public function test_sidebar_hides_indicator_links_when_no_permission(): void
    {
        $userWithoutKpiAccess = new class extends User
        {
            public function hasModuleAccess(string $moduleKey, string $action = 'view'): bool
            {
                return $moduleKey === 'home';
            }
        };

        $response = $this->actingAs($userWithoutKpiAccess)->get(route('home'));
        $response->assertOk()
            ->assertDontSee(route('dashboard.indikator-kinerja.index'))
            ->assertDontSee(route('setting-indikator-kinerja.index'));
    }
}
