<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BukuBankRouteTest extends TestCase
{
    public function test_buku_bank_route_is_read_only_and_protected_by_module_access(): void
    {
        $route = Route::getRoutes()->getByName('kasbank.buku-bank.index');

        $this->assertNotNull($route);
        $this->assertSame('kasbank/buku-bank', $route->uri());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertContains('module.access:kasbank.buku-bank,view', $route->gatherMiddleware());
    }
}
