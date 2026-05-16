<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ModulePageController extends Controller
{
    public function show(string $groupLabel, string $title): View
    {
        return view('menu-page', [
            'page' => 'app',
            'groupLabel' => $groupLabel,
            'title' => $title,
        ]);
    }
}
