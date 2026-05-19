<?php

namespace App\Http\Middleware;

use App\Services\Auth\ModuleAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function __construct(
        private readonly ModuleAccessService $moduleAccessService,
    ) {
    }

    public function handle(Request $request, Closure $next, string $moduleKey, string $action): Response
    {
        $this->moduleAccessService->authorize($request->user(), $moduleKey, $action);

        return $next($request);
    }
}
