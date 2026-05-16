<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePreviewAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('auth.preview_user')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
