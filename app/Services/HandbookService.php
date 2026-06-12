<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\File;

class HandbookService
{
    /**
     * @return list<array{slug: string, title: string, description: string, sort: int, last_verified: string|null}>
     */
    public function chapters(): array
    {
        return collect(config('help.chapters', []))
            ->map(fn (array $chapter): array => [
                'slug' => $chapter['slug'],
                'title' => $chapter['title'],
                'description' => $chapter['description'],
                'sort' => $chapter['sort'],
                'last_verified' => $this->extractLastVerified($this->chapterPath($chapter['file'])),
            ])
            ->sortBy('sort')
            ->values()
            ->all();
    }

    /**
     * @return array{slug: string, title: string, description: string, content: string, last_verified: string|null}|null
     */
    public function article(string $slug): ?array
    {
        $chapter = collect(config('help.chapters', []))->firstWhere('slug', $slug);

        if (! is_array($chapter)) {
            return null;
        }

        $path = $this->chapterPath($chapter['file']);

        if (! File::isFile($path)) {
            return null;
        }

        return [
            'slug' => $chapter['slug'],
            'title' => $chapter['title'],
            'description' => $chapter['description'],
            'content' => $this->transformMarkdown(File::get($path)),
            'last_verified' => $this->extractLastVerified($path),
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) config('help.enabled', true);
    }

    private function transformMarkdown(string $markdown): string
    {
        $markdown = preg_replace_callback(
            '/\]\(([0-9]{2}-[A-Za-z0-9-]+\.md)\)/',
            function (array $matches): string {
                $chapter = collect(config('help.chapters', []))->firstWhere('file', $matches[1]);

                return is_array($chapter) ? "](/help/{$chapter['slug']})" : $matches[0];
            },
            $markdown,
        ) ?? $markdown;

        $markdown = preg_replace('/^# .+\n+/m', '', $markdown, 1) ?? $markdown;

        return trim($markdown);
    }

    private function chapterPath(string $filename): string
    {
        return config('help.handbook_path').'/'.$filename;
    }

    private function extractLastVerified(string $path): ?string
    {
        if (! File::isFile($path)) {
            return null;
        }

        if (preg_match('/_Last verified:\s*([0-9]{4}-[0-9]{2}-[0-9]{2})_/i', File::get($path), $matches)) {
            return $matches[1];
        }

        return null;
    }
}
