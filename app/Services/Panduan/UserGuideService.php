<?php

namespace App\Services\Panduan;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UserGuideService
{
    private string $docsPath;

    public function __construct(?string $docsPath = null)
    {
        $this->docsPath = $docsPath ?? resource_path('docs/panduan');
    }

    /**
     * @return Collection<int, array{slug: string, title: string, order: int}>
     */
    public function getTopics(): Collection
    {
        if (! File::isDirectory($this->docsPath)) {
            return collect();
        }

        $files = File::files($this->docsPath);

        return collect($files)
            ->filter(fn (\SplFileInfo $file) => $file->getExtension() === 'md')
            ->map(function (\SplFileInfo $file) {
                $filename = $file->getBasename('.md');
                $content = File::get($file->getRealPath());
                $title = $this->extractTitle($content, $filename);

                preg_match('/^(\d+)/', $filename, $matches);
                $order = isset($matches[1]) ? (int) $matches[1] : 999;

                return [
                    'slug' => $filename,
                    'title' => $title,
                    'order' => $order,
                ];
            })
            ->sortBy('order')
            ->values();
    }

    /**
     * @return array{slug: string, title: string, html: string}|null
     */
    public function getTopic(string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        $safeSlug = basename($slug);
        if ($safeSlug !== $slug || Str::contains($slug, ['..', '/', '\\'])) {
            return null;
        }

        $filePath = $this->docsPath.'/'.$safeSlug.'.md';
        if (! File::isFile($filePath)) {
            return null;
        }

        $content = File::get($filePath);
        $title = $this->extractTitle($content, $safeSlug);
        $html = Str::markdown($content);

        return [
            'slug' => $safeSlug,
            'title' => $title,
            'html' => (string) $html,
        ];
    }

    private function extractTitle(string $content, string $fallback): string
    {
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            return trim($matches[1]);
        }

        return Str::headline(preg_replace('/^\d+[\s_-]*/', '', $fallback));
    }
}
