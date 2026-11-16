<?php

namespace App\Http\Controllers\Panduan;

use App\Http\Controllers\Controller;
use App\Services\Panduan\UserGuideService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserGuideController extends Controller
{
    public function __construct(private readonly UserGuideService $service) {}

    public function index(Request $request): View
    {
        $topics = $this->service->getTopics();
        $requestedSlug = $request->query('topic');

        if ($topics->isEmpty()) {
            return view('panduan.index', [
                'topics' => $topics,
                'currentTopic' => null,
            ]);
        }

        $activeSlug = $requestedSlug ?: $topics->first()['slug'];
        $currentTopic = $this->service->getTopic($activeSlug);

        if ($currentTopic === null && $requestedSlug !== null) {
            abort(404, 'Topik panduan tidak ditemukan.');
        }

        return view('panduan.index', [
            'topics' => $topics,
            'currentTopic' => $currentTopic,
        ]);
    }
}
